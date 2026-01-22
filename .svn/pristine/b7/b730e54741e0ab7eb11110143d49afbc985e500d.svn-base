<?php
include_once "../include/boot.php";
include_once "../include/authenticate.php";
include_once "../include/research_functions.php";
include_once "../include/request_functions.php";

$name        = getval('name', '');
$email       = getval('email', '');
$description = getval('description', '');
$save        = getval("save", "") != "" && enforcePostRequest(false);
$processed_rr_cfields = process_custom_fields_submission($custom_researchrequest_fields, $save, []);

if ($save) {
    $errors = false;
    if ($name == "") {
        $errors = true;
        $error_name = true;
    }

    if ($description == "") {
        $errors = true;
        $error_description = true;
    }

    if (isset($anonymous_login) && $anonymous_login == $username) {
        if ($email == "") {
            $errors = true;
            $error_email = true;
        }

        $spamcode = getval("antispamcode", "");
        $usercode = getval("antispam", "");
        $spamtime = getval("antispamtime", 0);

        if ($spamtime < (time() - 180) || $spamtime > time()) {
            $errors = true;
            $antispam_error = $lang["expiredantispam"];
        } elseif (!hook('replaceantispam_check') && !verify_antispam($spamcode, $usercode, $spamtime)) {
            $errors = true;
            $antispam_error = $lang["requiredantispam"];
        }
    }

    if (count_errors($processed_rr_cfields) > 0) {
        $errors = true;
    }

    if (!$errors) {
        daily_stat("New research request", 0);
        send_research_request($processed_rr_cfields);
        redirect($baseurl_short . "pages/done.php?text=research_request");
    }
}

include "../include/header.php";
?>

<div class="BasicsBox">
    <h1><?php echo escape($lang["researchrequest"]); ?></h1>
    <p class="tight">
        <?php
        echo strip_paragraph_tags(strip_tags_and_attributes(text("introtext")));
        render_help_link("resourceadmin/user-research-requests");
        ?>
    </p>
    <p class="greyText noPadding">* <?php echo escape($lang["requiredfield"]); ?></p>

    <form method="post" action="<?php echo $baseurl_short?>pages/research_request.php">
        <?php
        generateFormToken("research_request");

        if (getval("assign", "") != "") { ?>
            <div class="Question">
                <label><?php echo escape($lang["requestasuser"]); ?></label>
                <select name="as_user" class="stdwidth">
                    <?php
                    $users = get_users(0, "", "u.username", true);
                    for ($n = 0; $n < count($users); $n++) { ?>
                        <option value="<?php echo $users[$n]["ref"]; ?>">
                            <?php echo $users[$n]["username"] . " - " . $users[$n]["fullname"] . " ("  . $users[$n]["email"] . ")"?>
                        </option>
                        <?php
                    }
                    ?>
                </select>
                <div class="clearerleft"></div>
            </div>
        <?php } ?>

        <div class="Question">
            <label for="name"><?php echo escape($lang["nameofproject"]); ?> *</label>
            <input id="name" name="name" class="stdwidth" type="text" value="<?php echo escape($name) ?>">
            <div class="clearerleft"></div>
            <?php if (isset($error_name)) { ?>
                <div class="FormError"><?php echo escape($lang["noprojectname"]); ?></div>
            <?php } ?>
        </div>

        <div class="Question">
            <label for="description">
                <?php echo escape($lang["descriptionofproject"]); ?> *<br/>
                <span class="OxColourPale"><?php echo escape($lang["descriptionofprojecteg"]); ?></span>
            </label>
            <textarea id="description" rows="5" cols="50" name="description" class="stdwidth"><?php echo escape($description); ?></textarea>
            <div class="clearerleft"></div>
            <?php if (isset($error_description)) { ?>
                <div class="FormError"><?php echo escape($lang["noprojectdescription"]); ?></div>
            <?php } ?>
        </div>

        <div class="Question">
            <label for="deadline"><?php echo escape($lang["deadline"]); ?></label>
            <select id="deadline" name="deadline" class="stdwidth">
                <option value=""><?php echo escape($lang["nodeadline"]); ?></option>
                <?php
                for ($n = 0; $n <= 150; $n++) {
                    $date = time() + (60 * 60 * 24 * $n);
                    $d    = date("D", $date);
                    $option_class = '';
                    if (($d == "Sun") || ($d == "Sat")) {
                        $option_class = 'optionWeekend';
                    } ?>
                    <option class="<?php echo $option_class ?>" value="<?php echo date("Y-m-d", $date)?>">
                        <?php echo nicedate(date("Y-m-d", $date), false, true); ?>
                    </option>
                    <?php
                } ?>
            </select>
            <div class="clearerleft"></div>
        </div>

        <?php if (isset($anonymous_login) && $anonymous_login == $username) {
            # Anonymous access - we need to collect their e-mail address.
            ?>
            <div class="Question" id="email">
                <label for="email"><?php echo escape($lang["email"]); ?></label>
                <input id="email" name="email" class="stdwidth" type="text" maxlength="200" value="<?php echo escape($email) ?>">
                <div class="clearerleft"></div>
                <?php if (isset($error_email)) { ?>
                    <div class="FormError"><?php echo escape($lang["setup-emailerr"]); ?></div>
                <?php } ?>
            </div>
        <?php } ?>

        <div class="Question" id="contacttelephone">
            <label for="contact"><?php echo escape($lang["contacttelephone"]); ?></label>
            <input id="contact" name="contact" class="stdwidth" type="text" maxlength="100" value="<?php echo escape(getval("contact", "")) ?>">
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="finaluse">
                <?php echo escape($lang["finaluse"]); ?>
                <br/>
                <span class="OxColourPale"><?php echo escape($lang["finaluseeg"]); ?></span>
            </label>
            <input id="finaluse" name="finaluse" class="stdwidth" type="text" value="<?php echo escape(getval("finaluse", "")) ?>">
            <div class="clearerleft"></div>
        </div>

        <div class="Question" id="resourcetype">
            <label><?php echo escape($lang["resourcetype"]); ?></label>
            <div class="tickset lineup">
                <?php
                $types = get_resource_types();
                for ($n = 0; $n < count($types); $n++) { ?>
                    <div class="Inline">
                        <input id="TickBox" type="checkbox" name="resource<?php echo $types[$n]["ref"]; ?>" value="yes" checked>
                        &nbsp;<?php echo escape($types[$n]["name"])?>
                    </div>
                <?php } ?>
            </div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question" id="noresourcesrequired">
            <label for="noresources"><?php echo escape($lang["noresourcesrequired"]); ?></label>
            <input id="noresources" name="noresources" class="shrtwidth" type="text" value="<?php echo escape(getval("noresources", ""))?>">
            <div class="clearerleft"></div>
        </div>

        <div class="Question" id="shaperequired">
            <label for="shape"><?php echo escape($lang["shaperequired"]); ?></label>
            <select id="shape" name="shape" class="stdwidth">
                <option><?php echo escape($lang["portrait"]); ?></option>
                <option><?php echo escape($lang["landscape"]); ?></option>
                <option selected><?php echo escape($lang["either"]); ?></option>
            </select>
            <div class="clearerleft"></div>
        </div>

        <?php
        render_custom_fields($processed_rr_cfields);

        // Legacy plugins
        if (file_exists(__DIR__ . "/../plugins/research_request.php")) {
            include __DIR__ . "/../plugins/research_request.php";
        }

        if (isset($anonymous_login) && $anonymous_login == $username && !hook("replaceantispam")) {
            if (isset($antispam_error)) {
                error_alert($antispam_error, false);
            }
            render_antispam_question();
        }
        ?>

        <div class="QuestionSubmit">      
            <input name="save" type="submit" value="<?php echo escape($lang["sendrequest"]); ?>" />
        </div>
    </form>
</div>

<?php
include "../include/footer.php";