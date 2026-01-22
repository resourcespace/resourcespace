<?php
function HookTransformAdmin_manage_slideshowRender_new_element_for_manage_slideshow(array $slideshow_files)
    {
    global $baseurl, $lang, $cropper_enable_replace_slideshow;

    if ($cropper_enable_replace_slideshow != 1)
        { 
            // if config option in transform plugin doesn't allow slideshow images to be edited, then do not show input field

    print "<p><b>To add or replace slideshow images, update the setting 'Enable replacing the slideshow' in Admin > System > Manage plugins > transform > Options.</b></p>";

            return; // if config option in transform plugin doesn't allow slideshow images to be edited, then do not show input field
        }
    $return_to_url = $baseurl . '/pages/admin/admin_manage_slideshow.php';

    // Calculate the next slideshow image ID (ie. filename will be ID.jpg)
    $last_slideshow_file = end($slideshow_files);
    $new_slideshow_id = isset($last_slideshow_file["ref"]) ? ($last_slideshow_file["ref"] + 1) : 1;
    ?>
    <div id="add_new_slideshow" class="Question">
        <span class="stdwidth">
            <button type="submit" onclick="jQuery('#new_slideshow_form').fadeIn(); return false;"><?php echo escape($lang['action-add-new']); ?></button>
            <form
                id="new_slideshow_form"
                method="POST"
                action="<?php echo $baseurl; ?>/plugins/transform/pages/crop.php"
                onsubmit="return CentralSpacePost(this);"
            >
                <?php generateFormToken("add_new_slideshow"); ?>
                <input 
                    name="ref"
                    type="text"
                    value="<?php echo escape($lang['resourceid']); ?>"
                    onfocus="if(this.value == '<?php echo escape($lang['resourceid']); ?>') { this.value = ''; }"
                    onblur="if(this.value == '') {this.value = '<?php echo escape($lang['resourceid']); ?>';}"
                >
                <input name="manage_slideshow_action" type="hidden" value="add_new">
                <input name="manage_slideshow_id" type="hidden" value="<?php echo $new_slideshow_id; ?>">
                <input name="return_to_url" type="hidden" value="<?php echo $return_to_url; ?>">
                <button type="submit"><?php echo escape($lang['action-submit-button-label']); ?></button>
            </form>
        </span>
        <div class="clearerleft"></div>
    </div>
    <?php
    }


function HookTransformAdmin_manage_slideshowRender_replace_button_for_manage_slideshow($slideshow_image, array $slideshow_file_info)
    {
    global $lang, $baseurl, $cropper_enable_replace_slideshow;

    if ($cropper_enable_replace_slideshow != 1)
        { 
            return; // if config option in transform plugin doesn't allow slideshow images to be edited, then do not show input field
        }

    ?>
    <button type="submit" onclick="jQuery('#replace_slideshow_image_form_<?php echo $slideshow_image; ?>').slideToggle(229); return false;"><?php echo escape($lang['action-replace']); ?></button>
    <?php
    if($slideshow_file_info['resource_ref'] > 0)
    {
    ?>
    <button type="submit" form="RecropSlideshowImage_<?php echo $slideshow_image; ?>"><?php echo escape($lang['transform-recrop']); ?></button>
    <form id="RecropSlideshowImage_<?php echo $slideshow_image; ?>"
          method="POST"
          action="<?php echo $baseurl; ?>/plugins/transform/pages/crop.php"
          onsubmit="return CentralSpacePost(this);">
        <?php generateFormToken("RecropSlideshowImage_{$slideshow_image}"); ?>
        <input name="ref" type="hidden" value="<?php echo $slideshow_file_info['resource_ref']; ?>">
        <input name="manage_slideshow_action" type="hidden" value="replace">
        <input name="manage_slideshow_id" type="hidden" value="<?php echo $slideshow_image; ?>">
        <input name="return_to_url" type="hidden" value="<?php echo $baseurl; ?>/pages/admin/admin_manage_slideshow.php">
    </form>
    <?php
    }
    }


function HookTransformAdmin_manage_slideshowRender_replace_slideshow_form_for_manage_slideshow($replace_slideshow_id)
    {
    global $baseurl, $lang, $cropper_enable_replace_slideshow;

    if ($cropper_enable_replace_slideshow != 1)
    {
    
        return; 
    }

    $return_to_url = $baseurl . '/pages/admin/admin_manage_slideshow.php';
    ?>
    <form
        id="replace_slideshow_image_form_<?php echo $replace_slideshow_id; ?>"
        method="POST"
        action="<?php echo $baseurl; ?>/plugins/transform/pages/crop.php"
        onsubmit="return CentralSpacePost(this);"
        style="display: none;">
        <?php generateFormToken("replace_slideshow_image_form_{$replace_slideshow_id}"); ?>
        <input
            name="ref"
            type="text"
            value="<?php echo escape($lang['resourceid']); ?>"
            onfocus="if(this.value == '<?php echo escape($lang['resourceid']); ?>') { this.value = ''; }"
            onblur="if(this.value == '') {this.value = '<?php echo escape($lang['resourceid']); ?>';}"
        >
        <input name="manage_slideshow_action" type="hidden" value="replace">
        <input name="manage_slideshow_id" type="hidden" value="<?php echo $replace_slideshow_id; ?>">
        <input name="return_to_url" type="hidden" value="<?php echo $return_to_url; ?>">
        <button type="submit"><?php echo escape($lang['action-submit-button-label']); ?></button>
    </form>
    <?php
    }
