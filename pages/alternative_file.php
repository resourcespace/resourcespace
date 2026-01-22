<?php

include "../include/boot.php";
include "../include/authenticate.php";
include_once "../include/image_processing.php";

$ref = getval("ref", "", true);

$search = getval("search", "");
$offset = getval("offset", 0, true);
$order_by = getval("order_by", "");
$archive = getval("archive", "", true);
$restypes = getval("restypes", "");

if (strpos($search, "!") !== false) {
    $restypes = "";
}

$modal = (getval("modal", "") == "true");
$context = getval("context", "");
$default_sort_direction = "DESC";

if (substr($order_by, 0, 5) == "field") {
    $default_sort_direction = "ASC";
}

$sort = getval("sort", $default_sort_direction);
$resource = getval("resource", "", true);

# Fetch resource data.
$resourcedata = get_resource_data($resource);

if ($resourcedata === false) {
    http_response_code(400);
    exit(escape($lang["resourcenotfound"]));
}

if ($resourcedata["lock_user"] > 0 && $resourcedata["lock_user"] != $userref) {
    $error = get_resource_lock_message($resourcedata["lock_user"]);
    http_response_code(403);
    exit($error);
}

# Load the configuration for the selected resource type. Allows for alternative notification addresses, etc.
resource_type_config_override($resourcedata["resource_type"]);

# Not allowed to edit this resource?
if ((!get_edit_access($resource, $resourcedata["archive"], $resourcedata) || checkperm('A')) && $resource > 0) {
    exit("Permission denied.");
}

# Fetch alternative file data
$file = get_alternative_file($resource, $ref);
if ($file === false) {
    exit("Alternative file not found.");
}

# Tweak images
if (getval("tweak", "") != "" && enforcePostRequest(false)) {
    $tweak = getval("tweak", "");
    switch ($tweak) {
        case "rotateclock":
            $wait = tweak_preview_images($resource, 270, 0, "jpg", $ref);
            break;
        case "rotateanti":
            $wait = tweak_preview_images($resource, 90, 0, "jpg", $ref);
            break;
        case "restore":
            if ($enable_thumbnail_creation_on_upload) {
                $wait = create_previews($resource, false, "jpg", false, false, $ref);
            }
            break;
    }
}

$url_params = [
    "ref" => $resource,
    "search" => $search,
    "offset" => $offset,
    "order_by" => $order_by,
    "sort" => $sort,
    "archive" => $archive,
];

if ($modal) {
    $url_params["modal"] = "true";

    if ($context == "Modal") {
        $url_params["context"] = $context;
    }
}
$altname = getval("name", "");
if (
    $altname !== ""
    && getval("tweak", "") == ""
    && enforcePostRequest(false)
) {
    // Do not do this during a tweak operation!
    $alt_data = [
        "name"          => (string) $altname,
        "description"   => (string) getval("description", ""),
        "alt_type"      => (string) getval("alt_type", ""),
    ];
    save_alternative_file($resource, $ref, $alt_data);
    // Check to see if we need to notify users of this change
    if ($notify_on_resource_change_days != 0) {
        notify_resource_change($resource);
    }

    if (getval("tweak", "") != '') {
        $url_params["ref"] = $ref;
        $url_params = array_merge(["resource" => $resource], $url_params);
        redirect(generateURL(
            "{$baseurl_short}pages/alternative_file.php",
            $url_params
        ));
    } else {
        redirect(generateURL("{$baseurl_short}pages/alternative_files.php", $url_params));
    }
}

include "../include/header.php";
$backtoalternativefilesurl = generateURL("{$baseurl_short}pages/alternative_files.php", $url_params);
$backtoalternativefileurl = generateURL("{$baseurl_short}pages/alternative_file.php", $url_params);
?>

<div class="BasicsBox">
    <p>
        <a
            onClick="return <?php echo $context != "Modal" ? "CentralSpace" : "Modal"; ?>Load(this,true);" 
            href="<?php echo $backtoalternativefilesurl?>">
            <?php echo LINK_CARET_BACK . escape($lang["backtomanagealternativefiles"]); ?>
        </a>
    </p>

    <h1>
        <?php
        echo escape($lang["editalternativefile"]);
        render_help_link('user/alternative-files');
        ?>
    </h1>

    <form 
        method="post"
        class="form"
        id="fileform"
        onsubmit="return <?php echo $context == 'Modal' ? 'Modal' : 'CentralSpace'; ?>Post(this, true);"
        action="<?php echo $backtoalternativefileurl; ?>"
    >
        <?php
        if ($modal) {
            ?>
            <input type="hidden" name="modal" value="true">
            <?php
            if ($context == "Modal") {
                ?>
                <input type="hidden" name="context" value="Modal">
                <?php
            }
        }

        generateFormToken('fileform'); ?>

        <input type=hidden name=ref value="<?php echo escape($ref) ?>">
        <input type=hidden name=resource value="<?php echo escape($resource) ?>">

        <?php //display preview if exists
        $previews_exist = false;

        if (file_exists(get_resource_path($resource, true, 'thm', true, 'jpg', true, 1, false, '', $ref, true))) {
            $previews_exist = true;
            $fileurl = get_resource_path($resource, false, 'thm', true, 'jpg', true, 1, false, date('Y-m-d H:i:s'), $ref);
            ?>
            <div class="Question" style="border: 0px;">
                <img
                    alt="<?php echo escape(i18n_get_translated($file['name'] ?? "")); ?>"
                    id="preview"
                    align="top"
                    src="<?php echo $fileurl; ?>"
                    class="ImageBorder"
                    style="margin-right:10px;"
                />
                <br />
                <br />
                <div class="clearerleft"></div>
            </div>
            <?php
        }
        ?>
        <div class="Question">
            <label><?php echo escape($lang["resourceid"]); ?></label>
            <div class="Fixed"><?php echo escape($resource) ?></div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="name"><?php echo escape($lang["name"]); ?></label>
            <input type=text class="stdwidth" name="name" id="name" value="<?php echo escape($file["name"]) ?>" maxlength="100">
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="name"><?php echo escape($lang["description"]); ?></label>
            <input type=text class="stdwidth" name="description" id="description" value="<?php echo escape($file["description"]) ?>" maxlength="200">
            <div class="clearerleft"></div>
        </div>

        <?php
        // If the system is configured to support a type selector for alt files, show it
        if (isset($alt_types) && count($alt_types) > 1) {
            echo "<div class='Question'>\n<label for='alt_type'>" . escape($lang["alternatetype"]) . "</label><select name='alt_type' id='alt_type'>";
            foreach ($alt_types as $thealttype) {
                if ($thealttype == $file['alt_type']) {
                    $alt_type_selected = " selected='selected'";
                } else {
                    $alt_type_selected = '';
                }
                $thealttype = escape($thealttype);
                echo "\n   <option value='$thealttype' $alt_type_selected >$thealttype</option>";
            }
            echo "\n</select>\n<div class='clearerleft'> </div>\n</div>";
        }

        if ($previews_exist) { ?>
            <div class="Question" id="question_imagecorrection">
                <label>
                    <?php echo escape($lang["imagecorrection"]); ?>
                    <br/>
                    <?php echo escape($lang["previewthumbonly"]); ?>
                </label>
                <select name="tweak" id="tweak" onChange="form.submit()">
                    <option value=""><?php echo escape($lang["select"]); ?></option>
                    <?php
                    # On some PHP installations, the imagerotate() function is wrong and images are turned incorrectly.
                    # A local configuration setting allows this to be rectified
                    if (!$image_rotate_reverse_options) { ?>
                        <option value="rotateclock"><?php echo escape($lang["rotateclockwise"]); ?></option>
                        <option value="rotateanti"><?php echo escape($lang["rotateanticlockwise"]); ?></option>
                    <?php } else { ?>
                        <option value="rotateanti"><?php echo escape($lang["rotateclockwise"]); ?></option>
                        <option value="rotateclock"><?php echo escape($lang["rotateanticlockwise"]); ?></option>
                    <?php } ?>
                    <option value="restore"><?php echo escape($lang["recreatepreviews"]); ?></option>
                </select>
                <div class="clearerleft"></div>
            </div>
        <?php } ?>

        <div class="QuestionSubmit">
            <input name="save" type="submit" value="<?php echo escape($lang["save"]); ?>" />
        </div>
    </form>
</div>

<?php
include "../include/footer.php";
?>
