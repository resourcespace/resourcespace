<?php
// Generic modal page to create new resource types or metadata fields

include_once __DIR__ . "/../../include/boot.php";
include_once __DIR__ . "/../../include/authenticate.php";

$newtype = getval("type", "");

if ($newtype == "") {
    http_response_code(401);
    exit($lang['error-permissiondenied']);
}

$extraparams = array();
switch ($newtype) {
    case "resource_type":
        $targeturl = $baseurl_short . "pages/admin/admin_resource_types.php";
        $newparam = "newtype";
        $newtext = $lang["admin_resource_type_create"];
        $csrf_code = "admin_resource_types";
        break;

    case "resource_type_field":
        $targeturl = $baseurl_short . "pages/admin/admin_resource_type_fields.php";
        $newparam = "newfield";
        $newtext = $lang["admin_resource_type_field_create"];
        $extraparams["fieldtype"] = FIELD_TYPE_CHECK_BOX_LIST;
        $extraparams["newfieldrestype"] = getval("fieldrestype", "");
        $csrf_code = "admin_resource_type_fields";
        break;

    case "collection":
        $targeturl = $baseurl_short . "pages/collections.php";
        $newparam = "newcolname";
        $newtext = $lang["createnewcollection"];
        $extraparams["collection"] = "new";
        $csrf_code = "newcollection";
        $customformaction = "var newcolname=encodeURIComponent(jQuery('#newcolname').val());CollectionDivLoad('" . $baseurl_short . "pages/collections.php?collection=new&entername='+newcolname);ModalClose();ReloadBrowseBar();";
        break;

    case "node":
        $field = getval("field", 0, true);
        if ($field < 1) {
            http_response_code(401);
            exit($lang['error-permissiondenied']);
        }
        $parent_nodes = getval("parent_nodes", "");
        $targeturl = generateURL($baseurl_short . "pages/admin/admin_manage_field_options.php", array("field" => $field));
        $extraparams["submit_new_option"] = "add_new";
        $parents = explode(",", $parent_nodes);
        $parent = end($parents);
        $extraparams["new_option_parent"] = $parent;
        $extraparams["expand_nodes"] = $parent_nodes;
        $extraparams["reload"] = "true";
        $newparam = "new_option_name";
        $newtext = $lang["add"];
        $csrf_code = "newcollection";
        break;
}
?>

<div class="BasicsBox" id="create_new">
    <form action="<?php echo $targeturl; ?>" onsubmit="<?php echo isset($customformaction) ? $customformaction : "return CentralSpacePost(this,true);"; ?>">
        <div class="Question">
            <label><?php echo $newtext; ?></label>
            <?php generateFormToken($csrf_code);
            foreach ($extraparams as $extraparam => $extravalue) {
                echo "<input type=hidden name='" . escape($extraparam)  .  "' value='" . escape($extravalue) . "'>";
            } ?>
            <input type="text" class="medwidth" name="<?php echo $newparam ?>" id="<?php echo $newparam ?>" value="">
            <div class="clearerleft"></div>
        </div>
    
        <?php if ($newtype == "resource_type_field") { ?>
            <div class="Question">
            <label><?php echo escape($lang["property-field_type"]); ?></label>    
            <select name="field_type" class="medwidth">
                <?php foreach ($field_types as $field_type => $field_type_description) { ?>
                    <option value="<?php echo $field_type ?>"><?php echo escape($lang[$field_type_description]) ; ?></option>
                    <?php
                } ?>
            </select>
            </div>
        <?php } ?>

        <div class="Question">
            <input type="submit" class="medcomplementwidth" value="<?php echo escape($lang["save"]); ?>" />
            <input type="submit" class="medcomplementwidth" value="<?php echo escape($lang["cancel"]); ?>" onclick="ModalClose();" />
            <div class="clearerleft"></div>
        </div>
    </form>
</div>
