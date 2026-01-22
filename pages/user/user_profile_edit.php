<?php
include "../../include/boot.php";
include "../../include/authenticate.php";
include "../../include/api_functions.php";

// Do not allow access to anonymous users
if (isset($anonymous_login) && ($anonymous_login == $username)) {
    header('HTTP/1.1 401 Unauthorized');
    die('Permission denied!');
}

global $userref;

if (getval("save", "") != "" && enforcePostRequest(false)) {
    $image_path = "";
    $profile_text = getval("profile_bio", "");
    if ($_FILES['profile_image']['name'] != "") {
        $image_path = get_temp_dir(false) . '/' . $userref . '_' . uniqid() . ".jpg";
        $process_file_upload = process_file_upload(
            $_FILES['profile_image'],
            new SplFileInfo($image_path),
            ['allow_extensions' => ['jpg', 'jpeg']]
        );

        if (!$process_file_upload['success']) {
            error_alert(
                match ($process_file_upload['error']) {
                    ProcessFileUploadErrorCondition::InvalidExtension => $lang['error_not_jpeg'],
                    default => $process_file_upload['error']->i18n($lang),
                },
                true
            );
            exit();
        }
    }
    $result = set_user_profile($userref, $profile_text, $image_path);
    if ($result === false) {
        error_alert($lang["error_upload_failed"]);
        exit();
    }
}

if (getval("delete", "") != "" && enforcePostRequest(false)) {
    delete_profile_image($userref);
}

$profile_text = get_profile_text($userref);
$profile_image = get_profile_image($userref);

include "../../include/header.php";
?><meta http-equiv="Cache-control" content="no-cache">

<script>
function checkFileType(image_supplied)
{
    var image = image_supplied.profile_image.value;
    var pos = image.lastIndexOf(".");
    var ext = image.toLowerCase().substr(pos);
    if (image == "") return true;
    var ext_types = [".jpg", ".jpeg"];
    if (image != "" && ext_types.includes(ext)) return true;
    document.getElementById("profile_image_validate").innerHTML = "<?php echo escape($lang["error_not_jpeg"]); ?>";
    return false;
}
</script>

<div class="BasicsBox">
    <h1><?php echo escape($lang["profile"]); ?></h1>
    <p><?php echo escape($lang["profile_introtext"]) ;?>&nbsp;<?php render_help_link('user/profile'); ?></p>
    
    <form method="post"
        action="<?php echo $baseurl_short?>pages/user/user_profile_edit.php"
        enctype="multipart/form-data"
        onsubmit="return checkFileType(this);">
        <?php generateFormToken("user_profile_edit"); ?>

        <div class="Question">
            <label><?php echo escape($lang["profile_bio"]); ?></label>
            <textarea name="profile_bio" class="stdwidth" rows=7 cols=50><?php echo escape((string) $profile_text); ?></textarea>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["profile_image"]); ?></label>
            <input type="file" accept ="image/jpg, image/jpeg" name="profile_image" size="20">
            <div id="profile_image_validate" style="font-size:10; color:red;"></div>
            <div class="clearerleft"></div>
        </div>
        
        <?php if ($enable_remote_apis) { ?>
            <div class="Question">
                <label><?php echo escape($lang["api-key"]); ?></label>
                <div class="Fixed">
                    <button type="button" class="show-hidden-btn"><?php echo escape($lang["show_hidden_property"]); ?></button>
                    <span style="display:none;"><?php echo escape(get_api_key($userref)); ?></span>
                </div>
                <div class="clearerleft"></div>
            </div>
            <div class="Question">
                <label><?php echo escape($lang["api-url"]); ?></label>
                <div class="Fixed"><?php echo escape($baseurl) ?>/api/</div>
                <div class="clearerleft"></div>
            </div>
        <?php } ?>

        <div class="QuestionSubmit">
            <label for="save"></label>
            <input name="save" type="submit" value="<?php echo escape($lang["save"]); ?>" />
            <div class="clearerleft"></div>
        </div>

        <?php if ($profile_image != "") { ?>
            <div class="Question">
                <label><?php echo escape($lang["current_profile"]); ?></label>
                <img src="<?php echo escape($profile_image); ?>" alt="<?php echo escape($lang['current_profile']); ?>">
                <div class="clearerleft"></div>
            </div>

            <div class="QuestionSubmit">
                <label for="delete current"></label>
                <input name="delete" type="submit" value="<?php echo escape($lang["delete_current"]); ?>" />
                <div class="clearerleft"></div>
            </div>
        <?php } ?>
    </form>
</div>

<?php
include "../../include/footer.php";

