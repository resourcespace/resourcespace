<?php
include_once "../include/boot.php";

if (!hook("authenticate")) {
    include "../include/authenticate.php";
}

include "../include/header.php";
?>

<div class="BasicsBox">
    <h1><?php echo escape($lang["contactus"]); ?></h1>
    <p><?php echo strip_tags_and_attributes(text("contact"), ['a'], ['href']); ?></p>
</div>

<?php
include "../include/footer.php";