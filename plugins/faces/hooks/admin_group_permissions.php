<?php
function HookFacesAdmin_group_permissionsAdditionalperms()
{
    global $lang;
    ?>
    <tr class="ListviewTitleStyle">
        <th colspan=3 class="permheader"><?php echo escape($lang["faces-configuration"]); ?></th>
    </tr>
    <?php
    DrawOption("faces-v", $lang["faces-show-view"], true);
}
