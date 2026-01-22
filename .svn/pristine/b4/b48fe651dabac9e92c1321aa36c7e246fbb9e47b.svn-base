<?php

/**
 * Edit related keywords page (part of Team Center)
 */

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("k")) {
    exit("Permission denied.");
}

include "../../include/research_functions.php";

$keyword = strtolower(getval("keyword", ""));
$related = strtolower(getval("related", ""));

if (getval("save", "") != "" && enforcePostRequest(false)) {
    save_related_keywords($keyword, $related);
    redirect($baseurl_short . "pages/team/team_related_keywords.php?nc=" . time());
}

# Fetch existing relationships
$related = get_grouped_related_keywords("", $keyword);

if (count($related) == 0) {
    $related = "";
} else {
    $related = $related[0]["related"];
}

include "../../include/header.php";
?>

<div class="BasicsBox">
    <h1>
        <?php
        echo escape($lang["managerelatedkeywords"]);
        render_help_link('resourceadmin/related-keywords');
        ?>
    </h1>

    <form method=post id="mainform" action="<?php echo $baseurl_short?>pages/team/team_related_keywords_edit.php">
        <?php generateFormToken("mainform"); ?>
        <input type="hidden" name="keyword" value="<?php echo escape($keyword)?>">

        <div class="Question">
            <label><?php echo escape($lang["keyword"])?></label>
            <div class="Fixed"><?php echo escape($keyword)?></div>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label><?php echo escape($lang["relatedkeywords"])?></label>
            <textarea name="related" class="stdwidth" rows=5 cols=50><?php echo escape($related)?></textarea>
            <div class="clearerleft"></div>
        </div>

        <div class="QuestionSubmit">        
            <input name="save" type="submit" value="<?php echo escape($lang["save"])?>" />
        </div>
    </form>
</div>

<?php
include "../../include/footer.php";