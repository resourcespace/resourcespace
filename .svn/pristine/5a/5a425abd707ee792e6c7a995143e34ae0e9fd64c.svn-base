<?php
include '../include/boot.php';
include '../include/authenticate.php';
include_once '../include/pdf_functions.php';

$collection         = getval('ref', '', true);
$collectiondata     = get_collection($collection);
$ajax               = ('true' == getval('ajax', '') ? true : false);
$sheetstyle         = getval('sheetstyle', 'list');
$field_value_limit  = getval('field_value_limit', 0);
$filename_uid       = generateUserFilenameUID($userref);
$error              = getval("error", "");
$order_by           = getval("order_by", "");

if ($contactsheet_use_field_templates && !isset($contactsheet_field_template)) {
    $contactsheet_use_field_templates = false;
}

$templates = get_pdf_templates("contact_sheet");

if ($contactsheet_use_field_templates) {
    $field_template = getval('field_template', 0, true);
    $sheetstyle_fields = $contactsheet_field_template[$field_template]['fields'];
} else {
    switch ($sheetstyle) {
        case 'thumbnails':
            $sheetstyle_fields = $config_sheetthumb_fields;
            break;

        case 'list':
            $sheetstyle_fields = $config_sheetlist_fields;
            break;

        case 'single':
            $sheetstyle_fields = $config_sheetsingle_fields;
            break;
    }
}

/* Depending on the style, users get different fields to select from.
Super Admins decide what fields they can see based on config options (e.g. $config_sheetthumb_fields)and permissions
Note: By default we use thumbnails fields
*/

$available_contact_sheet_fields = array();

if (!$contactsheet_use_field_templates) {
    $available_contact_sheet_fields[] = array(
        'ref'   => '',
        'title' => $lang['allfields']
    );
}

foreach (get_fields($sheetstyle_fields, true) as $field_data) {
    $available_contact_sheet_fields[] = $field_data;
}

if ($ajax && 'get_sheetstyle_fields' == getval('action', '')) {
    $response = array();

    foreach ($available_contact_sheet_fields as $field_data) {
        $response[] = array(
            'ref'   => $field_data['ref'],
            'title' => i18n_get_translated($field_data['title']),
        );
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

include '../include/header.php';
?>

<div class="BasicsBox" >
    <h1><?php echo escape($lang['contactsheetconfiguration']); ?></h1>

    <?php
    # Check access
    if (!collection_readable($collection)) {
        echo escape($lang["no_access_to_collection"]);
        echo "</div></div>";
        include "../include/footer.php";
        exit();
    }
    ?>

    <p>
        <?php
        echo strip_tags_and_attributes($lang["contactsheetintrotext"]);
        render_help_link("user/contact-sheet");
        ?>
    </p>
    
    <?php if ($error != "" && isset($lang[$error])) {
        echo "<div class='PageInformal' name='error' id='error'>" . escape($lang[$error]) . "</div>";
    } ?>

    <!-- each time the form is modified, the variables are sent to contactsheet.php with preview=true
    contactsheet.php makes just the first page of the pdf (with col size images) 
    and then thumbnails it for the ajax request. This creates a very small but helpful 
    preview image that can be judged before initiating a download of sometimes several MB.-->
    <form method="post" name="contactsheetform" id="contactsheetform" action="<?php echo $baseurl_short; ?>pages/ajax/contactsheet.php">
        <?php generateFormToken("contactsheetform"); ?>
        <input type=hidden name="c" value="<?php echo escape($collection); ?>">
        <input type=hidden name="field_value_limit" value="<?php echo escape($field_value_limit); ?>">
        <?php
        if (!$contactsheet_sorting) {
            ?>
            <input type=hidden name="order_by" value="<?php echo escape($order_by); ?>">
            <?php
        }
        ?>
        <div class="BasicsBox" style="width:450px;float:left;margin-top:0;" >
            <div id="contact-sheet--collectionname" class="Question">
                <label><?php echo escape($lang["collectionname"]); ?></label>
                <span><?php echo i18n_get_collection_name($collectiondata); ?></span>
                <div class="clearerleft"></div>
            </div>

            <div class="Question">
                <label for="sheetstyle"><?php echo escape($lang["display"]); ?></label>
                <select
                    class="shrtwidth"
                    name="sheetstyle"
                    id="sheetstyle"
                    onchange="
                        if (jQuery('#sheetstyle').val()=='list') {
                            document.getElementById('OrientationOptions').style.display='block';        
                            document.getElementById('ThumbnailOptions').style.display='none';
                            if (document.getElementById('size_options')) {
                                document.getElementById('size_options').style.display='none';
                            }
                            updateAvailableContactSheetFields('list');
                        } else if (jQuery('#sheetstyle').val()=='single') {
                            document.getElementById('ThumbnailOptions').style.display='none';
                            if (document.getElementById('size_options')) {
                                document.getElementById('size_options').style.display='block';
                            }
                            updateAvailableContactSheetFields('single');
                        } else if (jQuery('#sheetstyle').val()=='thumbnails') {
                            document.getElementById('OrientationOptions').style.display='block';        
                            document.getElementById('ThumbnailOptions').style.display='block';
                            if (document.getElementById('size_options')) {
                                document.getElementById('size_options').style.display='none';
                            }
                            updateAvailableContactSheetFields('thumbnails');
                        }
                        jQuery().rsContactSheet('revert','<?php echo (int) $collection; ?>','<?php echo $filename_uid; ?>');"
                >
                    <?php
                    foreach ($templates as $template) {
                        echo "<option value='" . escape($template) . "'>" .  (isset($lang[$template]) ? $lang[$template] : escape($template)) . "</option>";
                    }
                    ?>
                </select>
                <div class="clearerleft"></div>

                <script>
                    function updateAvailableContactSheetFields(style) {
                        var contact_sheet_fields_selector = jQuery('#selected_contact_sheet_fields');
                        var post_url  = '<?php echo $baseurl; ?>/pages/contactsheet_settings.php';
                        var post_data =  {
                            ajax: true,
                            sheetstyle: style,
                            action: 'get_sheetstyle_fields',
                        };

                        jQuery.get(post_url, post_data, function(response) {
                            if (typeof response !== 'undefined') {
                                var response_obj = JSON.parse(response);

                                // Remove all options
                                contact_sheet_fields_selector.empty();

                                var x;
                                for (x in response_obj) {
                                    var contact_sheet_field_obj = response_obj[x];
                                    
                                    var fieldToAdd = '<span id="csf' + Number(contact_sheet_field_obj.ref) + '">';
                                    fieldToAdd += '<input type="checkbox" ';
                                    fieldToAdd += 'name="selected_contact_sheet_fields[]" ';
                                    fieldToAdd += 'class="selected_contact_sheet_field' + (Number(contact_sheet_field_obj.ref) == 0 ? 'all" ' : '" ');                                    
                                    fieldToAdd += 'value="' + contact_sheet_field_obj.ref + '" ';
                                    fieldToAdd += 'id="selected_contact_sheet_fields' + Number(contact_sheet_field_obj.ref) + '" checked>';
                                    fieldToAdd += '<label for="selected_contact_sheet_fields' + Number(contact_sheet_field_obj.ref) + '">' + contact_sheet_field_obj.title + '</label><br /></span>';

                                    contact_sheet_fields_selector.append(fieldToAdd);
                                }

                                jQuery(".selected_contact_sheet_fieldall").bind("change", checkAll);
                                jQuery(".selected_contact_sheet_field").bind("click", checkEachField);

                                return true;
                            }
                        });
                        return false;
                    }
                </script>
            </div>

            <?php
            if ($error != "contactsheet_data_toolong") {
                echo "<input type=hidden name='field_value_limit' value=" . urlencode($field_value_limit) . ">";
            } else {
                ?>
                <div class="Question">
                    <label for="field_value_limit"><?php echo escape($lang["contactsheet_data_field_value_limit"]); ?></label>
                    <input type="number" name='field_value_limit' value='<?php echo urlencode($field_value_limit); ?>'>
                    <div class="clearerleft"></div>
                </div>
                <?php
            }

            if ($contact_sheet_include_header_option) {
                ?>  
                <div class="Question">
                    <label for="includeheader"><?php echo escape($lang["contact_sheet-include_header_option"]); ?></label>
                    <select class="shrtwidth" name="includeheader" id="includeheader" onchange="jQuery().rsContactSheet('revert','<?php echo (int) $collection; ?>','<?php echo $filename_uid; ?>');">
                        <option value="true"><?php echo escape($lang["yes"]); ?></option>
                        <option value="false" <?php echo (!$contact_sheet_include_header) ? " selected" : ''; ?>>
                            <?php echo escape($lang["no"]); ?>
                        </option>
                    </select>
                    <div class="clearerleft"></div>
                </div>
                <?php
            }

            if ($contact_sheet_single_select_size) {
                $sizes = get_all_image_sizes(false, false);
                ?>
                <div id="size_options" class="Question" style="display:none">
                    <label for="ressize"><?php echo escape($lang["contact_sheet-single_select_size"]); ?></label>
                    <select class="shrtwidth" name="ressize" id="ressize" onchange="jQuery().rsContactSheet('revert','<?php echo (int) $collection; ?>','<?php echo $filename_uid; ?>');">
                        <?php
                        foreach ($sizes as $size) {
                            echo '    <option value="' . $size['id'] . '"' . ($size['id'] == 'lpr' ? ' selected' : '') . '>' . escape($size['name']) . '</option>';
                        }
                        ?>
                    </select>
                    <div class="clearerleft"></div>
                </div>
                <?php
            }

            if (isset($contact_sheet_logo_option) && $contact_sheet_logo_option && isset($contact_sheet_logo)) {
                ?>
                <div class="Question">
                    <label for="addlogo"><?php echo escape($lang["contact_sheet-add_logo_option"]); ?></label>
                    <select class="shrtwidth" name="addlogo" id="addlogo" onchange="jQuery().rsContactSheet('revert','<?php echo (int) $collection; ?>','<?php echo $filename_uid; ?>');">
                        <option value="true"><?php echo escape($lang["yes"]); ?></option>
                        <option value="false"><?php echo escape($lang["no"]); ?></option>
                    </select>
                    <div class="clearerleft"></div>
                </div>
                <?php
            }

            if ($contact_sheet_add_link_option) {
                ?>  
                <div class="Question">
                    <label for="addlink"><?php echo escape($lang["contact_sheet-add_link_option"]); ?></label>
                    <select class="shrtwidth" name="addlink" id="addlink" onchange="jQuery().rsContactSheet('revert','<?php echo (int) $collection; ?>','<?php echo $filename_uid; ?>');">
                        <option value="true"><?php echo escape($lang["yes"]); ?></option>
                        <option value="false" <?php echo (!$contact_sheet_add_link) ? " selected" : ''; ?>>
                            <?php echo escape($lang["no"]); ?>
                        </option>
                    </select>
                    <div class="clearerleft"></div>
                </div>
                <?php
            }

            if ($contact_sheet_field_name_option) {
                ?>  
                <div class="Question">
                    <label for="addfieldname"><?php echo escape($lang["contact_sheet-field_name_option"]); ?></label>
                    <select class="shrtwidth" name="addfieldname" id="addfieldname" onchange="jQuery().rsContactSheet('revert','<?php echo (int) $collection; ?>','<?php echo $filename_uid; ?>');">
                        <option value="true"><?php echo escape($lang["yes"]); ?></option>
                        <option value="false"><?php echo escape($lang["no"]); ?></option>
                    </select>
                    <div class="clearerleft"></div>
                </div>
                <?php
            }

            if ($contactsheet_use_field_templates) {
                ?>
                <div class="Question">
                    <label for="field_template"><?php echo escape($lang['contact_sheet_field_template']); ?></label>
                    <select id="field_template" class="shrtwidth" name="field_template" onchange="updateAvailableContactSheetFieldsTemplate(jQuery('#field_template').val());jQuery().rsContactSheet('revert','<?php echo (int) $collection; ?>','<?php echo $filename_uid; ?>');">
                        <?php
                        $t_count = count($contactsheet_field_template);
                        for ($t = 0; $t < $t_count; $t++) {
                            ?>
                            <option value="<?php echo $t; ?>"<?php echo $field_template == $t ? 'selected' : ''; ?>>
                                <?php echo escape($contactsheet_field_template[$t]['name']); ?>
                            </option>
                            <?php
                        }
                        ?>
                    </select>

                    <script>
                        function updateAvailableContactSheetFieldsTemplate(template) {
                            var contact_sheet_fields_selector = jQuery('#selected_contact_sheet_fields');
                            var post_url  = '<?php echo $baseurl; ?>/pages/contactsheet_settings.php';
                            var post_data = {
                                ajax: true,
                                field_template: template,
                                action: 'get_sheetstyle_fields',
                            };

                            jQuery.get(post_url, post_data, function(response) {
                                if (typeof response !== 'undefined') {
                                    var response_obj = JSON.parse(response);

                                    // Remove all options
                                    contact_sheet_fields_selector.empty();

                                    var x;
                                    for (x in response_obj) {
                                        var contact_sheet_field_obj = response_obj[x];
                                        
                                        contact_sheet_fields_selector.append(contact_sheet_field_obj.title + '<br/>');
                                    }

                                    return true;
                                }
                            });

                            return false;
                        }
                    </script>
                </div>
                <?php
            }
            ?>

            <div class="Question">
                <label for="selected_contact_sheet_fields">
                    <?php echo escape($contactsheet_use_field_templates ? $lang['contact_sheet_field_template_fields'] : $lang['contact_sheet_select_fields']); ?>
                </label>
                <?php
                if ($contactsheet_use_field_templates) {
                    $fieldlist = '';
                    foreach ($available_contact_sheet_fields as $contact_sheet_field) {
                        $fieldlist .= $contact_sheet_field['title'] . '<br/>';
                    }
                    ?>
                    <span id="selected_contact_sheet_fields"><?php echo $fieldlist ?></span>
                    <?php
                } else {
                    ?>
                    <fieldset name="selected_contact_sheet_fields[]" id="selected_contact_sheet_fields" class="MultiRTypeSelect" style="width:420px;">
                        <?php
                        foreach ($available_contact_sheet_fields as $contact_sheet_field) {
                            ?>
                            <span id="csf<?php echo (int) $contact_sheet_field['ref']; ?>">
                                <input
                                    type="checkbox"
                                    value="<?php echo (int) $contact_sheet_field['ref']; ?>"
                                    class="selected_contact_sheet_field<?php echo (int) $contact_sheet_field['ref'] == 0 ? 'all' : ''; ?>"
                                    name="selected_contact_sheet_fields[]"
                                    id="selected_contact_sheet_fields<?php echo (int) $contact_sheet_field['ref']; ?>"
                                    checked="checked"
                                />
                                <label for="selected_contact_sheet_fields<?php echo (int) $contact_sheet_field['ref']; ?>"><?php echo escape(i18n_get_translated($contact_sheet_field['title'])); ?></label>
                                <br />
                            </span>
                        <?php 
                        }
                        ?>
                    </fieldset>
                    <script>
                        function checkAll(e) {
                            if (this.checked) {
                                jQuery(".selected_contact_sheet_field").each(function() {
                                    this.checked = true;
                                })
                            } else {
                                jQuery(".selected_contact_sheet_field").each(function() {
                                    this.checked = false;
                                })
                            }
                        }

                        function checkEachField(e) {
                            if (jQuery(this).is(":checked")) {
                                var isAllChecked = 0;
                                jQuery(".selected_contact_sheet_field").each(function() {
                                    if (!this.checked) {
                                        isAllChecked = 1;
                                    }
                                });

                                if (isAllChecked == 0) {
                                    jQuery(".selected_contact_sheet_fieldall").prop("checked", true);
                                }
                            } else {
                                jQuery(".selected_contact_sheet_fieldall").prop("checked", false);
                            }
                        }

                        jQuery(document).ready(function() {
                            jQuery(".selected_contact_sheet_fieldall").bind("change", checkAll);
                            jQuery(".selected_contact_sheet_field").bind("click", checkEachField);
                        });
                    </script>

                    <?php
                }
                ?>
                <div class="updatePreviewButton">
                    <a href="#" onclick="jQuery().rsContactSheet('preview','<?php echo (int) $collection; ?>','<?php echo $filename_uid; ?>'); return false;">
                        <i aria-hidden="true" class="icon-refresh-cw"></i>
                        <?php echo escape($lang["contact_sheet_update_preview"]); ?>
                    </a>
                </div>
                <div class="clearerleft"></div>
            </div>

            <div class="Question">
                <label for="size"><?php echo escape($lang["size"]); ?></label>
                <select class="shrtwidth" name="size" id="size" style="width: 300px;" onchange="jQuery().rsContactSheet('revert','<?php echo (int) $collection; ?>','<?php echo $filename_uid; ?>');">
                    <?php echo $papersize_select; ?>
                </select>
                <div class="clearerleft"> </div>
            </div>

            <?php
            if ($contactsheet_sorting) {
                $all_field_info = get_fields_for_search_display(array_unique(array_merge($thumbs_display_fields, $list_display_fields, $config_sheetlist_fields, $config_sheetthumb_fields)));
                ?>
                <div class="Question">
                    <label for="orderby"><?php echo escape($lang["sortorder"]); ?></label>
                    <select class="shrtwidth" name="order_by" id="orderby" onchange="jQuery().rsContactSheet('preview','<?php echo (int) $collection; ?>','<?php echo $filename_uid; ?>');">
                        <option value="collection" selected><?php echo escape($lang["collection-order"]); ?></option>
                        <option value="date"><?php echo escape($lang["date"]); ?></option>
                        <option value="colour"><?php echo escape($lang["colour"]); ?></option>
                        <option value="resourceid"><?php echo escape($lang["resourceid"]); ?></option>
                        <?php
                        foreach ($all_field_info as $sortable_field) {
                            // don't display the ones we've already covered above.
                            if (!($sortable_field["title"] == $lang["date"] || $sortable_field["title"] == $lang["colour"])) {
                                ?>
                                <option value="<?php echo $sortable_field['ref']; ?>"><?php echo escape($sortable_field["title"]) ?></option>
                                <?php
                            }
                        }
                        ?>
                    </select>
                    <div class="clearerleft"></div>
                </div>

                <div class="Question">
                    <label for="sort"><?php echo escape($lang["sort-type"]) ?></label>
                    <select class="shrtwidth" name="sort" id="sort" onchange="jQuery().rsContactSheet('preview','<?php echo (int) $collection; ?>','<?php echo $filename_uid; ?>');">
                        <option value="asc" selected><?php echo escape($lang["ascending"]); ?></option>
                        <option value="desc"><?php echo escape($lang["descending"]); ?></option>
                    </select>
                    <div class="clearerleft"></div>
                </div>
                <?php
            }
            ?>

            <div id="ThumbnailOptions" class="Question">
                <label for="ThumbnailOptions"><?php echo escape($lang["columns"]); ?></label>
                <select class="shrtwidth" name="columns" id="ThumbnailOptions" onchange="jQuery().rsContactSheet('revert','<?php echo (int) $collection; ?>','<?php echo $filename_uid; ?>');">
                    <?php echo $columns_select ?>
                </select>
                <div class="clearerleft"></div>
            </div>

            <div id="OrientationOptions" class="Question">
                <label for="orientation"><?php echo escape($lang["orientation"]); ?></label>
                <select class="shrtwidth" name="orientation" id="orientation" onchange="jQuery().rsContactSheet('revert','<?php echo (int) $collection; ?>','<?php echo $filename_uid; ?>');">
                    <option value="P"><?php echo escape($lang["portrait"]); ?></option>
                    <option value="L"><?php echo escape($lang["landscape"]); ?></option>
                </select>
                <div class="clearerleft"></div>
            </div>
            
            <?php
            if ($contact_sheet_force_watermarks) {
                if ($contact_sheet_force_watermark_option) {
                    ?>
                    <div id="WatermarkOptions" class="Question">
                        <label for="force_watermark"><?php echo escape($lang["show_watermarked_previews_and_thumbnails"]); ?></label>
                        <select class="shrtwidth" name="force_watermark" id="force_watermark" onchange="jQuery().rsContactSheet('revert','<?php echo (int) $collection; ?>','<?php echo $filename_uid; ?>');">
                            <option value="true"><?php echo escape($lang["yes"]); ?></option>
                            <option value="false"><?php echo escape($lang["no"]); ?></option>
                        </select>
                        <div class="clearerleft"></div>
                    </div>
                    <?php
                } else {
                    ?>
                    <input type="hidden" name="force_watermark" id="force_watermark" value="true" />
                    <?php
                }
            }
            ?>

            <div name="previewPageOptions" id="previewPageOptions" class="Question" style="display:none">
                <label for="previewpage"><?php echo escape($lang['previewpage']); ?></label>
                <select class="shrtwidth" name="previewpage" id="previewpage" onchange="jQuery().rsContactSheet('preview','<?php echo (int) $collection; ?>','<?php echo $filename_uid; ?>');"></select>
            </div>

            <div class="QuestionSubmit">
                <input name="save" type="submit" value="<?php echo escape($lang["create"]); ?>" />
            </div>
        </div> <!-- end of small BasicBox -->
    </form>
</div>

<div>
    <!-- this is the container for some Ajax fun. The image will go here...-->
    <?php
    $cs_size = explode("x", $contact_sheet_preview_size);
    $height  = $cs_size[1];

    if ($contact_sheet_previews) {
        ?>
        <div style="float:left;padding:0px -50px 15px 0;height:<?php echo escape($height) ?>px;margin-top:-15px;margin-right:-50px">
            <img alt="" id="contact-sheet--previewimage" name="previewimage" src=""/>
        </div>
        <?php
    }
    ?>
</div>

<script>
    jQuery().setContactSheetCSRFTokenIdentifier('<?php echo $GLOBALS['CSRF_token_identifier']; ?>');
    jQuery().rsContactSheet('preview','<?php echo (int) $collection; ?>','<?php echo $filename_uid; ?>');
</script>

<?php
include '../include/footer.php';
