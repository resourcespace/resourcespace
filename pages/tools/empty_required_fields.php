<?php

# Script to display resources in a collection grouped by empty required fields.
# This may help with batch editing the affected resources / fields.
# The same resource may appear in multiple groups if it has more than one empty required field.
# It is intended to process a small collection of resources that has been created as a result of
# difficulty when attempting batch edit.

include "../../include/boot.php";
include "../../include/authenticate.php";
include "../../include/header.php";

if (!checkperm("t")) {
    echo escape($lang["error-permissiondenied"]);
    include "../../include/footer.php";
    exit();
}

$collection_ref = getval("col", '', true, 'is_int_loose');

?>
<div class="BasicsBox">
    <h1><?php echo escape($lang["missing_required_fields"]); ?></h1>
    <p><?php echo escape($lang["missing_required_fields_intro"]); ?></p>

     <form method="post"
        action="<?php echo $baseurl_short?>pages/tools/empty_required_fields.php">
        <?php generateFormToken("empty_required_fields"); ?>
        <div class="Question">
            <label><?php echo escape($lang["collection"]); ?></label>
            <input name="col" type="text" value="<?php echo escape($collection_ref); ?>"></input>
            <input type="submit" name="save" value="<?php echo escape($lang["searchbutton"]); ?>"></input>
        </div>
    </form>

<?php

if (getval("save", "") != "" && enforcePostRequest(false) && $collection_ref > 0 && collection_writeable($collection_ref)) {
    $resources = get_collection_resources($collection_ref);

    # Get resources with missing required field data.
    $missing_fields = array();
    foreach ($resources as $resource) {
        $fields = missing_fields_check($resource);
        if (count($fields) > 0) {
            $missing_fields[$resource] = $fields;
        }
    }

    # Get field headings.
    $required_fields = array();
    foreach ($missing_fields as $resource_metadata_fields) {
        $required_fields = array_merge($required_fields, array_column($resource_metadata_fields, 'title'));
    }
    $required_fields = array_unique($required_fields, SORT_STRING);

    # Group resources under relevant fields.
    $results = array();
    foreach ($missing_fields as $resource => $fields) {
        $missed_required_fields = array_column($fields, 'title');
        foreach ($required_fields as $column) {
            if (in_array($column, $missed_required_fields)) {
                $results[$column][] = $resource;
            }
        }
    }

    if (count($results) === 0) {
        ?>
        <p><?php echo escape($lang["no_results_found"]); ?></p>
        <?php
    } else {
        foreach ($results as $field_name => $field_resources) {
            sort($field_resources, SORT_NUMERIC);
            $field_resources = implode(', ', $field_resources);
            ?>
            <div class="BasicsBox">
                <h2><?php echo escape($field_name); ?></h2>
                <div class="Fixed"><?php echo escape($field_resources); ?></div>
            </div>
            <?php
        }
        ?>
        </div>
        <?php
    }
} elseif (getval("save", "") != "") {
    ?>
    <p><?php echo escape($lang["no_results_found"]); ?></p>
    <?php
}

include "../../include/footer.php";