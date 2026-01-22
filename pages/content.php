<?php

include_once "../include/boot.php";
include "../include/authenticate.php";
include "../include/header.php";

$content = getval("content", "");

if ($content != "") {
    $content = text($content);
} else {
    $content = "This is default content text. You can create text (including html) in Admin->Manage Content and display it here.";
}

$modal = getval("modal", "");

$allowed_tags = array_merge(array("a"), $permitted_html_tags);
$allowed_attributes = array_merge(array("href","target"), $permitted_html_attributes);
$content = strip_tags_and_attributes($content, $allowed_tags, $allowed_attributes);
?>

<div class="BasicsBox">
    <?php if ($modal) { ?>
        <div class="backtoresults">
            <a href="#" onclick="ModalClose();" class="closeLink icon-x" title="<?php echo escape($lang["close"]); ?>"></a>
        </div>
        <?php
    }
    echo $content;
    ?>
</div>

<?php
include "../include/footer.php";
?>
