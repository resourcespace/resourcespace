<?php
include '../../include/boot.php';
include '../../include/authenticate.php';

if (!checkperm('a')) {
    header('HTTP/1.1 401 Unauthorized');
    exit('Permission denied.');
}

include '../../include/slideshow_functions.php';

$slideshow_files = get_slideshow_files_data();

$ajax         = getval('ajax', '');
$action       = getval('action', '');
$slideshow_id = getval('slideshow_id', null, true);
$manageurl = "{$baseurl}/pages/admin/admin_manage_slideshow.php";

/* Re-order */
if (
    'true' === $ajax
    && ('moveup' === $action || 'movedown' === $action)
    && !is_null($slideshow_id)
    && enforcePostRequest($ajax)
) {
    header('Content-Type: application/json');
    $response['sibling']          = null;
    $response['is_first_sibling'] = false;
    $response['is_last_sibling']  = false;

    $allow_reorder = false;
    $slideshow_id_index = array_search($slideshow_id, array_column($slideshow_files, 'ref'));

    if ($slideshow_id_index === false) {
        http_response_code(500);
        $response['error']   = "{$lang["error-failed-to-move"]} {$lang['slideshow-image']} #{$slideshow_id}";
        $response['success'] = false;

        echo json_encode($response);
        exit();
    }

    // Set current slideshow entry to the entry which has the id to be moved
    reset($slideshow_files);
    while (current($slideshow_files) !== $slideshow_files[$slideshow_id_index]) {
        next($slideshow_files);
    }

    if (count($slideshow_files) > 1) {
        // Based on current pointer and direction of movement we can find the "to" element
        switch ($action) {
            case 'moveup':
                prev($slideshow_files);
                $to = key($slideshow_files);

                reset($slideshow_files);
                $response['is_first_sibling'] = ($slideshow_files[$to] == current($slideshow_files));

                $allow_reorder = true;
                break;

            case 'movedown':
                next($slideshow_files);
                $to = key($slideshow_files);

                $response['is_last_sibling'] = ($slideshow_files[$to] === end($slideshow_files));

                $allow_reorder = true;
                break;
        }
    }

    if ($allow_reorder && reorder_slideshow_images($slideshow_files[$slideshow_id_index], $slideshow_files[$to])) {
        $response['sibling'] = $slideshow_files[$to]["ref"];
    }

    echo json_encode($response);
    exit();
}

/* Delete */
if ('true' === $ajax && 'delete' === $action && !is_null($slideshow_id) && enforcePostRequest($ajax)) {
    $response['error']   = '';
    $response['success'] = true;

    $slideshow_id_index = array_search($slideshow_id, array_column($slideshow_files, 'ref'));
    if ($slideshow_id_index !== false) {
        $slideshow_file_info = $slideshow_files[$slideshow_id_index];
    } else {
        $slideshow_file_info = array();

        http_response_code(500);
        $response['error']   = "{$lang['error-failed-to-delete']} {$lang['slideshow-image']} #{$slideshow_id}";
        $response['success'] = false;
    }

    if (!empty($slideshow_file_info) && !delete_slideshow($slideshow_file_info['ref'])) {
        http_response_code(500);
        $response['error']   = "{$lang['error-failed-to-delete']} '{$slideshow_file_info['file_path']}'";
        $response['success'] = false;
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

/*
Set slideshow flags
===================
Available options:
 - homepage_show
 - login_show
*/
if ($ajax === 'true' && $action == 'set_flag' && enforcePostRequest($ajax)) {
    $slideshow_id_index = array_search($slideshow_id, array_column($slideshow_files, 'ref'));
    if ($slideshow_id_index !== false) {
        $slideshow = $slideshow_files[$slideshow_id_index];
    }

    $update_status = false;
    $flag = getval('flag', '');
    $value = getval('value', false, true);

    if ($value !== false && $flag != '') {
        $slideshow[$flag] = $value;

        $update_status = set_slideshow(
            $slideshow_id,
            $slideshow['resource_ref'],
            $slideshow['homepage_show'],
            $slideshow['login_show']
        );
    }

    if ($update_status !== false) {
        http_response_code(200);
        exit();
    }

    http_response_code(400);
    exit();
}

if ('true' === $ajax && getval("static", "") != "") {
    if (getval("static", "") == "true") {
        set_config_option(null, 'static_slideshow_image', true);
    } else {
        set_config_option(null, 'static_slideshow_image', false);
    }
}

include '../../include/header.php';
?>

<style>
    button:disabled,
    button[disabled] {
        color: #666666;
        cursor: not-allowed;
        background: white;
        box-shadow: unset;
    }
</style>

<div class="BasicsBox">
    <h1><?php echo escape($lang["manage_slideshow"]); ?></h1>
    <?php
    $links_trail = array(
        array(
            'title' => $lang["systemsetup"],
            'href'  => $baseurl_short . "pages/admin/admin_home.php",
            'menu' =>  true
        ),
        array(
            'title' => $lang["manage_slideshow"]
        )
    );

    renderBreadcrumbs($links_trail);
    ?>

    <p>
        <?php
        echo escape($lang['manage-slideshow-instructions']);
        render_help_link("resourceadmin/homepage-slideshow");
        ?>
    </p>

    <div class="Listview">
        <table class="ListviewStyle">
            <tbody>
                <tr class="ListviewTitleStyle">
                    <th><?php echo escape($lang["preview"]); ?></th>
                    <th><?php echo escape($lang["home_page"]); ?></th>
                    <th><?php echo escape($lang["login_word"]); ?></th>
                    <th><?php echo escape($lang["tools"]); ?></th>
                </tr>

                <?php
                foreach ($slideshow_files as $slideshow_index => $slideshow_file_info) {
                    $moveup_disabled = '';
                    $movedown_disabled = '';

                    if ($slideshow_index == 0 || count($slideshow_files) == 1) {
                        $moveup_disabled = ' disabled';
                    }

                    if (($slideshow_index == (count($slideshow_files) - 1)) || count($slideshow_files) == 1) {
                        $movedown_disabled = ' disabled';
                    }

                    $delete_btn_disabled = '';
                    if (count($slideshow_files) == 1) {
                        $delete_btn_disabled = ' disabled';
                    }

                    $homepage_show = ($slideshow_file_info['homepage_show'] == 1 ? 'checked' : '');
                    $login_show = ($slideshow_file_info['login_show'] == 1 ? 'checked' : '');
                    $slideshow_ref = $slideshow_file_info['ref'];
                    ?>

                    <tr id="slideshow_<?php echo $slideshow_ref; ?>">
                        <td>
                            <?php if (isset($slideshow_file_info['link'])) { ?>
                                <a href="<?php echo $slideshow_file_info['link']; ?>" onclick="return ModalLoad(this, true);">
                                    <img 
                                        id="slideshow_img_<?php echo $slideshow_ref; ?>"
                                        src="<?php echo $slideshow_file_info['file_url']; ?>"
                                        alt="Slideshow Image <?php echo $slideshow_ref; ?>"
                                        width="150"
                                        height="80"
                                    >
                                </a>
                            <?php } else { ?>
                                <img
                                    id="slideshow_img_<?php echo $slideshow_ref; ?>"
                                    src="<?php echo $slideshow_file_info['file_url']; ?>"
                                    alt="Slideshow Image <?php echo $slideshow_ref; ?>"
                                    width="150"
                                    height="80"
                                >
                            <?php } ?>
                        </td>

                        <td>
                            <input
                                type="checkbox"
                                name="homepage_show"
                                value="1"
                                onclick="SetSlideshowFlag(this);"
                                <?php echo $homepage_show; ?>
                            >
                        </td>

                        <td>
                            <input
                                type="checkbox"
                                name="login_show"
                                value="1"
                                onclick="SetSlideshowFlag(this);"
                                <?php echo $login_show; ?>
                            >
                        </td>

                        <td>
                            <button
                                id="slideshow_<?php echo $slideshow_ref; ?>_moveup"
                                type="submit" slideMoveUpButton
                                onclick="ReorderSlideshowImage(this, 'moveup');"
                                <?php echo $moveup_disabled; ?>>
                                <?php echo escape($lang['action-move-up']); ?>
                            </button>
                            <button
                                id="slideshow_<?php echo $slideshow_ref; ?>_movedown"
                                type="submit" slideMoveDownButton 
                                onclick="ReorderSlideshowImage(this, 'movedown');"
                                <?php echo $movedown_disabled; ?>>
                                <?php echo escape($lang['action-move-down']); ?>
                            </button>
                            <?php hook('render_replace_button_for_manage_slideshow', '', array($slideshow_ref, $slideshow_file_info)); ?>
                            <button
                                id="slideshow_<?php echo $slideshow_ref; ?>_delete"
                                type="submit" slideDeleteButton
                                onclick="DeleteSlideshowImage(this);"
                                <?php echo $delete_btn_disabled; ?>>
                                <?php echo escape($lang['action-delete']); ?>
                            </button>
                            <?php hook('render_replace_slideshow_form_for_manage_slideshow', '', array($slideshow_ref, $slideshow_files)); ?>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
    </div>

    <div id="slideshow_static_image" class="Question">
        <label for="slideshow_static_image_checkbox"><?php echo escape($lang["slideshow_use_static_image"]); ?></label>
        <input
            type="checkbox"
            name="slideshow_static_image"
            id="slideshow_static_image_checkbox"
            <?php echo ($static_slideshow_image) ? "checked" : ''; ?>
            onchange="if(this.checked){jQuery.get('<?php echo $manageurl ?>?ajax=true&static=true');}else{jQuery.get('<?php echo $manageurl ?>?ajax=true&static=false');}">
        </input>
        <div class="clearerleft"></div>
    </div>
    <?php hook('render_new_element_for_manage_slideshow', '', array($slideshow_files)); ?>
</div>

<script>
    function SetSlideshowFlag(element) {
        var input = jQuery(element);

        // Extract reference from row identifier r from "slideshow_r"
        var slideshow_row_id = element.parentElement.parentElement.id.substr(10);

        var flag_value = 0;
        if (element.checked) {
            flag_value = 1;
        }

        var post_url  = '<?php echo $manageurl; ?>';
        var post_data = {
            ajax: true,
            action: 'set_flag',
            slideshow_id: slideshow_row_id,
            flag: input.attr('name'),
            value: flag_value,
            <?php echo generateAjaxToken("SetSlideshowFlag"); ?>
        };

        CentralSpaceShowProcessing();

        jQuery.ajax({
            type: 'POST',
            url: post_url,
            data: post_data,
        }).fail(function(data, textStatus, jqXHR) {
            styledalert(data.status, data.statusText);
        }).always(function() {
            CentralSpaceHideProcessing();
        });

        return false;
    }

    function ReorderSlideshowImage(element, direction) {
        var post_url  = '<?php echo $manageurl ?>';

        // Extract reference from row identifier r from "slideshow_r"
        var slideshow_row_id = element.parentElement.parentElement.id.substr(10);

        var post_data = {
            ajax: true,
            action: direction,
            slideshow_id: slideshow_row_id,
            <?php echo generateAjaxToken("ReorderSlideshowImage"); ?>
        };

        jQuery.post(post_url, post_data, function(response) {
            if (response.sibling !== false) {
                // Establish row elements and their corresponding button elements
                var moving_row      = jQuery('#slideshow_' + slideshow_row_id);
                var moving_moveup   = jQuery('#slideshow_' + slideshow_row_id + '_moveup');
                var moving_movedown = jQuery('#slideshow_' + slideshow_row_id + '_movedown');
                var target_row      = jQuery('#slideshow_' + response.sibling);
                var target_moveup   = jQuery('#slideshow_' + response.sibling + '_moveup');
                var target_movedown = jQuery('#slideshow_' + response.sibling + '_movedown');
                
                // Swap rows
                if(direction == 'moveup')
                    {
                    jQuery(moving_row).insertBefore(target_row);
                }
                else // movedown
                    {
                    jQuery(moving_row).insertAfter(target_row);
                }

                // Swap row identifiers
                jQuery(moving_row).attr("id","slideshow_"+response.sibling);
                jQuery(target_row).attr("id","slideshow_"+slideshow_row_id);
                
                ResetSlideshowButtons();
            }
        }, 'json').fail(function(data, textStatus, jqXHR) {
            styledalert(data.statusText, data.responseText);
        });

        return false;
    }

    function DeleteSlideshowImage(element) {
        var post_url  = '<?php echo $manageurl ?>';

        // Extract reference from row identifier r from "slideshow_r"
        var slideshow_row_id = element.parentElement.parentElement.id.substr(10);

        var post_data = {
            ajax: true,
            action: 'delete',
            slideshow_id: slideshow_row_id,
            <?php echo generateAjaxToken("DeleteSlideshowImage"); ?>
        };

        jQuery.post(post_url, post_data, function(response) {
            if (response.success) {
                jQuery('#slideshow_' + slideshow_row_id).remove();
                ResetSlideshowButtons();
            }
        }, 'json').fail(function(data, textStatus, jqXHR) {
            styledalert(data.statusText, data.responseJSON.error);
        });

        return false;
        }

    function ResetSlideshowButtons() {
        // Re-establish move button availability
        jQuery("[slideMoveUpButton]").prop("disabled",false);
        jQuery("[slideMoveDownButton]").prop("disabled",false);
        // Cannot move first row up
        jQuery("[slideMoveUpButton]:first").prop("disabled",true);
        // Cannot move last row down
        jQuery("[slideMoveDownButton]:last").prop("disabled",true);

        // Disable delete button if only one slide present
        if (jQuery("[slideDeleteButton]").length == 1) {
            jQuery("[slideDeleteButton]").prop("disabled",true);
        }
    }
</script>

<?php
include '../../include/footer.php';
