<?php
function HookConsentmanagerAdmin_group_permissionsAdditionalperms()
{
    global $lang,$permissions;
    ?>
    <tr class="ListviewTitleStyle">
        <th colspan=3 class="permheader"><?php echo escape($lang["consent_management"]); ?></th>
    </tr>
    <?php
    DrawOption("cm", $lang["consent_manager_access"], false, false, (in_array("a", $permissions))); // Grey out if "a" selected);
}
