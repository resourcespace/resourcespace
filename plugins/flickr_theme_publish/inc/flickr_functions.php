<?php
/**
 * Get the title field value (as configured in the plugin)
 * 
 * @param integer $resource Resource ID
 * @return string Returns the i18l translated string of the field that represents the title for Flickr purposes.
 */
function flickr_get_title_field_value(int $resource)
    {
    $title_field = $GLOBALS['flickr_title_field'] ?? 0;
    $title = array_values(
        array_filter(
            get_resource_field_data($resource),
            function($f) use ($title_field) { return $f['ref'] == $title_field && $f['type'] == FIELD_TYPE_TEXT_BOX_SINGLE_LINE; }
        )
    );
    $title = array_reverse($title);
    $title = array_pop($title);

    return i18n_get_translated($title['value'] ?? '');
    }

function sync_flickr($search,$new_only=false,$photoset=0,$photoset_name="",$private=0)
    {
    # For the resources matching $search, synchronise with Flickr.

    global $flickr,$flickr_api_key, $flickr_token, $flickr_caption_field, $flickr_keywords_field, $flickr_prefix_id_title, $lang, $flickr_scale_up,
    $flickr_nice_progress,$flickr_default_size,$flickr_alt_image_sizes, $FIXED_LIST_FIELD_TYPES;
            
    $results=do_search($search);
    
    if($flickr_nice_progress)
        {
        $results_processed=0;
        $results_new_publish=0;
        $results_no_publish=0;
        $results_update_publish=0;
        }
    
    foreach ($results as $result)
        {
        global $flickr;

        # Fetch some resource details.
        $title = flickr_get_title_field_value($result['ref']);
        $description = get_data_by_field($result["ref"],$flickr_caption_field);
        
        $field_type=ps_value("select type value from resource_type_field where ref = ?", array("i",$flickr_keywords_field), "", "schema");
        if (in_array($field_type, $FIXED_LIST_FIELD_TYPES))
            {
            $keyword_node_values = get_resource_nodes($result["ref"], $flickr_keywords_field, true);
            $keyword_node_values = array_column($keyword_node_values,'name');
            # flickr requires a space separated string of tag words - adding comma allows flickr to pick up multi word tags.
            $keywords = array_map(function($kw) {return $kw . ',';}, $keyword_node_values);
            $keywords = implode(" ", array_unique($keywords));
            }
        else
            {
            $keywords = get_data_by_field($result["ref"],$flickr_keywords_field);
            }

        $photoid=ps_value("select flickr_photo_id value from resource where ref = ?", array("i",$result["ref"]), "");
        if($flickr_nice_progress)
            {
            $nice_title=$result["ref"]." - ".$title;
            }
        
        # Prefix ID to title?
        if ($flickr_prefix_id_title)
            {
            $title=$result["ref"] . " - " . $title;
            }
            
        if (!$new_only || $photoid=="")
            {
            // Output: Processing resource...
            if(!$flickr_nice_progress)
                {
                echo "<li>" . $lang["processing"] . ": '" . $title . "'\n";
                }
            else
                {
                flickr_update_progress_file("photo ".$nice_title);
                }
            if(strtolower($flickr_default_size)=="original")
                {
                $flickr_default_size="";
                }
            $im=get_resource_path($result["ref"],true,$flickr_default_size,false,"jpg");
            if(!file_exists($im) && $flickr_scale_up)
                {
                foreach($flickr_alt_image_sizes as $flickr_alt_image_size)
                    {
                    if(strtolower($flickr_alt_image_size)=="original")
                        {
                        $flickr_alt_image_size="";
                        }
                    $im=get_resource_path($result['ref'],true,$flickr_alt_image_size,false);
                    if(file_exists($im))
                        {
                        break;
                        }
                    }
                }
            if(!file_exists($im))
                {
                // Output: No suitable upload...
                if(!$flickr_nice_progress){echo "<li>" . $lang["flickr-problem-finding-upload"];}
                else
                    {
                    $results_no_publish++;
                    $results_processed++;
                    flickr_update_progress_file("no publish ".$nice_title." | processed=".$results_processed." new_publish=".$results_new_publish." no_publish=".$results_no_publish." update_meta=".$results_update_publish);
                    }
                continue;
                }
    
            # If replacing, add the photo ID of the photo to replace.
            if ($photoid != "")
                {
                // Output: Already published - updating metadata...
                if(!$flickr_nice_progress)
                    {
                    echo "<li>" . str_replace("%photoid", $photoid, $lang["updating_metadata_for_existing_photoid"]);
                    }
                else
                    {
                    flickr_update_progress_file("updating ".$nice_title);
                    }
                
                # Also resubmit title, description and keywords.
                $flickr->call("flickr.photos.setTags",array("photo_id"=>$photoid, "tags"=>$keywords));
                $flickr->call("flickr.photos.setMeta",array("photo_id"=>$photoid, "title"=>$title, "description"=>$description));
                
            
                if($flickr_nice_progress)
                    {
                    $results_update_publish++;
                    $results_processed++;
                    flickr_update_progress_file("updated ".$nice_title." | processed=".$results_processed." new_publish=".$results_new_publish." no_publish=".$results_no_publish." update_meta=".$results_update_publish);
                    }
                }
            else
                {
                # New uploads only. Send the photo file.
                // Output: Publishing new resource...
                if(!$flickr_nice_progress){echo "<li>" . str_replace("%photoid", $title, $lang["flickr_new_upload"]);}
                else{flickr_update_progress_file("adding ".$nice_title);}
                
                $photoid = $flickr->sync_upload($im, $title, ($description != "" ? $description : null), ($keywords != "" ? $keywords : null), false);
                
                if(!$flickr_nice_progress)
                    {
                    echo "<li>" . escape(str_replace("%photoid", (string) $photoid, $lang["photo-uploaded"]));
                    }
                else
                    {
                    $results_new_publish++;
                    $results_processed++;
                    flickr_update_progress_file("added ".$nice_title." | processed=".$results_processed." new_publish=".$results_new_publish." no_publish=".$results_no_publish." update_meta=".$results_update_publish);
                    }

                # Update Flickr tag ID
                ps_query("update resource set flickr_photo_id = ? where ref = ?", array("s",$photoid,"i",$result["ref"]));
                }

            $created_new_photoset=false;
            if ($photoset==0)
                {
                # Photoset must be created.
                $response = $flickr->call("flickr.photosets.create",array("title"=>$photoset_name, "primary_photo_id"=>$photoid));
                $photoset = $response["photoset"]["id"];                
                
                // Output: New photoset created...
                if(!$flickr_nice_progress)
                    {
                    echo "<li>" . str_replace(array("%photoset_name", "%photoset"), array($photoset_name, $photoset), $lang["created-new-photoset"]);
                    }
                else
                    {
                    flickr_update_progress_file("new photoset ".$photoset_name);
                    }
                $created_new_photoset=true;
                }

            # Add to photoset
            if (!$created_new_photoset)
                {
                # If we've just created a photoset then this will already be present within it as the primary photo (added during the create photoset request).
                 $flickr->call("flickr.photosets.addPhoto",array("photoset_id"=>$photoset, "photo_id"=>$photoid));
                // Output: Added new upload to photoset...
                if(!$flickr_nice_progress)
                    {
                    echo "<li>" . str_replace(array("%photoid", "%photoset"), array($photoid, $photoset), $lang["added-photo-to-photoset"]);
                    }
                else
                    {
                    flickr_update_progress_file("adding photo to photoset ".$nice_title);
                    }
                }
                        
            # Set permissions
            // Output: Updating permissions...
            if(!$flickr_nice_progress)
                {
                echo "<li>" . str_replace("%permission", $private==0 ? $lang["flickr_public"] : $lang["flickr_private"], $lang["setting-permissions"]);
                }
            else
                {
                $perm=$private==0 ? $lang["flickr_public"] : $lang["flickr_private"];
                flickr_update_progress_file("permissions ".$perm);
                }
            $flickr->call("flickr.photos.setPerms",array("photo_id"=>$photoid, "is_public"=>($private==0?1:0),"is_friend"=>0,"is_family"=>0,"perm_comment"=>0,"perm_addmetadata"=>0));
        }
        }
    // Output: Done with all requests...
    if(!$flickr_nice_progress)
        {
        echo "<li>" . $lang["done"];
        }
    else
        {
        flickr_update_progress_file($lang["done"]." | processed=".$results_processed." new_publish=".$results_new_publish." no_publish=".$results_no_publish." update_meta=".$results_update_publish);
        }
    }
    
function flickr_update_progress_file($note)
    {
    global $progress_file;
    $fp = fopen($progress_file, 'w');       
    $filedata=$note;
    fwrite($fp, $filedata);
    fclose($fp);
    }

function flickr_get_access_token($userref,$fromrequest=false)
    {
    global $flickr, $flickr_api_key,$flickr_api_secret,$flickr_token,$lang,$theme,$baseurl,$last_xml;
    
    $flickr_tokens = ps_query("select flickr_token, flickr_token_secret from user where ref = ?", array("i",$userref));
        
    if(isset($flickr_tokens[0]) && $flickr_tokens[0]["flickr_token"] != "" && $flickr_tokens[0]["flickr_token_secret"] != "")
        {
        debug("flickr_theme_publish - using existing user Flickr access token");
        $OauthToken = $flickr_tokens[0]["flickr_token"];
        $OauthSecretToken = $flickr_tokens[0]["flickr_token_secret"];
        $flickr->setOauthToken ($flickr_tokens[0]['flickr_token'], $flickr_tokens[0]['flickr_token_secret']);
        debug("flickr_theme_publish: OAuth token: " . $flickr_tokens[0]['flickr_token']);
        debug("flickr_theme_publish: OAuth secret token: " . $flickr_tokens[0]['flickr_token_secret']);
        
        if(!$flickr->auth_oauth_checkToken())
            {
            debug("flickr_theme_publish - access token invalid. Deleting");
            flickr_update_tokens($userref,"","");
            flickr_get_request_token("write");
            exit("ERROR - unable to get token for Flickr communication, please reload the page");    
            }
        }
    elseif($fromrequest)
        {
        $flickr->getAccessToken();
        $OauthToken = $flickr->getOauthToken();
        $OauthSecretToken = $flickr->getOauthSecretToken();        
        flickr_update_tokens($userref,$OauthToken,$OauthSecretToken);
        debug("flickr_theme_publish - tokens updated");
        }
    else
        {
        debug("flickr_theme_publish - redirect to obtain new request token");
        flickr_get_request_token("write");
        exit();
        }
    
    return array("flickr_token"=>$OauthToken,"flickr_token_secret"=>$OauthSecretToken);
    }

function flickr_get_photoset()
    {
    global $flickr, $flickr_api_key,$flickr_token,$last_xml,$theme;

    # Make sure a photoset exists for this theme
    $psetinfo = $flickr->call ("flickr.photosets.getList",array());
    $photosets = $psetinfo["photosets"]["photoset"];
    
    # Look for the name of the current collection.
    $photoset_name=ps_value("select name value from collection where ref = ?", array("i",$theme), "");
    $photosetid=0;
    foreach($photosets as $photoset)
        {
        if($photoset["title"] == $photoset_name)
            {
            # Name already exists. Just use this photoset ID.
            $photosetid=$photoset["id"];    
            }
        }

    return array($photoset_name, $photosetid);
    }


function flickr_update_tokens($userref, $OauthToken,$OauthSecretToken)
    {
    if((string)(int)$userref != $userref)
        {
        return false;
        }
    ps_query("update user set flickr_token = ?, flickr_token_secret = ? where ref = ?", array("s",$OauthToken,"s",$OauthSecretToken,"i",$userref));
    return true;
    }

function flickr_get_request_token($access)
    {
    global $flickr,$baseurl;
    $callback_url = $baseurl . $_SERVER["SCRIPT_NAME"] . "?" . $_SERVER["QUERY_STRING"];
    debug("flickr_theme_publish -  requesting authorisation. Callback URL: " . $callback_url);
    $flickr->getRequestToken($callback_url, $access);
    }