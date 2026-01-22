<?php

/**
 * Edit content strings page (part of System area)
 */

include "../../include/boot.php";
include "../../include/authenticate.php";

if (!checkperm("o")) {
    exit("Permission denied.");
}

include "../../include/research_functions.php";

$offset        = getval('offset', 0);
$page          = getval('page', '');
$name          = getval('name', '');
$findpage      = getval('findpage', '');
$findname      = getval('findname', '');
$findtext      = getval('findtext', '');
$newhelp       = getval('newhelp', '');
$editlanguage  = getval('editlanguage', $language);
$editgroup     = getval('editgroup', '');
$save          = getval('save', '');
$text          = getval('text', '');

// Validate HTML
$html_validation = validate_html($text);

# get custom value from database, unless it has been newly passed from admin_content.php
if (getval('custom', '') == 1) {
    $custom    = 1;
    $newcustom = true;
} else {
    $custom    = check_site_text_custom($page, $name);
    $newcustom = false;
}

if (($save != '') && getval('langswitch', '') == '' && $html_validation === true && enforcePostRequest(false)) {
    # Save data
    save_site_text($page, $name, $editlanguage, $editgroup);
    if (
        $newhelp != ''
        && getval('returntolist', '') == ''
    ) {
            redirect($baseurl_short . "pages/admin/admin_content_edit.php?page=help&name=" . urlencode($newhelp) . "&offset=" . urlencode($offset) . "&findpage=" . urlencode($findpage) . "&findname=" . urlencode($findname) . "&findtext=" . urlencode($findtext));
    }
    if (
        getval('custom', '') == 1
        && getval('returntolist', '') == ''
    ) {
            redirect($baseurl_short . "pages/admin/admin_content_edit.php?page=" . urlencode($page) . "&name=" . urlencode($name) . "&offset=" . urlencode($offset) . "&findpage=" . urlencode($findpage) . "&findname=" . urlencode($findname) . "&findtext=" . urlencode($findtext));
    }
    if (getval('returntolist', '') != '') {
        redirect($baseurl_short . "pages/admin/admin_content.php?nc=" . time() . "&findpage=" . urlencode($findpage) . "&findname=" . urlencode($findname) . "&findtext=" . urlencode($findtext) . "&offset=" . urlencode($offset));
    }
}

// Need to save $lang and $language so we can revert after finding specific text
$langsaved = $lang;
$languagesaved = $language;

$text        = get_site_text($page, $name, $editlanguage, $editgroup);
$defaulttext = get_site_text($page, $name, $defaultlanguage, '');

# Default text? Show that this is the case
$text_default = false;
if ($text == $defaulttext && ($editlanguage != $defaultlanguage || $editgroup != '')) {
    $text_default = true;
}

// Revert to original values
$lang = $langsaved;
$language = $languagesaved;

include "../../include/header.php";

if ($save != '') {
    toast_notification(ToastNotificationType::Success, $lang['saved']);
}
?>

<div class="BasicsBox">
    <h1><?php echo escape($lang["editcontent"]); ?></h1>

    <?php
    $links_trail = array(
        array(
            'title' => $lang["systemsetup"],
            'href'  => $baseurl_short . "pages/admin/admin_home.php",
            'menu' =>  true
        ),
        array(
            'title' => $lang["managecontent"],
            'href'  => $baseurl_short . "pages/admin/admin_content.php?nc=" . time() . "&findpage=" . urlencode($findpage) . "&findname=" . urlencode($findname) . "&findtext=" . urlencode($findtext) . "&offset=" . urlencode($offset)
        ),
        array(
            'title' => $lang["editcontent"]
        )
    );

    renderBreadcrumbs($links_trail);

    if ($html_validation !== true && $html_validation !== '') { ?>
        <div class="PageInformal"><?php echo escape($lang['error_check_html_first']); ?></div>
        <?php
    } ?>

    <form method="post" id="mainform" action="<?php echo $baseurl_short; ?>pages/admin/admin_content_edit.php?page=<?php echo urlencode($page);?>&name=<?php echo urlencode($name);?>&editlanguage=<?php echo urlencode($editlanguage);?>&editgroup=<?php echo urlencode($editgroup);?>&findpage=<?php echo urlencode($findpage)?>&findname=<?php echo urlencode($findname)?>&findtext=<?php echo urlencode($findtext)?>&offset=<?php echo urlencode($offset)?>">
        <?php generateFormToken("mainform"); ?>
        <input type=hidden name=page value="<?php echo escape($page)?>">
        <input type=hidden name=name value="<?php echo escape($name)?>">
        <input type=hidden name=copyme id="copyme" value="">
        <input type=hidden name=langswitch id="langswitch" value="">
        <input type=hidden name=groupswitch id="groupswitch" value="">
        <input type="hidden" name="custom" value="<?php echo getval('custom', 0, true)?>">

        <div class="Question">
            <label><?php echo escape($lang["page"]); ?></label>
            <div class="Fixed"><?php echo escape(($page == "" ? $lang["all"] : $page)) ?></div>
            <div class="clearerleft"></div>
        </div>

        <?php if ($page == 'help') { ?>
            <div class="Question">
                <label for="name"><?php echo escape($lang["name"]); ?></label>
                <input type=text name="name" class="stdwidth" value="<?php echo escape($name)?>">
                <div class="clearerleft"></div>
            </div>
        <?php } else { ?>
            <div class="Question">
                <label><?php echo escape($lang["name"]); ?></label>
                <div class="Fixed"><?php echo escape($name) ?></div>
                <div class="clearerleft"></div>
            </div>
        <?php } ?>

        <div class="Question">
            <label for="editlanguage"><?php echo escape($lang["language"]); ?></label>
            <select class="stdwidth" name="editlanguage" onchange="document.getElementById('langswitch').value='yes';document.getElementById('mainform').submit();">
                <?php foreach ($languages as $key => $value) { ?>
                    <option value="<?php echo $key?>" <?php echo ($editlanguage == $key) ? "selected" : ''; ?>>
                        <?php echo $value; ?>
                    </option>
                <?php } ?>
            </select>
            <div class="clearerleft"></div>
        </div>

        <div class="Question">
            <label for="editgroup"><?php echo escape($lang["group"]); ?></label>
            <select class="stdwidth" name="editgroup" onchange="document.getElementById('groupswitch').value='yes';document.getElementById('copyme').value='yes';document.getElementById('mainform').submit();">
                <option value=""></option>
                <?php
                $groups = get_usergroups();
                for ($n = 0; $n < count($groups); $n++) {
                    ?>
                    <option value="<?php echo $groups[$n]["ref"]; ?>" <?php echo ($editgroup == $groups[$n]["ref"]) ? "selected" : ''; ?>>
                        <?php echo $groups[$n]["name"]; ?>
                    </option>
                    <?php
                }
                ?>
            </select>
            <div class="clearerleft"></div>
        </div>

        <?php
        # Default text? Show that this is the case
        if ($text_default) {
            render_fixed_text_question($lang["default"], str_replace("?", $languages[$defaultlanguage], $lang['managecontent_defaulttextused']));
        }
        ?>

        <div class="Question">
                <label for="text"><?php echo escape($lang['text']); ?></label>
                <textarea id="text" class="stdwidth" name="text" rows=15 cols=50><?php echo escape($text); ?></textarea>
            <div class="clearerleft"></div>
        </div>

        <?php
        # Add special ability to create and remove help pages
        if ($page == 'help') {
            if ($name != 'introtext') {
                ?>
                <div class="Question">
                    <label for="deleteme"><?php echo escape($lang["ticktodeletehelp"]); ?></label>
                    <input id="deleteme" class="deleteBox" name="deleteme" type="checkbox" value="yes">
                    <div class="clearerleft"></div>
                </div>
                <?php
            }
            ?>
            <br />
            <br />
            <div class="Question">
                <label for="newhelp"><?php echo escape($lang["createnewhelp"]); ?></label>
                <input name="newhelp" type=text value="" />
                <div class="clearerleft"></div>
            </div>
            <?php
        }

        # Add ability to delete custom page/name entries
        if ($custom == 1 && $page != 'help') {
            ?>
            <div class="Question">
                <label for="deletecustom"><?php echo escape($lang["ticktodeletehelp"]); ?></label>
                <input id="deletecustom" class="deleteBox" name="deletecustom" type="checkbox" value="yes" />
                <div class="clearerleft"> </div>
            </div>
            <?php
        }
        ?>

        <input type=hidden id="returntolist" name="returntolist" value=""/>
        <div id="submissionResponse"></div>
        <div class="QuestionSubmit">
            <label for="save"></label>
            <input type="submit" name="checkhtml" id="checkhtml" value="Check HTML" />
            <input type="submit" name="save" value="<?php echo escape($lang["save"]); ?>" />
            <input type="submit" name="save" value="<?php echo escape($lang['saveandreturntolist']); ?>" onClick="jQuery('#returntolist').val(true);" />
        </div>
    </form>
</div><!-- End of BasicsBox -->

<script>
    // When to take us back to manage content list
    jQuery('#deleteme, #deletecustom').change(function() {
        if (jQuery(this).is(':checked')) {
            jQuery('#returntolist').val(true);
        } else {
            jQuery('#returntolist').val(null);
        }
    });

    // Manually check HTML:
    jQuery('#checkhtml').click(function(e) {
        var checktext = jQuery('#text').val();

        jQuery.post(
            '../tools/check_html.php', 
            {
                'text': checktext, 
                <?php echo generateAjaxToken('admin_content_edit'); ?>,
            }, function(response, status, xhr) {
                CentralSpaceHideProcessing();
                jQuery('#submissionResponse').html(response);
            }
        );
        e.preventDefault();
    });
</script>
<?php
include "../../include/footer.php";
