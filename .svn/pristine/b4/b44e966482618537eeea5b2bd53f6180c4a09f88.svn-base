<?php
include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("a")) {
    exit("Permission denied.");
}

$offset = getval("offset", 0, true);
$order_by = getval("orderby", "");
$filter_by_parent = getval("filterbyparent", "");
$find = getval("find", "");
$filter_by_permissions = getval("filterbypermissions", "");

$url_params =
    ($offset ? "&offset={$offset}" : "") .
    ($order_by ? "&orderby={$order_by}" : "") .
    ($filter_by_parent ? "&filterbyparent={$filter_by_parent}" : "") .
    ($find ? "&find={$find}" : "") .
    ($filter_by_permissions ? "&filterbypermissions={$filter_by_permissions}" : "");

# create new record from callback
$new_group_name = getval("newusergroupname", "");
if ($new_group_name != "" && enforcePostRequest(false)) {
    $setoptions = array("request_mode" => 1, "name" => $new_group_name);
    $ref = save_usergroup(0, $setoptions);

    log_activity(null, LOG_CODE_CREATED, null, 'usergroup', null, $ref);
    log_activity(null, LOG_CODE_CREATED, $new_group_name, 'usergroup', 'name', $ref, null, '');
    log_activity(null, LOG_CODE_CREATED, '1', 'usergroup', 'request_mode', $ref, null, '');

    redirect($baseurl_short . "pages/admin/admin_group_management_edit.php?ref={$ref}{$url_params}"); // redirect to prevent repost and expose of form data
    exit;
}

$ref = (int) getval('ref', 0, true, 'is_int_loose');
if ($ref === 0) {
    exit('No user group ref supplied.');
}

if (!ps_value("select ref as value from usergroup where ref = ?", array("i", $ref), false)) {
    redirect("{$baseurl_short}pages/admin/admin_group_management.php?{$url_params}");       // fail safe by returning to the user group management page if duff ref passed
    exit;
}

$dependant_user_count = ps_value("select count(*) as value from user where usergroup = ?", array("i", $ref), 0);
$dependant_groups = ps_value("select count(*) as value from usergroup where parent = ?", array("i", $ref), 0);
$has_dependants = $dependant_user_count + $dependant_groups > 0;

if (!$has_dependants && getval("deleteme", false) && enforcePostRequest(false)) {
    delete_usergroup($ref);
    redirect("{$baseurl_short}pages/admin/admin_group_management.php?{$url_params}");       // return to the user group management page
    exit;
}

$record = get_usergroup($ref);

if (getval("save", false) && enforcePostRequest(false)) {
    $error = false;
    $logo_dir = "{$storagedir}/admin/groupheaderimg/";

    // Remove group specific logo
    if (isset($_POST['removelogo'])) {
        $logo_extension = ps_value("select group_specific_logo as value from usergroup where ref = ?", array("i", $ref), false);
        $logo_filename = "{$logo_dir}/group{$ref}.{$logo_extension}";

        if ($logo_extension && file_exists($logo_filename) && unlink($logo_filename)) {
            $logo_extension = "";
        } else {
            unset($logo_extension);
        }
    }

    // Remove group specific logo - dark
    if (isset($_POST['removelogodark'])) {
        $logo_dark_extension = ps_value("select group_specific_logo_dark as value from usergroup where ref = ?", array("i", $ref), false);
        $logo_dark_filename = "{$logo_dir}/group{$ref}_dark.{$logo_dark_extension}";

        if ($logo_dark_extension && file_exists($logo_dark_filename) && unlink($logo_dark_filename)) {
            $logo_dark_extension = "";
        } else {
            unset($logo_dark_extension);
        }
    }

    // Upload group specific logo
    if (isset($_FILES['grouplogo']['tmp_name']) && is_uploaded_file($_FILES['grouplogo']['tmp_name'])) {
        if (!(file_exists($logo_dir) && is_dir($logo_dir))) {
            mkdir($logo_dir, 0777, true);
        }

        $logo_extension = parse_filename_extension($_FILES['grouplogo']['name']);
        $process_file_upload = process_file_upload(
            $_FILES['grouplogo'],
            new SplFileInfo("{$logo_dir}/group{$ref}.{$logo_extension}"),
            ['allow_extensions' => ['jpg', 'jpeg', 'gif', 'svg', 'png']]
        );

        if (!$process_file_upload['success']) {
            unset($logo_extension);
            $error = true;
            $onload_message = [
                'title' => $lang['error'],
                'text' => match ($process_file_upload['error']) {
                    ProcessFileUploadErrorCondition::InvalidExtension => str_replace(
                        '%EXTENSIONS',
                        'JPG, GIF, SVG, PNG',
                        $lang['allowedextensions-extensions']
                    ),
                    default => $process_file_upload['error']->i18n($lang),
                },
            ];
        }
    }

    // Upload group specific logo - dark
    if (isset($_FILES['grouplogodark']['tmp_name']) && is_uploaded_file($_FILES['grouplogodark']['tmp_name'])) {
        if (!(file_exists($logo_dir) && is_dir($logo_dir))) {
            mkdir($logo_dir, 0777, true);
        }

        $logo_dark_extension = parse_filename_extension($_FILES['grouplogodark']['name']);
        $process_file_upload = process_file_upload(
            $_FILES['grouplogodark'],
            new SplFileInfo("{$logo_dir}/group{$ref}_dark.{$logo_dark_extension}"),
            ['allow_extensions' => ['jpg', 'jpeg', 'gif', 'svg', 'png']]
        );

        if (!$process_file_upload['success']) {
            unset($logo_dark_extension);
            $error = true;
            $onload_message = [
                'title' => $lang['error'],
                'text' => match ($process_file_upload['error']) {
                    ProcessFileUploadErrorCondition::InvalidExtension => str_replace(
                        '%EXTENSIONS',
                        'JPG, GIF, SVG, PNG',
                        $lang['allowedextensions-extensions']
                    ),
                    default => $process_file_upload['error']->i18n($lang),
                },
            ];
        }
    }

    if (isset($logo_extension)) {
        ps_query("UPDATE usergroup SET group_specific_logo = ? WHERE ref = ?", array("s", $logo_extension, "i", $ref));
        log_activity(null, null, null, 'usergroup', 'group_specific_logo', $ref);
        clear_query_cache('usergroup');
    }

    if (isset($logo_dark_extension)) {
        ps_query("UPDATE usergroup SET group_specific_logo_dark = ? WHERE ref = ?", array("s", $logo_dark_extension, "i", $ref));
        log_activity(null, null, null, 'usergroup', 'group_specific_logo_dark', $ref);
    }

    $update_sql_params = array();
    foreach (
        array("name","permissions","parent","search_filter","search_filter_id","edit_filter","edit_filter_id","derestrict_filter",
            "derestrict_filter_id","resource_defaults","config_options","welcome_message","ip_restrict","request_mode",
            "allow_registration_selection","inherit_flags", "download_limit","download_log_days") as $column
    ) {
        if ($execution_lockout && $column == "config_options") {
            # Do not allow config overrides to be changed from UI if $execution_lockout is set.
            continue;
        }

        if (in_array($column, array("allow_registration_selection"))) {
            $groupoptions[$column] = getval($column, "0") ? "1" : "0";
        } elseif ($column == "inherit_flags" && getval($column, [], false, 'is_array') !== []) {
            $groupoptions[$column] = implode(',', getval($column, [], false, 'is_array'));
        } elseif (in_array($column, array("parent","download_limit","download_log_days","search_filter_id","edit_filter_id","derestrict_filter_id"))) {
            $groupoptions[$column] = getval($column, 0, true);
        } elseif ($column == "request_mode") {
            $groupoptions[$column] = getval($column, 1, true);
        } else {
            $groupoptions[$column] = getval($column, "");
        }
    }

    foreach ($groupoptions as $column_name => $column_value) {
        log_activity(null, LOG_CODE_EDITED, $column_value, 'usergroup', $column_name, $ref);
    }

    save_usergroup($ref, $groupoptions);

    hook("usergroup_edit_add_form_save", "", array($ref));
    if (!$error) {
        redirect("{$baseurl_short}pages/admin/admin_group_management.php?{$url_params}");       // return to the user group management page
        exit;
    }
}

include "../../include/header.php";

$url_params_edit = array(
    "ref" => $ref,
    "offset" => $offset,
    "order_by" => $order_by,
    "filterbyparent" => $filter_by_parent,
    "find" => $find,
    "filterbypermissions" => $filter_by_permissions
);

?>


<form
    method="post"
    enctype="multipart/form-data" 
    action="<?php echo generateURL($baseurl_short . 'pages/admin/admin_group_management_edit.php', $url_params_edit);?>"
    id="mainform"
    class="FormWide">
    <?php generateFormToken("mainform"); ?>
    <div class="BasicsBox">
        <h1><?php echo escape($lang["page-title_user_group_management_edit"]); ?></h1>
        <?php
        $links_trail = array(
            array(
                'title' => $lang["systemsetup"],
                'href'  => $baseurl_short . "pages/admin/admin_home.php",
                'menu' =>  true
            ),
            array(
                'title' => $lang["page-title_user_group_management"],
                'href'  => $baseurl_short . "pages/admin/admin_group_management.php?" . $url_params
            ),
            array(
                'title' => $lang["page-title_user_group_management_edit"]
            )
        );

        renderBreadcrumbs($links_trail);
        ?>

        <p>
            <?php
            echo escape($lang['page-subtitle_user_group_management_edit']);
            render_help_link("systemadmin/creating-user-groups");
            ?>
        </p>

        <input type="hidden" name="save" value="1">

        <div class="Question">
            <label for="reference"><?php echo escape($lang["property-reference"]); ?></label>
            <div class="Fixed"><?php echo (int)$ref; ?></div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="name"><?php echo escape($lang["property-name"]); ?></label>
            <input name="name" type="text" class="stdwidth" value="<?php echo escape($record['name']); ?>"> 
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="dependants"><?php echo escape($lang["property-contains"]); ?></label>
            <div class="Fixed">
                <?php echo $dependant_user_count; ?>&nbsp;<?php echo escape($lang['users']); ?>, <?php echo $dependant_groups; ?>&nbsp;<?php echo escape($lang['property-groups']); ?>
            </div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="permissions"><?php echo escape($lang["property-permissions"]); ?></label>

            <?php if ($record['parent']) { ?>
                <label for="permissions_inherit"><?php echo escape($lang["property-permissions_inherit"]); ?></label>
                <input id="permissions_inherit" name="inherit_flags[]" type="checkbox" value="permissions" onClick="if(jQuery('#permissions_inherit').is(':checked')){jQuery('#permissions_area').slideUp();}else{jQuery('#permissions_area').slideDown();}" <?php echo (in_array("permissions", $record['inherit'])) ? "checked" : ''; ?>>
                <div class="clearerleft"></div> 
                <?php
            } ?>

            <div id ="permissions_area" <?php echo (in_array("permissions", $record['inherit'])) ? 'style="display:none;"' : ''; ?>>
                <input type="button" class="stdwidth<?php echo $record['parent'] ? ' label-spacer' : ''; ?>" onclick="return CentralSpaceLoad('<?php echo $baseurl_short; ?>pages/admin/admin_group_permissions.php?ref=<?php echo escape($ref . $url_params); ?>',true);" value="<?php echo escape($lang["launchpermissionsmanager"]); ?>"></input>                       
                <div class="clearerleft"></div>
                <label></label>
                <textarea name="permissions" class="stdwidth" rows="5" cols="50"><?php echo escape((string) $record['permissions']); ?></textarea>
                <div class="clearerleft"></div>
            </div> <!-- End of permissions_area -->
        </div>

        <div class="Question">
            <label for="group_override_config"><?php echo escape($lang["fieldtitle-usergroup_config"]); ?></label>

            <?php if ($record['parent']) { ?>
                <label for="group_override_config_inherit"><?php echo escape($lang["property-group_preferences_inherit"]); ?></label>
                <input id="group_override_config_inherit" name="inherit_flags[]" type="checkbox" value="preferences" onClick="if(jQuery('#group_override_config_inherit').is(':checked')){jQuery('#group_override_config_area').slideUp();}else{jQuery('#group_override_config_area').slideDown();}" <?php echo (in_array("preferences", $record['inherit'])) ? "checked" : ''; ?>>
                <div class="clearerleft"></div> 
                <?php
            } ?>

            <div id ="group_override_config_area" <?php echo (in_array("preferences", $record['inherit'])) ? 'style="display:none;"' : ''; ?>>
                <input type="button" class="stdwidth<?php echo $record['parent'] ? ' label-spacer' : ''; ?>" onclick="return CentralSpaceLoad('<?php echo $baseurl_short; ?>pages/admin/admin_group_config_edit.php?ref=<?php echo escape($ref . $url_params); ?>',true);" value="<?php echo escape($lang["editgroupconfigoverrides"]); ?>"></input>
                <div class="clearerleft"></div>
            </div>
        </div>

        <div class="Question">
            <label for="parent"><?php echo escape($lang["property-parent"]); ?></label>
            <select name="parent" class="stdwidth">
                <option value="0" >
                    <?php echo ($record['parent']) ? escape($lang["property-user_group_remove_parent"]) : ''; ?>
                </option>
                <?php
                $groups = get_usergroups();
                foreach ($groups as $group) {
                    // Not allowed to be the parent of itself
                    if ($group['ref'] == $ref) {
                        continue;
                    }
                    ?>
                    <option <?php echo ($record['parent'] == $group['ref']) ? 'selected="true"' : ''; ?> value="<?php echo $group['ref']; ?>">
                        <?php echo $group['name']; ?>
                    </option>
                    <?php
                }
                ?>
            </select>
            <div class="clearerleft"></div>
        </div>
    </div>

    <h2 class="CollapsibleSectionHead collapsed"><?php echo escape($lang["fieldtitle-advanced_options"]); ?></h2>

    <div class="CollapsibleSection" style="display:none;">
        <p><?php echo strip_tags_and_attributes($lang["action-title_see_wiki_for_user_group_advanced_options"], ["a"], ["href"]); ?></p>

        <?php
        $filters = get_filters("name", "ASC");
        $filters[] = array("ref" => -1, "name" => $lang["disabled"]);
        // Show filter selector if already migrated or no filter has been set
        // Add the option to indicate filter migration failed
        ?>

        <div class="Question">
            <label for="search_filter_id"><?php echo escape($lang["property-search_filter"]); ?></label>
            <select name="search_filter_id" class="stdwidth">
                <?php
                echo "<option value='0' >" . escape($record['search_filter_id'] ? $lang["filter_none"] : $lang["select"]) . "</option>";
                foreach ($filters as $filter) {
                    echo "<option value='" . $filter['ref'] . "' " . ($record['search_filter_id'] == $filter['ref'] ? " selected " : "") . ">" . i18n_get_translated($filter['name']) . "</option>";
                }
                ?>
            </select>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="edit_filter_id"><?php echo escape($lang["property-edit_filter"]); ?></label>
            <select name="edit_filter_id" class="stdwidth">
                <?php
                echo "<option value='0' >" . escape($record['edit_filter_id'] ? $lang["filter_none"] : $lang["select"]) . "</option>";
                foreach ($filters as $filter) {
                    echo "<option value='" . $filter['ref'] . "' " . ($record['edit_filter_id'] == $filter['ref'] ? " selected " : "") . ">" . i18n_get_translated($filter['name']) . "</option>";
                }
                ?>
            </select>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="derestrict_filter_id"><?php echo escape($lang["fieldtitle-derestrict_filter"]); ?></label>
            <select name="derestrict_filter_id" class="stdwidth">
                <?php
                echo "<option value='0' >" . escape($record['derestrict_filter_id'] ? $lang["filter_none"] : $lang["select"]) . "</option>";
                foreach ($filters as $filter) {
                    echo "<option value='" . $filter['ref'] . "' " . ($record['derestrict_filter_id'] == $filter['ref'] ? " selected " : "") . ">" . i18n_get_translated($filter['name']) . "</option>";
                }
                ?>
            </select>
            <div class="clearerleft"></div>
            <div class="FormHelp">
                <div class="FormHelpInner"><?php echo escape($lang["information-derestrict_filter"]); ?></div>
            </div>
        </div>

        <div class="Question">
            <label for="download_limit"><?php echo escape($lang["group_download_limit_title"]); ?></label>
            <input name="download_limit" type="number" class="vshrtwidth" value="<?php echo escape((string)$record['download_limit']); ?>">
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="download_log_days"><?php echo escape($lang["group_download_limit_period"]); ?></label>
            <input name="download_log_days" type="number" class="vshrtwidth" value="<?php echo escape((string)$record['download_log_days']); ?>">
            <div class="clearerleft"></div>
        </div>


        <div class="Question">
            <label for="resource_defaults"><?php echo escape($lang["property-resource_defaults"]); ?></label>
            <textarea name="resource_defaults" class="stdwidth" rows="3" cols="50"><?php echo $record['resource_defaults']; ?></textarea>
            <div class="clearerleft"></div>
        </div>

        <?php if (!$execution_lockout) { ?>
            <div class="Question">
                <label for="config_options"><?php echo escape($lang["property-override_config_options"]); ?></label>

                <?php if ($record['parent']) { ?>
                    <label for="config_inherit"><?php echo escape($lang["property-config_inherit"]); ?></label>
                    <input id="config_inherit" name="inherit_flags[]" type="checkbox" value="config_options" onClick="if(jQuery('#config_inherit').is(':checked')){jQuery('#config_area').slideUp();}else{jQuery('#config_area').slideDown();}" <?php echo (in_array("config_options", $record['inherit'])) ? "checked" : ''; ?>>
                    <div class="clearerleft"></div> 
                    <?php
                } ?>

                <div id ="config_area" <?php echo (in_array("config_options", $record['inherit'])) ? "style=display:none;" : ''; ?>> 
                    <textarea name="config_options" id="configOptionsBox" class="stdwidth<?php echo $record['parent'] ? ' label-spacer' : ''; ?>" rows="12" cols="50" ><?php echo $record['config_options']; ?></textarea>
                    <div class="clearerleft"></div>
                </div>
            </div>
        <?php } ?>

        <div class="Question">
            <label for="welcome_message"><?php echo escape($lang["property-email_welcome_message"]); ?></label>
            <textarea name="welcome_message" class="stdwidth" rows="12" cols="50"><?php echo $record['welcome_message']; ?></textarea>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="ip_restrict"><?php echo escape($lang["property-ip_address_restriction"]); ?></label>
            <input name="ip_restrict" type="text" class="stdwidth" value="<?php echo $record['ip_restrict']; ?>">
            <div class="clearerleft"></div>
            <div class="FormHelp">
                <div class="FormHelpInner"><?php echo escape($lang["information-ip_address_restriction"]); ?></div>
            </div>
        </div>

        <div class="Question">
            <label for="request_mode"><?php echo escape($lang["property-request_mode"]); ?></label>
            <select name="request_mode" class="stdwidth">
                <?php for ($i = 0; $i < 2; $i++) { ?>
                    <option
                        <?php echo ($record['request_mode'] == $i) ? 'selected="true" ' : ''; ?>
                        value="<?php echo $i; ?>"><?php echo escape($lang["resourcerequesttype{$i}"]); ?>
                    </option>
                    <?php
                }
                ?>
            </select>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="allow_registration_selection"><?php echo escape($lang["property-allow_registration_selection"]); ?></label>
            <input
                name="allow_registration_selection"
                type="checkbox"
                value="1"
                <?php echo ($record['allow_registration_selection'] == 1) ? 'checked="checked"' : ''; ?>>
            <div class="clearerleft"></div>
        </div>

        <?php if ($record['group_specific_logo']) {
            $linkedheaderimgsrc = (isset($storageurl) ? $storageurl : $baseurl . "/filestore") . "/admin/groupheaderimg/group" . $record['ref'] . "." . $record["group_specific_logo"];
            ?>
            <div class="Question">
                <label for="grouplogocurrent"><?php echo escape($lang["fieldtitle-group_logo"]); ?></label>
                <img src="<?php echo $linkedheaderimgsrc;?>" alt="Group logo" height='126'>
            </div>

            <div class="Question">
                <label for="grouplogo"><?php echo escape($lang["fieldtitle-group_logo_replace"]); ?></label>
                <input name="grouplogo" type="file">
                <div class="clearerleft"></div>
            </div>

            <div class="Question">
                <label for="removelogo"><?php echo escape($lang["action-title_remove_user_group_logo"]); ?></label>
                <input name="removelogo" type="checkbox" value="1">
                <div class="clearerleft"></div>
            </div>

        <?php } else { ?>
            <div class="Question">
                <label for="grouplogo"><?php echo escape($lang["fieldtitle-group_logo"]); ?></label>
                <input name="grouplogo" type="file">
                <div class="clearerleft"></div>
            </div>
        <?php } ?>

        <?php if ($record['group_specific_logo_dark']) {
            $linkedheaderimgsrc_dark = (isset($storageurl) ? $storageurl : $baseurl . "/filestore") . "/admin/groupheaderimg/group" . $record['ref'] . "_dark." . $record["group_specific_logo_dark"];
            ?>
            <div class="Question">
                <label for="grouplogodarkcurrent"><?php echo escape($lang["fieldtitle-group_logo_dark"]); ?></label>
                <img src="<?php echo $linkedheaderimgsrc_dark;?>" alt="Group logo - Dark" height='126'>
            </div>

            <div class="Question">
                <label for="grouplogodark"><?php echo escape($lang["fieldtitle-group_logo_dark_replace"]); ?></label>
                <input name="grouplogodark" type="file">
                <div class="clearerleft"></div>
            </div>

            <div class="Question">
                <label for="removelogodark"><?php echo escape($lang["action-title_remove_user_group_logo_dark"]); ?></label>
                <input name="removelogodark" type="checkbox" value="1">
                <div class="clearerleft"></div>
            </div>

        <?php } else { ?>
            <div class="Question">
                <label for="grouplogodark"><?php echo escape($lang["fieldtitle-group_logo_dark"]); ?></label>
                <input name="grouplogodark" type="file">
                <div class="clearerleft"></div>
            </div>
        <?php } ?>
    </div><!-- end of advanced options -->

    <div class="BasicsBox">
        <div class="Question">
            <label for="delete_user_group"><?php echo escape($lang["fieldtitle-tick_to_delete_group"]); ?></label>
            <input
                id="delete_user_group"
                name="deleteme"
                type="checkbox"
                value="yes"
                <?php echo ($has_dependants) ? 'disabled="disabled"' : ''; ?>>
            <div class="clearerleft"></div>
            <div class="FormHelp">
                <div class="FormHelpInner"><?php echo escape($lang["fieldhelp-tick_to_delete_group"]); ?></div>
            </div>
        </div>

        <div class="QuestionSubmit">
            <input name="buttonsave" type="submit" value="<?php echo escape($lang["save"]); ?>">
        </div>
    </div>
</form>

<script>
    registerCollapsibleSections();

    jQuery('#delete_user_group').click(function () {
        <?php
        $language_specific_results = ps_value('SELECT count(*) AS `value` FROM site_text WHERE specific_to_group = ?', array("i",$ref), 0);
        $alert_message = str_replace('[recordscount]', $language_specific_results, $lang["delete_user_group_checkbox_alert_message"]);
        ?>

        if (<?php echo $language_specific_results; ?> > 0 && jQuery('#delete_user_group').is(':checked')) {
            alert("<?php echo escape($alert_message); ?>");
        }
    });
</script>

<?php
include "../../include/footer.php";
