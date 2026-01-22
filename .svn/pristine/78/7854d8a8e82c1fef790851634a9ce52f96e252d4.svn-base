<?php
include "../include/boot.php";
include "../include/authenticate.php";

$ref        = getval('ref', '', true);
$user_group = getval('usergroup', '', true);

# fetch the current search (for finding simlar matches)
$search       = getval("search", "");
$order_by     = getval("order_by", "relevance");
$offset       = getval("offset", 0, true);
$restypes     = getval("restypes", "");

if (strpos($search, "!") !== false) {
    $restypes = "";
}

$archive      = getval("archive", 0, true);
$default_sort_direction = (substr($order_by, 0, 5) == "field") ? "ASC" : "DESC";
$sort         = getval("sort", $default_sort_direction);
$ajax         = filter_var(getval("ajax", false), FILTER_VALIDATE_BOOLEAN);
$modal        = (getval("modal", "") == "true");
$backurl      = getval('backurl', '');

# Check if editing existing external share
$editaccess   = getval("editaccess", "");
$deleteaccess = getval('deleteaccess', '');
$editing      = ($editaccess != "" && $deleteaccess == "") ? true : false;

$editexternalurl = (getval("editexternalurl", "") != "");
$generateurl  = getval("generateurl", "") != "";
$share_user = getval("share_user", 0);

// Share options
if ($editing) {
    $shareinfo      = get_external_shares(array("share_resource" => $ref, "access_key" => $editaccess, "share_user" => (int)$share_user));
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

$minaccess = get_resource_access($ref);

# Check if sharing permitted
if (!can_share_resource($ref, $minaccess)) {
    $show_error = true;
    $error      = $lang["error-permissiondenied"];
}

$internal_share_only = checkperm("noex") || (isset($user_dl_limit) && intval($user_dl_limit) > 0);

# Process deletion of access keys
if ('' != $deleteaccess && enforcePostRequest($ajax)) {
    delete_resource_access_key($ref, $deleteaccess);
}

# Process deletion of custom user access
$deleteusercustomaccess = getval('deleteusercustomaccess', '');
$user = getval('user', '');

if ($deleteusercustomaccess == 'yes' && checkperm('v') && enforcePostRequest($ajax)) {
    delete_resource_custom_user_access($ref, $user);
    resource_log($ref, 'a', '', $lang['log-removedcustomuseraccess'] . $user);
}

include "../include/header.php";

if (isset($show_error)) { ?>
    <script type="text/javascript">
        alert('<?php echo $error;?>');
        history.go(-1);
    </script>
    <?php
    exit();
}

$query_string = 'ref=' . urlencode($ref) . '&search=' . urlencode($search) . '&offset=' . urlencode($offset) . '&order_by=' . urlencode($order_by) . '&sort=' . urlencode($sort) . '&archive=' . urlencode($archive) . '&modal=' . $modal;
$urlparams    = array(
    'ref'      => $ref,
    'search'   => $search,
    'offset'   => $offset,
    'order_by' => $order_by,
    'sort'     => $sort,
    'archive'  => $archive
);

$page_header = $lang["share-resource"];

if ($editing && !$editexternalurl) {
    $page_header .= " - {$lang["editingexternalshare"]} $editaccess";
}
?>

<div class="BasicsBox">
    <div class="RecordHeader">
        <div class="BackToResultsContainer">
            <div class="backtoresults">
                <?php if ($modal) { ?>
                    <a href="#" class="closeLink icon-x" onclick="ModalClose();" title="<?php echo escape($lang["close"]); ?>"></a>
                <?php } ?>
            </div>
        </div>

        <?php
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
            if (getval("context", false) == 'Modal') {
                $previous_page_modal = true;
            } else {
                $previous_page_modal = false;
            }
            ?>
            
            <h1>
                <?php
                echo escape($page_header);
                render_help_link("user/sharing-resources");
                ?>
            </h1>

            <p>
                <?php if ($previous_page_modal) { ?>
                    <a href="<?php echo generateURL($baseurl_short . 'pages/view.php', $urlparams); ?>" onclick="return ModalLoad(this,true);">
                <?php } else { ?>
                    <a href="<?php echo generateURL($baseurl_short . 'pages/view.php', $urlparams); ?>" onclick="return CentralSpaceLoad(this,true);">
                <?php }

                echo LINK_CARET_BACK . escape($lang["backtoresourceview"]);
                ?>
                </a>
            </p>
            <?php
        }
        ?>
    </div>

    <form method="post" id="resourceshareform" action="<?php echo $baseurl_short?>pages/resource_share.php?ref=<?php echo urlencode($ref)?>">
        <input type="hidden" name="deleteaccess" id="deleteaccess" value="">
        <input type="hidden" name="generateurl" id="generateurl" value="">
        <input type="hidden" name="editaccess" id="editaccess" value="<?php echo escape($editaccess)?>">
        <input type="hidden" name="editexpiration" id="editexpiration" value="">
        <input type="hidden" name="editgroup" id="editgroup" value="">
        <input type="hidden" name="editaccesslevel" id="editaccesslevel" value="">
        <input type="hidden" name="editexternalurl" id="editexternalurl" value="">
        <input type="hidden" name="user" id="user" value="">
        <input type="hidden" name="deleteusercustomaccess" id="deleteusercustomaccess" value="">
        
        <?php
        if ($modal) {
            ?>
            <input type="hidden" name="modal" value="true">
            <?php
        }
        generateFormToken("resourceshareform");
        ?>

        <div class="VerticalNav">
            <ul>
                <?php if ((!$editing || $editexternalurl) && $email_sharing && !is_anonymous_user()) { ?>
                    <li>
                        <i aria-hidden="true" class="icon-mail"></i>&nbsp;
                        <a href="<?php echo $baseurl_short . 'pages/resource_email.php?' . $query_string ?>" onclick="return <?php echo $modal ? 'ModalLoad' : 'CentralSpaceLoad';?>(this, true);">
                            <?php echo escape($lang["emailresourcetitle"]); ?>
                        </a>
                    </li> 
                    <?php
                }

                if (!$editing) { ?>
                    <p><?php echo escape($lang["generateurlinternal"]);?></p>
                    <p><input class="URLDisplay" type="text" value="<?php echo $baseurl?>/?r=<?php echo $ref?>"></p>
                    <?php
                }

                if ($deleteaccess == "" && !$internal_share_only) {
                    if (!($editexternalurl || $generateurl)) {
                        ?>                    
                        <p>
                            <?php
                            if (!$editing || $editexternalurl) {
                                echo strip_tags_and_attributes($lang["selectgenerateurlexternal"]);
                            }
                            ?>
                        </p>

                        <?php
                        $shareoptions = array(
                            "password"          => ($sharepwd != "" ? true : false),
                            "editaccesslevel"   => $access,
                            "editexpiration"    => $expires,
                            "editgroup"         => $group,
                        );

                        render_share_options($shareoptions);
                        ?>

                        <div class="QuestionSubmit">
                            <label>&nbsp;</label>
                            <?php if ($editing && !$editexternalurl) { ?>
                                <input
                                    name="editexternalurl"
                                    type="button"
                                    value="<?php echo escape($lang["save"]); ?>"
                                    onclick="<?php
                                    if ($share_password_required) {
                                        echo 'if (!enforceSharePassword(\'' . escape($lang['share-password-not-set']) . '\')) { return false; }; ';
                                    } ?>
                                        document.getElementById('editexternalurl').value = '<?php echo escape($lang["save"]); ?>';
                                        return <?php echo $modal ? "Modal" : "CentralSpace"; ?>Post(document.getElementById('resourceshareform'), true);"
                                >
                            <?php } else { ?>
                                <input
                                    name="generateurl"
                                    type="button"
                                    value="<?php echo escape($lang["generateexternalurl"]); ?>"
                                    onclick="<?php
                                    if ($share_password_required) {
                                        echo 'if (!enforceSharePassword(\'' . escape($lang['share-password-not-set']) . '\')) { return false; }; ';
                                    } ?>
                                        document.getElementById('generateurl').value = '<?php echo escape($lang["save"]); ?>';
                                        return <?php echo $modal ? "Modal" : "CentralSpace"; ?>Post(document.getElementById('resourceshareform'), true);"
                                >
                            <?php } ?>
                        </div>
                        <?php
                    }

                    if ($generateurl && $access > -1 && !$internal_share_only && enforcePostRequest(false)) {
                        // Access has been selected. Generate a new URL.
                        $generated_access_key = '';
                        enforceSharePassword($sharepwd);

                        if (empty($allowed_external_share_groups) || (!empty($allowed_external_share_groups) && in_array($user_group, $allowed_external_share_groups))) {
                            $generated_access_key = generate_resource_access_key($ref, $userref, $access, $expires, 'URL', $user_group, $sharepwd);
                        } elseif (!empty($allowed_external_share_groups) && !in_array($usergroup, $allowed_external_share_groups)) {
                            // Not allowed to select usergroup but this usergroup can not be used, default to the first entry in allowed_external_share_groups
                            $generated_access_key = generate_resource_access_key($ref, $userref, $access, $expires, 'URL', $allowed_external_share_groups[0], $sharepwd);
                        }

                        if (
                            '' != $generated_access_key
                            || (
                                isset($anonymous_login) 
                                && $generated_access_key === false 
                                && $username === $anonymous_login
                            )
                        ) {
                            $url_params = ['r' => $ref];
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
                    if ($editexternalurl && $access > -1 && enforcePostRequest(false)) {
                        enforceSharePassword($sharepwd);
                        edit_resource_external_access($editaccess, $access, $expires, $user_group, $sharepwd);
                    }
                }
                ?>
            </ul>
        
            <?php
            # Do not allow access to the existing shares if the user has restricted access to this resource.
            if (!$internal_share_only && $minaccess == 0) {
                ?>
                <h2><?php echo escape($lang["externalusersharing"]); ?></h2>
                <?php
                $keys = get_resource_external_access($ref);
                if (count($keys) == 0) {
                    ?>
                    <p><?php echo escape($lang["noexternalsharing"]); ?></p>
                    <?php
                } else {
                    ?>
                    <div class="Listview">
                        <table class="ListviewStyle">
                            <tr class="ListviewTitleStyle">
                                <th><?php echo escape($lang["accesskey"]); ?></th>
                                <th><?php echo escape($lang["type"]); ?></th>
                                <th><?php echo escape($lang["sharedby"]); ?></th>
                                <th><?php echo escape($lang["sharedwith"]); ?></th>
                                <th><?php echo escape($lang["lastupdated"]); ?></th>
                                <th><?php echo escape($lang["lastused"]); ?></th>
                                <th><?php echo escape($lang["expires"]); ?></th>
                                <th><?php echo escape($lang["access"]); ?></th>
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

                            <?php
                            foreach ($keys as $key) {
                                if (!$resource_share_filter_collections || in_array($userref, explode(",", $key["users"]))) {
                                    $collection_share = is_numeric($key["collection"]);

                                    if ($collection_share) {
                                        $url = $baseurl . "?c=" . urlencode($key["collection"]);
                                    } else {
                                        $url = $baseurl . "?r=" . urlencode($ref);
                                    }

                                    $url .= "&k=" . urlencode($key["access_key"]);
                                    $type = ($collection_share) ? $lang["sharecollection"] : $lang["share-resource"];
                                    $keyexpires = ($key["expires"] == "") ? $lang["never"] : nicedate($key["expires"], false);
                                    $keyaccess  = ($key["access"] == -1) ? "" : $lang["access" . $key["access"]];
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="ListTitle">
                                                <a target="_blank" href="<?php echo $url ?>">
                                                    <?php echo escape($key["access_key"]); ?>
                                                </a>
                                            </div>
                                        </td>
                                        <td><?php echo $type; ?></td>
                                        <td><?php echo escape(resolve_users($key["users"])); ?></td>
                                        <td><?php echo escape($key["emails"] ?? ""); ?></td>
                                        <td><?php echo escape(nicedate($key["maxdate"], true)); ?></td>
                                        <td><?php echo escape(nicedate($key["lastused"], true)); ?></td>
                                        <td><?php echo escape($keyexpires); ?></td>
                                        <td><?php echo escape($keyaccess); ?></td>
                                        <?php if (!empty($social_media_links)) { ?>
                                            <td><?php renderSocialMediaShareLinksForUrl($url); ?></td>
                                        <?php } ?>
                                        <td>
                                            <div class="ListTools">
                                                <?php
                                                if ($collection_share) {
                                                    $editlink = generateURL(
                                                        $baseurl . "/pages/collection_share.php",
                                                        array(
                                                            "ref"               => $key["collection"],
                                                            "editaccess"        => $key["access_key"],
                                                            "share_user"        => $key["users"]
                                                        )
                                                    );

                                                    $viewlink = generateURL($baseurl . "/", array("c" => $key["collection"]));
                                                    ?>
                                                    <a onclick="return CentralSpaceLoad(this,true);" href="<?php echo $editlink; ?>">
                                                        <?php echo LINK_CARET . escape($lang["action-edit"]); ?>
                                                    </a>
                                                    <a onclick="return CentralSpaceLoad(this,true);" href="<?php echo $viewlink; ?>">
                                                        <?php echo LINK_CARET . escape($lang["view"]); ?>
                                                    </a>
                                                    <?php
                                                } else {
                                                    $editlink = generateURL(
                                                        $baseurl . "/pages/resource_share.php",
                                                        array(
                                                            "ref"               => $ref,
                                                            "editaccess"        => $key["access_key"],
                                                            "share_user"        => $key["users"]
                                                        )
                                                    );
                                                    ?>
                                                    <a href="#" onclick="return resourceShareDeleteShare('<?php echo $key["access_key"]; ?>');">
                                                        <?php echo LINK_CARET . escape($lang["action-delete"]); ?>
                                                    </a>      
                                                    <a onclick="return CentralSpaceLoad(this,true);" href="<?php echo $editlink; ?>">
                                                        <?php echo LINK_CARET . escape($lang["action-edit"]); ?>
                                                    </a>
                                                    <?php
                                                }
                                                ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php
                                }
                            }
                            ?>
                        </table>
                    </div>
                    <?php
                }
                ?>
                
                <script type="text/javascript">
                    function resourceShareDeleteShare(access_key) {
                        if (confirm('<?php echo escape($lang["confirmdeleteaccessresource"]) ?>')) {
                            document.getElementById('deleteaccess').value = access_key;
                            <?php echo $modal ? "Modal" : "CentralSpace"; ?>Post(document.getElementById('resourceshareform'),true);
                        }
                        return false;
                    }

                    function resourceShareDeleteUserCustomAccess(event, user) {
                        if (confirm('<?php echo escape($lang["confirmdeleteusercustomaccessresource"]) ?>')) {

                            // Detect closest parent form
                            const link = event.target;
                            const form = link.closest('form');

                            form.querySelector('#deleteusercustomaccess').value = 'yes';
                            form.querySelector('#user').value = user;
                            form.submit();
                        }
                        return false;
                    }
                </script>

                <?php
            }
            ?>
    
            <h2><?php echo escape($lang["custompermissions"]); ?></h2>
            <?php
            $custom_access_rows = get_resource_custom_access_users_usergroups($ref);
            if (count($custom_access_rows) == 0) {
                ?>
                <p><?php echo escape($lang["remove_custom_access_no_users_found"]); ?></p>
                <?php
            } elseif ((count($custom_access_rows) > 0) && checkperm('v')) {
                ?>
                <div class="Listview">
                    <table class="ListviewStyle">
                        <tr class="ListviewTitleStyle">
                            <th><?php echo escape($lang["user"]); ?></th>
                            <th><?php echo escape($lang["property-user_group"]); ?></th>
                            <th><?php echo escape($lang["expires"]); ?></th>
                            <th><?php echo escape($lang["access"]); ?></th>
                            <th>
                                <div class="ListTools"><?php echo escape($lang["tools"]); ?></div>
                            </th>
                        </tr>

                        <?php
                        foreach ($custom_access_rows as $ca) {
                            $custexpires = ($ca["expires"] == "") ? $lang["never"] : nicedate($ca["expires"], false);
                            $custaccess  = ($ca["access"] == -1)  ? "" : $lang["access" . $ca["access"]];
                            ?>
                            <tr>
                                <td><?php echo escape($ca["user"] ?? ""); ?></td>
                                <td><?php echo escape($ca["usergroup"] ?? ""); ?></td>
                                <td><?php echo escape($custexpires); ?></td>
                                <td><?php echo escape($custaccess); ?></td>
                                <td>
                                    <div class="ListTools">
                                        <a href="#" onclick="return resourceShareDeleteUserCustomAccess(event, <?php echo get_user_by_username($ca["user"]) ?>);">
                                            <?php echo LINK_CARET . escape($lang["action-delete"]); ?>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <?php
                        }
                        ?>
                    </table>
                </div>
                <?php
            }
            ?>
        </div>
    </form>
</div><!-- BasicsBox -->

<?php
include "../include/footer.php";