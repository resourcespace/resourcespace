<?php
include "../include/boot.php";

$ref = getval("ref", "", true);
$k = getval("k", "");

if ($k == "" || !check_access_key_collection($ref, $k)) {
    include "../include/authenticate.php";
}

if (!checkperm('q')) {
    exit("<br /><br /><strong>" . escape($lang["error-permissiondenied"]) . "</strong>");
}

include "../include/request_functions.php";

if ($k != "" && (!isset($internal_share_access) || !$internal_share_access) && $prevent_external_requests) {
    echo "<script>window.location = '" .  $baseurl . "/login.php?error="  . (($allow_account_request) ? "signin_required_request_account" : "signin_required") . "'</script>";
    exit();
}

if ($k == "" && isset($anonymous_login) && $username == $anonymous_login) {
    $user_is_anon = true;
} else {
    $user_is_anon = false;
}

$use_antispam = ($k !== '' || $user_is_anon);

if ($ref == "" && isset($usercollection)) {
    $ref = $usercollection;
}

$cinfo = get_collection($ref);
$error = false;

# Determine the minimum access across all of the resources in the collection being requested
$collection_request_min_access = collection_min_access($ref);

# Check if any X?_ permissions are blocking sizes
$resource_types = get_resource_types();

foreach ($resource_types as $type) {
    if (checkperm("X" . $type["ref"] . "_")) {
        $collection_request_min_access = max($collection_request_min_access, 1);
        break;
    }
    foreach (get_all_image_sizes() as $size) {
        if (checkperm("X" . $type["ref"] . "_" . $size["id"])) {
            $collection_request_min_access = max($collection_request_min_access, 1);
            break;
        }
    }
}

# Prevent "request all" resources in a collection if the user has access to all of its resources
if ($collection_request_min_access == 0) {
    exit("<br /><br /><strong>" . escape($lang["error-cant-request-all-are-open"]) . "</strong>");
}

if (getval("save", "") != "" && enforcePostRequest(false)) {
    $antispamcode = getval('antispamcode', '');
    $antispam = getval('antispam', '');
    $antispamtime = getval('antispamtime', 0);

    // Check the anti-spam time is recent
    if ($use_antispam && ($antispamtime < (time() - 180) ||  $antispamtime > time())) {
        $result = false;
        $error = $lang["expiredantispam"];
    }
   // Check the anti-spam code is correct
    elseif ($use_antispam && !verify_antispam($antispamcode, $antispam, $antispamtime)) {
        $result = false;
        $error = $lang["requiredantispam"];
    } elseif ($k != "" || $userrequestmode == 0 || $user_is_anon) {
        if (($k != "" || $user_is_anon) && (getval("fullname", "") == "" || getval("email", "") == "")) {
            $result = false; # Required fields not completed.
        } else {
            # Request mode 0 : Simply e-mail the request.
            $result = email_collection_request($ref, getval("request", ""), getval("email", ""));
        }
    } else {
        # Request mode 1 : "Managed" mode via Manage Requests / Orders
        $result = managed_collection_request($ref, getval("request", ""));
    }

    if ($result === false) {
        $error = $lang["requiredfields-general"];
    } else {
        ?>
        <script>
            CentralSpaceLoad("<?php echo generateURL("{$baseurl_short}pages/done.php", ['text' => 'resource_request', 'k' => $k]); ?>", true);
        </script>
        <?php
    }
}

include "../include/header.php";
?>

<div class="BasicsBox">
    <?php
    $backlink = getval("backlink", "");
    if ($backlink != "") {
        ?>
        <p>
            <a href='<?php echo escape(rawurldecode($backlink)); ?>'>
                <?php echo LINK_CARET_BACK . escape($lang['back']); ?>
            </a>
        </p>
        <?php
    }
    ?>
        
    <h1>
        <?php
        echo escape($lang["requestcollection"]);
        render_help_link("resourceadmin/user-resource-requests");
        ?>
    </h1>

    <p><?php echo escape(text("introtext"))?></p>

    <form method="post" onsubmit="return CentralSpacePost(this,true);" action="<?php echo $baseurl_short?>pages/collection_request.php">  
        <?php generateFormToken("collection_request"); ?>
        <input type=hidden name=ref value="<?php echo escape($ref); ?>">
        <input type=hidden name="k" value="<?php echo escape($k); ?>">
        
        <div class="Question">
            <label><?php echo escape($lang["collectionname"]); ?></label>
            <div class="Fixed"><?php echo escape(i18n_get_collection_name($cinfo)); ?></div>
            <div class="clearerleft"></div>
        </div>

        <?php
        # Only ask for user details if this is an external share. Otherwise this is already known from the user record.
        if ($k != "" || $user_is_anon) {
            ?>
            <div class="Question">
                <label><?php echo escape($lang["fullname"]); ?> <sup>*</sup></label>
                <input type="hidden" name="fullname_label" value="<?php echo escape($lang["fullname"]); ?>">
                <input name="fullname" class="stdwidth" type="text" value="<?php echo escape(getval("fullname", "")); ?>">
                <div class="clearerleft"></div>
            </div>
        
            <div class="Question">
                <label><?php echo escape($lang["emailaddress"]); ?> <sup>*</sup></label>
                <input type="hidden" name="email_label" value="<?php echo escape($lang["emailaddress"]); ?>">
                <input name="email" class="stdwidth" type="text" value="<?php echo escape(getval("email", "")); ?>">
                <div class="clearerleft"></div>
            </div>

            <div class="Question">
                <label><?php echo escape($lang["contacttelephone"]); ?></label>
                <input name="contact" class="stdwidth" type="text" value="<?php echo escape(getval("contact", "")); ?>">
                <input type="hidden" name="contact_label" value="<?php echo escape($lang["contacttelephone"]); ?>">
                <div class="clearerleft"></div>
            </div>
            <?php
        } ?>
        
        <div class="Question">
            <label for="requestreason">
                <?php
                echo escape($lang["requestreason"]);

                if ($resource_request_reason_required) {
                    ?>
                    <sup>*</sup>
                    <?php
                }
                ?>
            </label>
            <textarea class="stdwidth" name="request" id="request" rows=5 cols=50><?php echo escape(getval("request", "")); ?></textarea>
            <div class="clearerleft"></div>
        </div>

        <?php # Add custom fields
        if (isset($custom_request_fields)) {
            $custom = explode(",", $custom_request_fields);
            $required = explode(",", $custom_request_required);

            for ($n = 0; $n < count($custom); $n++) {
                $type = 1;

                # Support different question types for the custom fields.
                if (isset($custom_request_types[$custom[$n]])) {
                    $type = $custom_request_types[$custom[$n]];
                }

                if ($type == 4) {
                    # HTML type - just output the HTML.
                    echo $custom_request_html[$custom[$n]];
                } else {
                    ?>
                    <div class="Question">
                        <label for="custom<?php echo $n?>">
                            <?php
                            echo escape(i18n_get_translated($custom[$n]));
                            if (in_array($custom[$n], $required)) {
                                ?>
                                <sup>*</sup>
                                <?php
                            }
                            ?>
                        </label>
                        
                        <?php if ($type == 1) {  # Normal text box ?>
                            <input type=text name="custom<?php echo $n; ?>" id="custom<?php echo $n; ?>" class="stdwidth" value="<?php echo escape(getval("custom" . $n, "")); ?>">
                        <?php } ?>

                        <?php if ($type == 2) { # Large text box ?>
                            <textarea name="custom<?php echo $n; ?>" id="custom<?php echo $n; ?>" class="stdwidth" rows="5"><?php echo escape(getval("custom" . $n, "")); ?></textarea>
                        <?php } ?>

                        <?php if ($type == 3) { # Drop down box ?>
                            <select name="custom<?php echo $n; ?>" id="custom<?php echo $n; ?>" class="stdwidth">
                                <?php foreach ($custom_request_options[$custom[$n]] as $option) {
                                    $val = i18n_get_translated($option);
                                    ?>
                                    <option <?php echo (getval("custom" . $n, "") == $val) ? " selected" : ''; ?>>
                                        <?php echo escape(i18n_get_translated($option)); ?>
                                    </option>
                                <?php } ?>
                            </select>
                        <?php } ?>
                        
                        <div class="clearerleft"></div>
                    </div>
                    <?php
                }
            }
        }

        if ($use_antispam) {
            render_antispam_question();
        }
        ?>

        <div class="QuestionSubmit">
            <?php if ($error) { ?>
                <div class="FormError">!! <?php echo escape($error); ?> !!</div>
                <?php
            } ?>         
            <input name="cancel" type="button" value="<?php echo escape($lang["cancel"]); ?>" onclick="document.location='<?php
                echo generateURL("{$baseurl_short}pages/search.php", ['search' => "!collection{$ref}"]);
            ?>';"/>&nbsp;
            <input name="save" value="true" type="hidden" />
            <input type="submit" value="<?php echo escape($lang["requestcollection"]); ?>" />
        </div>
    </form>
</div>

<?php
include "../include/footer.php";
?>
