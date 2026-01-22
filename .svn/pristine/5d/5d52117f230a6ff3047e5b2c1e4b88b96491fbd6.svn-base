<?php

/**
 * Export data page (part of Team Center)
 */

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("a")) {
    exit("Permission denied.");
}

include "../../include/reporting_functions.php";

set_time_limit(0);
$type = getval("type", "");

if ($type != "" && enforcePostRequest(false)) {
    if ($type == "sql") {
        $param = "";
        $extension = "sql";
    }
    if ($type == "xml") {
        $param = "--xml";
        $extension = "xml";
    }

    # Check for mysqldump at configured location
    $path = $mysql_bin_path . "/mysqldump";
    if (!file_exists($path)) {
        $path .= ".exe";
    } # Try windows.
    if (!file_exists($path)) {
        exit("Error: mysqldump not found at '$mysql_bin_path' - please check config.php");
    }

    # Add options to ignore index tables, which are very large and are easily regenerated (using tools/reindex.php)
    $param .= " --ignore-table=$mysql_db.resource_keyword --ignore-table=$mysql_db.keyword";

    # Send them the export.
    header("Content-type: application/octet-stream");
    header("Content-disposition: attachment; filename=" . $mysql_db . "_" . date("d_M_Y_h-iA") . "." . $extension . "");
    passthru('"' . $path . '" -h ' . $mysql_server . ' -u ' . $mysql_username . ($mysql_password == '' ? '' : ' -p' . $mysql_password) . ' ' . $param . ' ' . $mysql_db);

    log_activity($lang["exportdata"], LOG_CODE_SYSTEM);

    exit();
}
include "../../include/header.php";
?>

<div class="BasicsBox"> 
    <h1><?php echo escape($lang["exportdata"]) ?></h1>
  
    <form method="post" action="<?php echo $baseurl_short?>pages/team/team_export.php">
        <?php generateFormToken("team_export"); ?>
        <div class="Question">
            <label for="type"><?php echo escape($lang["exporttype"])?></label>
            <select id="type" name="type" class="stdwidth">
                <option value="sql">mysqldump - SQL</option>
                <option value="xml">mysqldump - XML</option>
            </select>
            <div class="clearerleft"></div>
        </div>

        <div class="QuestionSubmit">        
            <input name="save" type="submit" value="<?php echo escape($lang["exportdata"])?>" />
        </div>
    </form>
</div>

<?php
include "../../include/footer.php";
?>
