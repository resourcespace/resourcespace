<?php
#
# rse_workflow setup page, requires System Setup permission
#

include '../../../include/boot.php';
include '../../../include/authenticate.php'; if (!checkperm('a')) {exit ($lang['error-permissiondenied']);}
include '../../../include/header.php';

?>

<div class="BasicsBox"> 
    <h1><?php echo escape($lang["rse_workflow_manage_workflow"]); ?></h1>
    <?php
    $links_trail = array(
        array(
            'title' => $lang["systemsetup"],
            'href'  => $baseurl_short . "pages/admin/admin_home.php",
            'menu' =>  true
        ),
        array(
            'title' => $lang["rse_workflow_manage_workflow"]
        )
    );

    renderBreadcrumbs($links_trail);
    ?>

    <div class="VerticalNav">
    <ul>
    
    <li><a href="<?php echo $baseurl ?>/plugins/rse_workflow/pages/edit_workflow_states.php" onclick="return CentralSpaceLoad(this,true);"><?php echo LINK_CARET . escape($lang["rse_workflow_manage_states"])?></a></li>
    <li><a href="<?php echo $baseurl ?>/plugins/rse_workflow/pages/edit_workflow_actions.php" onclick="return CentralSpaceLoad(this,true);"><?php echo LINK_CARET . escape($lang["rse_workflow_manage_actions"])?></a></li>

    </ul>
    </div>

  </div>

<?php
include "../../../include/footer.php";
?>
