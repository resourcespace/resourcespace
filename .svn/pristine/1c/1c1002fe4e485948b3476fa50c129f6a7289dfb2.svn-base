<?php

function HookRse_workflowAdmin_homeCustomadminsetup()
    {
    global $baseurl;
        global $lang;
    
    if (checkperm("a"))
        {
        
        ?>
        <li title="<?php echo escape($lang["rse_workflow_manage_workflow-tooltip"]); ?>"><a href="<?php echo $baseurl ?>/plugins/rse_workflow/pages/edit_workflow.php" onclick="return CentralSpaceLoad(this,true);"><i class="icon-workflow"></i><br /><?php echo escape($lang["rse_workflow_manage_workflow"]); ?></a></li>
        <?php
        }
        ?>
    <?php
    }




