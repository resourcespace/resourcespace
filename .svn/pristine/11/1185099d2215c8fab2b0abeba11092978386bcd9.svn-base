<?php
include "../include/boot.php";
include "../include/authenticate.php";

$collection_url = getval('collection', '', true);
$col_order_by   = getval('col_order_by', '', true);
$find           = getval('find', '', true);
$offset         = getval('offset', '', true);
$order_by       = getval('order_by', '', true);
$ref            = getval('ref', '', true);
$restypes       = getval('restypes', '', true);
$search         = getval('search', '', true);
$sort           = getval('sort', '', true);
$user_group     = getval('usergroup', '', true);
$backurl        = getval('backurl', '');

// Check if editing existing external share
$editaccess     = trim(getval("editaccess", ""));
$editing        = ($editaccess != "");

$editexternalurl    = (getval("editexternalurl", "") != "");
$deleteaccess       = (getval("deleteaccess", "") != "");
$generateurl        = (getval("generateurl", "") != "");

// Share options
if ($editing) {
    $shareinfo      = get_external_shares(array("share_collection" => $ref, "access_key" => $editaccess));
    if (isset($shareinfo[0])) {
        $shareinfo  = $shareinfo[0];
    } else {
        error_alert($lang["error_invalid_key"], true);
        exit();
    }
    $expires        = getval("expires", $shareinfo["expires"]);
    $access         = getval("access", $shareinfo["access"], true);
    $group          = getval("usergroup", $shareinfo["usergroup"], true);
    $sharepwd       = getval('sharepassword', ($shareinfo["password_hash"] != "" ? "true" : ""));
} else {
    $expires        = getval("expires", "");
    $access         = getval("access", -1, true);
    $group          = getval("usergroup", 0, true);
    $sharepwd       = getval('sharepassword', '');
}

$collection = get_collection($ref);

if ($collection === false) {
    $error = $lang['error-collectionnotfound'];
    if (getval("ajax", "") != "") {
        error_alert($error, false, 404);
    } else {
        include "../include/header.php";
        $onload_message = array("title" => $lang["error"],"text" => $error);
        include "../include/footer.php";
    }
    exit();
}

if ($collection["type"] == COLLECTION_TYPE_FEATURED) {
    $collection_resources = get_collection_resources($collection["ref"]);
    $collection["has_resources"] = (is_array($collection_resources) && !empty($collection_resources) ? 1 : 0);
}

if ($bypass_share_screen && $collection["type"] != COLLECTION_TYPE_SELECTION && !is_anonymous_user()) {
    redirect('pages/collection_email.php?ref=' . $ref) ;
}

// Check access controls
if (!collection_readable($ref)) {
    exit($lang["no_access_to_collection"]);
} elseif (
    $collection["type"] == COLLECTION_TYPE_FEATURED
    && !featured_collection_check_access_control((int) $collection["ref"])
    && !allow_featured_collection_share($collection)
) {
    error_alert($lang["error-permissiondenied"], true, 403);
    exit();
}

if (!$allow_share || checkperm("b")) {
    $show_error = true;
    $error = $lang["error-permissiondenied"];
}

$internal_share_only = checkperm("noex") || (isset($user_dl_limit) && intval($user_dl_limit) > 0);

// Special collection being shared - we need to make a copy of it and disable internal access
$share_selected_resources = false;

if ($collection["type"] == COLLECTION_TYPE_SELECTION) {
    $share_selected_resources = true;

    // disable a few options
    $hide_internal_sharing_url = true;
    $email_sharing = false;
    $home_dash = false;

    // Prevent users from sharing the real collection. Copy it instead
    if (($generateurl && !$editing) || $editexternalurl || $deleteaccess) {
        $ref = create_collection($userref, $collection["name"]);
        copy_collection($collection["ref"], $ref);
        $collection = get_collection($ref);
    }
}
// Special collection being shared. Ensure certain features are enabled/disabled
elseif (is_featured_collection_category($collection)) {
    // Check this is not an empty FC category
    $fc_resources = get_featured_collection_resources($collection, array("limit" => 1));
    if (empty($fc_resources)) {
        error_alert($lang["cannotshareemptythemecategory"], true, 200);
        exit();
    }

    // Further checks at collection-resource level. Recurse through category's sub FCs
    $collection["sub_fcs"] = get_featured_collection_categ_sub_fcs($collection);
    $collectionstates = false;
    $sub_fcs_resources_states = array();
    $sub_fcs_resources_minaccess = array();
    foreach ($collection["sub_fcs"] as $sub_fc) {
        // Check all featured collections contain only active resources
        $collectionstates = is_collection_approved($sub_fc);
        if (!$collection_allow_not_approved_share && $collectionstates === false) {
            break;
        } elseif (is_array($collectionstates)) {
            $sub_fcs_resources_states = array_unique(array_merge($sub_fcs_resources_states, $collectionstates));
        }

        // Check minimum access is restricted or lower and sharing of restricted resources is not allowed
        $sub_fcs_resources_minaccess[] = collection_min_access($sub_fc);
    }
    $collectionstates = (!empty($sub_fcs_resources_states) ? $sub_fcs_resources_states : $collectionstates);

    if (!empty($sub_fcs_resources_minaccess)) {
        $minaccess = max(array_unique($sub_fcs_resources_minaccess));
    }

    // To keep it in line with the legacy theme_category_share.php page, disable these features (home_dash, hide_internal_sharing_url)
    $home_dash = false;

    // Beyond this point mark accordingly any validations that have been enforced specifically for Featured Collections
    // (categories or otherwise) type in a different way than for a normal collection
    // IMPORTANT: make sure there's code above this point (within this block) dealing with these validations.
    $collection_allow_empty_share = true;
}

$resource_count = count(get_collection_resources($ref));

// Sharing an empty collection?
if (!$collection_allow_empty_share && $resource_count == 0) {
    $show_error = true;
    $error = $lang["cannotshareemptycollection"];
}

#Check if any resources are not active
$collectionstates = (isset($collectionstates) ? $collectionstates : is_collection_approved($ref));
if (!$collection_allow_not_approved_share && !$collectionstates) {
        $show_error = true;
        $error = $lang["notapprovedsharecollection"];
}

if (is_array($collectionstates) && (count($collectionstates) > 1 || !in_array(0, $collectionstates))) {
    $warningtext = $lang["collection_share_status_warning"];
    foreach ($collectionstates as $collectionstate) {
        $warningtext .= "<br />" . $lang["status" . $collectionstate];
    }
}

# Minimum access is restricted or lower and sharing of restricted resources is not allowed. The user cannot share this collection.
# The same applies for collections where the user creating the share doesn't have access to all resources in the collection e.g. some resources are in states blocked by a z permission.
$minaccess = (isset($minaccess) ? $minaccess : collection_min_access($ref));
if (!$restricted_share && $minaccess >= RESOURCE_ACCESS_RESTRICTED || $resource_count != count(do_search("!collection{$ref}", '', 'relevance', 0, -1, 'desc', false, '', false, '', '', false, false))) {
    $show_error = true;
    $error = $lang["restrictedsharecollection"];
}

# Should those that have been granted open access to an otherwise restricted resource be able to share the resource? - as part of a collection
if (!$allow_custom_access_share && isset($customgroupaccess) && isset($customuseraccess)  && ($customgroupaccess || $customuseraccess)) {
    $show_error = true;
    $error = $lang["customaccesspreventshare"];
}

# Process deletion of access keys
if ($deleteaccess && !isset($show_error) && enforcePostRequest(getval("ajax", false))) {
    delete_collection_access_key($ref, getval("deleteaccess", ""));
}

include "../include/header.php";

if (isset($show_error)) { ?>
    <script type="text/javascript">
        alert('<?php echo escape($error); ?>');
        history.go(-1);
    </script>
    <?php
    exit();
}
?>

<div class="BasicsBox">     
    <form method=post id="collectionform" action="<?php echo $baseurl_short?>pages/collection_share.php?ref=<?php echo urlencode($ref)?>">
        <input type="hidden" name="ref" id="ref" value="<?php echo escape($ref) ?>">
        <input type="hidden" name="deleteaccess" id="deleteaccess" value="">
        <input type="hidden" name="editaccess" id="editaccess" value="<?php echo escape($editaccess)?>">
        <input type="hidden" name="editexpiration" id="editexpiration" value="">
        <input type="hidden" name="editaccesslevel" id="editaccesslevel" value="">
        <input type="hidden" name="editgroup" id="editgroup" value="">
        <?php generateFormToken("collectionform");

        $page_header = $lang["sharecollection"];
        if ($editing && !$editexternalurl) {
            $page_header .= " - {$lang["editingexternalshare"]} $editaccess";
        }

        if (strpos($backurl, "/pages/team/team_external_shares.php") !== false) {
            $links_trail = array(
                array(
                    'title' => $lang["teamcentre"],
                    'href'  => $baseurl_short . "pages/team/team_home.php",
                    'menu' =>  true
                ),
                array(
                    'title' => $lang["manage_external_shares"],
                    'href'  => $baseurl . $backurl
                ),
                array(
                    'title' => $page_header,
                    'help'  => "user/sharing-resources"
                )
            );

            renderBreadcrumbs($links_trail);
        } else {
            ?>
            <h1>
                <?php
                echo escape($page_header);
                render_help_link("user/sharing-resources");
                ?>
            </h1>
            <?php
        }

        if (isset($warningtext)) {
            echo "<div class='PageInformal'>" . $warningtext . "</div>";
        }

        if ($collection["type"] == COLLECTION_TYPE_FEATURED && is_featured_collection_category($collection)) {
            echo "<p>" . escape($lang["share_fc_warning"]) . "</p>";
        }
        ?>

        <div class="VerticalNav">
            <ul>
                <?php
                # Flag to prevent duplicate rendering of the "generateinternalurl" text and associated input field
                $generateinternalurl_rendered = false;
                $url_params = [
                    'ref'           => $ref,
                    'search'        => $search,
                    'collection'    => $collection,
                    'restypes'      => $restypes,
                    'order_by'      => $order_by,
                    'col_order_by'  => $col_order_by,
                    'sort'          => $sort,
                    'offset'        => $offset,
                    'find'          => $find,
                    'k'             => $k

                ];

                if (!$editing || $editexternalurl) {
                    if ($email_sharing && !is_anonymous_user()) {
                        ?>
                        <li>
                            <i aria-hidden="true" class="icon-mail"></i>&nbsp;
                            <a onClick="return CentralSpaceLoad(this,true);" href="<?php echo generateURL($baseurl_short . 'pages/collection_email.php', $url_params); ?>">
                                <?php echo escape($lang["emailcollectiontitle"]); ?>
                            </a>
                        </li>
                        <?php
                    }

                    # Share as a dash tile.
                    global $home_dash,$anonymous_login,$username;

                    if ($home_dash && checkPermission_dashcreate() && !hook('replace_share_dash_create')) {
                        ?>
                        <li>
                            <i aria-hidden="true" class="icon-layout-dashboard"></i>&nbsp;
                            <a href="<?php echo $baseurl_short;?>pages/dash_tile.php?create=true&tltype=srch&promoted_resource=true&freetext=true&all_users=1&link=/pages/search.php?search=!collection<?php echo $ref?>&order_by=relevance&sort=DESC" onclick="return CentralSpaceLoad(this,true);">
                                <?php echo escape($lang["createnewdashtile"]); ?>
                            </a>
                        </li>
                        <?php
                    }

                    if (!$internal_share_only) {
                        ?>
                        <li>
                            <i aria-hidden="true" class="icon-link"></i>&nbsp;
                            <a onClick="return CentralSpaceLoad(this,true);" href="<?php echo generateURL($baseurl_short . 'pages/collection_share.php', $url_params, ['generateurl' => true]); ?>">
                                <?php echo escape($lang["generateurl"]); ?>
                            </a>
                        </li> 
                        <?php
                    }

                    if (
                        !$hide_internal_sharing_url
                        && ($collection["type"] == COLLECTION_TYPE_FEATURED
                        && allow_featured_collection_share($collection)
                        || $collection["public"] == 1 || $ignore_collection_access)
                        && !$generateurl
                    ) { // Just show the internal share URL straight away as there is no generate link
                        ?>
                        <p><?php echo escape($lang["generateurlinternal"])?></p>
                        <p>
                            <input class="URLDisplay" type="text" value="<?php echo $baseurl; ?>/?c=<?php echo urlencode($ref); ?>">
                            <?php $generateinternalurl_rendered = true; ?>
                        </p>
                        <?php
                    }

                    hook("extra_share_options");
                }

                if (!$internal_share_only && ($editing || $generateurl)) {
                    if (
                        (
                            !($hide_internal_sharing_url) 
                            && (!$editing || $editexternalurl) 
                            && $collection["public"] == 1 
                            || $ignore_collection_access
                        )
                        && !$generateinternalurl_rendered # Only render "generateinternalurl" text and associated input field if it hasn't already been rendered
                    ) {
                        ?>
                        <p><?php echo escape($lang["generateurlinternal"])?></p>
                        <p>
                            <input class="URLDisplay" type="text" value="<?php echo $baseurl?>/?c=<?php echo urlencode($ref) ?>">
                            <?php $generateinternalurl_rendered = true; ?>
                        </p>
                        <?php
                    }

                    if (
                        ($access == -1 || ($editing && !$editexternalurl)) 
                        && (!isset($anonymous_login) || $username !== $anonymous_login)
                    ) {
                        ?>
                        <p>
                            <?php if (!$editing || $editexternalurl) {
                                echo strip_tags_and_attributes($lang["selectgenerateurlexternal"]);
                            } ?>
                        </p>

                        <?php
                        if ($editing) {
                            echo "<div class='Question'><label>"
                                . escape($lang["collectionname"])
                                . "</label><div class='Fixed'>"
                                . i18n_get_collection_name($collection)
                                . "</div><div class='clearerleft'></div></div>";
                        }

                        $shareoptions = array(
                            "password"          => ($sharepwd != "" ? true : false),
                            "editaccesslevel"   => $access,
                            "editexpiration"    => $expires,
                            "editgroup"         => $group,
                            );

                        render_share_options($shareoptions);
                        ?>
                        
                        <div class="QuestionSubmit">
                            <?php if ($editing && !$editexternalurl) { ?>
                                <input
                                    name="editexternalurl"
                                    type="submit"
                                    onclick="<?php
                                    if ($share_password_required) {
                                        echo 'if (!enforceSharePassword(\'' . escape($lang['share-password-not-set']) . '\')) { return false; }; ';
                                    } ?>"
                                    value="<?php echo escape($lang["save"]); ?>"
                                />
                            <?php } else { ?>
                                <input
                                    name="generateurl"
                                    type="submit"
                                    onclick="<?php
                                    if ($share_password_required) {
                                        echo 'if (!enforceSharePassword(\'' . escape($lang['share-password-not-set']) . '\')) { return false; }; ';
                                    } ?>"
                                    value="<?php echo escape($lang["generateexternalurl"]); ?>"
                                />
                            <?php } ?>
                        </div>

                        <?php
                    } elseif ($editaccess == "" && !($editing && $editexternalurl)) {
                        // Access has been selected. Generate a new URL.
                        $generated_access_key = '';

                        enforceSharePassword($sharepwd);

                        if (empty($allowed_external_share_groups) || (!empty($allowed_external_share_groups) && in_array($user_group, $allowed_external_share_groups))) {
                            $generated_access_key = generate_collection_access_key($collection, 0, 'URL', $access, $expires, $user_group, $sharepwd);
                        } elseif (!empty($allowed_external_share_groups) && !in_array($usergroup, $allowed_external_share_groups)) {
                            // Not allowed to select usergroup but this usergroup can not be used, default to the first entry in allowed_external_share_groups
                            $generated_access_key = generate_collection_access_key($collection, 0, 'URL', $access, $expires, $allowed_external_share_groups[0], $sharepwd);
                        }

                        if (
                            '' != $generated_access_key 
                            || (
                                isset($anonymous_login) 
                                && $generated_access_key === false 
                                && $username === $anonymous_login
                            )
                        ) {
                            $url_params = ['c' => $ref];
                            if ($generated_access_key != false) {
                                $url_params['k'] = $generated_access_key;
                            }
                            ?>  
                            <p><?php echo escape($lang['generateurlexternal']); ?></p>
                            <p>
                                <input class="URLDisplay" type="text" value="<?php echo generateURL($baseurl, $url_params); ?>">
                            </p>
                            <?php
                        } else {
                            ?>
                            <div class="PageInformal"><?php echo escape($lang['error_generating_access_key']); ?></div>
                            <?php
                        }
                    }

                    # Process editing of external share
                    if ($editexternalurl) {
                        enforceSharePassword($sharepwd);
                        $editsuccess = edit_collection_external_access($editaccess, $access, $expires, getval("usergroup", ""), $sharepwd);
                        if ($editsuccess) {
                            echo "<span style='font-weight:bold;'>"
                                . escape($lang['changessaved'])
                                . " - <em>" . escape($editaccess) . "</em>";
                        }
                    }
                }
                ?>
            </ul>
        </div>

        <?php
        if (
            collection_writeable($ref) ||
            (isset($collection['savedsearch']) && $collection['savedsearch'] != null && ($userref == $collection["user"] || checkperm("h")))
        ) {
            if (!($hide_internal_sharing_url) && (!$editing || $editexternalurl)) {
                ?>
                <h2><?php echo escape($lang["internalusersharing"])?></h2>
                <div class="Question">
                    <label for="users"><?php echo escape($lang["attachedusers"])?></label>
                    <div class="Fixed">
                        <?php echo escape($collection["users"] == "" ? $lang["noattachedusers"] : $collection["users"]); ?>
                        <br />
                        <br />
                        <a onclick="return CentralSpaceLoad(this, true);" href="<?php echo $baseurl_short?>pages/collection_edit.php?ref=<?php echo urlencode($ref); ?>">
                            <?php echo LINK_CARET . escape($lang["action-edit"]);?>
                        </a>
                    </div>
                    <div class="clearerleft"></div>
                </div>
                
                <p>&nbsp;</p>
                <?php
            }

            if (!$internal_share_only) { ?>
                <h2><?php echo escape($lang["externalusersharing"])?></h2>

                <?php
                $keys = get_external_shares(array("share_collection" => $ref));
                if (count($keys) == 0) {
                    ?>
                    <p><?php echo escape($lang["noexternalsharing"]) ?></p>
                    <?php
                } else {
                    ?>
                    <div class="Listview">
                        <table class="ListviewStyle">
                            <tr class="ListviewTitleStyle">
                                <th><?php echo escape($lang["accesskey"]);?></th>
                                <th><?php echo escape($lang["sharedby"]);?></th>
                                <th><?php echo escape($lang["sharedwith"]);?></th>
                                <th><?php echo escape($lang["lastupdated"]);?></th>
                                <th><?php echo escape($lang["lastused"]);?></th>
                                <th><?php echo escape($lang["expires"]);?></th>
                                <th><?php echo escape($lang["access"]);?></th>
                                <?php
                                global $social_media_links;
                                if (!empty($social_media_links)) {
                                    ?>
                                    <th><?php echo escape($lang['social_media']); ?></th>
                                    <?php
                                }
                                ?>
                                <th>
                                    <div class="ListTools"><?php echo escape($lang["tools"]); ?></div>
                                </th>
                            </tr>

                            <?php for ($n = 0; $n < count($keys); $n++) { ?>
                                <tr>
                                    <td>
                                        <div class="ListTitle">
                                            <a target="_blank" href="<?php echo $baseurl . "?c=" . urlencode($ref) . "&k=" . urlencode($keys[$n]["access_key"]) ?>">
                                                <?php echo escape($keys[$n]["access_key"]) ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td><?php echo escape(resolve_users($keys[$n]["user"]) ?? "")?></td>
                                    <td><?php echo escape($keys[$n]["email"] ?? "") ?></td>
                                    <td><?php echo escape(nicedate($keys[$n]["date"], true, true, true) ?? "");  ?></td>
                                    <td><?php echo escape(nicedate($keys[$n]["lastused"], true, true, true) ?? ""); ?></td>
                                    <td><?php echo escape(($keys[$n]["expires"] == "") ? $lang["never"] : nicedate($keys[$n]["expires"], false) ?? "") ?></td>
                                    <td><?php echo escape(($keys[$n]["access"] == -1) ? "" : $lang["access" . $keys[$n]["access"]] ?? ""); ?></td>
                                    <?php if (!empty($social_media_links)) { ?>
                                        <td><?php renderSocialMediaShareLinksForUrl(generateURL($baseurl, array('c' => $ref, 'k' => $keys[$n]['access_key']))); ?></td>
                                    <?php }
                                    $editlink = generateURL(
                                        $baseurl . "/pages/collection_share.php",
                                        array(
                                            "ref" => $keys[$n]["collection"],
                                            "editaccess" => $keys[$n]["access_key"],
                                        )
                                    );
                                ?>                
                                    <td>
                                        <div class="ListTools">
                                            <a href="#" onclick="if (confirm('<?php echo escape($lang["confirmdeleteaccess"])?>')) {document.getElementById('deleteaccess').value='<?php echo escape($keys[$n]["access_key"]) ?>';document.getElementById('collectionform').submit(); return false;}">
                                                <?php echo LINK_CARET . escape($lang["action-delete"]); ?>
                                            </a>
                                            <a onclick="return CentralSpaceLoad(this,true);" href="<?php echo $editlink; ?>">
                                                <?php echo LINK_CARET . escape($lang["action-edit"]); ?>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php } ?>
                        </table>
                    </div>
                    <?php
                }
            }
        }
        ?>
    </form>
</div>

<script>
    jQuery('#collectionform').submit(function() {
        CentralSpaceShowProcessing();
        jQuery('#collectionform :input[type=submit]').hide();
    });
</script>

<?php
include "../include/footer.php";
?>
