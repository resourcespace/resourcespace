<?php
include_once '../../../include/boot.php';
include_once '../../../include/authenticate.php';

if (!checkperm('u')) {
    header('HTTP/1.1 401 Unauthorized');
    die('Permission denied!');
}

include_once __DIR__ . '/../include/csv_functions.php';

// Init
$fd                  = 'user_' . $userref . '_csv_user_batch_uploaded_data';
$process_csv         = ('' !== getval('process_csv', '') && enforcePostRequest(false));
$user_group_selector = getval('user_group_selector', '');

// Uploaded file information
$csv_dir  = get_temp_dir() . DIRECTORY_SEPARATOR . 'csv_user_import_upload' . DIRECTORY_SEPARATOR . $session_hash;
$csv_file = $csv_dir . DIRECTORY_SEPARATOR  . 'csv_user_import.csv';

$messages = array();

include '../../../include/header.php';
?>
<div class="BasicsBox">
    <h1><?php echo escape($lang['csv_user_import']); ?></h1>

    <?php
    // CSV upload form
    if ((!isset($_FILES[$fd]) || 0 < $_FILES[$fd]['error']) && !$process_csv) {
        ?>
        <p><?php echo escape($lang['csv_user_import_intro']); ?></p>
        <ul>
            <?php
            $i = 1;
            while (isset($lang['csv_user_import_condition' . $i])) {
                ?>
                <li><?php echo escape($lang['csv_user_import_condition' . $i]); ?></li>
                <?php
                $i++;
            }
            ?>
        </ul>
        <form id="upload_csv_form" method="post" enctype="multipart/form-data" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>">
            <?php generateFormToken("upload_csv_form"); ?>
            <div class="Question">
                <label for="<?php echo $fd; ?>"><?php echo escape($lang['csv_user_import_upload_file']); ?></label>
                <input
                    id="<?php echo $fd; ?>"
                    type="file"
                    name="<?php echo $fd; ?>"
                    onchange="if(this.value == null || this.value == '') { jQuery('.file_selected').hide(); } else { jQuery('.file_selected').show(); }"
                >
            </div>
            <div class="Question file_selected" style="display: none;">
                <label for="user_group_selector"><?php echo escape($lang['property-user_group']); ?></label>
                <select id="user_group_selector" name="user_group_selector">
                    <?php
                    $usergroups = get_usergroups();
                    foreach ($usergroups as $usergroup) {
                        ?>
                        <option value="<?php echo $usergroup['ref']; ?>"><?php echo $usergroup['name']; ?></option>
                        <?php
                    }
                    ?>
                </select>
            </div>
            <div class="clearerleft"></div>
            <input id="submit" class="file_selected" type="submit" value="Next" style="display: none;">
        </form>
        <?php
    }
    // File has already been uploaded and validated, just process it
    elseif ($process_csv && file_exists($csv_file)) {
        ?>
        <p><?php echo escape($lang['csv_user_import_processing_file']); ?></p>
        <?php
        csv_user_import_process($csv_file, $user_group_selector, $messages, true);
        ?>
        <textarea rows="20" cols="100">
        <?php
        foreach ($messages as $message) {
            echo escape($message) . PHP_EOL;
        }
        ?>
        </textarea>
        <?php
    }
    // Validate submitted file
    else {
        if (check_valid_file_extension($_FILES[$fd], array("csv"))) {
            ?>
            <p>
                <a href="<?php echo $_SERVER['SCRIPT_NAME']; ?>" onclick="return CentralSpaceLoad(this, true);">
                    <?php echo LINK_CARET_BACK . escape($lang['back']); ?>
                </a>
            </p>
            <p>
                <b><?php echo escape($_FILES[$fd]['name']); ?></b>
            </p>

            <?php
            $validated = csv_user_import_process($_FILES[$fd]['tmp_name'], $user_group_selector, $messages, false);
            ?>

            <textarea rows="20" cols="100">
            <?php
            foreach ($messages as $message) {
                echo $message . PHP_EOL;
            }
            ?>
            </textarea>

            <?php
            if ($validated) {
                // We have a valid CSV, save it to a temporary location if not already created
                if (!file_exists($csv_dir)) {
                    mkdir($csv_dir, 0777, true);
                }

                if (
                    process_file_upload(
                        $_FILES[$fd],
                        new SplFileInfo($csv_file),
                        ['allow_extensions' => ['csv']]
                    )['success']
                ) {
                    ?>
                    <form method="post" action="<?php echo $_SERVER["SCRIPT_NAME"]; ?>">
                        <?php generateFormToken("csv_user_import"); ?>
                        <input type="hidden" id="process_csv"  name="process_csv" value="1">
                        <input type="hidden" id="user_group_selector"  name="user_group_selector" value="<?php echo escape($user_group_selector); ?>">
                        <input type="submit" name="process_csv" onClick="return CentralSpacePost(this, true);" value="Process CSV">
                    </form>
                    <?php
                } else {
                    ?>
                    <h2><?php echo escape($lang['csv_user_import_move_upload_file_failure']); ?></h2>
                    <?php
                }
            } else {
                ?>
                <h2><?php echo escape($lang['csv_user_import_error_found']); ?></h2>
                <?php
            }
        } else {
            ?>
            <p>
                <a href="<?php echo $_SERVER['SCRIPT_NAME']; ?>" onclick="return CentralSpaceLoad(this, true);"><?php echo LINK_CARET_BACK ?><?php echo escape($lang['back']); ?></a>
            </p>
            <p>
                <b><?php echo str_replace("%EXTENSIONS", ".csv", $lang["invalidextension_mustbe-extensions"]); ?></b>
            </p>
            <?php
        }
    }
    ?>
</div> <!-- end of BasicBox -->
<?php
include '../../../include/footer.php';