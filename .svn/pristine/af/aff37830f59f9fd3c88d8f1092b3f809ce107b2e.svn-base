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

<h1><?php echo escape($lang["requestnewpassword"]); ?></h1>
<p><?php echo escape(text("introtextreset")); ?></p>

<form method="post" action="<?php echo $baseurl_short?>pages/user_password.php">  
    <?php generateFormToken("user_password"); ?>
    <div class="Question">
        <label for="email"><?php echo escape($lang["youremailaddress"]); ?></label>
        <input type=text name="email" id="email" class="stdwidth" value="<?php echo escape(getval("email", ""))?>">
        <div class="clearerleft"></div>
    </div>

    <div class="QuestionSubmit">    
        <input name="save" type="submit" value="<?php echo escape($lang["sendnewpassword"]); ?>" />
    </div>
</form>

<div> <!-- end of login_box -->
<?php
include "../include/footer.php";
?>
