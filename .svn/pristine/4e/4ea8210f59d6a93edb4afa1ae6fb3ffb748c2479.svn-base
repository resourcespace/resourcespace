<?php

declare(strict_types=1);

namespace Montala\ResourceSpace\Plugins\BrandGuidelines;

include_once dirname(__DIR__, 4) . '/include/boot.php';
include_once RESOURCESPACE_BASE_PATH . '/include/authenticate.php';
include_once RESOURCESPACE_BASE_PATH . '/include/request_functions.php';
if (!acl_can_edit_brand_guidelines()) {
    http_response_code(401);
    exit(escape($lang['error-permissiondenied']));
}

$page_def = [
    BRAND_GUIDELINES_CONTENT_TYPES['text'] => [
        'title' => [
            'new' => $lang['brand_guidelines_title_new_text'],
            'edit' => $lang['brand_guidelines_title_edit_text'],
        ],
        'fields' => [
            [
            'id'       => 'richtext',
            'title'    => $lang['text'],
            'type'     => FIELD_TYPE_TEXT_RICH,
            'required' => true,
            ],
        ],
    ],
    BRAND_GUIDELINES_CONTENT_TYPES['resource'] => [
        'title' => [
            'new' => $lang['brand_guidelines_title_select_resource'],
            'edit' => $lang['brand_guidelines_title_select_resource'],
        ],
        'fields' => [
            [
            'id'       => 'resource_id',
            'title'    => $lang['resourceid'],
            'type'     => FIELD_TYPE_NUMERIC,
            'constraints' => ['min' => 1],
            'required' => true,
            ],
            [
            'id'       => 'layout',
            'title'    => $lang['brand_guidelines_layout'],
            'type'     => FIELD_TYPE_DROP_DOWN_LIST,
            'options'  => [
                'thumbnail' => $lang['imagesize-thumbnail'],
                'half-width' => $lang['brand_guidelines_half_width'],
                'full-width' => $lang['brand_guidelines_full_width'],
            ],
            'required' => true,
            ],
            [
            'id'       => 'caption',
            'title'    => $lang['fieldtitle-caption'],
            'type'     => FIELD_TYPE_TEXT_BOX_SINGLE_LINE,
            'required' => false,
            ],
        ],
    ],
    BRAND_GUIDELINES_CONTENT_TYPES['colour'] => [
        'title' => [
            'new' => $lang['colour'],
            'edit' => $lang['colour'],
        ],
        'fields' => [
            [
            'id'       => 'colour_preview',
            'title'    => $lang['preview'],
            'type'     => FIELD_TYPE_COLOUR_PREVIEW,
            'required' => false,
            ],
            [
            'id'       => 'name',
            'title'    => $lang['name'],
            'type'     => FIELD_TYPE_TEXT_BOX_SINGLE_LINE,
            'required' => true,
            ],
            [
            'id'       => 'hex',
            'title'    => $lang['brand_guidelines_hex'],
            'help_text' => $lang['brand_guidelines_hex_help_txt'],
            'type'     => FIELD_TYPE_TEXT_BOX_SINGLE_LINE,
            'required' => true,
            ],
            [
            'id'       => 'rgb',
            'title'    => $lang['brand_guidelines_rgb'],
            'help_text' => $lang['brand_guidelines_rgb_help_txt'],
            'type'     => FIELD_TYPE_TEXT_BOX_SINGLE_LINE,
            'required' => true,
            ],
            [
            'id'       => 'cmyk',
            'title'    => $lang['brand_guidelines_cmyk'],
            'help_text' => $lang['brand_guidelines_cmyk_help_txt'],
            'type'     => FIELD_TYPE_TEXT_BOX_SINGLE_LINE,
            'required' => true,
            ],
        ],
    ],
];

// Actions
$delete = $_COOKIE['ref'] = (int) getval('delete', 0, false, 'is_positive_int_loose');
$reorder = getval('reorder', '', false, __NAMESPACE__ . '\reorder_input_validator');
$group_members = array_values(array_unique(
    array_filter(
        array_map('intval', explode(',', getval('group_members', '', false, 'validate_digit_csv')))
    ),
    SORT_NUMERIC
));
if ($group_members !== []) {
    sort($group_members, SORT_NUMERIC);

    // Group actions simulate the action being applied to one item
    $first_group_member = reset($group_members);
    if (getval('delete_group', false) === 'true') {
        $delete = $_POST['ref'] = $first_group_member;
    } elseif ($reorder !== '') {
        $_POST['ref'] = $first_group_member;
    }
}
$save = getval('posting', '') !== '' && enforcePostRequest(false) && $delete === 0 && $reorder === '';
$edit = false;
$after_item = (int) getval('after_item', 0, false, 'is_positive_int_loose');

// Specific page content item
$ref = (int) getval('ref', 0, false, 'is_positive_int_loose');
if ($ref > 0) {
    $db_item = get_page_content_item($ref);
    if ($db_item !== []) {
        $edit = true;
        $page = $db_item['page'];
        $type = $db_item['type'];

        // Help the process_custom_fields_submission() fill in the form
        $item_content_fields = convert_from_db_content($db_item['content'], $page_def[$type]['fields']);
        foreach ($item_content_fields as $item_field) {
            if (isset($item_field['value'])) {
                /**
                 * Note: when saving, POSTd data already has precedence over GETd (through {@see getval()})
                 * {@see process_custom_fields_submission()}
                 */

                if ($item_field['type'] === FIELD_TYPE_DROP_DOWN_LIST) {
                    $html_field_id = $item_field['html_properties']['id'];
                    $_GET[$html_field_id] = [md5("{$html_field_id}_{$item_field['value']}")];
                    continue;
                }

                $_GET[$item_field['html_properties']['name']] = $item_field['value'];
            }
        }
    }
}

// Content always belongs to a page
$page ??= (int) getval('page', 0, false, 'is_positive_int_loose');
$all_pages = array_column(array_filter(get_all_pages(), __NAMESPACE__ . '\filter_only_pages'), null, 'ref');
if ($page === 0 && $all_pages !== []) {
    $page = (int) array_key_first($all_pages);
} elseif (!isset($all_pages[$page])) {
    http_response_code(400);
    exit(escape(str_replace('%key', 'page', $GLOBALS['lang']['error-request-missing-key'])));
}
$page_contents_db = array_column(get_page_contents($page), null, 'ref');

// Content (item) always has a type
$type ??= (int) getval(
    'type',
    BRAND_GUIDELINES_CONTENT_TYPES['text'],
    false,
    fn($V) => in_array($V, BRAND_GUIDELINES_CONTENT_TYPES)
);
$page_title = $page_def[$type]['title'];

// Add group logic, if applicable
$act_on_group_members = false;
if (!$save && $group_members !== []) {
    $decoded_page_contents = array_filter(array_map(__NAMESPACE__ . '\decode_page_content_item', $page_contents_db));
    $page_contents_grouped = group_content_items($decoded_page_contents);
    $applicable_group = array_filter($page_contents_grouped, is_group_member($ref, $decoded_page_contents));
    $applicable_group_members = array_column((reset($applicable_group) ?: [])['members'] ?? [], null, 'ref');
    $list_of_applicable_members = array_keys($applicable_group_members);
    sort($list_of_applicable_members, SORT_NUMERIC);

    if (array_intersect($list_of_applicable_members, $group_members) === $group_members) {
        $act_on_group_members = true;
        // Maintain the order in which the group members actually are or the final sorted version will be incorrect
        $group_members = array_intersect(array_keys($applicable_group_members), $group_members);
    }
}

// Allow new additional resource items to have a particular layout already selected
if (
    !($save || $edit)
    && ($layout = getval('layout', '', false, fn($V) => isset($page_def[$type]['fields'][1]['options'][$V])))
) {
    // Note: this is a hack to avoid knowing the hash value on the client side
    $_GET['layout'] = [md5("layout_{$page_def[$type]['fields'][1]['options'][$layout]}")];
}

// Allow the upload flow to call back with the new resource ID and preset it on the form
if (
    !($save || $edit)
    && $type === BRAND_GUIDELINES_CONTENT_TYPES['resource']
    && ($with_resource_ref = getval('w_ref', '', false, 'is_positive_int_loose'))
) {
    $_GET['resource_id'] = $with_resource_ref;
}

// Process
$_POST['colour_preview'] = $_GET['colour_preview'] = $_COOKIE['colour_preview'] = ''; # Not expected to be submitted!
$processed_fields = process_custom_fields_submission(
    $page_def[$type]['fields'],
    $save,
    ['html_properties_prefix' => '']
);
if ($save && count_errors($processed_fields) === 0) {
    $item = [
        'type' => $type,
        'fields' => $processed_fields,
        'position_after' => $after_item, # technically only required for NEW items
    ];

    if (
        ($edit && save_page_content($ref, $item))
        || (!$edit && create_page_content($page, $item))
    ) {
        js_call_CentralSpaceLoad(
            generateURL("{$GLOBALS['baseurl']}/plugins/brand_guidelines/pages/guidelines.php", ['spage' => $page])
        );
    }

    error_alert($lang['error_fail_save'], true, 200);
    exit();
} elseif ($delete > 0 && enforcePostRequest(false)) {
    if ($edit && delete_page_content($act_on_group_members ? $group_members : [$ref])) {
        js_call_CentralSpaceLoad(
            generateURL(
                "{$GLOBALS['baseurl']}/plugins/brand_guidelines/pages/guidelines.php",
                ['spage' => $page]
            )
        );
    }

    error_alert($lang['error-failed-to-delete'], true, 200);
    exit();
} elseif ($reorder !== '' && enforcePostRequest(false)) {
    if ($edit && reorder_page_content($reorder, $page_contents_db, $act_on_group_members ? $group_members : [$ref])) {
        js_call_CentralSpaceLoad(
            generateURL("{$GLOBALS['baseurl']}/plugins/brand_guidelines/pages/guidelines.php", ['spage' => $page])
                . "#page-content-item-" . urlencode((string) $ref)
        );
    }

    error_alert($lang['error-failed-to-move'], true, 200);
    exit();
}


if ($type === BRAND_GUIDELINES_CONTENT_TYPES['resource']) {
    $field_options = array_column($processed_fields, null, 'id')['layout']['options'];
    $half_width_val_hash = md5("layout_{$field_options['half-width']}");
}

include_once RESOURCESPACE_BASE_PATH . '/include/header.php';
?>
<div class="BasicsBox">
    <h1><?php echo escape($edit ? $page_title['edit'] : $page_title['new']); ?></h1>
    <form
        name="manage_content"
        id="manage_content" class="modalform guidelines"
        method="POST"
        action="<?php echo "{$baseurl_short}plugins/brand_guidelines/pages/manage/content.php"; ?>"
        onsubmit="return CentralSpacePost(this, true, true);"
    >
        <?php
        generateFormToken('manage_content');
        render_hidden_input('page', (string) $page);
        render_hidden_input('ref', (string) $ref);
        render_hidden_input('type', (string) $type);
        render_hidden_input('after_item', (string) $after_item);
        render_custom_fields($processed_fields);
        ?>
        <div class="QuestionSubmit">
            <input type="submit" name="content_submit" value="<?php
                echo escape($edit ? $lang['save'] : $lang['add']);
            ?>"></input>
            <div class="clearleft"></div>
        </div>
    </form>
</div>
<script>
tinymce.remove();
tinymce.init({
    selector: '#manage_content .Question textarea#richtext',
    width: 900,
    height: 400,
    license_key: 'gpl',
    promotion: false,
    branding: false,
    plugins: 'lists, link',
    relative_urls: false,
    toolbar: 'h2 h3 bold italic underline strikethrough removeformat | bullist numlist link | outdent indent',
    menubar: '',
    contextmenu: false, // Required to enable native copy and pasting
    <?php
    if (isset($user_pref_appearance)) {
        if ($user_pref_appearance == "dark") {
            ?>
            skin: "oxide-dark",
            content_css: "dark",
            <?php
        } elseif ($user_pref_appearance == "device") {
            ?>
            skin: (window.matchMedia("(prefers-color-scheme: dark)").matches ? "oxide-dark" : "oxide"),
            content_css: (window.matchMedia("(prefers-color-scheme: dark)").matches ? "dark" : "default"),
            <?php
        }
    }
    ?>
    setup: (editor) => {
        editor.on('blur', (e) => tinymce.triggerSave());
    },
});

jQuery(document).ready(() => {
    // Add the content type to the modal URL so ModalClosed event has the necessary information for its subscriber (see
    // guidelines.php page - needed to remove tinymce)
    let modal_url = new URL(modalurl);
    modal_url.searchParams.set('type', '<?php echo escape((string) $type); ?>');
    modalurl = modal_url.toString();
    <?php
    if ($type === BRAND_GUIDELINES_CONTENT_TYPES['colour']) {
        ?>
        update_colour_preview(jQuery('#hex').val());
        <?php
    } elseif ($type === BRAND_GUIDELINES_CONTENT_TYPES['resource']) {
        ?>
        jQuery('#layout')
            .change((e) => {
                if (e.target.value === '<?php echo escape($half_width_val_hash); ?>') {
                    jQuery('#Question_caption').slideDown(150);
                } else {
                    jQuery('#Question_caption').slideUp(150);
                }
            })
            .trigger('change');
        <?php
    }
    ?>
});

jQuery('#hex, #rgb, #cmyk').on('focusout', (e) => {
    const field_value = jQuery(e.target).val();
    if (field_value === '') {
        return;
    }

    jQuery('.Question div.FormError').remove();

    let background_colour = '';
    switch (e.target.id) {
        case 'hex':
            background_colour = field_value;
            const hex2rgb = hex_to_rgb(field_value);
            if (Object.keys(hex2rgb).length !== 0) {
                jQuery('#rgb').val(Object.values(hex2rgb));
                jQuery('#cmyk').val(Object.values(hex_to_cmyk(field_value)));
            } else {
                show_form_error('#Question_hex', '<?php echo escape($lang['brand_guidelines_err_invalid_input']); ?>');
            }

            break;

        case 'rgb':
            const [r, g, b] = field_value.split(',').map((v) => parseInt(v, 10));
            background_colour = rgb_to_hex(r, g, b);
            if (background_colour !== '') {
                jQuery('#hex').val(background_colour.substring(1));
                jQuery('#cmyk').val(Object.values(rgb_to_cmyk(r, g, b)));
            } else {
                show_form_error('#Question_rgb', '<?php echo escape($lang['brand_guidelines_err_invalid_input']); ?>');
            }
            break;

        case 'cmyk':
            const [c, m, y, k] = field_value.split(',');
            const rgb = cmyk_to_rgb(c, m, y, k);
            if (Object.keys(rgb).length !== 0) {
                background_colour = rgb_to_hex(rgb.r, rgb.g, rgb.b);
                jQuery('#hex').val(background_colour.substring(1));
                jQuery('#rgb').val(Object.values(rgb));
            } else {
                show_form_error('#Question_cmyk', '<?php echo escape($lang['brand_guidelines_err_invalid_input']); ?>');
            }
            break;
    }

    update_colour_preview(background_colour);
});

/**
 * Helper: update colour preview
 * @param {String} hex Hexadecimal value (with/without # prefix)
 */
function update_colour_preview(hex) {
    if (hex !== '') {
        jQuery('.preview.guidelines-colour-block')
            .css('background-color', hex.substring(0, 1) === '#' ? hex : `#${hex}`);
    }
}

/**
 * Helper: show individual form question error
 * @param {String} selector Selector to use to find the element to append the FormError div to
 */
function show_form_error(selector, msg) {
    jQuery('<div>', { class: 'FormError' }).text(msg).appendTo(selector);
}

/**
 * Calculate the RGB value from HEX
 * @param {String} hex Hexadecimal value (with/without # prefix)
 * @return {{r: Number, g: Number, b: Number}} Return an RGB object or an empty object if the input is invalid 
 */
function hex_to_rgb(hex) {
    // Expand short form, if applicable
    hex = hex.replace(/^#?([a-f\d])([a-f\d])([a-f\d])$/i, (m, r, g, b) => r + r + g + g + b + b);
    const result = /^#?([a-f\d]{2})([a-f\d]{2})([a-f\d]{2})$/i.exec(hex);
    if (result === null) {
        return {};
    }
    return {
        r: parseInt(result[1], 16),
        g: parseInt(result[2], 16),
        b: parseInt(result[3], 16),
    };
}

/**
 * Calculate the HEX value from RGB
 * @param {Number} r Red value
 * @param {Number} g Green value
 * @param {Number} b Blue value
 * @return {String} Returns the HEX value, empty string if input is invalid
 */
function rgb_to_hex(r, g, b) {
    return ([r, g, b].filter((i) => i >= 0 && i <= 255).length === 3)
        ? `#${(1 << 24 | r << 16 | g << 8 | b).toString(16).slice(1).toUpperCase()}`
        : '';
}

/**
 * Calculate the CMYK value from HEX
 * @param {String} hex Hexadecimal value (with/without # prefix)
 */
function hex_to_cmyk(hex) {
    const { r, g, b } = hex_to_rgb(hex);
    return rgb_to_cmyk(r, g, b);
}

/**
 * Calculate the CMYK value from RGB
 * @param {Number} red
 * @param {Number} green
 * @param {Number} blue
 * @return {{c: Number, m: Number, y: Number, k: Number}} Returns a CMYK object
 */
function rgb_to_cmyk(red, green, blue) {
    if (red === 0 && green === 0 && blue === 0) {
        return { c: 0, m: 0, y: 0, k: 100 };
    }

    const R = red > 0 ? red / 255 : 0;
    const G = green > 0 ? green / 255 : 0;
    const B = blue > 0 ? blue / 255 : 0;
    const K = 1 - Math.max(R, G, B);
    return {
        c: Math.round(((1 - R - K) / (1 - K)) * 100),
        m: Math.round(((1 - G - K) / (1 - K)) * 100),
        y: Math.round(((1 - B - K) / (1 - K)) * 100),
        k: Math.round(K * 100),
    };
}

/**
 * Calculate the RGB value from CMYK
 * @param {Number} c Cyan value
 * @param {Number} m Magenta value
 * @param {Number} y Yellow value
 * @param {Number} k Black (key) value
 * @return {{r: Number, g: Number, b: Number}} Return an RGB object or an empty object if the input is invalid 
 */
function cmyk_to_rgb(c, m, y, k) {
    if (([c, m, y, k].filter((i) => i >= 0 && i <= 100).length !== 4)) {
        return {};
    }
    const black = 1 - (k / 100);
    return {
        r: Math.round(255 * (1 - (c / 100)) * black),
        g: Math.round(255 * (1 - (m / 100)) * black),
        b: Math.round(255 * (1 - (y / 100)) * black),
    };
}
</script>
<?php
include_once RESOURCESPACE_BASE_PATH . '/include/footer.php';
