<?php
include "../../include/boot.php";
include "../../include/authenticate.php";
include "../../include/ajax_functions.php";

if (!checkperm("a")) {
    exit("Permission denied.");
}

$ref = getval("ref", "");
$offset = getval("offset", 0, true);
$order_by = getval("orderby", "");
$filter_by_parent = getval("filterbyparent", "");
$find = getval("find", "");
$filter_by_permissions = getval("filterbypermissions", "");
$copy_from = getval("copyfrom", "");
$save = getval('save', '');

$url_params = [
    'ref' => $ref,
];
if ($offset) {
    $url_params['offset'] = $offset;
}
if ($order_by) {
    $url_params['orderby'] = $order_by;
}
if ($filter_by_parent) {
    $url_params['filterbyparent'] = $filter_by_parent;
}
if ($find) {
    $url_params['find'] = $find;
}
if ($filter_by_permissions) {
    $url_params['filterbypermissions'] = $filter_by_permissions;
}

$admin_group_permissions_url = generateURL("{$baseurl_short}pages/admin/admin_group_permissions.php", $url_params);

if ($save !== '' && $copy_from === '' && enforcePostRequest(getval('ajax', '') == 'true')) {
    $group = get_usergroup($ref);
    if (
        $group !== false
        && isset($group['inherit'])
        && is_array($group['inherit'])
        && in_array('permissions', $group['inherit'])
    ) {
        ajax_unauthorized();
    }

    $permissions = trim_array(explode(',', (string) $group['permissions']));
    $permissions_to_add = $permissions_to_remove = [];

    $processing_permissions = $_POST['permissions'] ?? [];
    foreach ($processing_permissions as $perm) {
        if (!isset($perm['permission'], $perm['reverse'], $perm['checked'])) {
            ajax_send_response(400, ajax_response_fail(ajax_build_message($lang['error_invalid_input'])));
        }

        $permission = $perm['permission'];
        $reverse = $perm['reverse'] == 1;
        $checked = $perm['checked'] === 'true';

        if (
            // Normal permissions
            (!$reverse && $checked)
            // Negative permissions
            || ($reverse && !$checked)
        ) {
            $permissions_to_add[] = base64_decode($permission);
        } else {
            $permissions_to_remove[] = base64_decode($permission);
        }
    }

    $perms = array_values(array_unique(
        array_diff(
            array_merge($permissions, $permissions_to_add),
            $permissions_to_remove
        )
    ));
    $perms_csv = join(',', $perms);

    save_usergroup($ref, array('permissions' => $perms_csv));
    log_activity(null, LOG_CODE_EDITED, $perms_csv, 'usergroup', 'permissions', $ref, null, null, null, true);

    ajax_send_response(200, ajax_response_ok_no_data());
} elseif ($save !== '' && $copy_from !== '' && enforcePostRequest(getval('ajax', '') == 'true')) {
    copy_usergroup_permissions($copy_from, $ref);
}

$group = get_usergroup($ref);
if (isset($group['inherit']) && is_array($group['inherit']) && in_array("permissions", $group['inherit'])) {
    exit($lang["error-permissiondenied"]);
}
$permissions = trim_array(explode(",", (string)$group["permissions"]));
$permissions_done = array();

include "../../include/header.php";
?>

<div class="BasicsBox">
    <h1><?php echo escape($lang["page-title_user_group_permissions_edit"] . " - " . $group["name"]); ?></h1>
    <?php
    $links_trail = array(
        array(
            'title' => $lang["systemsetup"],
            'href'  => $baseurl_short . "pages/admin/admin_home.php",
            'menu' =>  true
        ),
        array(
            'title' => $lang["page-title_user_group_management"],
            'href'  => $baseurl_short . "pages/admin/admin_group_management.php"
        ),
        array(
            'title' => $lang["page-title_user_group_management_edit"],
            'href'  => generateURL("{$baseurl_short}pages/admin/admin_group_management_edit.php", $url_params),
        ),
        array(
            'title' => $lang["page-title_user_group_permissions_edit"] . " - " . escape($group["name"])
        )
    );

    renderBreadcrumbs($links_trail);
    ?>

    <p>
        <?php
        echo escape($lang['page-subtitle_user_group_permissions_edit']);
        render_help_link("systemadmin/all-user-permissions");
        ?>
    </p>

    <?php if (getval("submitted", false)) { ?>
        <div class="PageInformal"><?php echo escape($lang['changessaved']);?></div>
    <?php } ?>

    <form method="post" id="copypermissions" action="<?php echo $admin_group_permissions_url; ?>" onsubmit="return CentralSpacePost(this,true);">   
        <?php generateFormToken("permissions"); ?>
        <input type="hidden" name="save" value="1">

        <div class="BasicsBox">
            <label><?php echo escape($lang["copypermissions"]);?></label>
            <input type="text" name="copyfrom">
            <input name="save" type="submit" value="<?php echo escape($lang["copy"]); ?>" onClick="return confirm('<?php echo escape($lang["confirmcopypermissions"]); ?>');">
        </div>
    </form>

    <form method="post" id="permissions" action="<?php echo $admin_group_permissions_url; ?>" onsubmit="event.preventDefault();">   
        <?php if ($offset) { ?>
            <input type="hidden" name="offset" value="<?php echo escape($offset); ?>">
        <?php }

        if ($order_by) { ?>
            <input type="hidden" name="order_by" value="<?php echo escape($order_by); ?>">
        <?php } ?>

        <div class="Listview">
            <table class="ListviewStyle">
                <tr class="ListviewTitleStyle">
                    <th colspan=3 class="permheader"><?php echo escape($lang["searching_and_access"]) ?></th>
                </tr>

                <?php
                DrawOption("s", $lang["searchcapability"]);
                DrawOption("v", $lang["access_to_restricted_and_confidential_resources"], false);

                # ------------ View access to workflow states
                for ($n = -2; $n <= 3; $n++) {
                    DrawOption("z" . $n, $lang["hide_view_access_to_workflow_state"] . " '" . $lang["status" . $n] . "'", false);
                }

                foreach ($additional_archive_states as $additional_archive_state) {
                    DrawOption("z" . $additional_archive_state, $lang["hide_view_access_to_workflow_state"] . " '" . (isset($lang["status" . $additional_archive_state]) ? $lang["status" . $additional_archive_state] : $additional_archive_state) . "'", false);
                }

                DrawOption("g", $lang["restrict_access_to_all_available_resources"], true);

                // Permission for restricting access to resources per workflow state
                $default_workflow_states = range(-2, 3);
                $workflow_states = array_merge($default_workflow_states, $additional_archive_states);
                foreach ($workflow_states as $workflow_state_number) {
                    DrawOption(
                        "rws{$workflow_state_number}",
                        str_replace('%workflow_state_name', "'{$lang["status{$workflow_state_number}"]}'", $lang["restrict_access_to_workflow_state"]),
                        false
                    );
                }

                DrawOption("q", $lang["can_make_resource_requests"], false);
                DrawOption("w", $lang["show_watermarked_previews_and_thumbnails"]);
                ?>
                
                <tr class="ListviewTitleStyle">
                    <th colspan=3 class="permheader"><?php echo escape($lang["metadatafields"]); ?></th>
                </tr>

                <?php
                # ------------ View access to fields
                DrawOption("f*", $lang["can_see_all_fields"], false, true);
                $fields = ps_query("select " . columns_in("resource_type_field") . " from resource_type_field order by active desc,order_by", array(), "schema");
                foreach ($fields as $field) {
                    if (!in_array("f*", $permissions)) {
                        # Render disabled fields with strikethrough
                        $fieldprefix = "";
                        $fieldsuffix = "";
                        if ($field["active"] == 0) {
                            $fieldprefix = "<span class=FieldDisabled>";
                            $fieldsuffix = "</span>";
                        }

                        DrawOption("f" . $field["ref"], "&nbsp;&nbsp; - " . $lang["can_see_field"] . " '" . $fieldprefix . lang_or_i18n_get_translated($field["title"], "fieldtitle-") . $fieldsuffix . "'" . (($field["name"] == "") ? "" : "<em> (" . escape($field["name"]) . ")</em>"));
                    } else {
                        # Add it to the 'done' list so it is discarded.
                        $permissions_done[] = "f" . $field["ref"];
                    }
                }

                DrawOption("F*", $lang["can_edit_all_fields"], true, true);
                $fields = ps_query("select " . columns_in("resource_type_field") . " from resource_type_field order by active desc,order_by", array(), "schema");
                foreach ($fields as $field) {
                    if (in_array("F*", $permissions)) {
                        # Render disabled fields with strikethrough
                        $fieldprefix = "";
                        $fieldsuffix = "";
                        if ($field["active"] == 0) {
                            $fieldprefix = "<span class=FieldDisabled>";
                            $fieldsuffix = "</span>";
                        }

                        DrawOption("F-" . $field["ref"], "&nbsp;&nbsp; - " . $lang["can_edit_field"] . " '" . $fieldprefix . lang_or_i18n_get_translated($field["title"], "fieldtitle-") . $fieldsuffix . "'"  . (($field["name"] == "") ? "" : "<em> (" . escape($field["name"]) . ")</em>"), false);
                    } else {
                        # Add it to the 'done' list so it is discarded.
                        $permissions_done[] = "F-" . $field["ref"];
                    }
                }
                ?>
                
                <tr class="ListviewTitleStyle">
                    <th colspan=3 class="permheader"><?php echo escape($lang["resourcetypes"]); ?></th>
                </tr>

                <?php
                # ------------ View access to resource types
                # All resource types need to be visible so get_resource_types() is unsuitable
                # If the user can edit their own permissions they can access any resource type by editing the special permissons anyway
                $rtypes = get_all_resource_types();
                foreach ($rtypes as $rtype) {
                    DrawOption("T" . $rtype["ref"], str_replace(array("%TYPE"), array(lang_or_i18n_get_translated($rtype["name"], "resourcetype-")), $lang["can_see_resource_type"]), true);
                }

                # ------------ Restricted access to resource types
                foreach ($rtypes as $rtype) {
                    DrawOption("X" . $rtype["ref"], $lang["restricted_access_only_to_resource_type"] . " '" . lang_or_i18n_get_translated($rtype["name"], "resourcetype-") . "'", false);
                }

                # ------------ Restricted upload for resource of type
                foreach ($rtypes as $rtype) {
                    DrawOption("XU" . $rtype["ref"], $lang["restricted_upload_for_resource_of_type"] . " '" . lang_or_i18n_get_translated($rtype["name"], "resourcetype-") . "'", false);
                }

                # ------------ Edit access to resource types (in any archive state to which the group has access)
                foreach ($rtypes as $rtype) {
                    DrawOption("ert" . $rtype["ref"], $lang["force_edit_resource_type"] . " '" . lang_or_i18n_get_translated($rtype["name"], "resourcetype-") . "'");
                }

                foreach ($rtypes as $rtype) {
                    DrawOption("XE" . $rtype["ref"], $lang["deny_edit_resource_type"] . " '" . lang_or_i18n_get_translated($rtype["name"], "resourcetype-") . "'");
                }

                DrawOption("XE", $lang["deny_edit_all_resource_types"], false, true);
                # ------------ Allow edit access to specified resource types
                if (in_array("XE", $permissions)) {
                    foreach ($rtypes as $rtype) {
                        DrawOption("XE-" . $rtype["ref"], str_replace("[resourcetype]", "'" . lang_or_i18n_get_translated($rtype["name"], "resourcetype-") . "'", $lang["can_edit_resource_type"]));
                    }
                }
                ?>
                
                <tr class="ListviewTitleStyle">
                    <th colspan=3 class="permheader"><?php echo escape($lang["resource_creation_and_management"]); ?></th>
                </tr>

                <?php
                # ------------ Edit access to workflow states
                for ($n = -2; $n <= 3; $n++) {
                    DrawOption("e" . $n, $lang["edit_access_to_workflow_state"] . " '" . $lang["status" . $n] . "'", false);
                }
                foreach ($additional_archive_states as $additional_archive_state) {
                    DrawOption("e" . $additional_archive_state, $lang["edit_access_to_workflow_state"] . " '" . (isset($lang["status" . $additional_archive_state]) ? $lang["status" . $additional_archive_state] : $additional_archive_state) . "'", false);
                }
                for ($n = 0; $n <= ($custom_access ? 3 : 2); $n++) {
                    DrawOption("ea" . $n, str_replace(array("[state]"), array($lang["access" . $n]), $lang["edit_access_to_access"]), true);
                }

                DrawOption("c", $lang["can_create_resources_and_upload_files-admins"]);
                DrawOption("d", $lang["can_create_resources_and_upload_files-general_users"]);
                DrawOption("D", $lang["can_delete_resources"], true);
                DrawOption("i", $lang["can_manage_archive_resources"]);
                DrawOption('A', $lang["can_manage_alternative_files"], true);
                DrawOption("xupr", $lang["can_upload_preview_images"], true);
                ?>

                <tr class="ListviewTitleStyle">
                    <th colspan=3 class="permheader"><?php echo escape($lang["themes_and_collections"]); ?></th>
                </tr>

                <?php
                DrawOption("b", $lang["enable_bottom_collection_bar"], true);
                DrawOption("h", $lang["can_publish_collections_as_themes"], false, true);
                DrawOption("exup", $lang["permission_share_upload_link"], false, true);
                if (in_array('h', $permissions)) {
                    DrawOption('hdta', $lang['manage_all_dash_h'], true, false);
                    DrawOption('hdt_ug', $lang['manage_user_group_dash_tiles'], false, false);
                } else {
                    DrawOption('dta', $lang['manage_all_dash_perm'], false, false);
                }
                DrawOption("dtu", $lang["manage_own_dash"], true, false);

                # ------------ Access to featured collection categories
                DrawOption("j*", $lang["can_see_all_theme_categories"], false, true);

                if (!in_array("j*", $permissions)) {
                    render_featured_collections_category_permissions(array("permissions" => $permissions));
                    # Add any 'loose' featured collections at top level of the tree that contain resources (so aren't in a category)
                    $loose_fcs = array_values(array_filter(get_featured_collections(0, ["access_control" => false]), function ($fc) {
                        return $fc["has_resources"] > 0;
                    }));
                    foreach ($loose_fcs as $loose_fc) {
                        $description = $lang["can_see_featured_collection"] . i18n_get_translated($loose_fc["name"]);
                        DrawOption('j' . $loose_fc["ref"], $description, false, false);
                    }
                }

                DrawOption("J", $lang["display_only_resources_within_accessible_themes"]);
                # ---------- end of featured collection categories

                # ---------- End of Dash Tiles
                ?>
                
                <tr class="ListviewTitleStyle">
                    <th colspan=3 class="permheader"><?php echo escape($lang["administration"]); ?></th>
                </tr>

                <?php
                DrawOption("t", $lang["can_access_team_centre"], false, true);

                if (in_array("t", $permissions)) {
                    # Admin options
                    DrawOption("r", $lang["can_manage_research_requests"]);
                    DrawOption("R", $lang["can_manage_resource_requests"], false, true);

                    if (in_array("R", $permissions)) {
                        DrawOption("Ra", $lang["can_assign_resource_requests"]);
                        DrawOption("Rb", $lang["can_be_assigned_resource_requests"]);
                    }

                    DrawOption("o", $lang["can_manage_content"]);
                    DrawOption("m", $lang["can_bulk-mail_users"]);
                    DrawOption("u", $lang["can_manage_users"]);
                    DrawOption("k", $lang["can_manage_keywords"]);
                    DrawOption("a", $lang["can_access_system_setup"], false, true);
                } else {
                    $permissions_done[] = "r";
                    $permissions_done[] = "R";
                    $permissions_done[] = "o";
                    $permissions_done[] = "m";
                    $permissions_done[] = "u";
                    $permissions_done[] = "k";
                    $permissions_done[] = "a";
                }

                DrawOption('ex', $lang['permission_manage_external_shares']);
                ?>
                
                <tr class="ListviewTitleStyle">
                    <th colspan=3 class="permheader"><?php echo escape($lang["other"]); ?></th>
                </tr>

                <?php
                DrawOption("p", $lang["can_change_own_password"], true);
                DrawOption("U", $lang["can_manage_users_in_children_groups"]);
                DrawOption("E", $lang["can_email_resources_to_own_and_children_and_parent_groups"]);
                DrawOption("x", $lang["allow_user_group_selection_for_access_when_sharing_externally"]);
                DrawOption("noex", $lang["prevent_user_group_sharing_externally"]);
                DrawOption("nolock", $lang["permission_nolock"]);

                hook("additionalperms");
                $custom_permissions = join(",", array_diff($permissions, $permissions_done));
                ?>
                
            </table>
        </div><!-- end of Listview -->

        <div class="Question">
            <label for="other"><?php echo escape($lang["custompermissions"]); ?></label>
            <textarea
                name="other"
                class="stdwidth"
                rows="3"
                cols="50"
                data-custom_permissions_copy="<?php echo escape($custom_permissions);  ?>"
            ><?php echo escape($custom_permissions); ?></textarea>            
            <div class="clearerleft"></div>
        </div>

        <div class="QuestionSubmit">        
            <input 
                name="save"
                type="button"
                onclick="SaveCustomPermissions();"
                value="<?php echo escape($lang["save"]); ?>"
            >
        </div>
    </form> 
</div> <!-- end of BasicsBox -->

<script>
/**
 * Save specific permissions
 * @param  {array} perms List of permissions to get information for, if applicable, and save. A permission can either be
 *                       a base64 encoded permission or an object:
 *                       {
 *                       permission: same base64 value,
 *                       reverse: 1 for negative permissions, 0 otherwise
 *                       checked: bool
 *                       }
 * @return {void}
 */
function SavePermissions(perms, formsubmit) {
    console.debug('SavePermissions(perms = %o)', perms);

    CentralSpaceShowProcessing();
    let permissions_list = ProcessDisabledPermissions(perms).map(function(perm) {
        // Custom Permissions are provided with all the required info
        if (
            typeof perm === 'object'
            && perm.hasOwnProperty('permission')
            && perm.hasOwnProperty('reverse')
            && perm.hasOwnProperty('checked')
        ) {
            return perm;
        } else {
            // Auto saving a permission will only provide its base64 value
            let el = jQuery("input[name='checked_" + perm + "']");

            if (el.length === 0) {
                console.error('Unable to find permission!');
                return null;
            }

            return {
                permission: perm,
                reverse: el.data('reverse'),
                checked: el.prop('checked'),
            };
        }
    });

    let found_perms = permissions_list.filter(x => x);

    jQuery.ajax({
        type: 'POST',
        url: '<?php echo $admin_group_permissions_url; ?>',
        data: {
            ajax: true,
            save: '1',
            permissions: found_perms,
            <?php echo generateAjaxToken('SaveUsergroupPermission'); ?>
        },
        dataType: "json"
    })
    .done(function(response, textStatus, jqXHR) {
        CentralSpaceHideProcessing();
        // redraw page to show/hide any dependendant permissions
        CentralSpaceLoad('<?php echo $admin_group_permissions_url; ?>' + (formsubmit?'&submitted=true':''), false);
        if (formsubmit) {
            pageScrolltop(scrolltopElementCentral);
        }
    })
    .fail(function(data, textStatus, jqXHR) {
        if (typeof data.responseJSON === 'undefined') {
            console.debug('data = %o', data);
            styledalert('', "<?php echo escape($lang['error_generic']); ?>");
            return;
        }

        let response = data.responseJSON;
        styledalert(jqXHR, response.data.message);
    })
    .always(function() {
        CentralSpaceHideProcessing();
    });

    return;
}

/**
 * Save custom permissions. Removed permissions will be marked accordingly.
 * @return {void}
 */
function SaveCustomPermissions() {
    console.debug('SaveCustomPermissions()');

    let custom_perms_el = jQuery("textarea[name='other']");
    let perms = custom_perms_el.val().split(',');
    let diff = custom_perms_el
        .data('custom_permissions_copy')
        .split(',')
        .filter(x => !perms.includes(x));

    // Current custom permissions added by user
    let custom_perms = perms.map(function(perm) {
        return {
            permission: btoa(perm),
            reverse: 0,
            checked: true,
        };
    });

    // Custom permissions removed by user
    jQuery.each(diff, function(idx, perm) {
        custom_perms.push({
            permission: btoa(perm),
            reverse: 0,
            checked: false,
        });
    });

    SavePermissions(custom_perms, true);
}

/**
 * Process disabled permissions. Known use cases behaviour:
 * - normal permissions simply get disabled (ie not submitted). Usually when another permission is enabled instead
 * (e.g perm "a" - licensemanager).
 * - disabled negative permissions always get added when (auto)saving a permission. This was legacy behaviour.
 * 
 * @param  {array} perms List of permissions
 * @return {array}       List of disabled negative permissions
 */
function ProcessDisabledPermissions(perms) {
    jQuery("input[name^='checked_'][data-reverse=1]:disabled").each(function(idx, disabled_negative_perm) {
        perms.push({
            permission: jQuery(disabled_negative_perm).attr('name').substring(8),
            reverse: 1,
            checked: false,
        });
    });
    return perms;
}
</script>

<?php
include "../../include/footer.php";
