<?php
include '../../include/boot.php';
include '../../include/authenticate.php';

if (!checkperm('a')) {
    exit('Permission denied.');
}

$ref    = getval('ref', 0, true);
$copied = '';
$current = get_resource_type_field($ref);
$title = $current["title"];

# Perform copy
if (getval("saveform", "") != "" && $ref > 0 && enforcePostRequest(false)) {
    $allcolumns = columns_in("resource_type_field", null, null, true);
    $allcolumns = array_diff($allcolumns, ["name"]);
    $insert = array_diff($allcolumns, ["ref","name"]);

    // Create new short name
    $allcolumns[] = "name";
    $newname = $current["name"] . "copy";

    ps_query("INSERT INTO resource_type_field (" . implode(",", $allcolumns) . ") SELECT NULL, " . implode(",", $insert)  . ",? FROM resource_type_field WHERE ref = ?", ["s",$newname,"i",$ref]);

    $copied = sql_insert_id();

    // Copy any field mappings
    ps_query("INSERT INTO resource_type_field_resource_type (resource_type_field,resource_type) SELECT ?,resource_type FROM resource_type_field_resource_type WHERE resource_type_field = ?", ["i",$copied,"i",$ref]);

    // Copy nodes if resource type is a fixed list type:
    copy_resource_type_field_nodes($ref, $copied);

    log_activity(null, LOG_CODE_COPIED, "{$lang['copy_of']} {$ref}", 'resource_type_field', '', $copied);
    redirect($baseurl_short . "pages/admin/admin_resource_type_field_edit.php?ref=" . $copied);
}

if ($copied != '') {
    $saved_text = str_replace('?', $copied, $lang['copy-completed']);
}

include "../../include/header.php";

?>
<div class="BasicsBox">
    <?php
    $links_trail = array(
        array(
            'title' => $lang["systemsetup"],
            'href'  => $baseurl_short . "pages/admin/admin_home.php",
            'menu' =>  true
        ),
        array(
            'title' => $lang["admin_resource_type_fields"],
            'href'  => $baseurl_short . "pages/admin/admin_resource_type_fields.php"
        ),
        array(
            'title' => $lang["admin_resource_type_field"] . ": " . i18n_get_translated($title),
            'href'  => $baseurl_short . "pages/admin/admin_resource_type_field_edit.php?ref=" . $ref
        ),
        array(
            'title' => $lang['copy-field'] . ": " . i18n_get_translated($title),
            'help'  => "managing-metadata"
        )
    );

    renderBreadcrumbs($links_trail);

    if (isset($saved_text)) {
        ?>
        <div class="PageInformal"><?php echo escape($saved_text); ?></div>
        <?php
    }
    ?>
    <form method="post" action="admin_copy_field.php">
        <?php generateFormToken("admin_copy_field"); ?>
        <input type="hidden" name="saveform" value="true">
        <input type="hidden" name="ref" value="<?php echo $ref; ?>">
        <p align="right">
            <input type="submit" name="copy" value="<?php echo escape($lang['copy']) ; ?>" style="width:100px;">
        </p>
    </form>

</div><!--End of BasicsBox -->

<?php
include "../../include/footer.php";