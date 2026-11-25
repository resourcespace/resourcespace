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
    if (!metadata_field_view_access($smart_rtf)) {
        http_response_code(403);
        exit($lang["error-permissiondenied"]);
    }
    $field_data = get_field($smart_rtf);
}

if ($smart_fc_parent > 0) {
    $node_data = array();
    get_node($smart_fc_parent, $node_data);
}

$general_url_params = ($k == "" ? array() : array("k" => $k));

$parent_collection_data = get_collection($parent);
$parent_collection_data = (is_array($parent_collection_data) ? $parent_collection_data : array());

if ($parent > 0 && empty($parent_collection_data)) {
    $onload_message = ["title" => $lang["error"], "text" => $lang["error-collectionnotfound"]];
    $parent = 0;
}
if (getval("new", "") == "true" && getval("cta", "") == "true") {
    new_featured_collection_form($parent);
    exit();
}

// Fetch hierarchy flags without expanding all resource memberships. The complete list is still needed for branch paths
// and category tools, while only the current level is rendered in the initial tile batch.
$all_fcs = get_all_featured_collections();
$featured_collection_tile_data = get_featured_collection_tile_data($parent, $smart_rtf, $smart_fc_parent);
$featured_collection_batch_target = max(0, (int) $featured_collection_tiles_per_batch_target);
$featured_collection_initial_batch = get_featured_collection_tile_batch(
    $featured_collection_tile_data,
    0,
    $featured_collection_batch_target
);
$featured_collection_full_order = array_column($featured_collection_tile_data["featured"], "ref");
if ($descthemesorder) {
    $featured_collection_full_order = array_reverse($featured_collection_full_order);
}
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

        if (
            ($is_featured_collection || !$fc_category_has_children) 
            && collection_readable($current_fc_node['ref'])
            && !checkperm('b')
        ) {
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
    <?php if ($parent > 0 && $parent_collection_data['description'] != '') { ?> 
        <p> <?php echo escape(i18n_get_translated($parent_collection_data['description'])); ?> </p>
    <?php } ?>
</div>
<div class="BasicsBox FeaturedSimpleLinks">
    <?php
    $rendering_options = [
        "general_url_params" => $general_url_params,
        "all_fcs" => $all_fcs,
        "reorder" => $allow_fc_reorder && can_reorder_featured_collections(),
    ];
    render_featured_collections($rendering_options, $featured_collection_initial_batch["featured"]);

    $smart_rendering_options = $rendering_options;
    $smart_rendering_options["smart"] = true;
    $smart_rendering_options["reorder"] = false;
    render_featured_collections($smart_rendering_options, $featured_collection_initial_batch["smart"]);

    if ($featured_collection_initial_batch["has_more"]) {
        ?>
        <div id="featured-collection-batch-loading" class="featured-collection-batch-loading">
            <i class="icon-loader-circle lucide--spin"></i>
            <span><?php echo escape($lang["loading"]); ?></span>
        </div>
        <?php
    }

    if ($k == "" && $smart_rtf == 0) {
        $rendering_options["html_container_class"] = "featured-collection-action-tile";
        $rendering_options["hidden"] = $featured_collection_initial_batch["has_more"];
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
    window.featuredCollectionConfig = {
        batchUrl: <?php echo encode_js_value($baseurl_short . "pages/ajax/featured_collection_tiles.php"); ?>,
        imageUrl: <?php echo encode_js_value($baseurl_short . "pages/ajax/featured_collection_image.php"); ?>,
        parent: <?php echo (int) $parent; ?>,
        smartRtf: <?php echo (int) $smart_rtf; ?>,
        smartFcParent: <?php echo (int) ($smart_fc_parent ?? 0); ?>,
        key: <?php echo encode_js_value($k); ?>,
        offset: <?php echo (int) $featured_collection_initial_batch["offset"]; ?>,
        fullOrder: <?php echo encode_js_value(array_map("intval", $featured_collection_full_order)); ?>,
        descending: <?php echo $descthemesorder ? "true" : "false"; ?>
    };

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
        

        // Show below button by default. Use the viewport height, excluding browser chrome, to check for overflow.
        top = btn_bb.bottom;
        if (top + menu_bb.height > window.innerHeight) {
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

    jQuery(document).ready(function () {
        const container = jQuery('.BasicsBox.FeaturedSimpleLinks');
        const featuredCollectionConfig = window.featuredCollectionConfig;
        const interactiveMenuSelector =
            '.top-right-menu > i, .featured-collection.flyout-menu';
        let batchRequestActive = false;
        let batchRequestSucceeded = false;
        let rowReconciliationPending = false;
        let resizeTimeout = null;
        let currentColumnCount = null;
        let pageActive = true;
        let imageObserver = null;
        let batchObserver = null;
        let rowResizeObserver = null;
        let pageReplacementObserver = null;
        let batchRequest = null;

        if (container.length === 0) {
            return;
        }

        const pageSpace = container.closest('#CentralSpace, #modal');

        function isFeaturedCollectionPageActive() {
            return pageActive && document.documentElement.contains(container[0]);
        }

        function cleanupFeaturedCollectionPage() {
            if (!pageActive) {
                return;
            }

            pageActive = false;
            imageObserver?.disconnect();
            batchObserver?.disconnect();
            rowResizeObserver?.disconnect();
            pageReplacementObserver?.disconnect();
            window.clearTimeout(resizeTimeout);

            container.find('.featured-collection-ajax-load').each(function() {
                window.clearTimeout(this.fcLoadTimeout);
                this.fcLoadTimeout = null;
            });

            if (batchRequest) {
                const request = batchRequest;
                batchRequest = null;
                request.abort();
            }
        }

        pageReplacementObserver = new MutationObserver(function() {
            if (!document.documentElement.contains(container[0])) {
                cleanupFeaturedCollectionPage();
            }
        });
        pageReplacementObserver.observe(pageSpace[0], {childList: true});

        function loadFeaturedCollectionResourceCounts() {
            const waitingTotals = container.find(
                '.featured-tile p[data-tag="resources_count"]:not([data-count-requested])'
            );
            const refs = [];

            waitingTotals.each(function() {
                const counter = jQuery(this);
                counter.attr('data-count-requested', '1');
                refs.push(counter.data('fc-ref'));
            });

            if (refs.length === 0) {
                return;
            }

            api('get_collections_resource_count', {'refs': refs.join(',')}, function(response) {
                if (!isFeaturedCollectionPageActive()) {
                    return;
                }

                const langResource = <?php echo encode_js_value($lang['youfoundresource']); ?>;
                const langResources = <?php echo encode_js_value($lang['youfoundresources']); ?>;

                Object.keys(response).forEach(function(ref) {
                    const totalCount = response[ref];
                    container.find(
                        '.featured-tile p[data-tag="resources_count"][data-fc-ref="' + ref + '"]'
                    ).text(
                        totalCount + ' ' + (totalCount == 1 ? langResource : langResources)
                    );
                });
            },
            <?php echo generate_csrf_js_object('get_collections_resource_count'); ?>
            );
        }

        // Share one observer across all current and appended tiles. The dwell delay avoids requesting thumbnails for
        // tiles that pass through the prefetch area during fast scrolling.
        imageObserver = new IntersectionObserver(
            function(entries, observer) {
                if (!isFeaturedCollectionPageActive()) {
                    return;
                }

                entries.forEach(function(entry) {
                    const tile = entry.target;

                    if (entry.isIntersecting) {
                        if (tile.fcLoadTimeout !== null && typeof tile.fcLoadTimeout !== 'undefined') {
                            return;
                        }

                        tile.fcLoadTimeout = setTimeout(function() {
                            if (!isFeaturedCollectionPageActive()) {
                                return;
                            }

                            observer.unobserve(tile);

                            jQuery.ajax({
                                url: featuredCollectionConfig.imageUrl,
                                data: {
                                    node: parseInt(tile.dataset.fcRef, 10),
                                    smart: parseInt(tile.dataset.fcSmart, 10)
                                },
                                success: function(html) {
                                    if (!isFeaturedCollectionPageActive()) {
                                        return;
                                    }

                                    jQuery(tile)
                                        .find('.featured-tile-image-container')
                                        .hide()
                                        .html(html)
                                        .fadeIn(300);
                                },
                                error: function() {
                                    if (!isFeaturedCollectionPageActive()) {
                                        return;
                                    }

                                    jQuery(tile)
                                        .find('.featured-collection-image-loading')
                                        .remove();
                                }
                            });
                        }, 200);
                    } else if (tile.fcLoadTimeout !== null && typeof tile.fcLoadTimeout !== 'undefined') {
                        clearTimeout(tile.fcLoadTimeout);
                        tile.fcLoadTimeout = null;
                    }
                });
            },
            {rootMargin: '300px'}
        );

        function observeFeaturedCollectionImages() {
            if (!isFeaturedCollectionPageActive()) {
                return;
            }

            container.find(
                '.featured-collection-ajax-load:not([data-fc-observed])'
            ).each(function() {
                this.dataset.fcObserved = '1';
                imageObserver.observe(this);
            });
        }

        function finishFeaturedCollectionBatchLoading() {
            batchObserver?.disconnect();
            rowResizeObserver?.disconnect();
            container.find('.featured-collection-row-placeholder').remove();
            container.find('#featured-collection-batch-loading').remove();
            container.find('.featured-collection-action-tile').prop('hidden', false);
        }

        /** Read the live CSS grid rather than duplicating responsive breakpoints in JavaScript. */
        function getFeaturedCollectionColumnCount() {
            const gridColumns = window.getComputedStyle(container[0]).gridTemplateColumns;
            const columns = gridColumns.split(' ').filter(Boolean).length;

            return Math.max(1, Math.min(12, columns));
        }

        /** Count collection tiles only; action tiles and temporary row placeholders are deliberately excluded. */
        function getLoadedFeaturedCollectionTileCount() {
            return container.find(
                'a.FeaturedSimpleTile:not(.featured-collection-action-tile)'
            ).length;
        }

        function removeFeaturedCollectionRowPlaceholders() {
            container.find('.featured-collection-row-placeholder').remove();
        }

        /** Occupy missing grid cells while a resize-triggered row-fill request is in progress. */
        function showFeaturedCollectionRowPlaceholders(count) {
            const loader = container.find('#featured-collection-batch-loading');
            const firstTile = container.find(
                'a.FeaturedSimpleTile:not(.featured-collection-action-tile)'
            ).first();
            const tileHeight = firstTile.outerHeight();

            removeFeaturedCollectionRowPlaceholders();
            for (let index = 0; index < count; index++) {
                const placeholder = jQuery('<div>', {
                    class: 'featured-collection-row-placeholder',
                    'aria-hidden': 'true'
                });
                if (tileHeight) {
                    placeholder.height(tileHeight);
                }
                loader.before(placeholder);
            }
        }

        function observeFeaturedCollectionBatchLoader() {
            const loader = container.find('#featured-collection-batch-loading')[0];
            if (!loader || !batchObserver || !isFeaturedCollectionPageActive()) {
                return;
            }

            batchObserver.unobserve(loader);
            batchObserver.observe(loader);
        }

        /**
         * Load either a normal target-sized batch or only the tiles required to complete a row after layout changes.
         * The endpoint validates the column hint and retains control of the actual response size.
         */
        function loadNextFeaturedCollectionBatch(fillRow) {
            if (!isFeaturedCollectionPageActive()) {
                return;
            }

            const loader = container.find('#featured-collection-batch-loading');
            const columns = getFeaturedCollectionColumnCount();
            const loadedTileCount = getLoadedFeaturedCollectionTileCount();
            const rowRemainder = loadedTileCount % columns;

            if (loader.length === 0) {
                return;
            }

            if (batchRequestActive) {
                rowReconciliationPending = rowReconciliationPending || fillRow;
                return;
            }

            if (fillRow && rowRemainder === 0) {
                return;
            }

            if (fillRow) {
                showFeaturedCollectionRowPlaceholders(columns - rowRemainder);
            }

            batchRequestActive = true;
            batchRequestSucceeded = false;
            batchRequest = jQuery.ajax({
                url: featuredCollectionConfig.batchUrl,
                dataType: 'json',
                data: {
                    parent: featuredCollectionConfig.parent,
                    smart_rtf: featuredCollectionConfig.smartRtf,
                    smart_fc_parent: featuredCollectionConfig.smartFcParent,
                    offset: featuredCollectionConfig.offset,
                    columns: columns,
                    fill_row: fillRow ? 1 : 0,
                    k: featuredCollectionConfig.key
                },
                success: function(response) {
                    if (!isFeaturedCollectionPageActive()) {
                        return;
                    }

                    batchRequestSucceeded = true;
                    removeFeaturedCollectionRowPlaceholders();
                    if (response.html) {
                        loader.before(response.html);
                    }
                    featuredCollectionConfig.offset = parseInt(response.offset, 10);
                    observeFeaturedCollectionImages();
                    loadFeaturedCollectionResourceCounts();

                    if (container.hasClass('ui-sortable')) {
                        container.sortable('refresh');
                    }

                    if (!response.has_more) {
                        finishFeaturedCollectionBatchLoading();
                    }
                },
                error: function() {
                    if (!isFeaturedCollectionPageActive()) {
                        return;
                    }

                    removeFeaturedCollectionRowPlaceholders();
                    loader.find('i').removeClass('lucide--spin');
                    loader.find('span').text(<?php echo encode_js_value($lang['error']); ?>);
                },
                complete: function() {
                    if (!isFeaturedCollectionPageActive()) {
                        return;
                    }

                    batchRequest = null;
                    batchRequestActive = false;
                    if (batchRequestSucceeded) {
                        observeFeaturedCollectionBatchLoader();
                    }
                    if (rowReconciliationPending) {
                        rowReconciliationPending = false;
                        reconcileFeaturedCollectionRows();
                    }
                }
            });
        }

        /** Complete a partial collection row whenever responsive layout changes expose empty grid cells. */
        function reconcileFeaturedCollectionRows() {
            if (!isFeaturedCollectionPageActive()) {
                return;
            }

            const loader = container.find('#featured-collection-batch-loading');
            if (loader.length === 0) {
                return;
            }

            const columns = getFeaturedCollectionColumnCount();
            if (getLoadedFeaturedCollectionTileCount() % columns !== 0) {
                loadNextFeaturedCollectionBatch(true);
            }
        }

        // Delegation keeps menu interaction to one handler set, including tiles appended by later batches.
        container
            .off('.featuredCollections')
            .on('mouseenter.featuredCollections', interactiveMenuSelector, function() {
                const tile = jQuery(this).closest('a.FeaturedSimpleTile');
                if (tile.data('fc-link-disabled')) {
                    return;
                }

                tile.data('fc-link-disabled', true);
                tile.data('fc-original-href', tile.attr('href'));
                tile.data('fc-original-onclick', tile.attr('onclick'));
                tile.attr('href', '#').attr('onclick', 'return false;');
                container.find('a.FeaturedSimpleTile').not(tile).css('pointer-events', 'none');
            })
            .on('mouseleave.featuredCollections', interactiveMenuSelector, function() {
                const tile = jQuery(this).closest('a.FeaturedSimpleTile');
                if (!tile.data('fc-link-disabled')) {
                    return;
                }

                tile.attr('href', tile.data('fc-original-href'));
                tile.attr('onclick', tile.data('fc-original-onclick'));
                tile.removeData('fc-link-disabled fc-original-href fc-original-onclick');
                container.find('a.FeaturedSimpleTile').css('pointer-events', '');
            })
            .on('mousedown.featuredCollections', interactiveMenuSelector, function(event) {
                event.stopPropagation();
            });

        jQuery(document)
            .off('keydown.featuredCollections mousedown.featuredCollections')
            .on('keydown.featuredCollections', function(event) {
                if (event.key === 'Escape') {
                    hideContextMenu();
                }
            })
            .on('mousedown.featuredCollections', function(event) {
                if (!event.target.closest('.flyout-menu')) {
                    hideContextMenu();
                }
            });

        observeFeaturedCollectionImages();
        loadFeaturedCollectionResourceCounts();

        batchObserver = new IntersectionObserver(
            function(entries) {
                if (!isFeaturedCollectionPageActive()) {
                    return;
                }

                if (entries.some(function(entry) { return entry.isIntersecting; })) {
                    loadNextFeaturedCollectionBatch(false);
                }
            },
            {rootMargin: '300px'}
        );
        observeFeaturedCollectionBatchLoader();
        reconcileFeaturedCollectionRows();

        // Observe the grid itself because navigation/UI changes can alter its width without a window resize event.
        if (container.find('#featured-collection-batch-loading').length > 0) {
            currentColumnCount = getFeaturedCollectionColumnCount();
            rowResizeObserver = new ResizeObserver(function() {
                if (!isFeaturedCollectionPageActive()) {
                    return;
                }

                window.clearTimeout(resizeTimeout);
                resizeTimeout = window.setTimeout(function() {
                    if (!isFeaturedCollectionPageActive()) {
                        return;
                    }

                    const newColumnCount = getFeaturedCollectionColumnCount();
                    if (newColumnCount === currentColumnCount) {
                        return;
                    }
                    currentColumnCount = newColumnCount;
                    reconcileFeaturedCollectionRows();
                }, 150);
            });
            rowResizeObserver.observe(container[0]);
        }

    <?php if ($allow_fc_reorder) { ?>
        // Re-order capability
        if (!is_touch_device() && container.find('.SortableItem').length > 0) {
            container.sortable({
                items: '.SortableItem',
                distance: 10,
                // A sortable tile is also a navigation link. Disable it only after dragging has actually started so
                // the click generated by mouse-up cannot navigate away from the reordered tile list.
                start: function(event, ui) {
                    ui.item.data('fc-sort-original-href', ui.item.attr('href'));
                    ui.item.data('fc-sort-original-onclick', ui.item.attr('onclick'));
                    ui.item.attr('href', '#').attr('onclick', 'return false;');
                },
                stop: function(event, ui) {
                    window.setTimeout(function() {
                        ui.item.attr('href', ui.item.data('fc-sort-original-href'));
                        ui.item.attr('onclick', ui.item.data('fc-sort-original-onclick'));
                        ui.item.removeData('fc-sort-original-href fc-sort-original-onclick');
                    }, 0);
                },
                update: function() {
                    let visibleOrder = container.find('.SortableItem').map(function() {
                        return parseInt(jQuery(this).data('fc-ref'), 10);
                    }).get();
                    const visibleRefs = new Set(visibleOrder);
                    const positions = [];

                    if (featuredCollectionConfig.descending) {
                        visibleOrder = visibleOrder.reverse();
                    }

                    // Map loaded tiles back into their original positions so unloaded siblings retain their order.
                    featuredCollectionConfig.fullOrder.forEach(function(ref, index) {
                        if (visibleRefs.has(ref)) {
                            positions.push(index);
                        }
                    });

                    if (positions.length !== visibleOrder.length) {
                        return;
                    }

                    positions.forEach(function(position, index) {
                        featuredCollectionConfig.fullOrder[position] = visibleOrder[index];
                    });

                    api(
                        'reorder_featured_collections',
                        {'refs': featuredCollectionConfig.fullOrder},
                        null,
                        <?php echo generate_csrf_js_object('reorder_featured_collections'); ?>
                    );
                }
            });
        }
    <?php } ?>
    });
</script>

<?php
include "../include/footer.php";
