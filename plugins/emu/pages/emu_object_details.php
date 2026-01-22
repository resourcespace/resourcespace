<?php
include '../../../include/boot.php';
include '../../../include/authenticate.php';

if (!checkperm('t')) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Access denied!');
}

include_once '../include/emu_functions.php';
include_once '../include/emu_api.php';

$irn = getval('irn', '', true);

if ('' == $irn) {
    exit($lang['emu_no_resource']);
}

$emu_rs_mappings = plugin_decode_complex_configs($emu_rs_saved_mappings);
$emu_data        = get_emu_data($emu_api_server, $emu_api_server_port, array($irn), $emu_rs_mappings);

include '../../../include/header.php';
?>

<h2>EMu Object details</h2>
<div id="EmuObjectDetailContainer" class='Listview'>
    <table>
        <?php
        if (isset($emu_data[$irn])) {
            foreach ($emu_data[$irn] as $key => $value) {
                ?>
                <tr> 
                    <td><strong><?php echo $key; ?></strong></td>
                    <td><?php echo emu_convert_to_atomic($value); ?></td> 
                </tr>
                <?php
            }
        }
        ?>
    </table>
</div>

<?php
include '../../../include/footer.php';