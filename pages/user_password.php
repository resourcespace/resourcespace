<?php
include "../include/boot.php";

if (!$allow_password_reset) {
    exit("Password requests have been disabled.");
} # User should never see this.

if (getval("save", "") != "" && enforcePostRequest(false)) {
    email_reset_link(getval("email", ""));
    redirect("pages/done.php?text=user_password_link_sent");
}

include "../include/header.php";
include "../include/login_background.php";
?>

<?php $header_img_src = get_header_image(); ?>

<div id="LoginHeader">
    <img src="<?php echo $header_img_src; ?>" class="LoginHeaderImg" alt="<?php echo $applicationname; ?>">
</div>

<a class="text-link" href="<?php echo $baseurl_short; ?>login.php">
    <i class="icon-arrow-left"></i><?php echo escape($lang["back_to_login"]); ?>
</a>

<div id="login_box">
    <form method="post" action="<?php echo $baseurl_short?>pages/user_password.php">  
        <?php generateFormToken("user_password"); ?>
        <div>
            <div class="field-text-only">
                <label><?php echo escape($lang["requestnewpassword"]); ?></label>
                <?php echo escape(text("introtextreset")); ?>
            </div>

            <div class="field-input">
                <label for="email"><?php echo escape($lang["youremailaddress"]); ?></label>
                <input type=text name="email" id="email" value="<?php echo escape(getval("email", "")); ?>">
            </div>

            <div class="button">    
                <input name="save" type="submit" value="<?php echo escape($lang["sendnewpassword"]); ?>" />
            </div>
        </div>
    </form>
</div>

</div><!-- Close CentralSpaceLogin -->

<div id="login-slideshow"></div>
<?php
include "../include/footer.php";
?>
