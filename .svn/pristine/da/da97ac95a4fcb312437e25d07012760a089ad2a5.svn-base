<?php
include "../include/boot.php";
include "../include/authenticate.php";

if (!(checkperm('a') || checkperm('exup') || in_array($usergroup, $upload_link_usergroups))) {
    error_alert($lang["error-permissiondenied"], true);
    exit();
}

// Set up array of valid users that share can be set to emulate
$validsharegroups = array();

foreach ($upload_link_usergroups as $upload_link_usergroup) {
    $up_group = get_usergroup($upload_link_usergroup);

    if ($up_group) {
        $validsharegroups[$upload_link_usergroup] = $up_group["name"];
    }
}

if (count($validsharegroups) == 0) {
    // No specific configuration, add the user's own group
    $up_group = get_usergroup($usergroup);
    $validsharegroups[$usergroup] = $up_group["name"];
}

$share_collection   = getval('share_collection', 0, true);
$uploadkey          = getval("uploadkey", "");
$emailmessage       = getval("message", "");
$messages           = array();

if ($uploadkey != "") {
    $shareinfo      = get_external_shares(array("share_collection" => $share_collection, "access_key" => $uploadkey, "share_type" => 1));
    if (isset($shareinfo[0])) {
        $shareinfo  = $shareinfo[0];
    } else {
        error_alert($lang["error_invalid_key"], true);
        exit();
    }

    $editable       = can_edit_upload_share($share_collection, $uploadkey);

    if (!$editable) {
        error_alert($lang["error-permissiondenied"], true);
        exit();
    }

    $editing        = $uploadkey != "" && $editable;
    $exsharepwd       = isset($shareinfo["password_hash"]) && $shareinfo["password_hash"] != "" ? "password_placeholder" : "";
    $exshareusergroup = isset($shareinfo["usergroup"]) ? $shareinfo["usergroup"] : $usergroup;
    $exshareexpires   = isset($shareinfo["expires"]) ? $shareinfo["expires"] : null;

    if (!isset($validsharegroups[$exshareusergroup])) {
        $cursharegroup = get_usergroup($exshareusergroup);
        $validsharegroups[$exshareusergroup]  = $cursharegroup["name"];
    }

    $sharepwd       = getval("sharepassword", $exsharepwd);
    $shareusergroup = getval("usergroup", $exshareusergroup, true);
    $shareexpires   = getval("shareexpires", $exshareexpires);
} else {
    $sharepwd       = getval("sharepassword", "");
    $shareusergroup = getval("usergroup", $usergroup, true);
    $shareexpires   = getval("shareexpires", "");
    $share_emails   = getval("share_emails", "");
    $editing = false;
}

$collectiondata = get_collection($share_collection);

// Get existing shares for this collection
$cursharefltr = array(
    "share_user"        => (checkperm('a') || checkperm('ex') ? '' : $userref),
    "share_type"        => "1",
    "share_collection"  => $share_collection,
    );

$curshares = get_external_shares($cursharefltr);
$submitted = getval("submitted", "") != "";

if ($submitted) {
    if ($shareexpires == "") {
        $messages[] = $lang["error_invalid_date"];
    }
    if (!isset($validsharegroups[$shareusergroup])) {
        $messages[] = $lang["error_invalid_usergroup"];
    }

    enforceSharePassword($sharepwd);

    if (count($messages) == 0) {
        $shareoptions = array(
            "collection" => $share_collection,
            "usergroup" => $shareusergroup,
            "user"      => $userref,
            "expires"   => $shareexpires,
            "password"  => $sharepwd,
            "upload"    => 1,
            "message"   => $emailmessage,
        );

        if (isset($share_emails) && trim($share_emails) != "") {
            $shareoptions["emails"] = trim_array(explode(",", $share_emails));
        }

        if ($uploadkey != "") {
            $shareoptions["group"] = $shareusergroup;
            $result = edit_collection_external_access($uploadkey, -1, $shareexpires, $shareusergroup, $sharepwd, $shareoptions);
            if ($result) {
                $messages[] = $lang["saved"];
                $shareurl = $baseurl . "/?c=" . $share_collection . "&k=" . $uploadkey;
                $messages[] = "<a href='" . $shareurl . "'>" . $shareurl  . "</a>";
            } else {
                $messages[] = $lang["error"];
            }
        } else {
            $result = create_upload_link($share_collection, $shareoptions);
            if (is_array($result)) {
                $keysgenerated = false;
                foreach ($result as $key => $sharekey) {
                    if ($sharekey === "") {
                        $messages[] = $lang["error_invalid_email"]  . (isset($shareoptions["emails"][$key]) ? " (" . $shareoptions["emails"][$key] . ")" : "");
                    } else {
                        $shareurl = $baseurl . "/?c=" . $share_collection . "&k=" . $sharekey;
                        $messages[] = "<a href='" . $shareurl . "'>" . $shareurl  . "</a>" . (isset($shareoptions["emails"][$key]) ? " (" . $shareoptions["emails"][$key] . ")" : "");
                        $keysgenerated = true;
                    }
                }
                if ($keysgenerated) {
                    array_unshift($messages, $lang["upload_shares_emailed"]);
                }
            } elseif (is_string($result)) {
                $messages[] = $result; // An error message was returned.
            }
        }
    }
}
$page_header = $editing ? $lang["title-upload-link-edit"] . ":  " . $uploadkey : $lang["title-upload-link-create"];

include "../include/header.php";
?>

<div class="BasicsBox">
    <h1>
        <?php
        echo escape($page_header);
        render_help_link("user/sharing-upload");
        ?>
    </h1>

    <?php
    if (count($messages) > 0) {
        echo "<div class='PageInformal'>" . implode("<br/>", $messages) . "</div>";
    }

    echo "<p><strong>" . escape($lang["warning-upload-link"]) . "</strong></p>";
    echo "<p>" . escape($lang["warning-upload-instructions"]) . "</p>";

    if (count($curshares) > 0) {
        echo "<p><a href='" . generateURL($baseurl_short . "pages/manage_external_shares.php", $cursharefltr) . "'>" . LINK_CARET . escape($lang["external_shares_view_existing"]) . "</a></p>";
    }
    ?>

    <form method=post id="shareuploadform" action="<?php echo generateURL($baseurl_short . "pages/share_upload.php", $cursharefltr); ?>" onsubmit="return CentralSpacePost(this,true);">
        <input type="hidden" name="deleteshare" id="deleteshare" value="">   
        <input type="hidden" name="submitted" id="submit" value="true">    
        <input type="hidden" name="uploadkey" id="uploadkey" value="<?php echo escape($uploadkey); ?>">
        <?php generateFormToken("shareuploadform"); ?>
        
        <div class="Question">
            <label><?php echo escape($lang["collectionname"]); ?></label>
            <div class="Fixed"><?php echo i18n_get_collection_name($collectiondata); ?></div>
            <div class="clearerleft"></div>
        </div>

        <?php
        if (count($validsharegroups) > 1) {
            render_dropdown_question($lang["property-user_group"], "usergroup", $validsharegroups, $shareusergroup, " class=\"stdwidth\"");
        } else {
            echo "<input type='hidden' name='usergroup' value='" . escape(isset($upload_link_usergroups[0]) ? $upload_link_usergroups[0] : $usergroup) . "'>";
        }
        ?>

        <div class="Question">
            <label><?php echo escape($lang["expires"]); ?></label>
            <input
                name="shareexpires"
                type=date
                class="stdwidth"
                min="<?php echo date("Y-m-d", time()); ?>"
                value="<?php
                if ($shareexpires != "") {
                    echo escape(substr($shareexpires, 0, 10));
                } else {
                    echo date("Y-m-d", time() + 60 * 60 * 24 * 7);
                } ?>"
            >
            </input>
            <div class="clearerleft"></div>
        </div>

        <?php
        render_share_password_question($sharepwd == "");

        if ($editing) { ?>
            <div class="QuestionSubmit">
                <input
                    name="submit"
                    type="submit"
                    value="<?php echo escape($lang["save"]); ?>"
                    onclick="<?php
                    if ($share_password_required) {
                        echo 'if (!enforceSharePassword(\'' . escape($lang['share-password-not-set']) . '\')) { return false; }; ';
                    } ?>
                        return CentralSpacePost(this.form,true);"
                />
            </div>
        <?php } else { ?>
            <h2 class="CollapsibleSectionHead collapsed" id="EmailUploadSectionHead"><?php echo escape($lang["action-email-upload-link"]); ?></h2>

            <div class="CollapsibleSection" id="EmailUploadSection" style="display:none;">
                <div class="Question">
                    <label for="message"><?php echo escape($lang["message"]); ?></label>
                    <textarea class="stdwidth" rows=6 cols=50 name="message" id="message"><?php echo escape($emailmessage); ?></textarea>
                    <div class="clearerleft"></div>
                </div>

                <div class="Question">
                    <label for="share_emails"><?php echo strip_tags_and_attributes($lang["upload_share_email_users"]); ?></label>
                    <input name="share_emails" id="share_emails" type="text" class="stdwidth"></input>
                    <div class="clearerleft"></div>
                </div>
            </div>

            <div class="QuestionSubmit">
                <input
                    name="submit"
                    type="submit"
                    value="<?php echo escape($lang["button-upload-link-create"]); ?>"
                    onclick="<?php
                    if ($share_password_required) {
                        echo 'if (!enforceSharePassword(\'' . escape($lang['share-password-not-set']) . '\')) { return false; }; ';
                    } ?>
                    return CentralSpacePost(this.form,true);"
                />
            </div>
        <?php } ?>
    </form>

    <script>
        jQuery('document').ready(function() {
            registerCollapsibleSections(false);
        });
    </script>
</div><!-- End of BasicsBox -->

<?php
include "../include/footer.php";