<?php
include "../../../include/boot.php";
include "../../../include/authenticate.php";

$ref      = getval('ref', '');
$resource = getval('resource', '');

# Should never arrive at this page without edit access
if (!get_edit_access($resource)) {
    exit("Access denied");
}

if (getval('submitted', '') != '' && enforcePostRequest(false)) {
    $parameters = array("i", $ref, "i", $resource);
    ps_query("DELETE FROM resource_usage WHERE ref = ? AND resource = ?", $parameters);

    resource_log($resource, '', '', $lang['delete_usage'] . ' ' . $ref);

    redirect('pages/view.php?ref=' . $resource);
}
        
include "../../../include/header.php";
?>
<div class="BasicsBox">
    <p>
        <a href="<?php echo $baseurl_short?>pages/view.php?ref=<?php echo escape($resource) ?>" onClick="return CentralSpaceLoad(this, true);">
            <?php echo LINK_CARET_BACK . escape($lang['backtoresourceview']); ?>
        </a>
    </p>
    
    <h1><?php echo escape($lang['delete_usage']); ?></h1>

    <form method="post" action="<?php echo $baseurl_short; ?>plugins/resource_usage/pages/delete.php" onSubmit="return CentralSpacePost(this, true);">
        <?php generateFormToken("resource_usage_deleteForm"); ?>
        <input type=hidden name="submitted" value="true">
        <input type=hidden name="ref" value="<?php echo escape($ref); ?>">
        <input type=hidden name="resource" value="<?php echo escape($resource); ?>">

        <div class="Question">
            <label><?php echo escape($lang['resourceid']); ?></label>
            <div class="Fixed"><?php echo escape($resource); ?></div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang['usage_ref']); ?></label>
            <div class="Fixed"><?php echo escape($ref); ?></div>
            <div class="clearerleft"></div>
        </div>

        <div class="QuestionSubmit">     
            <input name="delete" type="submit" value="<?php echo escape($lang["action-delete"]); ?>" />
        </div>
    </form>
</div>
<?php       
include "../../../include/footer.php";
