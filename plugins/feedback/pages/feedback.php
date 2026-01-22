<?php
include "../../../include/boot.php";
include_once "../include/feedback_functions.php";

if (array_key_exists("user", $_COOKIE)) {
    # Check to see if this user is logged in.
    $session_hash = $_COOKIE["user"];
    $loggedin = ps_value("SELECT count(*) value FROM user WHERE session = ? and approved = 1 and timestampdiff(second, last_active, now()) < (30*60)", array("s", $session_hash), 0);

    if ($loggedin > 0 || $session_hash == "|") { // Also checks for dummy cookie used in external authentication
        # User is logged in. Proceed to full authentication.
        include "../../../include/authenticate.php";
    }
}

# Make a folder for this
if (!is_dir($storagedir . "/feedback")) {
    // If it does not exist, create it.
    mkdir($storagedir . "/feedback", 0777);
}

# Load config
$config               = get_feedback_config(__DIR__ . '/../config/config.php');
$feedback_questions   = $config['questions'];
$feedback_prompt_text = $config['prompt_text'];

if (!isset($userref)) {
    # User is not logged in. Fetch username from posted form value.
    $username = getval("username", "");
    $usergroupname = "(Not logged in)";
    $userfullname = "";
    $anonymous_login = $username;
    $pagename = "terms";
    $plugins = array();
}

$error = "";
$errorfields = array();
$sent = false;

if (getval("send", "") != "" && enforcePostRequest(false)) {
    //Initialize array used to write to database
    $feebackData = [];

    debug("feedback.php: Sending user survey...");
    $csvheaders = "\"date\"";
    $csvline = "\"" . date("Y-m-d") . "\"";
    $message = "Date: " . date("Y-m-d") . "\n";

    // Add intial values to the data array
    $feebackData['date'] = date("Y-m-d");
    $feebackData['user'] = $username;

    debug("feedback.php: count(\$feedback_questions) = " . count($feedback_questions));

    for ($n = 0; $n < count($feedback_questions); $n++) {
        $type = $feedback_questions[$n]["type"];
        debug("feedback.php: \$type = {$type}");

        if ($type != 4) { # Do not run for labels
            $value = getval("question_" . $n, "");

            # Check required fields
            if ($type == 3 && trim($value) == "") {
                $error = $lang["requiredfields"];
                $errorfields[] = $n;
            }

            if ($type == 5) {
                # Multi select: contruct value from options
                $s = explode(",", $feedback_questions[$n]["options"]);
                $value = "";
                for ($m = 0; $m < count($s); $m++) {
                    if (getval("question_" . $n . "_" . $m, "") != "") { # Option is selected, add to value
                        if ($value != "") {
                            $value .= ",";
                        }
                        $value .= $s[$m];
                    }
                }
            }

            # Append to CSV line
            if ($csvline != "") {
                $csvline .= ",";
            }

            if ($csvheaders != "") {
                $csvheaders .= ",";
            }

            $csvline .= "\"" . str_replace("\"", "'", $value) . "\"";
            debug("feedback.php: \$csvline = {$csvline}");
            $csvheaders .= "\"" . str_replace("\"", "'", str_replace("\n", "", $feedback_questions[$n]['text'])) . "\"";

            if ($value != "") {
                $message .= $feedback_questions[$n]['text'] . ": \n" . $value . "\n\n";
            }

            $feebackData[$feedback_questions[$n]['text']] = $value;
        }
    }

    # Append user name and group to CSV file
    $message = "\n\nUser: " . $username . "\nUsergroup: " . $usergroupname . "\n" . $message;
    $csvline = "\"$username\",\"$usergroupname\"," . $csvline;
    $csvheaders = "\"username\",\"usergroupname\"," . $csvheaders;

    if ($error == "") {
        # Write results.
        $sent = true;
        $f = fopen($storagedir . "/feedback/" . get_feedback_results_file($storagedir . '/feedback/', 'results', false), "a+b");

        # avoid writing headers again
        $line = file($storagedir . '/feedback/' . get_feedback_results_file($storagedir . '/feedback/', 'results', false));

        if (isset($line[0])) {
            $line = $line[0];
        }

        if ($line == $csvheaders . "\n") {
            $csvheaders = "";
        } else {
            $csvheaders = $csvheaders . "\n";
        }

        fwrite($f, $csvheaders . file_get_contents($storagedir . '/feedback/' . get_feedback_results_file($storagedir . '/feedback/', 'results', false)) . $csvline . "\n");
        fclose($f);

        # install email template
        $result = ps_query("SELECT " . columns_in("site_text") . " FROM site_text WHERE page='all' AND name = 'emailfeedback'");
        if (count($result) == 0) {
            $email_text = "[img_headerlogo][message] [text_footer]";
            $wait = ps_query('INSERT INTO site_text (page,name,text,language) VALUES ("all","emailfeedback",?,"en")', array("s", $email_text));
        }

        debug("feedback.php: Send form results");
        $templatevars['message'] = $message . "Survey results attached\n\n";
        $csvfile = $storagedir . "/feedback/" . get_feedback_results_file($storagedir . '/feedback/', 'results', false);
        send_mail($email_notify, $username . " has submitted feedback for " . $applicationname, $message, "", "", "emailfeedback", $templatevars, "", "", "", ["feedback.csv" => $csvfile]);

        save_feedback_data($feebackData);
    }
}

include "../../../include/header.php";
?>
<style>
h2 {font-size:18px;}
<?php if (!isset($userref)) { ?>
#SearchBox {display:none;}
<?php } ?>
</style>

<div class="BasicsBox"> 
    <h2>&nbsp;</h2>
    <h1><?php echo escape($lang["feedback_user_survey"]); ?></h1>

    <?php if ($sent) { ?>
        <p><?php echo escape($lang["feedback_thank_you"]); ?></p>
    <?php } else { ?>
        <form method=post action="<?php echo $baseurl_short?>plugins/feedback/pages/feedback.php">
            <?php
            generateFormToken("feedback");

            if ($error) {
                ?>
                <div class="FormError">!! <?php echo $error ?> !!</div><br />
                <?php
            }

            for ($n = 0; $n < count($feedback_questions); $n++) {
                $type = $feedback_questions[$n]["type"];
                $text = $feedback_questions[$n]["text"];

                if ($type == 4) {
                    # Label type - just output the HTML.
                    echo $feedback_questions[$n]["text"];
                } else {
                    if (in_array($n, $errorfields)) {
                        $text = "<strong style='color:red;'>" . $text . "</strong>";
                    } # Highlight fields with errors.
                    ?>
                    <div class="Question" style="border-top:none;">
                        <label style="width:250px;padding-right:5px;" for="question_<?php echo $n?>"><?php echo $text;?></label>
                            <?php
                            if ($type == 1) {  # Normal text box ?>
                                <input type=text name="question_<?php echo $n?>" id="question_<?php echo $n?>" class="stdwidth" value="<?php echo escape(getval("question_" . $n, ""))?>">
                                <?php
                            }

                            if ($type == 2) { # Large text box ?>
                                <textarea name="question_<?php echo $n?>" id="question_<?php echo $n?>" class="stdwidth" rows="5"><?php echo escape(getval("question_" . $n, ""))?></textarea>
                                <?php
                            }

                            if ($type == 3) { # Single Select List ?>
                                <table cellpadding=2 cellspacing=0>
                                    <?php foreach (explode(",", $feedback_questions[$n]["options"]) as $option) { ?>
                                        <tr>
                                            <td width="1">
                                                <input type="radio"
                                                    name="question_<?php echo $n?>" 
                                                    value="<?php echo escape($option);?>"
                                                    <?php echo ($option == getval("question_" . $n, "")) ? 'checked' : ''; ?>
                                                >
                                            </td>
                                            <td><?php echo escape($option); ?></td>
                                        </tr>
                                        <?php
                                    }
                                    ?>
                                </table>
                                <?php
                            }

                            if ($type == 5) { # Multi Select List ?>
                                <table cellpadding=2 cellspacing=0>
                                    <?php
                                    $opt = 0;
                                    foreach (explode(",", $feedback_questions[$n]["options"]) as $option) { ?>
                                        <tr>
                                            <td width="1">
                                                <input type="checkbox"
                                                    name="question_<?php echo $n?>_<?php echo $opt; ?>"
                                                    value="yes"
                                                    <?php echo (getval("question_" . $n . "_" . $opt, "") != "") ? 'checked' : ''; ?>
                                                >
                                            </td>
                                            <td><?php echo escape($option); ?></td>
                                        </tr>
                                        <?php
                                        $opt++;
                                    }
                                    ?>
                                </table>
                            <?php } ?>
                        <div class="clearerleft"></div>
                    </div>
                    <?php
                }
            }

            if (!isset($userref)) {
                # User is not logged in. Ask them for their user name
                ?>
                <br>
                <br>
                <div class="Question" style="border-top:none;">
                    <label style="width:250px;padding-right:5px;" for="username"><?php echo escape($lang["feedback_your_full_name"]); ?></label>
                    <input type=text name="username" id="username" class="stdwidth" value="<?php echo escape(getval("username", ""))?>">
                    <div class="clearerleft"></div>
                </div>
                <?php
            }
            ?>

            <div class="QuestionSubmit">
                <?php if ($error) { ?>
                    <div class="FormError">!! <?php echo $error ?> !!</div><br />
                <?php } ?>
                <label style="width:250px;" for="buttons"> </label>         
                <input name="send" type="submit" onclick="return CentralSpacePost(this,true);" value="<?php echo escape($lang["send"]); ?>" />
            </div>
        </form>
    <?php } ?>
</div>

<?php include "../../../include/footer.php"; ?>
