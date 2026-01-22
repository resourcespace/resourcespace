<?php
include_once __DIR__ . '/../include/emu_api.php';

function HookEmuCheckAddinstallationcheck()
{
    $GLOBALS['use_error_exception'] = true;
    try {
        new EMuAPI($GLOBALS['emu_api_server'], $GLOBALS['emu_api_server_port']);
    } catch (Throwable $t) {
        ?>
        <tr>
            <td colspan="2"><?php echo escape("{$GLOBALS['lang']['emu_configuration']}: {$t->getMessage()}"); ?></td>
            <td><b><?php echo escape($GLOBALS['lang']['status-fail']); ?></b></td>
        </tr>
        <?php
    }
    unset($GLOBALS['use_error_exception']);

    return false;
}