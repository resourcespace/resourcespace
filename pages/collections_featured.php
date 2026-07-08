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

if ($smart_rtf > 0) {
    $field_data = get_field($smart_rtf);
}

if ($smart_fc_parent > 0) {
    $node_data = array();
    get_node($smart_fc_parent, $node_data);
}

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
    ?>
    <div class="fc-breadcrumbs">
        <?php renderBreadcrumbs(array_merge($links_trail, $branch_trail), "", "BreadcrumbsBoxTheme"); ?>
    </div>
    <?php
} elseif ($smart_rtf > 0) {

    $links_trail = array(
        array(
            "title" => $lang["themes"],
            "href"  => generateURL("{$baseurl_short}pages/collections_featured.php", $general_url_params)
        )
    );

    $branch_trail = array(
        array(
            "title" => i18n_get_translated($field_data['smart_theme_name']),
            "href"  => generateURL(
                "{$baseurl_short}pages/collections_featured.php",
                $general_url_params,
                array("smart_rtf" => $field_data['ref'])
            ),
    ));

    if ($smart_fc_parent > 0) {
        $tree_node_level = get_tree_node_level($smart_fc_parent);
        if ($tree_node_level !== 0) {
            $branch_trail = get_smart_fc_branch_trail($smart_fc_parent, $tree_node_level, $field_data, $general_url_params, $branch_trail);
        }

        $end_trail = array(
            array(
                "title" => $node_data['name'],
                "href"  => generateURL(
                    "{$baseurl_short}pages/collections_featured.php",
                    $general_url_params,
                    array("smart_rtf" => $field_data['ref'],"smart_fc_parent" => $node_data['ref'])
                ),
            )
        ); 
        $branch_trail = array_merge($branch_trail, $end_trail);
    }

    ?>
    <div class="fc-breadcrumbs">
        <?php renderBreadcrumbs(array_merge($links_trail, $branch_trail), "", "BreadcrumbsBoxTheme"); ?>
    </div>
    <?php
}

?>
<div class="page-title">
    <h1>
        <?php 
        if ($parent > 0) {
            echo escape(i18n_get_translated($parent_collection_data['name']));
        } elseif ($smart_fc_parent > 0) {
            echo escape(i18n_get_translated($node_data['name']));
        } elseif ($smart_rtf > 0) {
            echo escape(i18n_get_translated($field_data['smart_theme_name']));
        } else {
            echo escape($lang["page-title_collections_featured"]);
        }
        ?>
    </h1>
</div>
<div class="BasicsBox FeaturedSimpleLinks">
    <?php

    $featured_collections = ($smart_rtf == 0 ? get_featured_collections($parent, array()) : array());
    usort($featured_collections, "order_featured_collections");
    render_featured_collections(
            [
                "general_url_params" => $general_url_params,
                "all_fcs" => $all_fcs,
                "reorder" => can_reorder_featured_collections()
            ],
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
            $rendering_options["h2_text"] = $lang["createnewcollection"];
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
            $rendering_options["h2_text"] = $lang["action-upload-to-collection"];

            render_new_featured_collection_cta($upload_url, $rendering_options);
        }
    }
    ?>
</div><!-- End of BasicsBox FeaturedSimpleLinks -->

<script>
    /** Show the Featured Collection (category) context menu */
    function showContextMenu(el) {
        hideContextMenu();

        const top_right_menu_btn = jQuery(el);
        top_right_menu_btn.addClass('is-open');
        const context_menu = top_right_menu_btn
            .closest('.FeaturedSimpleTile, .BreadcrumbsBox')
            .find('.flyout-menu');

        const menu_el_tmp = context_menu.clone().appendTo('body').css({
            display: 'block',
            visibility: 'hidden',
            position: 'fixed'
        });

        const btn_bb = el.getBoundingClientRect();
        const menu_bb = menu_el_tmp[0].getBoundingClientRect();

        const container = jQuery('.FeaturedSimpleLinks')[0].getBoundingClientRect();

        let top = btn_bb.top;
        let left = btn_bb.right - 28;

        // Keep menu inside viewport horizontally
        if (left < 0) {
            left = btn_bb.left;
        }
        if (left + menu_bb.width > container.right) {
            left = left - menu_bb.width + 28;
        }
        

        // Show below button by default. If it would overflow bottom then show it above
        top = btn_bb.bottom;
        if (top + menu_bb.height > window.outerHeight) {
            top = btn_bb.top - menu_bb.height;
        }

        // Clamp top just in case
        if (top < 0) {
            top = 8;
        }

        menu_el_tmp.remove();

        context_menu
            .css({
                display: 'none',
                position: 'fixed',
                top: `${top}px`,
                left: `${left}px`
            })
            .show();

        return false;
    }

    /** Hide the Featured Collection (category) context menu */
    function hideContextMenu()
    {
        let menu_content = jQuery('.FeaturedSimpleTile .flyout-menu, .BreadcrumbsBox .flyout-menu');
        if (menu_content.is(':visible')) {
            menu_content.hide();
            jQuery('.is-open').removeClass('is-open');
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
        if (!e.target.closest('.flyout-menu')) {
            hideContextMenu();
        }
    };

    jQuery(document).ready(function () {
        // Get and update display for total resource count for each of the rendered featured collections (@see render_featured_collection() for more info)
        var fcs_waiting_total = jQuery('.HomePanel .featured-tile p[data-tag="resources_count"]');
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
                    jQuery('.HomePanel .featured-tile p[data-tag="resources_count"][data-fc-ref="' + k + '"]')
                        .text(total_count + ' ' + (total_count == 1 ? lang_resource : lang_resources));
                });
            },
            <?php echo generate_csrf_js_object('get_collections_resource_count'); ?>
            );
        }
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
                distance: 10,
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