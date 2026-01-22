<?php
include '../../../include/boot.php';
include "../../../include/authenticate.php";
if(!checkperm("t")){exit ("Access denied"); }


include_once "../include/tms_link_functions.php";


include "../../../include/header.php";

echo "<h2>" . $lang["tms_link_tms_resources"] . "</h2>";
echo "<div class='Listview'>";
echo "<table style='border=1;'>";
echo "<tr>"; 
    echo "<td><strong>{$lang["tms_link_resource_id"]}</strong></td>";
    echo "<td><strong>{$lang["tms_link_module"]}</strong></td>";
    echo "<td><strong>" . $lang["tms_link_object_id"] . "</strong></td>";   
    echo "<td><strong>" . $lang["tms_link_checksum"] . "</td>"; 
    echo "</tr>";


$tmscount = 0;
foreach(tms_link_get_modules_mappings() as $module)
    {
    $tms_resources = tms_link_get_tms_resources($module);

    if(empty($tms_resources))
        {
        continue;
        }

    $tmscount++;
    foreach($tms_resources as $tms_resource)
        {
        ?>
        <tr>
            <td>
                <a href="<?php echo "$baseurl/?r={$tms_resource["resource"]}"; ?>"
                   onClick="CentralSpaceload(this, true); return false;"><?php echo $tms_resource["resource"]; ?></a>
            </td>
            <td><?php echo $module['module_name']; ?></td>
            <td>
                <a href="<?php echo "$baseurl/plugins/tms_link/pages/test.php?ref={$tms_resource["resource"]}"; ?>"
                   onClick="CentralSpaceload(this, true); return false;"><?php echo $tms_resource["identifier"]; ?></a>
            </td>
            <td><?php echo $tms_resource["checksum"]; ?></td>
        </tr>
        <?php
        }
    }

if($tmscount == 0)
    {
    ?>
    <td colspan='4'><?php echo escape($lang["tms_link_no_tms_resources"]); ?></td>
    <?php
    }
?>
    </table>
</div>  
<?php
include "../../../include/footer.php";