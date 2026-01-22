<?php
include "../../../include/boot.php";

include "../../../include/authenticate.php";

# Check access
if (!checkperm("a")) {
    exit("Access denied");
} # Should never arrive at this page without admin access



if (getval("submitted", "") != "") {
    // Allow longer to execute
    set_time_limit(60 * 60 * 2);

    // Delete all files.
    $folders = scandir($storagedir);
    foreach ($folders as $folder) {
        if ($folder != "." && $folder != "..") {
            // Recursively delete, ignoring storagedir and slideshow folders.
            $slideshow_dir = substr($homeanim_folder, strrpos($homeanim_folder, "/") + 1);
            rcRmdir(
                $storagedir . "/" . $folder,
                array($storagedir . "/system", $storagedir . "/system/" . $slideshow_dir)
            );
        }
    }

    // It's unlikely we have permission to drop the whole DB so we drop the tables one by one.
    // Omit user and usergroup table so user is still logged in.
    $tables = ps_query("show tables");
    foreach ($tables as $table) {
        $table = (array_values($table)[0]); # Get table name
        if ($table != "user" && $table != "usergroup") {
            ps_query("drop table " . $table, array(), false, -1, false);
        }
    }

    // Recreate tables
    check_db_structs();
    set_sysvar(SYSVAR_CURRENT_UPGRADE_LEVEL, SYSTEM_UPGRADE_LEVEL);
    // Back to login screen
    sleep(5); // Wait for any background DB creation to finish.
    // Start the resource table after template resources
    if ($fstemplate_alt_threshold > 0) {
        ps_query("ALTER TABLE resource AUTO_INCREMENT = " . (int) $fstemplate_alt_threshold);
    }
    resign_all_code(false, false);

    redirect("/login.php");
}


include "../../../include/header.php";
?>
<div class="BasicsBox">

<h1><?php echo escape($lang["system_reset"]); ?></h1>

<p><?php echo escape($lang["system_reset_warning"]); ?></p>

<form 
    method="post"
    action="<?php echo $baseurl_short?>plugins/system_reset/pages/reset.php"
    onSubmit="return confirm('<?php echo escape($lang["system_reset_confirm"]); ?>');"
>
<?php generateFormToken("system_reset"); ?>
<input type=hidden name="submitted" value="true">

<div class="QuestionSubmit">
<label for="buttons"><?php echo escape($lang["proceed"]); ?></label>
<input
    name="save"
    type="submit"
    value="&nbsp;&nbsp;<?php echo escape($lang["system_reset_delete_all"]); ?>&nbsp;&nbsp;"
/>
</div>
</form>
</div>

<?php
include "../../../include/footer.php";
?>
