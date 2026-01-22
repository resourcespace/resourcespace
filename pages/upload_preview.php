<?php
include "../include/boot.php";
include "../include/authenticate.php";

if (checkperm("F*") && !$custompermshowfile) {
    exit("Permission denied.");
}

include_once "../include/image_processing.php";

$ref = getval("ref", "", true);
$status = "";
$error = false;
$resource = get_resource_data($ref);

if (resource_is_template($ref)) {
    error_alert($lang['error-permissiondenied']);
} elseif (!is_array($resource)) {
    error_alert($lang['resourcenotfound']);
    exit();
} elseif (!get_edit_access($ref, $resource["archive"], $resource)) {
    error_alert($lang['error-permissiondenied']);
    exit();
}

if ($resource["lock_user"] > 0 && $resource["lock_user"] != $userref) {
    $error = get_resource_lock_message($resource["lock_user"]);
    http_response_code(403);
    exit($error);
}

# fetch the current search
$search = getval("search", "");
$order_by = getval("order_by", "relevance");
$offset = getval("offset", 0, true);
$restypes = getval("restypes", "");

if (strpos($search, "!") !== false) {
    $restypes = "";
}

$archive = getval("archive", "");
$per_page = getval("per_page", 0, true);
$default_sort_direction = "DESC";

if (substr($order_by, 0, 5) == "field") {
    $default_sort_direction = "ASC";
}

$sort = getval("sort", $default_sort_direction);
$previewresource = getval("previewref", 0, true);
$previewresourcealt = getval("previewalt", -1, true);
$default_sort_direction = "DESC";

if (substr($order_by, 0, 5) == "field") {
    $default_sort_direction = "ASC";
}

$sort = getval("sort", $default_sort_direction);
$curpos = getval("curpos", "");
$go = getval("go", "");

$urlparams = array(
    'ref'                       => $ref,
    'search'                    => $search,
    'order_by'                  => $order_by,
    'offset'                    => $offset,
    'restypes'                  => $restypes,
    'archive'                   => $archive,
    'default_sort_direction'    => $default_sort_direction,
    'sort'                      => $sort,
    'curpos'                    => $curpos,
    'refreshcollectionframe'    => 'true'
);

if (
    array_key_exists("userfile", $_FILES)
    && enforcePostRequest(false)
    && $_FILES['userfile']['error'] === UPLOAD_ERR_OK
) {
    $status = upload_preview($ref);
    if ($status !== false) {
        redirect(generateURL($baseurl . "/pages/view.php", $urlparams));
        exit();
    }
    $error = true;
} elseif ($previewresource > 0 && enforcePostRequest(false)) {
    $status = replace_preview_from_resource($ref, $previewresource, $previewresourcealt);
    if ($status !== false) {
        redirect(generateURL($baseurl . "/pages/view.php", $urlparams));
        exit();
    }
    $error = true;
}

include "../include/header.php";
?>

<div class="BasicsBox"> 
    <h1>
        <?php
        echo escape($lang["uploadpreview"]);
        render_help_link("user/edit-resource-preview");
        ?>
    </h1>

    <p><?php echo escape(text("introtext")); ?></p>

    <script language="JavaScript">
        // Check allowed extensions:
        function check(filename) {
            var allowedExtensions = 'jpg,jpeg';
            var ext = filename.substr(filename.lastIndexOf('.'));
            ext = ext.substr(1).toLowerCase();

            if (allowedExtensions.indexOf(ext) == -1) {
                return false;
            } else {
                return true;
            }
        }
    </script>

    <form method="post" class="form" enctype="multipart/form-data" action="upload_preview.php">
        <?php generateFormToken("upload_preview"); ?>
        <input type="hidden" name="ref" value="<?php echo escape($ref)?>">
        <br/>

        <?php if ($status != "") {
            echo $status;
        } ?>

        <div id="invalid" <?php echo (!$error) ? "style=\"display:none;\"" : ''; ?> class="FormIncorrect">
            <?php echo escape(str_replace_formatted_placeholder("%extensions", "JPG", $lang['invalidextension_mustbe-extensions'])); ?>
        </div>

        <div class="Question">
            <label for="userfile"><?php echo escape($lang["clickbrowsetolocate"]); ?></label>
            <input type=file name=userfile id=userfile>
            <div class="clearerleft"></div>
        </div>

        <div class="QuestionSubmit">        
            <input name="save" type="submit" onclick="if (!check(this.form.userfile.value)){document.getElementById('invalid').style.display='block';return false;}else {document.getElementById('invalid').style.display='none';}" value="<?php echo escape($lang["upload_file"]); ?>" />
        </div>

        <p>
            <a onclick="return ModalLoad(this,true);" href="view.php?ref=<?php echo urlencode($ref)?>">
                <?php echo LINK_CARET_BACK . escape($lang["backtoresourceview"]); ?>
            </a>
        </p>
    </form>
</div>

<?php
include "../include/footer.php";
