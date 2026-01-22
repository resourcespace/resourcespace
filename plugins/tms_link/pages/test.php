<?php
include '../../../include/boot.php';

include "../../../include/authenticate.php";
if(!checkperm("t"))
    {
    exit("Access denied");
    }

include_once "../include/tms_link_functions.php";


$ref = getval("ref", 0, true);

if($ref == 0)
    {
    exit($lang["tms_link_no_resource"]);
    }

$tmsdata = tms_link_get_tms_data($ref);

if(!is_array($tmsdata))
    {
    echo $tmsdata;
    }

include "../../../include/header.php";
?>
<h2><?php echo escape($lang["tms_link_tms_data"]); ?></h2>
<div class='Listview'>
<?php
foreach($tmsdata as $modulename => $tmsmodule) {
    ?>
    <table style="border:1;">
        <th><?php echo escape($modulename); ?></th>
    <?php
    foreach($tmsmodule as $tmsobject) {
        foreach($tmsobject as $key=>$value) {
            ?>
            <tr> 
            <td><strong><?php echo escape($key); ?></strong></td>
            <td><?php echo escape($value); ?></td>
            </tr>
            <?php
        }
        ?><tr><td></td></tr><?php
    }
    ?>
    </table>
    <?php }?>
</div>
<?php	
include "../../../include/footer.php";