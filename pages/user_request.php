<?php
include "../include/boot.php";

if (!$allow_account_request) {
    exit($lang["error-permissiondenied"]);
}

$error = false;
$error_extra = "";
$completed = false;
$user_email = getval("email", "");

if (getval("save", "") != "") {
    # Check for required fields

    # Required fields (name, email) not set?
    $missing_fields = hook('replacemainrequired');
    if (!is_array($missing_fields)) {
        $missing_fields = array();
        if (getval("name", "") == "") {
            $missing_fields[] = [$lang["enter_a_full_name"], "name"];
        }

        if (getval("email", "") == "") {
            $missing_fields[] = [$lang["enter_an_email_address"], "email"];
        }

        if (
            $registration_group_select
            && getval("usergroup", 0, true) == 0
        ) {
            $missing_fields[] = [$lang["select_a_user_group"], "usergroup"];
        }
    }

    # Add custom fields
    $customContents = "";
    if (isset($custom_registration_fields)) {
        $custom = explode(",", $custom_registration_fields);

        # Required fields?
        if (isset($custom_registration_required)) {
            $required = explode(",", $custom_registration_required);
        }

        # Loop through custom fields
        for ($n = 0; $n < count($custom); $n++) {
            $custom_field_value = getval("custom" . $n, "");
            $custom_field_sub_value_list = "";

            for ($i = 1; $i <= 1000; $i++) {
                # Check if there are sub values, i.e. custom<n>_<n> form fields, for example a bunch of checkboxes if custom type is set to "5"
                $custom_field_sub_value = getval("custom" . $n . "_" . $i, "");

                if ($custom_field_sub_value == "") {
                    continue;
                }

                $custom_field_sub_value_list .= ($custom_field_sub_value_list == "" ? "" : ", ") . $custom_field_sub_value;  # we have found a sub value so append to the list
            }

            if ($custom_field_sub_value_list != "") {
                # We found sub values, append with list of all sub values found
                $customContents .= i18n_get_translated($custom[$n]) . ": " . i18n_get_translated($custom_field_sub_value_list) . "\n\n";
            } elseif ($custom_field_value != "") {
                # If no sub values found then treat as normal field, there is a value so append it
                $customContents .= i18n_get_translated($custom[$n]) . ": " . i18n_get_translated($custom_field_value) . "\n\n";
            } elseif (isset($required) && in_array($custom[$n], $required)) {
                # If the field was mandatory and a value or sub value(s) not set then we return false
                $missing_fields[] = [$custom[$n], "custom" . $n];
            }
        }
    }

    $spamcode = getval("antispamcode", "");
    $usercode = getval("antispam", "");
    $spamtime = getval("antispamtime", 0);

    if (!empty($missing_fields)) {
        $error = $lang["requiredfields"];
    }
    # Check the anti-spam time is recent
    elseif (getval("antispamtime", 0) < (time() - 180) ||  getval("antispamtime", 0) > time()) {
        $error = $lang["expiredantispam"];
    }
    # Check the anti-spam code is correct
    elseif (!hook('replaceantispam_check') && !verify_antispam($spamcode, $usercode, $spamtime)) {
        $error = $lang["requiredantispam"];
    }
    # Check the email is valid
    elseif (filter_var($user_email, FILTER_VALIDATE_EMAIL) === false) {
        $error = $lang["error_invalid_email"];
    }
    # Check that the e-mail address doesn't already exist in the system
    elseif (getval("login_opt_in", "") != "yes" && $user_registration_opt_in) {
        $error = $lang["error_user_registration_opt_in"];
    } else {
        # E-mail is unique

        if ($user_account_auto_creation) {
            # Automatically create a new user account
            $success = auto_create_user_account(md5($usercode . $spamtime));
            if ($success !== true) {
                // send an email about the user request
                $account_email_exists_notify = true; // Email to admins to explain account with existing email was requested.
                $success = email_user_request();
            }
        } else {
            $account_email_exists_notify = user_email_exists($user_email);
            $success = email_user_request();
        }

        if ($success !== true) {
            $error = $success;
        } else {
            $completed = true;
        }
    }
}
include "../include/header.php";
include "../include/login_background.php";
?>

<?php $header_img_src = get_header_image(); ?>

<div id="LoginHeader">
    <img src="<?php echo $header_img_src; ?>" class="LoginHeaderImg" alt="<?php echo $applicationname; ?>">
</div>

<a class="text-link" href="<?php echo $baseurl_short; ?>login.php">
    <i class="icon-arrow-left"></i><?php echo escape($lang["back_to_login"]); ?>
</a>

<?php if ($error) { ?>
    <div class="error-panel" tabindex="-1">
        <i class="icon-triangle-alert"></i>
        <div class="error-panel-details">
            <div class="error-panel-title"><?php echo strip_tags_and_attributes($error . ' ' . $error_extra); ?></div>
            <?php
            if (!empty($missing_fields)) {
                foreach ($missing_fields as $missing_field) {
                    ?>
                    <div>
                        <a href="#<?php echo $missing_field[1]; ?>"><?php echo $missing_field[0]; ?></a>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
    </div>
    <script>window.onload = function() { document.getElementById("error-panel").focus(); }</script>
<?php } ?>

<div id="login_box">
    <form method="post" id='mainform' action="<?php echo $baseurl_short?>pages/user_request.php">
        <div>
            <?php
            $name = getval("name", "");
            $name = is_array($name) ? "" : escape($name);

            $email = getval("email", "");
            $email = is_array($email) ? "" : escape($email);
            ?>

            <div class="field-text-only">
                <label><?php echo escape($lang["requestuserlogin"]); ?></label>
                <?php echo strip_tags_and_attributes(text("introtext")); ?>
            </div>

            <div class="field-input">
                <label for="name"><?php echo escape($lang["yourname"]); ?>
                    <div class="required"><?php echo escape($lang["required"]); ?></div>
                </label>
                <input type=text name="name" id="name" value="<?php echo escape($name); ?>" required>
            </div>

            <div class="field-input">
                <label for="email"><?php echo escape($lang["youremailaddress"]); ?>
                    <div class="required"><?php echo escape($lang["required"]); ?></div>
                </label>
                <input type=text name="email" id="email" value="<?php echo escape($email); ?>" required>
            </div>

            <?php
            # Add custom fields
            if (isset($custom_registration_fields)) {
                $custom = explode(",", $custom_registration_fields);

                if (isset($custom_registration_required)) {
                    $required = explode(",", $custom_registration_required);
                }

                for ($n = 0; $n < count($custom); $n++) {
                    $type = 1;

                    # Support different question types for the custom fields.
                    if (isset($custom_registration_types[$custom[$n]])) {
                        $type = $custom_registration_types[$custom[$n]];
                    }

                    if ($type == 4) {
                        # HTML type - just output the HTML.
                        $html = $custom_registration_html[$custom[$n]];
                        if (is_string($html)) {
                            echo $html;
                        } elseif (isset($html[$language])) {
                            echo $html[$language];
                        } elseif (isset($html[$defaultlanguage])) {
                            echo $html[$defaultlanguage];
                        }
                    } else {
                        ?>
                        <div class="field-input" id="Question<?php echo $n; ?>">
                            <label for="custom<?php echo $n; ?>"><?php echo escape(i18n_get_translated($custom[$n])); ?>
                                <?php if (isset($required) && in_array($custom[$n], $required)) { ?>
                                    <div class="required"><?php echo escape($lang["required"]); ?></div>
                                <?php } ?>
                            </label>

                            <?php if ($type == 1) {  # Normal text box ?>
                                <input type=text name="custom<?php echo $n; ?>" id="custom<?php echo $n; ?>" value="<?php echo escape(getval("custom" . $n, "")); ?>">
                            <?php }

                            if ($type == 2) { # Large text box ?>
                                <textarea name="custom<?php echo $n; ?>" id="custom<?php echo $n; ?>" rows="5"><?php echo escape(getval("custom" . $n, "")); ?></textarea>
                            <?php }

                            if ($type == 3) { # Drop down box ?>
                                <select name="custom<?php echo $n; ?>" id="custom<?php echo $n; ?>">
                                    <?php foreach ($custom_registration_options[$custom[$n]] as $option) { ?>
                                        <option><?php echo escape(i18n_get_translated($option)); ?></option>
                                    <?php } ?>
                                </select>
                            <?php }

                            if ($type == 5) { # checkbox
                                $i = 0;

                                # display each checkbox
                                foreach ($custom_registration_options[$custom[$n]] as $option) {      
                                    $i++;
                                    $option_exploded = explode(":", $option);

                                    if (count($option_exploded) == 2) {
                                        # there are two fields, the first indicates if checked by default, the second is the name
                                        $option_checked = ($option_exploded[0] == "1");
                                        $option_label = escape(i18n_get_translated(trim($option_exploded[1])));
                                    } else {
                                        # there are not two fields so treat the whole string as the name and set to unchecked
                                        $option_checked = false;
                                        $option_label = escape(i18n_get_translated(trim($option)));
                                    }

                                    # same format as all custom fields, but with a _<n> indicating sub field number
                                    $option_field_name = "custom" . $n . "_" . $i;
                                    ?>
                                    <div class="checkbox-set">
                                        <div class="checkbox-field">
                                            <label for="<?php echo escape($option_field_name); ?>" class="checkbox-label">
                                                <input name="<?php echo escape($option_field_name); ?>" id="<?php echo escape($option_field_name); ?>" type="checkbox" <?php echo $option_checked ? ' checked="checked"' : ''; ?> value="<?php echo escape($option_label); ?>">
                                                <span aria-hidden="true"></span>
                                                <div class="label-text"><?php echo escape($option_label); ?></div>
                                            </label>
                                        </div>
                                    </div>
                                    <?php
                                }
                            } ?>

                            <div class="clearerleft"></div>
                        </div>
                        <?php
                    }
                }
            }

            if ($registration_group_select) {
                # Allow users to select their own group
                $groups = get_registration_selectable_usergroups();
                ?>
                <div class="field-input">
                    <label for="usergroup"><?php echo escape($lang["group"]); ?>
                        <div class="required"><?php echo escape($lang["required"]); ?></div>
                    </label>
                    <select name="usergroup" id="usergroup">
                        <option value></option>
                        <?php for ($n = 0; $n < count($groups); $n++) { ?>
                            <option
                                value="<?php echo (int) $groups[$n]["ref"]; ?>"
                                <?php if ($groups[$n]["ref"] == getval("usergroup", 0, true)) { ?>
                                    selected
                                <?php } ?>
                            >
                                <?php echo escape($groups[$n]["name"]) ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>
                <?php
            }

            $userrequestcomment = getval("userrequestcomment", "");
            $userrequestcomment = is_array($userrequestcomment) ? "" : escape($userrequestcomment);
            ?>

            <div class="field-input">
                <label for="userrequestcomment"><?php echo escape($lang["userrequestcomment"]); ?></label>
                <textarea name="userrequestcomment" id="userrequestcomment"><?php echo escape($userrequestcomment); ?></textarea>
            </div>

            <?php if ($user_registration_opt_in) { ?>
                <div class="field-input">
                    <div class="checkbox-field">
                        <label for="login_opt_in" class="checkbox-label">
                            <input type="checkbox" id="login_opt_in" name="login_opt_in" value="yes">
                            <span aria-hidden="true"></span>
                            <div class="label-text"><?php echo strip_tags_and_attributes($lang['user_registration_opt_in_message'], array("a"), array("href","target")); ?></div>
                        </label>
                    </div>
                </div>
            <?php }

            if (!hook("replaceantispam")) {
                render_antispam_question();
            }
            ?>

            <script>
                <?php
                if ($completed) {
                    echo "jQuery(document).ready(function() {
                        window.location.href = '" . $baseurl . "/pages/done.php?text=user_request';
                    });";
                } ?>

                function submitForm() {
                    document.getElementById("user_submit").disabled = true;
                    CentralSpacePost(document.getElementById('mainform'),true,false,false,'CentralSpaceLogin');
                }
            </script>

            <div class="button">
                <input name='save' value='yes' type='hidden'>
                <input name="user_save" id="user_submit" onclick="submitForm()" type="button" value="<?php echo escape($lang["requestuserlogin"]); ?>" />
            </div>
        </div>
    </form>
</div>

</div><!-- Close CentralSpaceLogin -->

<div id="login-slideshow"></div>

<?php
include "../include/footer.php";