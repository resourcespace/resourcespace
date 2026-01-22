<?php
include "../include/boot.php";

# External access support (authenticate only if no key provided, or if invalid access key provided)
$k = getval("k", "");
if (($k == "") || (!check_access_key_collection(getval("collection", "", true), $k))) {
    include "../include/authenticate.php";
}

$collection = getval("collection", "", true);
$errors = "";
$done = false;

# Fetch collection data
$cinfo = get_collection($collection);
if ($cinfo === false) {
    exit("Collection not found.");
}

# Check access
if (!collection_readable($collection)) {
    exit($lang["no_access_to_collection"]);
}

if (!$cinfo["request_feedback"]) {
    exit("Access denied.");
}

# Check that comments have been added.
$comments = get_collection_comments($collection);
global $internal_share_access, $userfullname;

if (
    $collection_commenting
    && ($k == '' || $internal_share_access)
    && count($comments) == 0
    && !$feedback_resource_select
) {
    $errors = $lang["feedbacknocomments"];
}

$comment = "";

if (getval("save", "") != "" && enforcePostRequest(false)) {
    # Save comment
    if (empty($userfullname) && $k !== '') {
        $userfullname = getval('name', '');
    }
    $comment = trim(getval("comment", ""));
    $saveerrors = send_collection_feedback($collection, $comment);
    if (is_array($saveerrors)) {
        foreach ($saveerrors as $saveerror) {
            if ($errors == "") {
                $errors = $saveerror;
            } else {
                $errors .= "<br /><br /> " . $saveerror;
            }
        }
    } else {
        # Stay on this page for external access users (no access to search)
        refresh_collection_frame();
        $done = true;
    }
}

$headerinsert .= "<script src=\"../lib/lightbox/js/lightbox.min.js\" type=\"text/javascript\"></script>";
$headerinsert .= "<link type=\"text/css\" href=\"../lib/lightbox/css/lightbox.min.css?css_reload_key=" . $css_reload_key . "\" rel=\"stylesheet\">";

include "../include/header.php";

if ($errors != "") {
    echo "<script>alert('" .  str_replace(array("<br />","<br/>","<br />"), "\\n\\n", $errors) . "');</script>";
}
?>

<div class="BasicsBox">
    <h1><?php echo escape($lang["sendfeedback"])?></h1>

    <?php if ($done) { ?>
        <p><?php echo escape($lang["feedbacksent"])?></p>
    <?php } else { ?>
        <form method="post" action="<?php echo $baseurl_short?>pages/collection_feedback.php">
            <?php generateFormToken("collection_feedback"); ?>
            <input type="hidden" name="k" value="<?php echo escape($k); ?>">
            <input type="hidden" name="collection" value="<?php echo escape($collection); ?>">

            <p>
                <a
                    class="downloadcollection"
                    href="<?php echo $baseurl_short?>pages/collection_download.php?collection=<?php echo urlencode($collection)?>&k=<?php echo urlencode($k)?>"
                    onclick="return CentralSpaceLoad(this,true);"
                >
                    <?php echo LINK_CARET . escape($lang["download_collection"]); ?>
                </a>
            </p>

            <?php if ($feedback_resource_select) { ?>
                <h2><?php echo escape($lang["selectedresources"])?>:</h2>
                <?php
                # Show thumbnails and allow the user to select resources.
                $result = do_search("!collection" . $collection, "", "resourceid", 0, -1, "desc");

                for ($n = 0; $n < count($result); $n++) {
                    $ref = $result[$n]["ref"];
                    $access = get_resource_access($ref);
                    $use_watermark = check_use_watermark();
                    $title = $ref . " : " . escape(tidy_trim(i18n_get_translated($result[$n]["field" . $view_title_field]), 60));

                    if (isset($collection_feedback_display_field)) {
                        $displaytitle = escape(get_data_by_field($ref, $collection_feedback_display_field));
                    } else {
                        $displaytitle = $title;
                    }
                    ?>
                    <!--Resource Panel-->
                    <div class="ResourcePanelShell" id="ResourceShell<?php echo urlencode($ref)?>">
                        <div class="ResourcePanel">
                            <table border="0" class="ResourceAlign">
                                <tr>
                                    <td>
                                        <?php
                                        if ($result[$n]["has_image"] == 1) {
                                            $path = get_resource_path($ref, true, "scr", false, $result[$n]["preview_extension"], -1, 1, $use_watermark, $result[$n]["file_modified"]);
                                            if (file_exists($path)) {
                                                # Use 'scr' path
                                                $path = get_resource_path($ref, false, "scr", false, $result[$n]["preview_extension"], -1, 1, $use_watermark, $result[$n]["file_modified"]);
                                            } elseif (!file_exists($path)) {
                                                # Attempt original file if jpeg
                                                $path = get_resource_path($ref, false, "", false, $result[$n]["preview_extension"], -1, 1, $use_watermark, $result[$n]["file_modified"]);
                                            }
                                            ?>
                                            <a class="lightbox-feedback" href="<?php echo escape($path)?>" title="<?php echo escape($displaytitle); ?>">
                                                <img
                                                    alt="<?php echo escape(i18n_get_translated($result[$n]['field' . $view_title_field] ?? "")); ?>"
                                                    width="<?php echo (int) $result[$n]["thumb_width"]; ?>"
                                                    height="<?php echo (int) $result[$n]["thumb_height"]; ?>"
                                                    src="<?php echo escape(get_resource_path($ref, false, "thm", false, $result[$n]["preview_extension"], -1, 1, (checkperm("w") || ($k != "" && $watermark !== "")) && $access == 1, $result[$n]["file_modified"]))?>"
                                                    class="ImageBorder"
                                                >
                                            </a>
                                            <?php
                                        } else {
                                            echo get_nopreview_html((string) $result[$n]["file_extension"], $result[$n]["resource_type"]);
                                        }
                                        ?>
                                    </td>
                                </tr>
                            </table>
                            <span class="ResourceSelect">
                                <input type="checkbox" name="select_<?php echo urlencode($ref) ?>" value="yes">
                            </span>
                            <div class="ResourcePanelInfo"><?php echo escape($displaytitle) ?>&nbsp;</div>
                            <div class="clearer"></div>
                        </div>
                    </div>
                    <?php
                }
                ?>
                <div class="clearer"></div>
                <?php
            }
            ?>

            <div class="Question">
                <?php if ($errors != "") { ?>
                    <div class="FormError"><?php echo $errors?></div>
                <?php } ?>
                <label for="comment"><?php echo escape($lang["message"])?></label>
                <textarea class="stdwidth" style="width:450px;" rows=20 cols=80 name="comment" id="comment"><?php echo escape($comment) ?></textarea>
                <div class="clearerleft"></div>
            </div>

            <?php if (!isset($userfullname)) {
                # For external users, ask for their name/e-mail in case this has been passed to several users.
                ?>
                <div class="Question">
                    <label for="name"><?php echo escape($lang["yourname"]); ?></label>
                    <input type="text" class="stdwidth" name="name" id="name" value="<?php echo escape(getval("name", "")); ?>">
                    <div class="clearerleft"></div>
                </div>

                <div class="Question">
                    <label for="email"><?php echo escape($lang["youremailaddress"]); ?> *</label>
                    <input type="text" class="stdwidth" name="email" id="email" value="<?php echo escape(getval("email", "")); ?>">
                    <div class="clearerleft"></div>
                </div>
                <?php
            }
            ?>

            <div class="QuestionSubmit">        
                <input name="save" type="submit" value="<?php echo escape($lang["send"])?>" />
            </div>
        </form>
    <?php } ?>
</div>

<?php
if ($feedback_resource_select) {
    addLightBox('.lightbox-feedback');
}
include "../include/footer.php";
?>
