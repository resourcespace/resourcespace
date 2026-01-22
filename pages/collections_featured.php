<?php

include_once "../include/boot.php";

$k = trim(getval("k", ""));
$parent = (int) getval("parent", $featured_collections_root_collection, true);

if ($k == "" || !check_access_key_collection($parent, $k)) {
    include "../include/authenticate.php";
    $parent = (int) getval("parent", $featured_collections_root_collection, true);
} else {
    // Disable CSRF when someone is accessing an external share (public context)
    $CSRF_enabled = false;

    // Force simple view because otherwise it assumes you're logged in. The JS api function will use the native mode to
    // get the resource count and loading the actions always authenticates and both actions will (obviously) error.
    $themes_simple_view = true;
}

if (!$enable_themes) {
    http_response_code(403);
    exit($lang["error-permissiondenied"]);
}

// Access control
if ($parent > 0 && !featured_collection_check_access_control($parent)) {
    error_alert($lang["error-permissiondenied"], true, 403);
    exit();
}

$smart_rtf = (int) getval("smart_rtf", 0, true);
$smart_fc_parent = getval("smart_fc_parent", 0, true);
$smart_fc_parent = ($smart_fc_parent > 0 ? $smart_fc_parent : null);

$general_url_params = ($k == "" ? array() : array("k" => $k));

$parent_collection_data = get_collection($parent);
$parent_collection_data = (is_array($parent_collection_data) ? $parent_collection_data : array());


if (getval("new", "") == "true" && getval("cta", "") == "true") {
    new_featured_collection_form($parent);
    exit();
}

// List of all FCs. For huge trees, helps increase performance but might require an increase for memory_limit in php.ini
$all_fcs = get_all_featured_collections();
include "../include/header.php";
?>

<div class="BasicsBox FeaturedSimpleLinks">
    <?php
    if ($parent > 0) {
        $links_trail = array(
            array(
                "title" => $lang["themes"],
                "href"  => generateURL("{$baseurl_short}pages/collections_featured.php", $general_url_params)
            )
        );

        $fc_branch_path = move_featured_collection_branch_path_root(compute_node_branch_path($all_fcs, $parent));

        if (empty($fc_branch_path)) {
            $links_trail = [];
        }

        // Add menu options for the current FC (category) node
        $current_fc_node = end($fc_branch_path);
        $current_fc_node_key = key($fc_branch_path);
        reset($fc_branch_path);
        if ($current_fc_node_key !== null) {
            if ($smart_rtf == 0 && get_smart_theme_headers() !== []) {
                $is_smart_featured_collection = true;
            } else if ($parent == 0 && $smart_rtf > 0 && metadata_field_view_access($smart_rtf)) {
                $is_smart_featured_collection = true;
            } else {
                $is_smart_featured_collection = false;
            }
            $is_featured_collection_category = is_featured_collection_category($current_fc_node);
            $is_featured_collection = (!$is_featured_collection_category && !$is_smart_featured_collection);
            $fc_category_has_children = $is_featured_collection_category && (isset($fc['has_children']) ? (bool) $fc['has_children'] : false);

            $collection_data = get_collection($current_fc_node['ref']);
            if (!is_array($collection_data)) {
                $collection_data = [];
            }

            if (($is_featured_collection || !$fc_category_has_children) && collection_readable($current_fc_node['ref'])) {
                $fc_branch_path[$current_fc_node_key]['context_menu'][] = [
                    'icon' => 'icon-circle-check',
                    'text' => $lang['action-select'],
                    'custom_onclick' => sprintf("return ChangeCollection(%s, '');", escape($current_fc_node['ref'])),
                ];
            }

            if (
                (
                    ($is_featured_collection && !$is_smart_featured_collection)
                    || !$fc_category_has_children
                )
                && allow_upload_to_collection($collection_data)
            ) {
                $fc_branch_path[$current_fc_node_key]['context_menu'][] = [
                    'href' => $GLOBALS['upload_then_edit']
                        ? generateURL(
                            "{$baseurl_short}pages/upload_batch.php", 
                            [
                                'collection_add' => $current_fc_node['ref'], 
                                'entercolname' => $current_fc_node['name']
                            ]
                        )
                        : generateURL(
                            "{$baseurl_short}pages/edit.php",
                            [
                                'uploader' => $GLOBALS['upload_then_edit'],
                                'ref' => -$GLOBALS['userref'],
                                'collection_add' => $current_fc_node['ref']
                            ]
                        ),
                    'icon' => 'icon-upload',
                    'text' => $lang['action-upload-to-collection'],
                ];
            }

            if (($is_featured_collection || can_edit_featured_collection_category()) && collection_writeable($current_fc_node['ref'])) {
                $fc_branch_path[$current_fc_node_key]['context_menu'][] = [
                    'href' => generateURL(
                        "{$baseurl_short}pages/collection_edit.php",
                        [
                            'ref' => $current_fc_node['ref'],
                            'redirection_endpoint' => urlencode(
                                generateURL(
                                    "{$baseurl_short}pages/collections_featured.php",
                                    $general_url_params,
                                    ['parent' => $current_fc_node['parent']]
                                )
                            )
                        ]
                    ),
                    'icon' => 'icon-square-pen',
                    'text' => $lang['action-edit'],
                    'modal_load' => true,
                ];
            }

            if (
                can_delete_collection($collection_data, $userref, $k)
                && can_delete_featured_collection($current_fc_node['ref'])
            ) {
                $fc_branch_path[$current_fc_node_key]['context_menu'][] = [
                    'icon' => 'icon-trash-2',
                    'text' => $lang['action-deletecollection'],
                    'custom_onclick' => sprintf(
                        'return delete_collection(%s, \'%s\', \'%s\');',
                        escape($current_fc_node['ref']),
                        escape($lang['collectiondeleteconfirm']),
                        escape(generate_csrf_js_object('delete_collection'))
                    ),
                ];
            }
        }

        $branch_trail = array_map(function ($branch) use ($baseurl_short, $general_url_params) {
            $current_fc_node_menu = isset($branch['context_menu']) ? ['context_menu' => $branch['context_menu']] : [];

            return [
                "title" => strip_prefix_chars(i18n_get_translated($branch["name"]), "*"),
                "href"  => generateURL(
                    "{$baseurl_short}pages/collections_featured.php",
                    $general_url_params,
                    array("parent" => $branch["ref"])
                ),
                ...$current_fc_node_menu,
            ];
        }, $fc_branch_path);

        renderBreadcrumbs(array_merge($links_trail, $branch_trail), "", "BreadcrumbsBoxTheme");
    }

    // Default rendering options (should apply to both FCs and smart FCs)
    $full_width = !$themes_simple_view;
    $rendering_options = array(
        "full_width" => $full_width,
        "general_url_params" => $general_url_params,
        "all_fcs" => $all_fcs,
    );

    $featured_collections = ($smart_rtf == 0 ? get_featured_collections($parent, array()) : array());
    usort($featured_collections, "order_featured_collections");
    render_featured_collections(
        array_merge($rendering_options, ["reorder" => can_reorder_featured_collections()]),
        $featured_collections
    );

    $smart_fcs_list = array();

    if ($parent == 0 && $smart_rtf == 0) {
        // Root level - this is made up of all the fields that have a Smart theme name set.
        $smart_fc_headers = array_filter(get_smart_theme_headers(), function (array $v) {
            return metadata_field_view_access($v["ref"]);
        });

        $smart_fcs_list = array_map(function (array $v) use ($FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS) {
            return array(
                "ref" => $v["ref"],
                "name" => $v["smart_theme_name"],
                "type" => COLLECTION_TYPE_FEATURED,
                "parent" => null,
                "thumbnail_selection_method" => $FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS["most_popular_image"],
                "has_resources" => 0,
                "resource_type_field" => $v["ref"]);
        },
        $smart_fc_headers);
    } elseif ($parent == 0 && $smart_rtf > 0 && metadata_field_view_access($smart_rtf)) {
        // Smart fields. If a category tree, then a parent could be passed once user requests a lower level than root of the tree
        $resource_type_field = get_resource_type_field($smart_rtf);

        if ($resource_type_field !== false && in_array($resource_type_field["type"], $FIXED_LIST_FIELD_TYPES)) {
            // We go one level at a time so we don't need it to search recursively even if this is a FIELD_TYPE_CATEGORY_TREE
            $smart_fc_nodes = get_smart_themes_nodes($smart_rtf, false, $smart_fc_parent, $resource_type_field);
            $smart_fcs_list = array_map(function (array $v) use ($smart_rtf, $FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS) {
                return array(
                    "ref" => $v["ref"],
                    "name" => $v["name"],
                    "type" => COLLECTION_TYPE_FEATURED,
                    "parent" => $v["ref"], # parent here is the node ID. When transformed to a FC this parent will be used for going to the next level down the branch
                    "thumbnail_selection_method" => $FEATURED_COLLECTION_BG_IMG_SELECTION_OPTIONS["most_popular_image"],
                    "has_resources" => 0,
                    "resource_type_field" => $smart_rtf,
                    "node_is_parent" => $v["is_parent"]
                );
            },
            $smart_fc_nodes);
        }
    }

    $rendering_options["smart"] = (count($smart_fcs_list) > 0);
    render_featured_collections($rendering_options, $smart_fcs_list);
    unset($rendering_options["smart"]);

    if ($k == "" && $smart_rtf == 0) {
        if (checkperm("h") && can_create_collections()) {
            render_new_featured_collection_cta(
                generateURL(
                    "{$baseurl_short}pages/collections_featured.php",
                    array(
                        "new" => "true",
                        "cta" => "true",
                        "parent" => $parent,
                    )
                ),
                $rendering_options
            );
        }

        if (allow_upload_to_collection($parent_collection_data)) {
            $upload_url = generateURL(
                "{$baseurl_short}pages/edit.php",
                [
                    "uploader"       => $top_nav_upload_type,
                    "ref"            => -$userref,
                    "collection_add" => $parent,
                    "entercolname"   => $parent_collection_data['name']
                ]
            );

            if ($upload_then_edit) {
                $upload_url = generateURL(
                    "{$baseurl_short}pages/upload_batch.php", 
                    [
                        "collection_add" => $parent,
                        "entercolname"   => $parent_collection_data['name']
                    ]
                );
            }

            $rendering_options["html_h2_span_class"] = "icon-upload";
            $rendering_options["centralspaceload"] = true;

            render_new_featured_collection_cta($upload_url, $rendering_options);
        }
    }
    ?>
</div><!-- End of BasicsBox FeaturedSimpleLinks -->

<script>
    /** Show the Featured Collection (category) context menu */
    function showContextMenu(el)
    {
        hideContextMenu();

        const top_right_menu_btn = jQuery(el);
        const context_menu = top_right_menu_btn.closest('.FeaturedSimpleTile, .BreadcrumbsBox').find('.context-menu-container');
        let menu_el_tmp = context_menu.clone().appendTo('.FeaturedSimpleLinks');
        menu_el_tmp.css({
            'display': 'block',
            'visibility': 'hidden',
        });
        const is_responsive = window.matchMedia("(max-width: 900px)").matches;
        const uicenter_el = document.getElementById('UICenter');
        console.debug('top_right_menu_btn = %o', top_right_menu_btn);
        console.debug('context_menu = %o', context_menu);
        console.debug('is_responsive = %o', is_responsive);

        let off_top = 0;
        let off_left = 0;
        let off_top_rev = 0;
        let off_left_rev = 0;
        const header_bb = document.getElementById('Header').getBoundingClientRect();
        const container_bb = document.querySelector('.FeaturedSimpleLinks').getBoundingClientRect();
        const menu_btn_bb = el.getBoundingClientRect();
        const menu_btn_computed_style = getComputedStyle(el);
        const menu_bb = menu_el_tmp[0].getBoundingClientRect();

        /*
        Determine the position offset for the menu so it's within the proximity of the calling top right menu icon. 
        Notes:
        - the bounding box (BB) ignores margins so we have to account for those too;
        - in responsive mode, instead of the UICenter, the body is overflowing vertically (Y axis);
        */
        if (is_responsive) {
            off_top += document.body.scrollTop
                - header_bb.height
                - document.getElementById('SearchBarContainer').getBoundingClientRect().height;
            off_top_rev = menu_bb.height - menu_btn_bb.height + 50;
            off_left_rev -= menu_bb.width + menu_btn_bb.width + parseInt(menu_btn_computed_style.margin);
        } else {
            const menu_btn_margin = 2 * parseInt(menu_btn_computed_style.margin);
            off_top += uicenter_el.scrollTop - header_bb.height;
            off_left += menu_btn_margin;
            off_left_rev -= menu_bb.width + menu_btn_bb.width - menu_btn_margin;
        }

        // For a better UX, check if the menu will go outside the container/view boundaries to ensure users always have
        // the menu in sight
        if ((menu_btn_bb.left + off_left + menu_bb.width) > container_bb.right) {
            off_left = off_left_rev;
        }
        if (is_responsive && (menu_btn_bb.top + menu_bb.height) > window.innerHeight) {
            off_top -= off_top_rev;
        }

        console.debug("off_top = %o -- off_left = %o", off_top, off_left);
        menu_el_tmp.remove();

        context_menu
            .css({
                display: 'none',
                top: menu_btn_bb.top + off_top,
                left: menu_btn_bb.left + off_left,
            })
            .slideDown(150);

        return false;
    }

    /** Hide the Featured Collection (category) context menu */
    function hideContextMenu()
    {
        let menu_content = jQuery('.FeaturedSimpleTile .context-menu-container, .BreadcrumbsBox .context-menu-container');
        if (menu_content.is(':visible')) {
            menu_content.slideUp(150);
        }
    }

    onkeydown = (e) => {
        // On esc, close down contextual menus 
        if (e.keyCode === 27) {
            hideContextMenu();
        }
    };
    onmousedown = (e) => {
        // Close menus when clicking away
        if (!e.target.closest('.context-menu-container')) {
            hideContextMenu();
        }
    };

    jQuery(document).ready(function () {
        // Get and update display for total resource count for each of the rendered featured collections (@see render_featured_collection() for more info)
        var fcs_waiting_total = jQuery('.FeaturedSimpleTile.FullWidth .FeaturedSimpleTileContents h2 span[data-tag="resources_count"]');
        var fc_refs = [];

        fcs_waiting_total.each(function(i, v) {
            fc_refs.push(jQuery(v).data('fc-ref'));
        });

        if (fc_refs.length > 0) {
            api('get_collections_resource_count', {'refs': fc_refs.join(',')}, function(response) {
                var lang_resource = '<?php echo escape($lang['youfoundresource']); ?>';
                var lang_resources = '<?php echo escape($lang['youfoundresources']); ?>';

                Object.keys(response).forEach(function(k) {
                    var total_count = response[k];
                    jQuery('.FeaturedSimpleTile.FullWidth .FeaturedSimpleTileContents h2 span[data-tag="resources_count"][data-fc-ref="' + k + '"]')
                        .text(total_count + ' ' + (total_count == 1 ? lang_resource : lang_resources));
                });
            },
            <?php echo generate_csrf_js_object('get_collections_resource_count'); ?>
            );
        }

        <?php if (!$themes_simple_view) { ?>
            // Load collection actions when dropdown is clicked
            jQuery('.fcollectionactions').on("focus", function(e) {
                var el = jQuery(this);

                if (el.attr('data-actions-populating') != '0') {
                    return false
                }

                el.attr('data-actions-populating','1');
                var action_selection_id = el.attr('id');
                var colref = el.attr('data-col-id');

                LoadActions('themes',action_selection_id,'collection',colref);
            });
        <?php } ?>
    });

    <?php if ($allow_fc_reorder) { ?>
        // Re-order capability
        jQuery(function() {
            // Disable for touch screens
            if (is_touch_device()) {
                return false;
            }

            jQuery('.BasicsBox.FeaturedSimpleLinks').sortable({
                items: '.SortableItem',
                distance: 20,
                update: function(event, ui) {
                    let html_ids_new_order = jQuery('.BasicsBox.FeaturedSimpleLinks').sortable('toArray');
                    let fcs_new_order = html_ids_new_order.map(id => jQuery('#' + id).data('fc-ref'));
                    console.debug('fcs_new_order=%o', fcs_new_order);
                    <?php if ($descthemesorder) { ?>
                        fcs_new_order = fcs_new_order.reverse();
                        console.debug('fcs_new_order_reversed=%o', fcs_new_order);
                    <?php } ?>
                    api(
                        'reorder_featured_collections',
                        {'refs': fcs_new_order},
                        null,
                        <?php echo generate_csrf_js_object('reorder_featured_collections'); ?>
                    );
                }
            });
        });
    <?php } ?>
</script>

<?php
include "../include/footer.php";