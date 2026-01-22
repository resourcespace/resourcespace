<?php
#
# Unindex_field.php
#
#
# Removes Indexes for a field
#

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("a")) {
    exit("Permission denied");
}

include "../../include/image_processing.php";

set_time_limit(0);

if (PHP_SAPI != 'cli') {
    include "../../include/authenticate.php";
    if (!checkperm("a")) {
        exit("Permission denied");
    }
    $field = getval("field", 0, true);
} else {
    $field = $argv[1] ?? 0;
    if ($field == 0) {
        exit("Please specify a field ID\n e.g.\n php reindex_field.php 8\n");
    }
}
if ($field != 0) {
    $fieldinfo = get_resource_type_field($field);
    if (!$fieldinfo) {
        exit("Invalid field specified.");
    }
}

if (PHP_SAPI != 'cli') {
    include __DIR__ . "/../../include/header.php";
}

if (PHP_SAPI == 'cli' || (getval("submit", "") != "" && enforcePostRequest(false))) {
    // Delete existing keywords index for this field
    remove_field_keywords($field);
    $result = PHP_SAPI == 'cli' ? "Keywords removed from field $field\n\n" : "<div class='PageInfoMessage'>Keywords removed from " . escape($fieldinfo["title"]) . "</div>";
    echo $result;
}

if (PHP_SAPI != 'cli') {
    ?>
    <form method="post" action="unindex_field.php">
        <?php generateFormToken("Unindex_field"); ?>
        <?php render_field_selector_question("Choose field to remove indexes from", "field", []); ?>
        <div class="Question">
            <input type="submit" name="submit" value="Unindex field">
        </div>
    </form>
    <?php
}