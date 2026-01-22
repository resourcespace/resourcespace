<?php
include "../../../include/boot.php";
include_once "../../../include/authenticate.php";
include_once "../include/file_functions.php";

$ref = getval("ref", "");
if (!is_numeric($ref)) {
    $ref = "new";
} // force to either a number or "new"

$resource = getval("resource", "", true);
$file_path = get_consent_file_path($ref);

# Check access
if ($resource != "" && !consentmanager_check_write($resource)) {
    exit("Access denied");
} # Should never arrive at this page without edit access

$url_params = array(
    'search'     => getval('search', ''),
    'order_by'   => getval('order_by', ''),
    'collection' => getval('collection', ''),
    'offset'     => getval('offset', 0),
    'restypes'   => getval('restypes', ''),
    'archive'    => getval('archive', '')
);
    # Added from resource page?
if ($resource != "") {
    $url_params['ref'] = $resource;
    $redirect_url = generateURL($baseurl_short . "pages/view.php", $url_params);
} else {
    # Added from Manage Consents
    $redirect_url = generateURL($baseurl_short . "plugins/consentmanager/pages/list.php", $url_params);
}

if (getval("submitted", "") != "") {
    # Save consent data

    # Construct expiry date
    $expires = getval("expires","");

    if ($expires == "") {
        $expires = null;
    }

    # Construct date of birth
    $date_of_birth = getval("date_of_birth", null);

    if ($date_of_birth == "") {
        $date_of_birth = null;
    }

    # Construct date of consent
    $date_of_consent = getval("date_of_consent", null);

    if ($date_of_consent == "") {
        $date_of_consent = null;
    }

    # Construct usage
    $consent_usage = "";
    if (isset($_POST["consent_usage"])) {
        $consent_usage = join(", ", $_POST["consent_usage"]);
    }

    # No expiry date ticked? Insert null
    if (getval("no_expiry_date", "") == "yes") {
        $expires = null;
    }

    if ($ref == "new") {
        # New record
        $ref = consentmanager_create_consent(getval('name', ''), 
                                             $date_of_birth,
                                             getval('address', ''),
                                             getval('parent_guardian_name', ''),
                                             getval('email', ''), 
                                             getval('telephone', ''), 
                                             $consent_usage, 
                                             getval('notes', ''),
                                             $date_of_consent,
                                             $expires, 
                                             $userref);

        $file_path = get_consent_file_path($ref); // get updated path

        # Add to all the selected resources
        if (getval("resources", "") != "") {
            $resources = explode(", ", getval("resources", ""));
            foreach ($resources as $r) {
                $r = trim($r);
                if (is_numeric($r)) {
                    consentmanager_link_consent($ref, $r);
                }
            }
        }
    } else {
        # Update existing record
        consentmanager_update_consent($ref, getval('name', ''), $date_of_birth, getval('address', ''), getval('parent_guardian_name', ''),getval('email', ''), getval('telephone', ''), $consent_usage, getval('notes', ''), $date_of_consent, $expires);

        # Add all the selected resources
        ps_query("delete from resource_consent where consent= ?", ['i', $ref]);
        $resources = explode(",", getval("resources", ""));

        if (getval("resources", "") != "") {
            foreach ($resources as $r) {
                $r = trim($r);
                if (is_numeric($r)) {
                    consentmanager_link_consent($ref, $r);
                }
            }
        }
    }

    # Handle file upload
    global $banned_extensions;
    if (isset($_FILES["file"]) && $_FILES["file"]["tmp_name"] != "") {
        $process_file_upload = process_file_upload($_FILES['file'], new SplFileInfo($file_path), []);

        if ($process_file_upload['success']) {
            ps_query("UPDATE consent set file= ? where ref= ?", ['s', $_FILES["file"]["name"], 'i', $ref]);
        } else {
            error_alert(
                match ($process_file_upload['error']) {
                    ProcessFileUploadErrorCondition::InvalidExtension => str_replace(
                        '[filetype]',
                        parse_filename_extension($_FILES['file']['name']),
                        $lang['error_upload_invalid_file']
                    ),
                    default => $process_file_upload['error']->i18n($lang),
                },
                true
            );
            exit();
        }
    }

    # Handle file clear
    if (getval("clear_file", "") != "") {
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        ps_query("UPDATE consent set file='' where ref= ?", ['i', $ref]);
    }

    redirect($redirect_url);
}

# Fetch consent data
if ($ref == "new") {
    # Set default values for the creation of a new record.
    $consent = array(
        "name" => "",
        "date_of_birth" => "",
        "address" => "",
        "parent_guardian_name" => "",
        "email" => "",
        "telephone" => "",
        "consent_usage" => "",
        "notes" => "",
        "date_of_consent" => "",
        "expires" => "",
        "file" => ""
        );
    if ($resource == "") {
        $resources = array();
    } else {
        $resources = array($resource);
    }
} else {
    $consent = consentmanager_get_consent($ref);
    if ($consent === false) {
        exit("Consent not found.");
    }
    $resources = $consent["resources"];

    $created_by_user = get_user($consent['created_by']);
}

include "../../../include/header.php";
?>
<div class="BasicsBox">
    <p>
        <a href="<?php echo $redirect_url ?>" onclick="return CentralSpaceLoad(this,true);">
            <?php echo LINK_CARET_BACK . escape($resource != "" ? $lang["backtoresourceview"] : $lang["back"]); ?>
        </a>
    </p>

    <h1><?php echo escape($ref == "new" ? $lang["new_consent"] : $lang["edit_consent"]); ?></h1>

    <form method="post" action="<?php echo $baseurl_short?>plugins/consentmanager/pages/edit.php" enctype="multipart/form-data">
        <input type=hidden name="submitted" value="true">
        <input type=hidden name="ref" value="<?php echo $ref?>">
        <input type=hidden name="resource" value="<?php echo $resource?>">
        <?php generateFormToken("consentmanager_edit"); ?>

        <div class="Question">
            <label><?php echo escape($lang["consent_id"]); ?></label>
            <div class="Fixed"><?php echo escape($ref == "new" ? $lang["consentmanager_new"] : $ref)?></div>
            <div class="clearerleft"></div>
        </div>
        <?php if ($ref != "new"): ?>
        <div class="Question">
            <label>Created by:</label>
            <div class="Fixed">
                <?php 
                if (is_array($created_by_user)) {
                    echo escape($created_by_user["fullname"] == "" ? $created_by_user["username"] : $created_by_user["fullname"]); 
                }                
                ?>                
            </div>
            <div class="clearerleft"></div>
        </div>
        <?php endif; ?>

        <div class="Question">
            <label><?php echo escape($lang["name"]); ?></label>
            <input type=text class="stdwidth" name="name" id="name" value="<?php echo escape($consent["name"]); ?>" />
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="date_of_birth"><?php echo escape($lang["date_of_birth"]); ?></label>
            <input type=date name="date_of_birth" id="date_of_birth" value="<?php echo escape((string) $consent["date_of_birth"]); ?>" />
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="address"><?php echo escape($lang["address"]); ?></label>
            <textarea class="stdwidth" rows="5" name="address" id="address"><?php echo escape((string) $consent["address"]); ?></textarea>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="parent_guardian_name"><?php echo escape($lang["parent_guardian"]); ?></label>
            <input type=text class="stdwidth" name="parent_guardian_name" id="parent_guardian_name" value="<?php echo escape((string) $consent["parent_guardian_name"]); ?>" />
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["email"]); ?></label>
            <input type=text class="stdwidth" name="email" id="email" value="<?php echo escape((string) $consent["email"]); ?>" />
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["telephone"]); ?></label>
            <input type=text class="stdwidth" name="telephone" id="telephone" value="<?php echo escape((string) $consent["telephone"]); ?>" />
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["indicateusagemedium"]); ?></label>
            <table>
                <?php
                $s = trim_array(explode(",", $consent["consent_usage"]));
                $allchecked = true;

                foreach ($consent_usage_mediums as $medium) {
                    ?>
                    <tr>
                        <td>
                            <label>
                                <input
                                    type="checkbox"
                                    class="consent_usage"
                                    name="consent_usage[]"
                                    value="<?php echo $medium; ?>"
                                    <?php if (in_array($medium, $s)) { ?>
                                        checked
                                    <?php } else {
                                        $allchecked = false;
                                    } ?>
                                >&nbsp;<?php echo lang_or_i18n_get_translated($medium, "consent_usage-"); ?>
                            </label>
                        </td>
                    </tr>
                    <?php
                }
                ?>

                <tr>
                    <td>
                        <!-- Option to tick all mediums -->
                        <label>
                            <input type="checkbox" onChange="jQuery('.consent_usage').prop('checked',this.checked);" <?php echo ($allchecked) ? " checked" : ''; ?> />
                            <?php echo escape($lang["selectall"]); ?>
                        </label>
                    </td>
                </tr>
            </table>

            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="date_of_consent"><?php echo escape($lang["date_of_consent"]); ?></label>
            <input type=date name="date_of_consent" id="date_of_consent" value="<?php echo escape($consent['date_of_consent'] ? $consent['date_of_consent'] : date('Y-m-d')); ?>" />
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="expires"><?php echo escape($lang["fieldtitle-expiry_date"]); ?></label>
            <input type=date name="expires" id="expires" min="<?php echo $minyear . "-01-01"; ?>" max="<?php echo date("Y") + 30 . "-12-31"; ?>" value="<?php echo escape((string) $consent["expires"]); ?>" />

            <!-- Option for no expiry date -->
            &nbsp;&nbsp;&nbsp;&nbsp;
            <input
                type="checkbox"
                name="no_expiry_date"
                value="yes"
                id="no_expiry"
                <?php echo ($consent["expires"] == "") ? " checked" : ''; ?>
                onChange="jQuery('#expires').attr('disabled',this.checked);"
            />
            <?php
            echo escape($lang["no_expiry_date"]);
            if ($consent["expires"] == "") {
                ?>
                <script>jQuery('#expires').attr('disabled',true);</script>
                <?php
            } ?>

            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="resources"><?php echo escape($lang["linkedresources"]); ?></label>
            <textarea class="stdwidth" rows="3" name="resources" id="resources"><?php echo join(", ", $resources)?></textarea>
            <div class="clearerleft"></div>      
        </div>

        <div class="Question">
            <label for="notes"><?php echo escape($lang["notes"]); ?></label>
            <textarea class="stdwidth" rows="5" name="notes" id="notes"><?php echo escape($consent["notes"]) ?></textarea>
            <div class="clearerleft"></div>
        </div>

        <div class="Question" id="file">
            <label for="file"><?php echo escape($lang["file"]); ?></label>
            <?php
            if ($consent["file"] != "") {
                ?>
                <span>
                    <i class="icon-file"></i>
                    <a href="download.php?resource=<?php echo $resource ?>&ref=<?php echo $ref ?>"><?php echo $consent['file']; ?></a>
                </span>
                &nbsp;&nbsp;&nbsp;&nbsp;
                <input type="submit" name="clear_file" value="<?php echo escape($lang["clearbutton"]); ?>" onclick="return confirm('<?php echo escape($lang["confirmdeleteconsentfile"]); ?>');">
                <?php
            } else {
                ?>
                <input type="file" name="file" style="width:300px">
                <input type="submit" name="upload_file" value="<?php echo escape($lang['upload']); ?>">
                <?php
            }
            ?>
            <div class="clearerleft"></div>
        </div>

        <div class="QuestionSubmit">        
            <input name="save" type="submit" value="<?php echo escape($lang["save"]); ?>" />
        </div>
    </form>
</div>

<?php
include "../../../include/footer.php";
?>
