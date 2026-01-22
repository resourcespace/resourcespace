<?php
include '../../../include/boot.php';
include "../../../include/authenticate.php";
if(!checkperm("a")){exit ("Access denied"); }


include_once "../include/tms_link_functions.php";


$tmsid=getval("tmsid",0,true);

$conn = odbc_connect($tms_link_dsn_name, $tms_link_user,$tms_link_password);
if(!$conn)
    {
    $error = odbc_errormsg();
    exit($error);
    }

$modules_mappings = tms_link_get_modules_mappings();


include "../../../include/header.php";

?>
<div class="BasicsBox">
    <form id="tmsraw" method="POST" action="show_raw.php">
     <?php generateFormToken("tmsraw"); ?>
        <div class="Question">
            <label for="id" >Enter TMS Identifier</label>
            <input type="text" name="tmsid" value ="<?php echo escape($tmsid); ?>" />
        </div>
        <div class="Question">
            <input type="submit" name="save" value="Get data" onclick="return CentralSpacePost(jQuery('#tmsraw'), false);">
        </div>
    </form>
</div>
<?php


if($tmsid != 0)
    {
    echo "<div class='BasicsBox'>";
    foreach($modules_mappings as $module)
        {
        $conditionsql = " WHERE {$module['tms_uid_field']} = '" . escape_check($tmsid) . "'";

        $tmscountsql = "SELECT Count(*) FROM {$module['module_name']} {$conditionsql};";

        $tmscountset = odbc_exec($conn, $tmscountsql);
        $tmscount_arr = odbc_fetch_array($tmscountset);
        $resultcount =  count($tmscount_arr);
        if($resultcount == 0)
            {
            return $lang["tms_link_no_tms_data"];
            }
        $tmssql = "SELECT * FROM {$module['module_name']} {$conditionsql};";

        echo "Query: <strong>" . $tmssql . "</strong><br />";


        $tmsresultset = odbc_exec($conn, $tmssql);
        for($r = 1; $r <= $resultcount; $r++)
            {
            $tmsdata = odbc_fetch_array($tmsresultset, $r);
            if(is_array($tmsdata))
                {
                foreach($tmsdata as $name=>$value)
                    {
                    echo escape($name . " : " . ($value ?? '[NULL]')) . "<br />";
                    }
                }
            else
                {
                echo "No data returned";
                }
            }
        }
    echo "</div>";
    }

odbc_close($conn);
include "../../../include/footer.php";
