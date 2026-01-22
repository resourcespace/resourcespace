<?php

include "../include/boot.php";
include "../include/authenticate.php";

if (getval("save", "") != "" && enforcePostRequest(false)) {
    rs_setcookie("language", getval("language", ""), 1000); # Only used if not global cookies
    rs_setcookie("language", getval("language", ""), 1000, $baseurl_short);
    rs_setcookie("language", getval("language", ""), 1000, $baseurl_short . "pages/");
    log_activity($lang["languageselection"], LOG_CODE_EDITED, getval("language", ""));
    redirect(getval("uri", $baseurl_short . "pages/" . ($use_theme_as_home ? 'collections_featured.php' : ($use_recent_as_home ? "search.php?search=!last1000" : "home.php"))));
}

include "../include/header.php";
?>

<div class="BasicsBox">
    <h1><?php echo escape($lang["languageselection"]); ?></h1>
    <p>
        <?php
        echo escape(text("introtext"));
        render_help_link('user/language-options');
        ?>
    </p>
    <form method="post" action="<?php echo $baseurl_short?>pages/change_language.php">
        <?php generateFormToken("change_language"); ?>
        <div class="Question">
            <label for="password"><?php echo escape($lang["language"]); ?></label>
            <select class="stdwidth" name="language">
                <?php reset($languages);
                foreach ($languages as $key => $value) { ?>
                    <option
                        value="<?php echo escape($key); ?>"
                        <?php if ($language == $key) { ?>
                            selected
                        <?php } ?>
                    >
                        <?php echo escape(get_display_language($key, $value)); ?>
                    </option>
                <?php } ?>
            </select>
            <div class="clearerleft"></div>
        </div>

        <div class="QuestionSubmit">    
            <input name="save" type="submit" value="<?php echo escape($lang["save"]); ?>" />
        </div>
    </form>
</div>

<?php
include "../include/footer.php";
?>
