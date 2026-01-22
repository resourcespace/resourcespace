<?php

    global $baseurl,$watched_searches_url;

    $watched_searches_url = $baseurl . '/plugins/rse_search_notifications/pages/watched_searches.php';

    function search_notifications_get(&$results, $user="",$enabled_only=true,$search="",$orderby=1,$orderbydirection="DESC")
        {
        $query = "";
        $parameters = array();
        if ($user != "")
            {
            $query .= "WHERE `owner` = ?";
            $parameters = array_merge($parameters, array("i", $user));
            }
        if ($enabled_only)
            {
            if ($user == "")
                {
                $query .= "WHERE enabled = 1";
                }
            else
                {
                $query .= " AND enabled = 1";
                }
            }
        if ($search != "")
            {
            $query .= " AND (title LIKE ? OR u.username LIKE ?)";
            $parameters = array_merge($parameters, array("s", '%' . $search . '%', "s", '%' . $search . '%'));
            }

        if (!is_numeric($orderby))
            {
            $orderby = 1;
            }

        if (!in_array(strtoupper($orderbydirection), array("ASC", "DESC")))
            {
            $orderbydirection = "DESC";
            }

        $results=ps_query("SELECT s.ref, s.created, s.`owner`, s.title, s.search, s.restypes, s.archive, s.enabled, s.`checksum`, s.checksum_when, s.checksum_matches, s.checksum_data, s.checksum_data_previous, u.username FROM search_saved s JOIN `user` u ON s.`owner` = u.ref " 
            . $query . " ORDER BY '{$orderby}' '{$orderbydirection}'", $parameters);
        return count($results) > 0;
        }

    function search_notification_add($search,$restypes,$archive)
        {
        global $userref;
        if (ps_value("SELECT COUNT(*) AS value FROM search_saved WHERE owner = ? AND search = ? AND restypes = ?", array("i", $userref, "s", $search, "s", $restypes), 0) != 0)
            {
            return;     // we do not want dupes or empty searches
            }
        if ($archive=="")
            {
            $archive=0;
            }
        $restypes_names = ps_query("SELECT name FROM resource_type WHERE ref IN (" .
        ps_param_insert(count(explode(",", $restypes))) . ")", ps_param_fill(explode(",", $restypes), "i"), "");

        // Because search can contain direct node IDs searches like @@257
        // We resolve them back into node names
        $rebuilt_search  = $search;
        $node_bucket     = array();
        $node_bucket_not = array();
        $searched_nodes  = resolve_given_nodes($rebuilt_search, $node_bucket, $node_bucket_not);

        foreach($node_bucket as $searched_nodes)
            {
            $searched_node_data = array();

            foreach($searched_nodes as $searched_node)
                {
                if(!get_node($searched_node, $searched_node_data))
                    {
                    continue;
                    }

                $rebuilt_search .= rebuild_specific_field_search_from_node($searched_node_data);
                }
            }
        $rebuilt_search = str_replace('"', '', $rebuilt_search);

        $title = '"' . $rebuilt_search . '"';
        $title .= ($restypes_names == "") ? "" : " (" . implode(',', array_map('i18n_get_translated',array_column($restypes_names, 'name'))) . ")";
        $title = mb_strcut($title,0,500);

        ps_query("INSERT INTO search_saved(created,owner,title,search,restypes,archive,enabled) VALUES (
			NOW(), ?, ?, ?, ? ,?, 1)", array("i", $userref, "s", $title, "s", $search, "s", $restypes, "s", $archive));
        search_notification_process($userref,sql_insert_id());
        }

    function search_notification_delete($ref,$force=false)
        {
        if ($force)
            {
            ps_query("DELETE FROM search_saved WHERE ref = ?", array("i", $ref));
            }
        else
            {
            global $userref;
            ps_query("DELETE FROM search_saved WHERE ref = ? AND owner = ?", array("i", $ref, "i", $userref));
            }
        }

    function search_notification_enable($ref,$force=false)
        {
        if ($force)
            {
            ps_query("UPDATE search_saved SET enabled = 1 WHERE ref = ?", array("i", $ref));
            }
        else
            {
            global $userref;
            ps_query("UPDATE search_saved SET enabled = 1 WHERE ref = ? AND owner = ?", array("i", $ref, "i", $userref));
            }
        }

    function search_notification_disable($ref,$force=false)
        {
        if ($force)
            {
            ps_query("UPDATE search_saved SET enabled = 0 WHERE ref = ?", array("i", $ref));
            }
        else
            {
            global $userref;
            ps_query("UPDATE search_saved SET enabled = 0 WHERE ref = ? AND owner = ?", array("i", $ref, "i", $userref));
            }
        }

    function search_notification_enable_all($force=false)
        {
        if ($force)
            {
            ps_query("UPDATE search_saved SET enabled = 1", array());
            }
        else
            {
            global $userref;
            ps_query("UPDATE search_saved SET enabled = 1 WHERE owner = ?", array("i", $userref));
            }
        }

    function search_notification_disable_all($force=false)
        {
        if ($force)
            {
            ps_query("UPDATE search_saved SET enabled = 0", array());
            }
        else
            {
            global $userref;
            ps_query("UPDATE search_saved SET enabled = 0 WHERE owner = ?", array("i", $userref));
            }
        }

    function search_notification_process($owner=-1,$search_saved=-1)
        {

        global $lang,$baseurl,$search_notification_max_thumbnails;

        $sql_and = "";
        $parameters = array();
        if ($owner != -1)
            {
            $sql_and = " AND owner = ?";
            $parameters = array("i", $owner);
            }
        if ($search_saved != -1)
            {
            $sql_and .= " AND ref = ?";
            $parameters = array_merge($parameters, array("i", $search_saved));
            }

        $saved_searches = ps_query("SELECT * FROM search_saved WHERE enabled = 1" . $sql_and . " ORDER BY owner", $parameters);

        foreach ($saved_searches as $search)
            {
            $results=do_search($search['search'],$search['restypes'],'resourceid',$search['archive']);
            $resources_found=array();

            if (is_array($results) && count($results) > 0)
                {
                foreach ($results as $result)
                    {
                    array_push($resources_found, $result['ref']);
                    }
                }

            $checksum_data=implode(',',$resources_found);
            $checksum=sha1('#' . $checksum_data);       // the '#' avoids blank checksum if no resources found
            $checksum_matches=count($resources_found);

            if ($checksum==$search['checksum'])     // nothing has changed so process the next saved search
                {
                continue;
                }

            if ($search['checksum'] != "")      // this search has been run before so work out differences
                {

                $resources_existing=$search['checksum_data']=='' ? array() : explode(',',$search['checksum_data']);     // ensure empty resource list produces zero array entries

                $resources_subtracted=array_diff($resources_existing,$resources_found);
                $resources_added=array_diff($resources_found,$resources_existing);
                $resources_added_count=count($resources_added);
                $resources_subtracted_count=count($resources_subtracted);

                $message=
                ($resources_added_count == 1 ? "{$resources_added_count} {$lang['search_notifications_new match_found']}" : "") .
                ($resources_added_count > 1 ? "{$resources_added_count} {$lang['search_notifications_new matches_found']}" : "") .
                ($resources_added_count > 0 && $resources_subtracted_count > 0 ? " {$lang['and']} " : "") .
                ($resources_subtracted_count == 1 ? "{$resources_subtracted_count} {$lang['search_notifications_resource_no_longer_matches']}" : "") .
                ($resources_subtracted_count > 1 ? "{$resources_subtracted_count} {$lang['search_notifications_resources_no_longer_match']}" : "") .
                " {$lang['search_notifications_for_watch_search']} " . $search['title'] . "<br />";

                $added_to_message_count = 0;

                foreach ($resources_added as $resource_added)
                    {
                    if ($added_to_message_count == $search_notification_max_thumbnails)
                        {
                        break;
                        }

                    $thumb_file=get_resource_path($resource_added,true,'col');
                    if (file_exists($thumb_file))
                        {
                        $thumb_url=get_resource_path($resource_added,false,'col');
                        $message.="<a href='{$baseurl}/pages/view.php?ref={$resource_added}&search={$search['search']}&restypes={$search['restypes']}&archive={$search['archive']}'";
                        $message.=" onclick='return ModalLoad(this,true);'>";
                        $message.="<img src='{$thumb_url}' >";
                        $message.="</a>";
                        $added_to_message_count++;
                        }
                    }

                $search['checksum_data_previous'] = $search['checksum_data'];
                $search['checksum_data'] = implode(',', $resources_found);              

                message_add(
                    $search['owner'],
                    $message,
                    search_notification_make_url($search)
                );

                }

            // finally update with the new checksum, timestamp and resources
            ps_query("UPDATE search_saved SET checksum = ?,checksum_matches = ?, checksum_when = NOW(), checksum_data_previous = checksum_data, checksum_data = ? WHERE ref = ?", ['s', $checksum, 's', $checksum_matches, 's', $checksum_data, 'i', $search['ref']]);

            }       // end for each saved search
        }

    function search_notification_make_url($watched_search)
        {
        global $baseurl, $only_show_changes;

        $url = $baseurl . "/pages/search.php?restypes=" . urlencode($watched_search['restypes']) . "&archive=" . $watched_search['archive'] . "&search=";
    
        if($only_show_changes)
            {
            $current = explode(',',$watched_search['checksum_data']);
            $previous = explode(',',$watched_search['checksum_data_previous']);

            $additions = array_diff($current, $previous);
            $removals = array_diff($previous, $current);

            $changes = array_merge($additions, $removals);
            if(count($changes) > 0)
                {
                $url .= urlencode('!list' . implode(':', $changes));
                }
            }
        else
            {
            $url .= urlencode($watched_search['search']);
            }

        return $url;

        }

 /**
  * Delete all watched searches by user
  *
  * @param int  $user User ID
  * 
  * @return void
  */
 function search_notification_delete_by_owner($user): void
{
    ps_query("DELETE FROM search_saved WHERE owner = ?", array("i", $user));    
}