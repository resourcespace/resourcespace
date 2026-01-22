<?php
include '../../include/boot.php';
include '../../include/authenticate.php';

if (!acl_can_manage_tabs()) {
    exit($lang['error-permissiondenied']);
}

// [Sorting functionality]
$tab_orderby = getval('tab_orderby', 'ref');
$tab_sort = (strtoupper(getval('tab_sort', 'ASC')) === 'DESC') ? 'DESC' : 'ASC';

// [URL]
$admin_tabs_management_page = "{$baseurl}/pages/admin/tabs.php";
$request_params = [
    'tab_orderby'  => $tab_orderby,
    'tab_sort' => $tab_sort,
];
$admin_tabs_management_page_url = generateURL($admin_tabs_management_page, $request_params);

// [Action] Create new record
$new_tab_name = trim(getval('new_tab_name', ''));
if ($new_tab_name !== '' && enforcePostRequest(false)) {
    $new_tab_ref = create_tab(['name' => $new_tab_name]);
    if ($new_tab_ref !== false) {
        redirect($admin_tabs_management_page_url);
    }

    $admin_tabs_management_error = "{$lang['error_fail_save']} -- $new_tab_name";
}

// [Paging functionality]
$per_page = (int) getval('per_page', get_per_page_cookie(), true);
$list_display_array[] = $default_perpage_list;
$list_display_array[] = $default_perpage;
if ($per_page === 99999) {
    // all results option - see render_table()
    $list_display_array['all'] = 99999;
    $allow_reorder = true;

    // When viewing all, switch to order by the "order_by" column since we also enabled re-ordering
    $tab_orderby = 'order_by';
    $tab_sort = 'ASC';
    $request_params = [];
    $admin_tabs_management_page_url = generateURL($admin_tabs_management_page, $request_params);
}
$list_display_array = array_unique($list_display_array);
natsort($list_display_array);
$per_page = in_array($per_page, $list_display_array) ? $per_page : $default_perpage;
set_per_page_cookie($per_page);
$offset = (int) getval('offset', 0, true);
$tab_records = get_tabs_with_usage_count([
    'order_by' => [$tab_orderby, $tab_sort],
    'limit' => ['per_page' => $per_page, 'offset' => $offset],
]);

$tabsfound = count($tab_records["data"]) > 0;
if (!$tabsfound) {
    // No results, go to last page
    $offset     = floor(($tab_records['total'] - 1) / $per_page) * $per_page;
    $tab_records = get_tabs_with_usage_count([
        'order_by' => [$tab_orderby, $tab_sort],
        'limit' => ['per_page' => $per_page, 'offset' => $offset],
    ]);
}

$totalpages = ceil($tab_records['total'] / $per_page);
$curpage = floor($offset / $per_page) + 1;

$table_info = [
    'class' => 'SystemTabs',
    'headers' => [
        'reorder_handle' => ['name' => '', 'sortable' => false, 'html' => true],
        'ref' => ['name' => $lang['property-reference'], 'sortable' => !isset($allow_reorder)],
        'name' => ['name' => $lang['name'], 'sortable' => false, 'html' => true, 'width' => '50%'],
        'usage' => ['name' => $lang['usage'], 'sortable' => false],
        'tools' => ['name' => $lang['tools'], 'sortable' => false, 'width' => '20%']
    ],

    'orderbyname' => 'tab_orderby',
    'orderby' => $tab_orderby,
    'sortname' => 'tab_sort',
    'sort' => $tab_sort,

    'defaulturl' => $admin_tabs_management_page,
    'params' => $request_params,
    'pager' => [
        'current' => $curpage,
        'total' => $totalpages,
        'per_page' => $per_page,
    ],

    'data' => [],
];

foreach ($tab_records['data'] as $tab_record) {
    $tab_record['reorder_handle'] = isset($allow_reorder) ? '<i class="icon-arrow-down-up"></i>' : '';
    $tab_record['name'] = sprintf(
        '<span>%s</span><input name="tab_name_inline_edit_%s" type="text" class="DisplayNone" value="%s">',
        escape(i18n_get_translated($tab_record['name'])),
        escape($tab_record['ref']),
        escape($tab_record['name'])
    );
    $tab_record['usage'] = sprintf(
        '%s %s, %s %s',
        $tab_record['usage_rtf'],
        mb_strtolower($lang['admin_resource_type_fields']),
        $tab_record['usage_rt'],
        mb_strtolower($lang['resourcetypes'])
    );

    // Allow users to delete tabs except the Default one which is always ID #1 (created by dbstruct).
    if ($tab_record['ref'] > 1) {
        $tab_record['tools'] = [
            [
                'icon' => 'icon-trash-2',
                'text' => $lang['action-delete'],
                'url' => '#',
                'modal' => false,
                'onclick' => "return delete_tabs(this, [{$tab_record['ref']}]);",
            ],
        ];
    }

    $tab_record['tools'][] = [
        'icon' => 'icon-square-pen',
        'text' => $lang['action-edit'],
        'url' => '#',
        'modal' => false,
        'onclick' => "return update_tab(this, {$tab_record['ref']}, \"init_edit\");"
    ];

    // Save & Cancel buttons for when editing a tab record
    $tab_record['tools'][] = [
        'icon' => 'icon-x',
        'text' => $lang['cancel'],
        'url' => '#',
        'url:class' => 'DisplayNone',
        'modal' => false,
        'onclick' => "return update_tab(this, {$tab_record['ref']}, \"cancel\");"
    ];
    $tab_record['tools'][] = [
        'icon' => 'icon-save',
        'text' => $lang['save'],
        'url' => '#',
        'url:class' => 'DisplayNone',
        'modal' => false,
        'onclick' => "return update_tab(this, {$tab_record['ref']}, \"save\");"
    ];

    $table_info['data'][] = $tab_record;
}

include '../../include/header.php';
?>

<div class="BasicsBox">
    <h1><?php echo escape($lang["system_tabs"]); ?></h1>

    <?php
    render_top_page_error_style($admin_tabs_management_error ?? '');
    renderBreadcrumbs([
        ['title' => $lang['systemsetup'], 'href' => "{$baseurl_short}pages/admin/admin_home.php"],
        ['title' => $lang['system_tabs']],
    ]); ?>
    <p>
        <?php
        echo escape($lang['manage_tabs_instructions']);
        render_help_link('systemadmin/manage-tabs');
        ?>
    </p>

    <?php render_table($table_info); ?>

    <!-- Create new tab form -->
    <form method="post" action="<?php echo $admin_tabs_management_page_url; ?>" onsubmit="return CentralSpacePost(this, false);">
        <?php generateFormToken('admin_tabs'); ?>
        <div class="Question">
            <label for="new_tab_name"><?php echo escape($lang['tabs_create_new']); ?></label>
            <div class="tickset">
                <div class="Inline">
                    <input name="new_tab_name" type="text" value="" id="new_tab_name" class="shrtwidth">
                </div>
                <div class="Inline">
                    <input
                        name="action_create"
                        type="submit"
                        value="<?php echo escape($lang['create']); ?>"
                        onclick="return (this.form.elements[0].value != '');"
                    >
                </div>
            </div>
            <div class="clearerleft"></div>
        </div>
    </form>
</div>

<script>
    // Re-order capability
    jQuery(function() {
        // Disable for touch screens
        if (is_touch_device()) {
            return false;
        }

        // Make all table rows sortable (except the header)
        jQuery('.BasicsBox .Listview.SystemTabs > table').sortable({
            items: 'tr:not(:first-child)',
            handle: 'td > i.arrow-down-up',
            containment: 'div.SystemTabs > table',
            distance: 20,
            update: function(event, ui) {
                let tabs_new_order = jQuery(event.target)
                    .find('tr:not(:first-child) > td:nth-child(2)')
                    .map((i, val) => parseInt(jQuery(val).text())).get();
                console.debug('[Re-ordering tabs] tabs_new_order = %o', tabs_new_order);
                api('reorder_tabs', {'refs': tabs_new_order}, null, <?php echo generate_csrf_js_object('reorder_tabs'); ?>);
            }
        });
    });

    function delete_tabs(el, refs) {
        console.debug('Called delete_tabs(refs = %o)', refs);
        if (confirm('<?php echo escape($lang["confirm-deletion"]); ?>')) {
            api('delete_tabs', {'refs': refs}, function(successful) {
                if (successful) {
                    // Remove row from table
                    jQuery(el).parents('tr').remove();
                } else {
                    styledalert("<?php echo escape($lang["error"]); ?>", "<?php echo escape($lang["error-failed-to-delete"]); ?>");
                }
            },
            <?php echo generate_csrf_js_object('delete_tabs'); ?>
            );
        };

        return false;
    }

    function update_tab(el, ref, action) {
        console.debug('Called update_tab(ref = %o, action = %o)', ref, action);

        let el_obj = jQuery(el);
        let record = el_obj.parents('tr');
        let tools = el_obj.parents('div.ListTools');
        let tools_edit_save_cancel = tools.find('a i.icon-square-pen, a i.icon-save, a i.icon-x').parents('a');

        let record_name_inline_edit = record.find('input[name="tab_name_inline_edit_' + ref + '"');
        let record_name_translated = record_name_inline_edit.siblings().first();

        if (action === 'init_edit') {
            // Hide the translated tab name and show the inline edit input
            record_name_translated.toggleClass('DisplayNone');
            record_name_inline_edit.toggleClass('DisplayNone');

            // Hide the edit tool and show the Save & Cancel ones
            tools_edit_save_cancel.toggleClass('DisplayNone');
        } else if (action === 'save') {
            api(
                'save_tab',
                {
                    tab: {
                        ref: ref,
                        name: record_name_inline_edit.val()
                    }
                },
                function(response) {
                    if (response.status === 'success') {
                        record_name_translated.text(response.data.name_translated);
                    } else {
                        styledalert("<?php echo escape($lang["error"]); ?>", response.data.message);
                    }
                },
                <?php echo generate_csrf_js_object('save_tab'); ?>
            );


            // Show the translated tab name and hide the inline edit input
            record_name_translated.toggleClass('DisplayNone');
            record_name_inline_edit.toggleClass('DisplayNone');

            // Show the edit tool and hide the Save & Cancel ones
            tools_edit_save_cancel.toggleClass('DisplayNone');
        } else if (action === 'cancel') {
            // Show the translated tab name and hide the inline edit input
            record_name_translated.toggleClass('DisplayNone');
            record_name_inline_edit.toggleClass('DisplayNone');

            // Show the edit tool and hide the Save & Cancel ones
            tools_edit_save_cancel.toggleClass('DisplayNone');
        }
    }
</script>

<?php
include '../../include/footer.php';