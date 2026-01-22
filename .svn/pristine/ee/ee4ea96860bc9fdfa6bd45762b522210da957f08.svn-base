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

$ref = (int) getval('ref', 0, false, 'is_positive_int_loose');
$edit = false;
$delete = (int) getval('delete', 0, false, 'is_positive_int_loose');
$reorder = getval('reorder', '', false, __NAMESPACE__ . '\reorder_input_validator');
$save = getval('posting', '') !== '' && enforcePostRequest(false) && $delete === 0 && $reorder === '';
$pages_db = get_all_pages();
$all_sections = extract_node_options(array_filter($pages_db, __NAMESPACE__ . '\is_section'), true, true);
$parent = getval(
    'parent',
    0,
    false,
    (fn($V) => is_positive_int_loose($V) || ($save && is_array($V) && count($V) === 1))
);

if ($ref > 0) {
    $all_pages_index = array_column($pages_db, null, 'ref');
    if (isset($all_pages_index[$ref])) {
        $edit = true;

        // Help the process_custom_fields_submission() fill in the form
        if (!$save) {
            $_GET['name'] = $all_pages_index[$ref]['name'];
            $parent = $all_pages_index[$ref]['parent'];
        }
    }
}

// Quick page parent (ie. section) validation (value type is different depending how we got here - by new page button
// or saving)
foreach ($all_sections as $section_id => $section) {
    if (is_positive_int_loose($parent) && $section_id == $parent) {
        $parent = (int) $parent;

        // Convert submitted data into the expected type so process_custom_fields_submission() can automatically select
        // the dropdown option
        $_GET['parent'] = [md5("parent_{$section}")];
        break;
    } elseif ($save && is_array($parent) && $parent[0] === md5("parent_{$section}")) {
        $parent = (int) $section_id;
        break;
    }
}


// Page setup
$page_title = $edit ? $lang['brand_guidelines_edit_section_title'] : $lang['brand_guidelines_new_section_title'];
$toc_fields = [
    [
    'id'       => 'name',
    'title'    => $lang['name'],
    'type'     => FIELD_TYPE_TEXT_BOX_SINGLE_LINE,
    'required' => true,
    ],
];
$is_page = !is_section(['parent' => $parent]);
if ($is_page) {
    $page_title = $edit ? $lang['brand_guidelines_edit_page_title'] : $lang['brand_guidelines_new_page_title'];
    $toc_fields[] = [
        'id'       => 'parent',
        'title'    => $lang['brand_guidelines_section'],
        'type'     => FIELD_TYPE_DROP_DOWN_LIST,
        'options'  => $all_sections,
        'required' => true,
    ];
}
$processed_toc_fields = process_custom_fields_submission($toc_fields, $save, ['html_properties_prefix' => '']);

if ($save && count_errors($processed_toc_fields) === 0) {
    $bg_page_name = $processed_toc_fields[0]['value'];
    $bg_page_parent = $is_page && isset($processed_toc_fields[1]['selected_options'][$parent]) ? $parent : 0;

    if ($edit) {
        save_page($ref, $bg_page_name, $bg_page_parent);
        $redirect_params = $is_page ? ['spage' => $ref] : [];
    } else {
        $redirect_params = ['spage' => create_page($bg_page_name, $bg_page_parent)];
    }

    js_call_CentralSpaceLoad(
        generateURL("{$GLOBALS['baseurl']}/plugins/brand_guidelines/pages/guidelines.php", $redirect_params)
    );
} elseif ($delete > 0 && enforcePostRequest(false)) {
    $delete_list = [$delete];
    if (isset($all_sections[$delete])) {
        $section_page_struct = array_column(get_node_tree(0, $pages_db), 'children', 'ref');
        $children = isset($section_page_struct[$delete]) ? array_column($section_page_struct[$delete], 'ref') : [];
        $delete_list = array_merge($delete_list, $children);
    }
    $delete_list = array_map('intval', $delete_list);

    if (delete_pages($delete_list)) {
        js_call_CentralSpaceLoad("{$GLOBALS['baseurl']}/plugins/brand_guidelines/pages/guidelines.php");
    }

    error_alert($lang['error-failed-to-delete'], true, 200);
    exit();
} elseif ($reorder !== '' && enforcePostRequest(false)) {
    if ($edit) {
        reorder_items(
            'brand_guidelines_pages',
            array_replace(
                $all_pages_index,
                [$ref => compute_item_order($all_pages_index[$ref], $reorder)]
            ),
            fn($V) => $V['parent'] === $all_pages_index[$ref]['parent']
        );
        js_call_CentralSpaceLoad("{$GLOBALS['baseurl']}/plugins/brand_guidelines/pages/guidelines.php");
    }
    error_alert($lang['error-failed-to-move'], true, 200);
    exit();
}


include_once RESOURCESPACE_BASE_PATH . '/include/header.php';
?>
<div class="BasicsBox">
    <h1><?php echo escape($page_title); ?></h1>
    <form
        name="manage_toc"
        id="manage_toc" class="modalform guidelines"
        method="POST"
        action="<?php echo "{$baseurl_short}plugins/brand_guidelines/pages/manage/toc.php"; ?>"
        onsubmit="return CentralSpacePost(this, true, true);"
    >
        <?php
        generateFormToken('manage_toc');
        render_hidden_input('ref', (string) $ref);
        render_custom_fields($processed_toc_fields);
        ?>
        <div class="QuestionSubmit" >
            <input
                type="submit"
                name="toc_submit"
                value="<?php echo escape($edit ? $lang['save'] : $lang['create']); ?>"
            >
            <div class="clearleft"></div>
        </div>
    </form>
</div>
<?php
include_once RESOURCESPACE_BASE_PATH . '/include/footer.php';
