<?php
include "../../include/boot.php";
include_once "../../include/authenticate.php";

if (!checkperm("a")) {
    exit($lang["error-permissiondenied"]);
}

set_time_limit(0);

$cleanuprestype = getval("cleanuprestype", '');
$cleanupfield   = getval("cleanupfield", 0, true);
$dryrun         = getval("dryrun", "") != "";
$backurl        = getval("backurl", "");

$cleanuprestypes = explode(",", $cleanuprestype);
$cleanuprestypes = array_filter($cleanuprestypes, "is_int_loose");

if (getval("submit", "") != "" && $cleanupfield != 0) {
    $result = cleanup_invalid_nodes([$cleanupfield], $cleanuprestypes, $dryrun);
}

include_once "../../include/header.php";

$allfields = get_resource_type_fields("", "order_by");
foreach ($allfields as $field) {
    if ($field["global"] == 0) {
        $validfields[] = $field;
    }
}
?>

<script>
    fieldtypes = [];
    <?php
    foreach ($validfields as $validfield) {
        echo "fieldtypes[" . $validfield["ref"] . "] = [" . $validfield["resource_types"] . "];\n";
    }
    ?>
    jQuery(document).ready(function() {
        jQuery('#cleanupfield').on('change',function() {
            id = (this.value);
            jQuery('#cleanuprestype option').attr('disabled', false);
            // Disable all valid resource types
            fieldtypes[id].forEach(function(currentValue, index, arr) {
                jQuery('#cleanuprestype option[value=' + currentValue + ']').attr('disabled', true);
            });    
        });
    });
</script>

<div class="BasicsBox">
    <?php
    if ($backurl == "") { ?>
        <p>
            <a href="<?php echo escape($backurl) ?>" onClick="return CentralSpaceLoad(this,true);"><?php echo LINK_CARET_BACK ?><?php echo escape($lang["back"]) ?></a>
        </p>
        <?php
    }

    if (isset($result)) {
        render_top_page_error_style($result);
    }
    ?>

    <h1><?php echo escape($lang["cleanup_invalid_nodes"]) ?></h1>

    <form method="post" class="FormWide" action="<?php echo $baseurl_short ?>pages/tools/cleanup_invalid_nodes.php" onsubmit="return CentralSpacePost(this,true);">
        <?php
        generateFormToken("cleanup_invalid_nodes");
        render_dropdown_question($lang["field"], "cleanupfield", array_column($validfields, "title", "ref"), $cleanupfield, '', ["input_class" => "medwidth"]);
        render_resource_type_selector_question($lang["property-resource_type"], "cleanuprestype", "medwidth", false, $cleanuprestype[0] ?? 0);
        ?>
        <div class="Question" >
            <label for="dryrun" ><?php echo escape($lang["admin_resource_type_field_migrate_dry_run"]) ?></label>
            <input type="checkbox" name="dryrun" value="true" <?php echo ($dryrun) ? " checked" : ''; ?>>
            <div class="clearerleft"> </div>
        </div>

        <div class="Question" >
            <input type="hidden" id="submitinput" name="submit" value="">
            <input type="submit" name="submit" value="<?php echo escape($lang["action-submit-button-label"]) ?>" onclick="document.getElementById('submitinput').value='true';">
            <div class="clearerleft"> </div>
        </div>
        <div class="clearerleft"> </div>
    </form>
</div>
<?php

include_once "../../include/footer.php";


