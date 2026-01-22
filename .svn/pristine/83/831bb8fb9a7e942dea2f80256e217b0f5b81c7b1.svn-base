<?php
function HookRse_workflowAdmin_group_permissionsAdditionalperms()
{
    include_once __DIR__ . "/../include/rse_workflow_functions.php";
    global $lang;
    # ------------ Edit access to workflow actions
    $workflowactions = rse_workflow_get_actions();
    ?>
    <tr class="ListviewTitleStyle">
      <th colspan=3 class="permheader"><?php echo escape($lang["rse_workflow_actions_heading"]); ?></th>
    </tr>
    <?php
    foreach ($workflowactions as $workflowaction) {
         DrawOption(
            "wf" . $workflowaction["ref"],
            $lang["rse_workflow_access"] . " " . $workflowaction["name"]
        );
    }
}
