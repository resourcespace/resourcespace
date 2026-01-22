<?php

/**
 * CSV upload *
  */

include __DIR__ . "/../../../include/boot.php";
include __DIR__ . "/../../../include/authenticate.php";
include_once __DIR__ . "/../include/meta_functions.php";
include_once __DIR__ . "/../include/csv_functions.php";

$fd = "user_{$userref}_uploaded_meta";            // file descriptor for uploaded file
$allfields              = get_resource_type_fields("", "title");
$csv_set_options = array();
$csv_saved_options = getval("saved_csv_options", "");
$existing_config = false;

if (isset($_FILES["csv_config"]) && $_FILES["csv_config"]['error'] == 0) {
    // We have a CSV config file
    if (check_valid_file_extension($_FILES["csv_config"], array("json"))) {
        $csv_saved_options = file_get_contents($_FILES["csv_config"]["tmp_name"]);
        $onload_message = array("title" => $lang["ok"],"text" => $lang["csv_upload_upload_config_set"]);
    } else {
        $onload_message = array("title" => $lang["error"],"text" => str_replace("%EXTENSIONS", ".json", $lang["invalidextension_mustbe-extensions"]));
        $csv_saved_options = "";
    }
}

if (getval("getconfig", "") != "") {
    header('Content-Type: text/json');
    header("Content-Disposition: attachment; filename=csv_upload.json");
    echo $csv_saved_options;
    exit();
}

$default_status = get_default_archive_state();
$csv_default_settings = array(
    "add_to_collection" => 0,
    "csv_update_col" => 0,
    "csv_update_col_id" => 0,
    "update_existing" => 0,
    "id_column" => "",
    "id_field" => 0,
    "id_column_match" => 0,
    "multiple_match" => 0,
    "resource_type_column" => "",
    "resource_type_default" => 0,
    "status_column" => "",
    "status_default" => $default_status,
    "access_column" => "",
    "access_default" => 0,
    "fieldmapping" => array(),
    "csvchecksum" => "",
    "csv_filename" => ""
    );

if ($csv_saved_options != "" && getval("resetconfig", "") == "") {
    $csv_set_options = json_decode($csv_saved_options, true);
    $existing_config = true;
}

foreach ($csv_default_settings as $csv_setting => $csv_setting_default) {
    $setoption = isset($_POST[$csv_setting]) ? $_POST[$csv_setting] : "";
    if ($setoption != "") {
        $csv_set_options[$csv_setting] = $setoption;
    } elseif (!isset($csv_set_options[$csv_setting])) {
        $csv_set_options[$csv_setting] = $csv_setting_default;
    }
}

$selected_columns = array();
$selected_columns[] = $csv_set_options["resource_type_column"];
$selected_columns[] = $csv_set_options["id_column"];
$selected_columns[] = $csv_set_options["status_column"];
$selected_columns[] = $csv_set_options["access_column"];
$selected_columns = array_filter($selected_columns, "emptyiszero");

$usehash = $session_hash ?? get_rs_session_id(true);
$csvdir = get_temp_dir() . DIRECTORY_SEPARATOR . "csv_upload" . DIRECTORY_SEPARATOR . md5($usehash);
if (!file_exists($csvdir)) {
    mkdir($csvdir, 0777, true);
}

$csvfile = $csvdir . DIRECTORY_SEPARATOR  . "csv_upload.csv";
if (isset($_FILES[$fd]) && $_FILES[$fd]['error'] == 0) {
    $process_file_upload = process_file_upload(
        $_FILES[$fd],
        new SplFileInfo($csvfile),
        [
            'allow_extensions' => ['csv'],
            'mime_file_based_detection' => false,
        ]
    );    

    if ($process_file_upload['success']) {

        // Check if file is valid UTF-8, remove BOM if present
        $csv_utf_check = csv_check_utf8($csvfile);

        if ($csv_utf_check['success']) {
            $csv_set_options['csvchecksum'] = get_checksum($csvfile, true);
            $csv_set_options['csv_filename'] = $_FILES[$fd]['name'];
        } else {
            // File is not valid to process - remove the CSV and the directory created earlier
            try_unlink($csvfile);
            rmdir($csvdir);
            $onload_message = [
                'title' => $lang['error'],
                'text'  => $csv_utf_check['message'],
            ];
        }
    } else {
        $onload_message = [
            'title' => $lang['error'],
            'text' => match ($process_file_upload['error']) {
                ProcessFileUploadErrorCondition::InvalidExtension => str_replace(
                    '%EXTENSIONS',
                    'csv',
                    $lang['invalidextension_mustbe-extensions']
                ),
                default => $process_file_upload['error']->i18n($lang),
            },
        ];
    }
}
rs_setcookie("saved_csv_options", json_encode($csv_set_options));

$csvuploaded = file_exists($csvfile);
$csvstep = $csvuploaded ? getval("csvstep", 1, true) : 1;
if ($csvuploaded) {
    $messages = array();
    $csv_info = csv_upload_get_info($csvdir . DIRECTORY_SEPARATOR  . "csv_upload.csv", $messages);
    $offline_limit = $csv_info["row_count"] > 1000;
    $force_offline = $offline_limit && $offline_job_queue;
    $offline_text = $force_offline ? $lang["csv_upload_force_offline"] : $lang["csv_upload_recommend_offline"];
    unset($csv_info["row_count"]); // No longer needed
}

include __DIR__ . "/../../../include/header.php";

if (!checkperm("c")) {
    echo "<div class=\"BasicsBox\">" . $lang['csv_upload_error_no_permission'] . "</div>";
    include __DIR__ . "/../../../include/footer.php";
    return;
}
?>

<div class="BasicsBox">
    <h1>
        <?php
        echo escape($lang["csv_upload_nav_link"]);
        render_help_link("plugins/csv-upload");
        ?>
    </h1>

    <h2><?php echo escape($lang["csv_upload_step" . $csvstep]); ?></h2>

    <script>
        selectedFields = new Array();

        jQuery('document').ready(function() {
            // Record the existing values to re-enable once deselected
            jQuery('.columnselect').each(function() {
                jQuery(this).attr("prev",this.value);

                if (this.value == -1 || this.value == '') {
                    return;
                }

                selectedFields.push(this.value);
                // Disable selected column options in other inputs
                var fieldCount = selectedFields.length;
                for (var i = 0; i < fieldCount; i++) {
                    jQuery('.columnselect option[value="' + selectedFields[i] + '"]')
                    .not("option:selected",this)
                    .prop("disabled", true)
                }
            });

            jQuery('.columnselect').change(function() {
                selField  = this.value;
                prevField = jQuery(this).attr("prev");
            
                console.log("selected: " + selField);
                console.log("prev:     " + prevField);

                if (selField == undefined || selField == '') {
                    return; 
                }

                jQuery(this).attr("prev",selField);

                if (prevField != selField) {
                    var index = selectedFields.indexOf(prevField);
                    if (index !== -1) {
                        selectedFields.splice(index, 1);
                    }
                }

                if (!selectedFields.includes(selField) && selField != -1) {
                    selectedFields.push(selField);
                }

                // Re-enable options
                jQuery('.columnselect option').prop("disabled", false);

                // Disable selected column options in other inputs
                var fieldCount = selectedFields.length;
                for (var i = 0; i < fieldCount; i++)  {
                    jQuery('.columnselect option[value="' + selectedFields[i] + '"]')
                    .not("option:selected",this)
                    .prop("disabled", true)
                }
            });
        });
    </script>

    <?php
    $restypearr = get_resource_types();
    $resource_types = array();
    // Sort into array with ids as keys
    foreach ($restypearr as $restype) {
        $resource_types[$restype["ref"]] = $restype;
    }

    switch ($csvstep) {
        case 1:
            echo "<p>" . strip_tags_and_attributes($lang["csv_upload_intro"]) . "</p>";
            echo "<ul>";
            $condition = 1;
            while (isset($lang["csv_upload_condition" . $condition])) {
                echo "<li>"
                    . strip_tags_and_attributes($lang["csv_upload_condition" . $condition], ['a','b'], ['href','target'])
                    . "</li>";
                $condition++;
            }
            echo "</ul>";
            ?>
            <form action="<?php echo $_SERVER["SCRIPT_NAME"]; ?>" id="upload_csv_form" method="post" enctype="multipart/form-data">
                <?php generateFormToken("upload_csv_form"); ?>
                <input type="hidden" id="csvstepmain" name="csvstep" value="2">            
                <div class="Question">
                    <label for="<?php echo $fd; ?>"><?php echo escape($lang['csv_upload_file']); ?></label>
                    <input type="file" id="<?php echo $fd; ?>" name="<?php echo $fd; ?>" onchange="if(this.value==null || this.value=='') { jQuery('.file_selected').hide(); } else { jQuery('.file_selected').show(); } "> 
                    <div class="clearerleft"></div>
                </div>  
                
                <div class="file_selected Question" style="display: none;">
                    <input id="update_existing" name="update_existing" type=hidden value="0">
                    <label for="update_existing_option"><?php echo escape($lang["csv_upload_update_existing"]); ?></label>
                    <input type="checkbox" id="update_existing_option" name="update_existing_option" onchange="if(this.value==null || this.value=='') {jQuery('#update_existing').val('0'); } else {jQuery('#update_existing').val('1');}" >
                    <div class="clearerleft"></div>
                </div>

                <div class="file_selected Question" style="display: none;">
                    <label for="submit" class="file_selected" style="display: none;"></label>
                    <input type="submit" id="submit" value="<?php echo escape($lang["next"]); ?>" class="file_selected" style="display: none;"> 
                    <div class="clearerleft"></div>
                </div>
            </form>

            <br/>
            <div>
                <h2><?php echo escape($lang["csv_upload_mapping config"]); ?></h2>
                <form action="<?php echo $_SERVER["SCRIPT_NAME"]; ?>" id="upload_csv_config_form" method="post" enctype="multipart/form-data" >
                    <?php generateFormToken("upload_csv_config_form"); ?>
                    <input type="hidden" id="csvstepconfig" name="csvstep" value="1" >          

                    <?php
                    if ($existing_config) {?>
                        <div class="Question">
                            <label for="clear"><?php echo escape($lang["csv_upload_using_config"]); ?></label>
                            <div class="fixed" >
                                <a href="<?php echo generateURL($_SERVER["SCRIPT_NAME"], array("resetconfig" => "1")); ?>" onclick="return CentralSpaceLoad(this,false);">
                                    <?php echo LINK_CARET . $lang["csv_upload_upload_config_clear"]; ?>
                                </a>
                            </div>
                            <div class="clearerleft"></div>
                        </div>
                        <?php
                    } else {?>
                        <div class="Question">
                            <label for="csv_config"><?php echo escape($lang['csv_upload_upload_config']); ?></label>
                            <input type="file" id="csv_config" name="csv_config" onchange="if(this.value==null || this.value=='') { jQuery('.config_selected').hide(); } else { jQuery('.config_selected').show(); } "> 
                            <div class="clearerleft"></div>
                        </div>  
                        
                        <div class="config_selected Question" style="display: none;">
                            <label for="submit" class="config_selected" style="display: none;"></label>
                            <input type="submit" id="submit" value="<?php echo escape($lang["upload"]); ?>" class="config_selected" style="display: none;"> 
                            <div class="clearerleft"></div>
                        </div>
                        <?php
                    }?>

                    <div class="VerticalNav">
                        <ul>
                            <li></li>
                        </ul>
                    </div>
                </form>
            </div>
            <?php
            break;
        case 2:
            if (!$csv_set_options["update_existing"]) {
                // Step 2(a) Create new resources
                if ($offline_limit) {
                    echo "<div class='PageInformal'>" . $offline_text . "</div>";
                }
                echo "<h2>" . $lang["csv_upload_create_new_title"] . "</h2>";
                echo "<p>" . $lang["csv_upload_create_new_notes"] . "</p>";
                ?>
                <form action="<?php echo $_SERVER["SCRIPT_NAME"]; ?>" id="upload_csv_form" method="post" enctype="multipart/form-data" onSubmit="return CentralSpacePost(this,true);">
                    <?php generateFormToken("upload_csv_form"); ?>
                    <input type="hidden" id="csvstep" name="csvstep" value="3" > 
                    <div class="Question">
                        <label for="add_to_collection"><?php echo escape($lang['csv_upload_add_to_collection']); ?></label>
                        <input type="checkbox" id="add_to_collection" name="add_to_collection" value="1"<?php echo ($csv_set_options["add_to_collection"] != "") ? " checked " : ''; ?>> 
                        <div class="clearerleft"></div>
                    </div>

                    <div class="Question" id="resource_type_question">
                        <label for="resource_type_column"><?php echo escape($lang["csv_upload_resource_type_column"]); ?></label>
                        <select id="resource_type_column" name="resource_type_column"  class="stdwidth columnselect">
                            <option value=""><?php echo escape($lang["select"]); ?></option>
                            <?php
                            foreach ($csv_info as $csv_column => $csv_field_data) {
                                echo "<option value=\"" . $csv_column . "\" " . (($csv_set_options["resource_type_column"] != "" && $csv_set_options["resource_type_column"] == $csv_column) || strtolower($csv_field_data["header"]) == strtolower($lang["resourcetype"]) ? " selected " : "") . ">" . escape($csv_field_data["header"]) . "</option>\n";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="Question" id="resource_type_default_question">
                        <label for="resource_type_default"><?php echo escape($lang["csv_upload_resource_type_default"]); ?></label>
                        <select id="resource_type_default" name="resource_type_default" class="stdwidth" onchange="hide_override(this)" required>
                                <option value="0"><?php echo escape($lang["select"]); ?></option>
                                <?php foreach ($resource_types as $resource_type) { ?>
                                    <option value="<?php echo $resource_type["ref"]; ?>" <?php echo ($csv_set_options["resource_type_default"] == $resource_type["ref"]) ? " selected " : ''; ?>>
                                        <?php echo escape($resource_type["name"]); ?>
                                    </option>                                   
                                <?php } ?>
                        </select>
                        <div class="clearerleft"></div>
                    </div>

                    <div class="Question" id="status_question">
                        <label for="status_column"><?php echo escape($lang["csv_upload_workflow_column"]); ?></label>
                        <select id="status_column" name="status_column"  class="stdwidth columnselect">                    
                            <option value=""><?php echo escape($lang["select"]); ?></option>
                            <?php
                            foreach ($csv_info as $csv_column => $csv_field_data) {
                                echo "<option value=\"" . $csv_column . "\" " . (($csv_set_options["status_column"] === $csv_column || strtolower($csv_field_data["header"]) == strtolower($lang["status"])) ? " selected " : "") . ">" . escape($csv_field_data["header"]) . "</option>\n";
                            }
                            ?>
                        </select>
                        <div class="clearerleft"></div>
                    </div>

                    <div class="Question" id="status_default_question">
                        <label for="status_default"><?php echo escape($lang["csv_upload_workflow_default"]); ?></label>
                        <select id="status_default" name="status_default" class="stdwidth" onchange="hide_override(this)" required>
                                <option value=""><?php echo escape($lang["select"]); ?></option>
                                <?php
                                $workflow_states = get_editable_states($userref);
                                foreach ($workflow_states as $workflow_state) {
                                    ?>
                                    <option
                                        value="<?php echo $workflow_state["id"]; ?>"
                                        <?php if ($csv_set_options["status_default"] == $workflow_state["id"]) {
                                            echo " selected ";
                                        } ?>
                                    >
                                        <?php echo escape($workflow_state["name"]); ?>
                                    </option>                                   
                                    <?php
                                }
                                ?>
                        </select>
                        <div class="clearerleft"></div>
                    </div>

                    <div class="Question" id="access_question">
                        <label for="access_column"><?php echo escape($lang["csv_upload_access_column"]); ?></label>
                        <select id="access_column" name="access_column" class="stdwidth columnselect">                    
                            <option value=""><?php echo escape($lang["select"]); ?></option>
                            <?php
                            foreach ($csv_info as $csv_column => $csv_field_data) {
                                echo "<option value=\"" . $csv_column . "\" ";
                                if (
                                    ($csv_set_options["access_column"] != "" && $csv_set_options["access_column"] == $csv_column)
                                    ||
                                    strtolower($csv_field_data["header"]) == strtolower($lang["access"])
                                ) {
                                    echo " selected ";
                                }
                                echo  ">" . escape($csv_field_data["header"]) . "</option>\n";
                            }
                            ?>
                        </select>
                        <div class="clearerleft"></div>
                    </div>

                    <div class="Question" id="access_default_question">
                        <label for="access_default"><?php echo escape($lang["csv_upload_access_default"]); ?></label>
                        <select id="access_default" name="access_default" class="stdwidth" onchange="hide_override(this)" required>
                            <option value=""><?php echo escape($lang["select"]); ?></option>
                            <?php
                            // Get applicable access options - custom access omitted as can be added by batch editing later
                            for ($n = 0; $n < 3; $n++) {
                                if (!checkperm("ea" . $n) || checkperm("v")) {
                                    echo "<option value=\"" . $n . "\" " . (($csv_set_options["access_default"] == $n) ? " selected " : "") . ">" . escape($lang["access" . $n]) . "</option>\n";
                                }
                            }
                            ?>
                        </select>
                        <div class="clearerleft"></div>
                    </div>               

                    <div class="QuestionSubmit NoPaddingSaveClear QuestionSticky">
                        <input type="button" id="back" value="<?php echo escape($lang["back"]); ?>"  onClick="CentralSpaceLoad('<?php echo generateURL($_SERVER["SCRIPT_NAME"], array("csvstep" => $csvstep - 1)); ?>',true);return false;" > 
                        <input type="submit" id="submit" value="<?php echo escape($lang["next"]); ?>">
                        <div class="clearerleft"></div>
                    </div>   
                </form>

                <?php
            } else {
                // Step 2(b) Update existing
                if ($offline_limit) {
                    echo "<div class='PageInformal'>" . $offline_text . "</div>";
                }
                echo "<h2>" . $lang["csv_upload_update_existing_title"] . "</h2>";
                echo "<p>" . $lang["csv_upload_update_existing_notes"] . "</p>";
                ?>
                <form action="<?php echo $_SERVER["SCRIPT_NAME"]; ?>" id="upload_csv_form" method="post" enctype="multipart/form-data" onSubmit="return CentralSpacePost(this,true);" >
                    <?php generateFormToken("upload_csv_form"); ?>
                    <input type="hidden" id="csvstep" name="csvstep" value="3"> 

                    <div class="Question">
                        <label for="csv_update_col"><?php echo escape($lang["csv_upload_update_existing_collection"]); ?></label>
                        <input id="csv_update_col" name="csv_update_col" type=hidden value="<?php echo (int) $csv_set_options["csv_update_col"]; ?>">
                        <input
                            type="checkbox"
                            name="csv_update_col_select"
                            onchange="if(this.checked) { jQuery('#csv_update_col_id_select').show(); jQuery('#csv_update_col').val('1');} else { jQuery('#csv_update_col_id_select').hide(); jQuery('#csv_update_col').val('0'); }"
                            <?php echo ($csv_set_options["csv_update_col"]) ? " checked" : ''; ?>
                        > 
                        
                        <div class="clearerleft"></div>
                        
                        <div id="csv_update_col_id_select" <?php echo ($csv_set_options["csv_update_col"] == 0) ? "style='display:none;' " : ''; ?>>
                            <label for="csv_update_col_id"></label>
                            <?php
                            render_user_collection_select("csv_update_col_id", array(), $csv_set_options["csv_update_col_id"], "stdwidth");
                            ?>
                        </div>
                        <div class="clearerleft"></div>
                    </div>

                    <div class="Question" id="resource_type_question">
                        <label for="resource_type_column"><?php echo escape($lang["csv_upload_resource_type_column"]); ?></label>
                        <select id="resource_type_column" name="resource_type_column" class="stdwidth columnselect">                    
                            <option value=""><?php echo escape($lang["select"]); ?></option>
                            <?php
                            foreach ($csv_info as $csv_column => $csv_field_data) {
                                echo "<option value=\"" . $csv_column . "\" ";
                                if (
                                    ($csv_set_options["resource_type_column"] != "" && $csv_set_options["resource_type_column"] == $csv_column)
                                    ||
                                    strtolower($csv_field_data["header"]) == strtolower($lang["resourcetype"])
                                ) {
                                    echo " selected ";
                                }
                                echo  ">" . escape($csv_field_data["header"]) . "</option>\n";
                            }
                            ?>
                        </select>
                        <div class="clearerleft"></div>
                    </div>

                    <div class="Question" id="id_column_question">
                        <label for="id_column"><?php echo escape($lang["csv_upload_resource_match_column"]); ?></label>
                            <select id="id_column" name="id_column" class="stdwidth columnselect">
                                <option value=""><?php echo escape($lang["select"]); ?></option>
                                <?php
                                foreach ($csv_info as $csv_column => $csv_field_data) {
                                    echo "<option value=\"" . $csv_column . "\" ";
                                    if (
                                        ($csv_set_options["id_column"] != "" && $csv_set_options["id_column"] == $csv_column)
                                        ||
                                        strtolower($csv_field_data["header"]) == strtolower($lang["resourceids"])
                                    ) {
                                        echo " selected ";
                                    }
                                    echo  ">" . escape($csv_field_data["header"]) . "</option>\n";
                                }
                                ?>
                            </select>
                        <div class="clearerleft"></div>
                    </div>

                    <div class="Question" id="id_column_match_question">
                        <label for="id_column_match"><?php echo escape($lang["csv_upload_match_type"]); ?></label>
                        <select id="id_column_match" name="id_column_match" class="stdwidth" onchange="if (this.value==0) { jQuery('#multiple_match_question').hide();} else { jQuery('#multiple_match_question').show(); }">
                            <option value="0"><?php echo escape($lang["resourceid"]); ?></option>
                            <?php
                            foreach ($allfields as $field) {
                                echo "<option value='" . $field["ref"] . "' " . ($csv_set_options["id_column_match"]  == $field["ref"] ? " selected " : "") . " >" . $field["title"] . "</option>\n";
                            }
                            ?>
                        </select>
                        <div class="clearerleft"></div>
                    </div>

                    <div class="Question" id="multiple_match_question" style="display: none;">
                        <label for="multiple_match"><?php echo escape($lang["csv_upload_multiple_match_action"]); ?></label>
                        <select id="multiple_match" name="multiple_match" class="stdwidth">                                     
                            <option value="0" <?php echo ($csv_set_options["multiple_match"] == 0) ? " selected " : ''; ?>>Update none</option>                                    
                            <option value="1" <?php echo ($csv_set_options["multiple_match"] == 1) ? " selected " : ''; ?>>Update all matching</option>
                        </select>
                        <div class="clearerleft"></div>
                    </div>   
                    
                    <div class="Question" id="status_question">
                        <label for="status_column"><?php echo escape($lang["csv_upload_workflow_column"]); ?></label>
                        <select id="status_column" name="status_column" class="stdwidth columnselect">                    
                            <option value=""><?php echo escape($lang["select"]); ?></option>
                            <?php
                            foreach ($csv_info as $csv_column => $csv_field_data) {
                                echo "<option value=\"" . escape($csv_column) . "\" " . (($csv_set_options["status_column"] === $csv_column || strtolower($csv_field_data["header"]) == strtolower($lang["status"])) ? " selected " : "") . ">" . escape($csv_field_data["header"]) . "</option>\n";
                            }
                            ?>
                        </select>
                        <div class="clearerleft"></div>
                    </div>

                    <div class="Question" id="status_default_question">
                        <label for="status_default"><?php echo escape($lang["csv_upload_workflow_default"]); ?></label>
                        <select id="status_default" name="status_default" class="stdwidth" onchange="hide_override(this)">
                            <option value=""><?php echo escape($lang["select"]); ?></option>
                            <?php
                            $workflow_states = get_editable_states($userref);
                            foreach ($workflow_states as $workflow_state) {
                                ?>
                                <option value="<?php echo (int) $workflow_state["id"]; ?>"><?php echo escape($workflow_state["name"]); ?></option>                                   
                                <?php
                            }
                            ?>
                        </select>
                        <div class="clearerleft"></div>
                    </div>

                    <div class="Question" id="access_question">
                        <label for="access_column"><?php echo escape($lang["csv_upload_access_column"]); ?></label>
                        <select id="access_column" name="access_column" class="stdwidth columnselect">                    
                            <option value=""><?php echo escape($lang["select"]); ?></option>
                            <?php
                            foreach ($csv_info as $csv_column => $csv_field_data) {
                                echo "<option value=\"" . escape($csv_column) . "\" ";
                                if (
                                    ($csv_set_options["access_column"] != "" && $csv_set_options["access_column"] == $csv_column)
                                    ||
                                    strtolower($csv_field_data["header"]) == strtolower($lang["access"])
                                ) {
                                    echo " selected ";
                                }
                                echo  ">" . escape($csv_field_data["header"]) . "</option>\n";
                            }
                            ?>
                        </select>
                        <div class="clearerleft"></div>
                    </div>

                    <div class="Question" id="access_default_question">
                        <label for="access_default"><?php echo escape($lang["csv_upload_access_default"]); ?></label>
                        <select id="access_default" name="access_default" class="stdwidth" onchange="hide_override(this)">
                            <option value=""><?php echo escape($lang["select"]); ?></option>
                            <?php
                            // Get applicable access options - custom access omitted as can be added by batch editing later
                            for ($n = 0; $n < 3; $n++) {
                                if (!checkperm("ea" . $n) || checkperm("v")) {
                                    echo "<option value=\"" . $n . "\">" . escape($lang["access" . $n]) . "</option>\n";
                                }
                            }
                            ?>
                        </select>
                        <div class="clearerleft"></div>
                    </div>

                    <div class="QuestionSubmit NoPaddingSaveClear QuestionSticky">
                        <input type="button" id="back" value="<?php echo escape($lang["back"]); ?>"  onClick="CentralSpaceLoad('<?php echo generateURL($_SERVER["SCRIPT_NAME"], array("csvstep" => $csvstep - 1)); ?>',true);return false;"> 
                        <input type="submit" id="submit" value="<?php echo escape($lang["next"]); ?>">
                        <div class="clearerleft"></div>
                    </div>
                </form>
                <?php
            }
            ?>

            <script>
                function hide_override(dropdown) {
                    if (dropdown.options[dropdown.selectedIndex].value=='default') { 
                        jQuery('.override').hide();
                        jQuery('.override').attr('disabled','disabled'); 
                    } else { 
                        jQuery('.override').removeAttr('disabled');
                        jQuery('.override').show(); 
                    }
                }
            </script>

            <?php
            break;
        case 3:
            // Map metadata
            // Step 2(b) Update existing
            if ($offline_limit) {
                echo "<div class='PageInformal'>" . $offline_text . "</div>";
            }
            if (is_array($csv_info)) {
                echo "<p>" . $lang["csv_upload_map_fields_notes"] . "</p>";
                echo "<p>" . $lang["csv_upload_map_fields_auto_notes"] . "</p>";
                // Render each header with an option to map to a field
                ?>
                <div class="BasicsBox">
                    <form action="<?php echo $_SERVER["SCRIPT_NAME"]; ?>" id="upload_csv_form" method="post" enctype="multipart/form-data" onSubmit="return CentralSpacePost(this,true);">
                        <?php generateFormToken("upload_csv_form"); ?>
                        <input type="hidden" id="csvstep" name="csvstep" value="4"> 
                        <div class="Listview">
                            <table id="csv_upload_table" class="ListviewStyle">
                                <tr class="ListviewTitleStyle"> 
                                    <th><?php echo escape($lang["csv_upload_mapping_header"]); ?></th>
                                    <th><?php echo escape($lang["field"]); ?></th>
                                    <th><?php echo escape($lang["csv_upload_mapping_csv_data"]); ?></th>
                                </tr>

                                <?php
                                foreach ($csv_info as $csv_column => $csv_field_data) {
                                    // Used to stop selection process if a mapping found for particular resource type version of field
                                    $csv_set_options_found = false;

                                    if (in_array($csv_column, $selected_columns)) {
                                        continue;
                                    }

                                    echo "<tr>";
                                    echo "<td><div class='fixed medwidth' >" . escape($csv_field_data["header"]) . "</div></td>\n";
                                    echo "<td><select name='fieldmapping[" . $csv_column  . "]' class='stdwidth columnselect'>";
                                    echo "<option value='-1' " . ((isset($csv_set_options["fieldmapping"][$csv_column]) && $csv_set_options["fieldmapping"][$csv_column] == -1) ? "selected" : "") . ">" . $lang["csv_upload_mapping_ignore"] . "</option>";

                                    foreach ($allfields as $field) {
                                        echo "<option value=\"" . $field["ref"] . "\" ";

                                        if (isset($csv_set_options["fieldmapping"][$csv_column]) && $csv_set_options["fieldmapping"][$csv_column] == $field["ref"]) {
                                            echo " selected ";
                                            $csv_set_options_found = true;
                                        } elseif (
                                            !$csv_set_options_found && (in_array(mb_strtolower($csv_field_data["header"]), array(mb_strtolower((string) $field["name"]),mb_strtolower($field["title"]))) &&
                                            !(isset($csv_set_options["fieldmapping"][$csv_column]) && $csv_set_options["fieldmapping"][$csv_column] == -1))
                                        ) {
                                            echo " selected ";
                                        }

                                        echo  ">" . escape(i18n_get_translated($field["title"]));

                                        if ((int)$field["global"] !== 1) {
                                            $fieldrestypes = explode(",", (string)$field["resource_types"]);
                                            $fieldrestypenames = [];

                                            foreach ($fieldrestypes as $fieldrestype) {
                                                if (in_array($fieldrestype, array_keys($resource_types))) {
                                                    $fieldrestypenames[] = $resource_types[$fieldrestype]["name"];
                                                }
                                            }

                                            if (count($fieldrestypes) < count($restypearr) - 1) {
                                                // Don't show this if they are linked to all but one resource types
                                                echo " (" .  implode(",", $fieldrestypenames) . ")";
                                            }
                                        }
                                        echo "</option>\n";
                                    }

                                    echo "</select></td>";
                                    echo "<td>";

                                    if (count($csv_field_data["values"]) > 0) {
                                        echo "<div class=\"keywordselected\">" . implode("</div><div class=\"keywordselected\">", array_slice(array_map("htmlspecialchars", $csv_field_data["values"]), 0, 5)) . "</div></td>";
                                    }

                                    echo "</td>";
                                    echo "</tr>";
                                }
                                ?>
                            </table>
                            <div class="clearerleft"></div>
                        </div>

                        <div class="QuestionSubmit NoPaddingSaveClear QuestionSticky">
                            <?php if ($offline_job_queue) { ?>
                                <div class="Question">
                                    <label for="process_offline"><?php echo escape($lang["csv_upload_process_offline_quick"]); ?></label>
                                    <input type="checkbox" id="process_offline" name="process_offline" value="1" onclick="if(this.checked){jQuery('#csvstep').val('5');}else{jQuery('#csvstep').val('4');}">
                                    <div class="clearerleft"></div>
                                </div>
                            <?php } ?>

                            <div class="Question">
                                <input type="button" id="back" value="<?php echo escape($lang["back"]); ?>" onclick="CentralSpaceLoad('<?php echo generateURL($_SERVER["SCRIPT_NAME"], array("csvstep" => $csvstep - 1)); ?>',true);return false;" > 
                                <input type="submit" id="submit" value="<?php echo escape($lang["next"]); ?>">
                                <div class="clearerleft"></div>
                            </div>
                        </div>
                    </form>
                </div>
                <?php
            } else {
                exit("No data found");
            }
            break;
        case 4:
            // Test file processing
            // Ensure connection does not get dropped
            set_time_limit(0);
            $meta = meta_get_map();
            $messages = array();
            $log_time = date("Ymd-H:i:s", time());
            $prelog_file = get_temp_dir(false, 'user_downloads') . "/" . $userref . "_" . md5($username . md5($csv_set_options["csvchecksum"] . $log_time) . $scramble_key) . ".log";
            $prelog_url = $baseurl . "/pages/download.php?userfile=" . $userref . "_" . md5($csv_set_options["csvchecksum"] . $log_time) . ".log&filename=csv_upload_" . $log_time;
            $csv_set_options["log_file"] = $prelog_file;
            $valid_csv = csv_upload_process($csvfile, $meta, $resource_types, $messages, $csv_set_options);

            if ($offline_limit) {
                echo "<div class='PageInformal'>" . $offline_text . "</div>";
            }

            echo "<p>" . $lang["csv_upload_validation_notes"] . "</p>";

            if (count($messages) > 1000) {
                $messages = array_slice($messages, 0, 1000);
                echo "<p>" . str_replace("[log_url]", $prelog_url, $lang["csv_upload_full_messages_link"]) . "</p>";
            }
            ?>

            <div class="BasicsBox">
                <textarea rows="20" cols="100"><?php
                foreach ($messages as $message) {
                    echo $message . PHP_EOL;
                } ?></textarea>
                <div class="clearerleft"></div>
            </div>

            <div class="BasicsBox">
                <form action="<?php echo $_SERVER["SCRIPT_NAME"]; ?>" id="upload_csv_form" method="post" enctype="multipart/form-data" onSubmit="return CentralSpacePost(this,true);">
                    <?php
                    generateFormToken("upload_csv_form");

                    if (!$force_offline) {
                        ?>
                        <div class="Question">
                            <label for="process_offline"><?php echo escape($lang["csv_upload_process_offline"]); ?></label>
                            <?php if ($offline_job_queue) { ?>
                                <input type="checkbox" id="process_offline" name="process_offline" value="1">
                            <?php } else {
                                echo "<div class='Fixed'>" . $lang["offline_processing_disabled"] . "</div>";
                            } ?>
                            <div class="clearerleft"></div>
                        </div>
                        <?php
                    } ?>

                    <input type="hidden" id="csvstep" name="csvstep" value="5"> 

                    <div class="QuestionSubmit NoPaddingSaveClear QuestionSticky">
                        <input type="button" id="back" value="<?php echo escape($lang["back"]); ?>"  onClick="CentralSpaceLoad('<?php echo generateURL($_SERVER["SCRIPT_NAME"], array("csvstep" => $csvstep - 1)); ?>',true);return false;" > 
                        <input
                            type="submit"
                            id="submit"
                            value="<?php echo escape($lang["csv_upload_process"]); ?>"
                            <?php if (!$valid_csv) {
                                echo "onclick=\"return confirm('" . $lang["csv_upload_ignore_errors"] . "');\"";
                            } ?>
                        >
                        <div class="clearerleft"></div>
                    </div>    
                </form>
            </div>

            <?php
            break;

        case 5:
            // Process file
            $meta = meta_get_map();
            $csv_set_options["process_offline"] = $force_offline || getval("process_offline", "") != "";

            if ($csv_set_options["process_offline"]) {
                // Move the CSV to a new location so that it doesn't get overwritten
                $csvdir     = get_temp_dir() . DIRECTORY_SEPARATOR . "csv_upload" . DIRECTORY_SEPARATOR . ($usehash);

                if (!file_exists($csvdir)) {
                    mkdir($csvdir, 0777, true);
                }

                $offlinecsv = $csvdir . DIRECTORY_SEPARATOR . uniqid() . ".csv";
                rename($csvfile, $offlinecsv);

                $csv_upload_job_data = array(
                    'csvfile'           => $offlinecsv,
                    'csv_set_options'   => $csv_set_options
                );

                $csvjob = job_queue_add(
                    'csv_upload',
                    $csv_upload_job_data,
                    $userref,
                    '',
                    $lang["csv_upload_oj_complete"],
                    $lang["csv_upload_oj_failed"],
                    $csv_set_options["csvchecksum"]
                );

                if ($csvjob) {
                    echo str_replace("[jobref]", $csvjob, $lang["csv_upload_oj_created"]);
                } elseif (is_string($csvjob)) {
                    echo "<div class='PageInfoMessage'>" . $lang["error"] . $csvjob . "</div>";
                }
            } else {
                $messages = array();
                // Processing immediately. Ensure connection does not get dropped
                set_time_limit(0);
                $log_time = date("Ymd-H:i:s", time());
                $log_file = get_temp_dir(false, 'user_downloads') . "/" . $userref . "_" . md5($username . md5($csv_set_options["csvchecksum"] . $log_time) . $scramble_key) . ".log";
                $log_url = $baseurl . "/pages/download.php?userfile=" . $userref . "_" . md5($csv_set_options["csvchecksum"] . $log_time) . ".log&filename=csv_upload_" . $log_time;
                $csv_set_options["log_file"] = $log_file;
                csv_upload_process($csvfile, $meta, $resource_types, $messages, $csv_set_options, 0, true);
            }

            if (count($messages) > 0) {
                // If this is a very large CSV we need to limit the output displayed or it may crash the browser
                if (count($messages) > 1000) {
                    $messages = array_slice($messages, 0, 1000);
                    echo "<p>" . str_replace("[log_url]", $log_url, $lang["csv_upload_full_messages_link"]) . "</p>";
                }
                ?>
                <div class="BasicsBox">
                    <textarea rows="20" cols="100"><?php
                    foreach ($messages as $message) {
                        echo $message . PHP_EOL;
                    } ?></textarea>
                </div>
                <?php
            } ?>

            <div class="BasicsBox">
                <div class="VerticalNav">
                    <ul>
                        <li>
                            <a href="<?php echo generateURL($_SERVER["SCRIPT_NAME"], array("getconfig" => "1")); ?>"><?php echo LINK_CARET . $lang["csv_upload_download_config"]; ?></a>
                        </li>
                        <li>
                            <a href="<?php echo generateURL($_SERVER["SCRIPT_NAME"], array("step" => "1")); ?>"><?php echo LINK_CARET . $lang["csv_upload_upload_another"]; ?></a>
                        </li>
                    </ul>
                </div>
            </div>

            <?php
            if ($csv_set_options["add_to_collection"] != "") { ?>
                <script>
                    jQuery(document).ready(function() {
                        CollectionDivLoad('<?php echo $baseurl_short?>pages/collections.php');
                        ShowThumbs();
                    });         
                </script>
                <?php
            }

            break;
        default:
            break;
    }
    ?>
</div><!-- end of BasicsBox -->

<?php
include __DIR__ . "/../../../include/footer.php";

