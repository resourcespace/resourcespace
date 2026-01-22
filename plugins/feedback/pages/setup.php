<?php
include "../../../include/boot.php";
include_once "../include/feedback_functions.php";
include "../../../include/authenticate.php";

if (!checkperm("a")) {
    exit("Permission denied.");
}

$plugin_name = 'feedback';
if (!in_array($plugin_name, $plugins)) {
    plugin_activate_for_setup($plugin_name);
}

# Make a folder for storing results
if (!is_dir($storagedir . "/feedback")) {
    // If it does not exist, create it.
    mkdir($storagedir . "/feedback", 0777);
}

# Load config
$config               = get_feedback_config(__DIR__ . '../config/config.php');
$feedback_questions   = $config['questions'];
$feedback_prompt_text = $config['prompt_text'];

if (!isset($feedback_prompt_text)) {
    $feedback_prompt_text = "";
}

if ((getval("submit", "") != "" || getval("add", "") != "") && enforcePostRequest(false)) {
    make_new_results_file($storagedir);

    $readfrom = 0;
    if (getval("delete_1", "") != "") {
        $readfrom++;
    } # Delete first question.
    $count = count($feedback_questions);
    $feedback_questions = [];

    for ($n = 0; $readfrom < $count; $n++) {
        # Deleting next question? Skip ahead
        if (getval("delete_" . ($readfrom), "") == "") {
            # add question to array
            $feedback_questions[$n] = [
                'text'    => getval("text_" . $readfrom, ""),
                'type'    => getval("type_" . $readfrom, 1),
                'options' => getval("options_" . $readfrom, ""),
            ];
        } else {
            $n--;
        }

        # Add new question after this one?
        if (getval("add_" . $readfrom, "") != "") {
            $n++;
            $feedback_questions[$n] = [
                'text'    => "",
                'type'    => 1,
                'options' => "",
            ];
        }
        $readfrom++;
    }

    $add = "";
    if (getval("add", "") != "") {
        # Add a new question
        $feedback_questions[$n] = [
            'text'    => "",
            'type'    => 1,
            'options' => "",
        ];
        $add = "#add";
    }
    $config['prompt_text'] = getval('feedback_prompt_text', '');
    $config['questions'] = $feedback_questions;
    update_feedback_fields($feedback_questions);

    set_plugin_config($plugin_name, $config);
    redirect("plugins/feedback/pages/setup.php?nc=" . time() . $add);
    exit();
}

include "../../../include/header.php";
?>

<div class="BasicsBox"> 
    <h1><?php echo escape($lang["feedback_user feedback_configuration"]); ?></h1>
    <form id="form1" name="form1" method="post" action="">
        <?php generateFormToken("form1"); ?>
        <p>
            <?php echo escape($lang["feedback_pop-up_prompt_box_text"]); ?>
            <br />
            <textarea rows=6 cols=50 style="width:600px;" name="feedback_prompt_text"><?php echo $feedback_prompt_text; ?></textarea>
        </p>
        <h2><?php echo escape($lang["feedback_questions"]); ?></h2>
        <hr />  

        <?php for ($n = 0; $n < count($feedback_questions); $n++) { ?>
            <p>
                <?php echo escape($lang["feedback_type"]); ?>
                <select name="type_<?php echo $n?>" style="width:150px;">
                    <option value="1" <?php echo $feedback_questions[$n]["type"] == 1 ? "selected" : ""; ?>>
                        <?php echo escape($lang["feedback_small_text_field"]); ?>
                    </option>
                    <option value="2" <?php echo $feedback_questions[$n]["type"] == 2 ? "selected" : ""; ?>>
                        <?php echo escape($lang["feedback_large_text_field"]); ?>
                    </option>
                    <option value="3" <?php echo $feedback_questions[$n]["type"] == 3 ? "selected" : ""; ?>>
                        <?php echo escape($lang["feedback_list-single_selection"]); ?>
                    </option>
                    <option value="5" <?php echo $feedback_questions[$n]["type"] == 5 ? "selected" : ""; ?>>
                        <?php echo escape($lang["feedback_list-multiple_selection"]); ?>
                    </option>
                    <option value="4" <?php echo $feedback_questions[$n]["type"] == 4 ? "selected" : ""; ?>>
                        <?php echo escape($lang["feedback_label"]); ?>
                    </option>
                </select>
                <input type="checkbox" name="delete_<?php echo $n; ?>" value="yes"><?php echo escape($lang["feedback_delete_this_question"]); ?>
                <input type="checkbox" name="add_<?php echo $n; ?>" value="yes"><?php echo escape($lang["feedback_add_new_question_after"]); ?>
            </p>
            <p>
                <?php echo escape($lang["feedback_text-html"]); ?><br/>
                <textarea rows=3 cols=50 style="width:600px;" name="text_<?php echo $n; ?>"><?php echo escape($feedback_questions[$n]["text"]); ?></textarea>
            </p>
            <p><?php echo escape($lang["feedback_options-comma_separated"]); ?>
                <br />
                <textarea rows=2 cols=50 style="width:600px;" name="options_<?php echo $n?>"><?php echo escape($feedback_questions[$n]["options"]); ?></textarea>
            </p>
            <hr />
        <?php } ?>

        <br />
        <br />
        <a name="add"></a>
        <input type="submit" name="add" value="<?php echo escape($lang["feedback_add_new_field"]); ?>">   
        <input type="submit" name="submit" value="<?php echo escape($lang["save"]); ?>">   
        <br/><br/>
        <p>
            <?php echo LINK_CARET_BACK ?>
            <a onclick="return CentralSpaceLoad(this,true);" href="<?php echo $baseurl_short?>pages/team/team_plugins.php">
                <?php echo escape($lang["feedback_back_to_plugin_manager"]); ?>
            </a>
        </p>
    </form>
</div>

<?php include "../../../include/footer.php";
