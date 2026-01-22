<?php
function HookLicensemanagerAdmin_group_permissionsAdditionalperms()
    {
    global $lang,$permissions; 
    ?>
    <tr class="ListviewTitleStyle">
      <th colspan=3 class="permheader"><?php echo escape($lang["license_management"]); ?></th>
    </tr>
    <?php
    DrawOption("lm", $lang["license_manager_access"],false,false,(in_array("a",$permissions))); // Grey out if "a" selected
    }
