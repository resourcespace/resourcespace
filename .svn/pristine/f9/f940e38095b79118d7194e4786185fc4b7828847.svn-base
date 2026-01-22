<?php

declare(strict_types=1);

function HookBrand_guidelinesAdmin_group_permissionsAdditionalperms()
{
    $pyaml = get_plugin_yaml('brand_guidelines', false, true);
    ?>
    <tr class="ListviewTitleStyle">
        <td colspan=3 class="permheader"><?php echo escape($pyaml['title'] ?? ''); ?></td>
    </tr>
    <?php
    DrawOption('bgv', $GLOBALS['lang']['brand_guidelines_permission_bgv'], true, true);

    /** {@see acl_can_edit_brand_guidelines()} */
    if (!in_array('a', $GLOBALS['permissions'])) {
        DrawOption('bge', $GLOBALS['lang']['brand_guidelines_permission_bge'], false, true);
    }
}
