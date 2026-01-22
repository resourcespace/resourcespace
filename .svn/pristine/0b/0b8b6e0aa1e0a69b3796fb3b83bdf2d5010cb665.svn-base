<?php
include "../include/boot.php";
include "../include/authenticate.php";

if (!$enable_themes || checkperm("b") || !checkperm("h")) {
    error_alert($lang["error-permissiondenied"], true, 403);
    exit();
}

$ref = getval("ref", 0, true);

if (!collection_writeable($ref)) {
    exit($lang["no_access_to_collection"]);
}

$collection = get_collection($ref);

if ($collection === false) {
    error_alert($lang["error-collectionnotfound"], true);
    exit();
}

if (
    (!in_array($collection["type"], array(COLLECTION_TYPE_STANDARD, COLLECTION_TYPE_PUBLIC, COLLECTION_TYPE_FEATURED)))
    || ($collection["type"] == COLLECTION_TYPE_FEATURED
        && !featured_collection_check_access_control((int) $collection["ref"]))
) {
    error_alert($lang["error-permissiondenied"], true, 403);
    exit();
}

if (getval("submitted", "") != "" && enforcePostRequest(false)) {
    $coldata = array();

    if (getval("update_parent", "") == "true") {
        // Prepare coldata for save_collection() for posted featured collections (if any changes have been made)
        $current_branch_path = get_featured_collection_category_branch_by_leaf((int) $collection["ref"], array());
        $featured_collections_changes = process_posted_featured_collection_categories(0, $current_branch_path);
        if (!empty($featured_collections_changes)) {
            $coldata["featured_collections_changes"] = $featured_collections_changes;
        }
    }

    if (
        !empty($coldata)
        && isset($coldata["featured_collections_changes"]["update_parent"])
        && $coldata["featured_collections_changes"]["update_parent"] == 0
        && getval("force_featured_collection_type", "") != "true"
        && is_featured_collection_category_by_children($collection["ref"])
    ) {
        $error = $lang["error_save_not_allowed_fc_has_children"];
    }

    if (!empty($coldata) && !isset($error)) {
        save_collection($collection["ref"], $coldata);
        $collection = get_collection($collection["ref"]);
    }
}

$action_url = generateURL("{$baseurl_short}pages/collection_set_category.php", array("ref" => $collection["ref"]));
include "../include/header.php";
?>

<div class="BasicsBox">
    <?php
    if (isset($error)) {
        render_top_page_error_style($error);
    }
    ?>
    <h1>
        <?php
        echo escape($lang["collection_set_theme_category_title"]);
        render_help_link("user/themes-public-collections");
        ?>
    </h1>
    <p><?php echo escape(text("introtext")); ?></p>
    <form method=post id="collectionform" action="<?php echo $action_url; ?>">
        <?php generateFormToken("collectionform"); ?>
        <input type=hidden name=ref value="<?php echo escape($ref); ?>">
        <input type=hidden name="submitted" value="true">
        <input type="hidden" name="redirect" id="redirect" value="yes" >
        <input type=hidden name="update_parent" value="false">
        <div class="Question">
            <label for="name"><?php echo escape($lang["collection"]); ?></label>
            <div class="Fixed"><?php echo escape(i18n_get_collection_name($collection, $index = "name")); ?></div>
            <div class="clearerleft"></div>
        </div>
        <?php
        render_featured_collection_category_selector(
            0,
            array(
                "collection" => $collection,
                "depth" => 0,
                "current_branch_path" => get_featured_collection_category_branch_by_leaf((int) $collection["ref"], array()),
            )
        );
        ?>
    </form>
</div>
<?php
include "../include/footer.php";