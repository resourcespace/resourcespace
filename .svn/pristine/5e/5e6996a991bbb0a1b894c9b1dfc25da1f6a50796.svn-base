<?php

include "../include/boot.php";
include "../include/authenticate.php";

$search = getval("search", "");
$offset = getval("offset", 0, true);
$order_by = getval("order_by", "");
$archive = getval("archive", "", true);
$restypes = getval("restypes", "");

if (strpos($search, "!") !== false) {
    $restypes = "";
}

$default_sort_direction = "DESC";
if (substr($order_by, 0, 5) == "field") {
    $default_sort_direction = "ASC";
}
$sort = getval("sort", $default_sort_direction);

$results = do_search(getval("search", ""), getval("restypes", ""), "relevance", getval("archive", ""), -1, "desc", false, DEPRECATED_STARSEARCH, false, true, getval("daylimit", ""));
$disk_usage = $results[0]["total_disk_usage"];
$count = $results[0]["total_resources"];

include "../include/header.php";

?>
<div class="BasicsBox">
    <h1><?php echo escape($lang["searchitemsdiskusage"]); ?></h1>
    <?php
    $intro = text("introtext");
    if ($intro != "") {
        ?>
        <p><?php echo escape($intro); ?></p>
        <?php
    }
    ?>
    <div class="Question">
        <label><?php echo escape($lang["matchingresourceslabel"]); ?></label>
        <div class="Fixed"><?php echo number_format($count); ?></div>
        <div class="clearerleft"></div>
    </div>

    <div class="Question">
        <label><?php echo escape($lang["diskusage"]); ?></label>
        <div class="Fixed"><strong> <?php echo formatfilesize($disk_usage) ?></strong></div>
        <div class="clearerleft"></div>
    </div>
</div>

<?php
include "../include/footer.php";
