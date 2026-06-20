<?php
/*
 * Dash Tile Generation Functions - Montala Ltd, Jethro Dew
 * These control the content for the different variations of tile type and tile style.
 *
 */

/*
 * Tile serving
 *
 */
function tile_select($tile_type, $tile_style, $tile, $tile_id, $tile_width, $tile_height)
{
    /*
     * Preconfigured and the legacy tiles controlled by config.
     */
    if ($tile_type == "conf") {
        switch ($tile_style) {
            case "thmsl":
                tile_config_themeselector($tile, $tile_id, $tile_width, $tile_height);
                exit;
            case "custm":
                tile_freetext($tile, $tile_id);
                exit;
            case "pend":
            case "upld":
                tile_icon($tile, $tile_id);
                exit;
            case "analytics":
                tile_graph($tile, $tile_id);
                exit();
        }
    }
    /*
     * Free Text Tile
     */
    if ($tile_type == "ftxt") {
        tile_freetext($tile, $tile_id);
        exit;
    }

    /*
     * Search Type tiles
     */
    if ($tile_type == "srch") {
        switch ($tile_style) {
            case "thmbs":
                $promoted_image = getval("promimg", false);
                tile_search_thumbs($tile, $tile_id, $tile_width, $tile_height, $promoted_image);
                exit;
            case "multi":
                tile_search_multi_or_blank($tile, $tile_id, $tile_width, $tile_height);
                exit;
            case "blank":
                tile_freetext($tile, $tile_id);
                exit;
        }
    }

    // Featured collection - themes specific tiles
    if ('fcthm' == $tile_type) {
        switch ($tile_style) {
            case 'thmbs':
                tile_featured_collection_thumbs($tile, $tile_id, $tile_width, $tile_height, getval('promimg', 0));
                break;

            case 'multi':
                tile_featured_collection_multi($tile, $tile_id, $tile_width, $tile_height, getval('promimg', 0));
                break;

            case 'blank':
            default:
                tile_freetext($tile, $tile_id);
                break;
        }

        exit();
    }
}

/**
 *  Generate the HTML for a graph dash tile.
 * 
 *  @param array    $tile array usually from get_tile()
 *  @param string   $tile_id string used to identify the tile on the page
 */
function tile_graph(array $tile, string $tile_id): void
{     
    global $baseurl_short, $lang;

    $url_parts = parse_url($tile['url'], PHP_URL_QUERY);
    parse_str($url_parts, $url_parts);

    $graph_build_string = $url_parts['data'];
    $graph_build_string = parse_url($graph_build_string);
    if (strpos($graph_build_string['path'], 'pages/team/ajax/graph.php') !== 0) {
        die($graph_build_string['path']);
        exit();
    }
    // Sanatize url
    parse_str($graph_build_string['query'], $graph_params);
    $graph_build_string = generateURL($baseurl_short . $graph_build_string['path'], $graph_params);

    $graph_types = [
        'pie'               => 'doughnut',
        'piegroup'          => 'doughnut',
        'pieresourcetype'   => 'doughnut',
        'line'              => 'line'
    ];
    
    if (in_array($graph_params['type'], array_keys($graph_types))) {
        ?>
        <div class="tile-graph">
            <h2 class="tile-graph-title"><?php echo escape($tile['title']); ?></h2>
            <div class="tile-graph-container <?php echo $graph_params['type'] == 'line' ? 'tile-line-graph' : 'tile-pie-graph'; ?>" >
                <canvas id="tile_graph_canvas<?php echo escape($tile_id); ?>"></canvas>
            </div>
            <?php
                if($graph_types[$graph_params['type']] == 'doughnut') {
                    ?>
                        <div id="legend_container_<?php echo escape($tile_id); ?>" class="tile-graph-legend"></div>
                    <?php
                }
            ?>
            <script>
                new Chart(
                    jQuery('#tile_graph_canvas<?php echo escape($tile_id); ?>'),
                    {
                        type: "<?php echo $graph_types[$graph_params['type']]; ?>",
                        data: {
                            labels: [],
                            datasets:[{
                                label: '',
                                data: [],
                                pointStyle: 'circle',
                                pointRadius: 4
                            }]
                        },
                        options: {
                            <?php 
                                if($graph_types[$graph_params['type']] == 'doughnut') { 
                                ?>
                                    cutout: "60%",
                                <?php
                                }
                            ?>
                            responsive: true,
                            <?php
                                if ($graph_params['type'] == 'line') {
                                    ?>
                                        aspectRatio: 3,
                                    <?php
                                }
                            ?>
                            plugins: {
                                colors: {
                                    enabled: false,
                                },
                                legend: {
                                    display: false,
                                },
                                <?php if ($graph_types[$graph_params['type']] == 'doughnut') { ?>
                                    tooltip: {
                                        callbacks: {
                                            label: function(context) {
                                                let value = context.raw;
                                                let sum = context.dataset.data.reduce(function(s,a){return s+a;},0);

                                                let label = Math.round(value/sum*100) + "% (" + value + ")";
                                                return label;
                                            }
                                        }
                                    },
                                <?php } ?>
                                legend: {
                                    display: false,                                      
                                },
                                tile_graph_legend: {
                                    containerID: 'legend_container_<?php echo escape($tile_id); ?>'
                                }
                            },
                            scales: {
                                x: {
                                    type: 'time',
                                    time: {
                                        unit:'day',
                                        displayFormats :{
                                            day: 'dd-MM-YYY',
                                        }
                                    },
                                    unit: 'seconds',
                                    ticks: {
                                        display: false
                                    },
                                    border: {
                                        display: false
                                    }
                                }
                            }
                        },
                        plugins: [tile_graph_legend]
                    }
                )

                async function load_tile_graph_<?php echo escape($tile_id); ?>() {
                    let response = await fetch('<?php echo $graph_build_string ?>');
                    let data     = await response.json();
                    let canvas   = jQuery('#tile_graph_canvas<?php echo escape($tile_id); ?>');
                    let chart    = Chart.getChart(canvas);

                    <?php 
                        if ($graph_types[$graph_params['type']] == 'doughnut') {
                        ?>
                            chart.data.labels = data.map(d => d['name']);
                            chart.data.datasets[0].data = data.map(d => d['c']);
                            chart.data.datasets[0].backgroundColor = dash_chart_palette;
                        <?php 
                        } else {
                        ?>
                            let root = getComputedStyle(document.documentElement);
                            chart.data.datasets[0].data = data;
                            chart.data.datasets[0].borderColor = root.getPropertyValue('--colour-brand-primary-default');
                            chart.data.datasets[0].backgroundColor = root.getPropertyValue('--colour-brand-primary-default');
                            chart.data.datasets[0].pointBorderColor = root.getPropertyValue('--colour-brand-primary-default');
                            chart.options.scales.y.grid.color = root.getPropertyValue('--colour-surface-400');
                            chart.options.scales.x.grid.color = root.getPropertyValue('--colour-surface-400');     
                        <?php
                        }
                    ?>
                    // Has to be called twice as there is a known bug when calling update with no animation that the points will not be
                    // recoloured on a line graph. This allows for the no animation load and the recolouring
                    chart.update('none');
                    chart.update();
                }

                load_tile_graph_<?php echo escape($tile_id); ?>();
            </script>
            </div>
            <div class="tile-desc">
                <?php
                    generate_dash_tile_toolbar($tile, $tile_id);
                ?>
            </div>
        <?php
    } elseif ($graph_params['type'] == 'summary') {
        ?>
        <div id="summary_<?php echo escape($tile_id); ?>" class="tile-graph">
            <h2 class="tile-graph-title"><?php echo escape($tile['title']); ?></h2>
            <div class="tile-summary">
                <p></p>
                <h2><?php echo escape($lang['report_total']); ?></h2>
            </div>
            <div class="tile-summary">
                <p></p>
                <h2><?php echo escape($lang['report_average']); ?></h2>
            </div>
        </div>
        <div class="tile-desc">
            <?php
                generate_dash_tile_toolbar($tile, $tile_id);
            ?>
        </div>
        <script>
            async function load_tile_graph_<?php echo escape($tile_id); ?>() {
                let response = await fetch('<?php echo $graph_build_string ?>');
                let data     = await response.json();
                let parent   = jQuery('#summary_<?php echo escape($tile_id); ?>');
                
                let totals   = parent.children('.tile-summary')
                jQuery(totals[0]).children('p').text(data.total)
                jQuery(totals[1]).children('p').text(data.average)
            }

            load_tile_graph_<?php echo escape($tile_id); ?>()
        </script>
        <?php
    }
}

/*
 * Config controlled panels
 *
 */

function tile_config_themeselector($tile, $tile_id, $tile_width, $tile_height)
{
    global $lang,$pagename,$baseurl_short, $theme_direct_jump;

    $url = "{$baseurl_short}pages/collections_featured.php";
    $fc_categories = get_featured_collection_categories(0, []);    
    
    $resources = dash_tile_featured_collection_get_top_resources();
    $resources = array_pad($resources, 3, null);

    ?>
    <div class="tile-multi">
        <?php
            for ($i = 0;$i <= 2; $i++) {            
                if ($resources[$i] !== null && $i == 0) {
                    ?>
                        <img
                            alt="<?php echo escape($resources[$i]['title']); ?>"
                            src="<?php echo get_resource_path($resources[$i]['ref'], false, 'pre', false, 'jpg'); ?>">
                    <?php
                } elseif ($i == 0) { 
                    ?>
                        <div class="tile-placeholder">
                            <div class="thumbs-tile-image"></div>
                        </div>
                    <?php
                } else {
                    if ($i == 1) {
                        ?>
                        <div class="tile-sub-multi">
                        <?php    
                    }
                            if ($resources[$i] !== null) {
                                ?>
                                    <img
                                        alt="<?php echo escape($resources[$i]['title']); ?>"
                                        src="<?php echo get_resource_path($resources[$i]['ref'], false, 'pre', false, 'jpg'); ?>">
                                <?php
                            } else {
                                ?>
                                    <div></div>
                                <?php
                            }
                    if ($i == 2) {
                        ?>
                        </div>
                        <?php    
                    }
                }
            }
        ?>
    </div>
    <div class="tile-desc">
        <div class="field-input tile-select">
            <?php if (!empty($fc_categories)) { ?>
                <select id="themeselect" onChange="CentralSpaceLoad(this.value,true);">
                    <option value=""><?php echo escape($lang["select"]); ?></option>
                        <?php foreach ($fc_categories as $header) { ?>
                            <option value="<?php echo generateURL($url, array("parent" => $header["ref"])); ?>">
                                <?php echo escape(i18n_get_translated($header["name"])); ?>
                            </option>
                            <?php
                        }
                        ?>
                </select>
                <?php
            }
            ?>
        </div>
        <h2><?php echo escape($lang["themes"]); ?></h2>
        <?php
            if (!$theme_direct_jump) { 
                ?>
                    <p>
                        <?php echo escape($lang['or']); ?>
                        <a onClick="return CentralSpaceLoad(this,true);" href="<?php echo $url; ?>">
                            <?php echo escape($lang['view_all_fcs']); ?>
                            <i class="icon-arrow-right"></i>
                        </a>
                    </p>
                <?php
            }
        generate_dash_tile_toolbar($tile, $tile_id);
        ?>
    </div>
    <?php
}

/**
 *  Generate HTML for an icon tile, i.e. pending submission, pending review or upload
 * 
 *  @param array    $tile array usually from get_tile()
 *  @param string   $tile_id string used to identify the tile on the page
 */
function tile_icon(array $tile, string $tile_id): void
{
    global $lang, $search_all_workflow_states;
    
    $upload_tile = true;
    if (strpos($tile['link'], 'uploader=') === false) {
        $upload_tile = false;
        $linkstring = explode('?', $tile["link"]);
        parse_str(str_replace("&amp;", "&", $linkstring[1]), $linkstring);

        $search = "";
        $count = 1;
        $restypes = "";
        $order_by = "relevance";
        $archive = $linkstring["archive"];
        $sort = "";
        $search_all_workflow_states = false;
        $tile_search = do_search($search, $restypes, $order_by, $archive, $count, $sort, false, 0, false, false, "", false, false, false, true);

        if (!is_array($tile_search)) {
            $found_resources = false;
            $count = 0;
        } else {
            $found_resources = true;
            $count = count($tile_search);
        }

        // Hide if no results
        if (!$found_resources || $count == 0) {
            global $usertile;

            $tile_element_id = isset($usertile) ? "user_tile{$usertile['ref']}" : "tile{$tile['ref']}";
            ?>
            <style>
                #<?php echo escape($tile_element_id); ?> {
                    display: none;
                }
            </style>
            <?php
            return;
        }
    }
    if ($upload_tile) {
        $icon = "icon-upload";
    } elseif ($archive == -2) {
        $icon = "icon-file-input";
    } elseif ($archive == -1) {
        $icon = "icon-file-search";
    }
    ?>

    <div class="tile-special-content">
        <div class="tile-special-icon">
            <i class="<?php echo $icon; ?>"></i>
            <?php 
                if (!$upload_tile) {
                    ?>
                        <span class="tile-pill">
                            <?php echo (int) $count; ?>
                        </span>
                    <?php
                }
            ?>
        </div>
    </div>
    <div class="tile-desc">
        <h2>
            <?php
                if (!empty($tile['title'])) {
                    echo escape(i18n_get_translated($tile['title']));
                } elseif (!empty($tile['txt']) && isset($lang[strtolower($tile['txt'])])) {
                    echo escape($lang[strtolower($tile['txt'])]);
                } elseif (!empty($tile['txt'])) {
                    echo escape(i18n_get_translated($tile['txt']));
                }
            ?>
        </h2>
        <?php
            if (!empty($tile['title']) && !empty($tile['txt'])) {
                if (isset($lang[strtolower($tile['txt'])])) {
                    ?>
                    <p><?php echo escape($lang[strtolower($tile['txt'])]); ?></p>
                    <?php
                } else {
                    ?>
                    <p><?php echo escape(i18n_get_translated($tile['txt'])); ?></p>
                    <?php
                }
            }
            generate_dash_tile_toolbar($tile, $tile_id);
        ?>
    </div>
    <?php
}

/**
 *  Generate HTML for a freetext tile
 * 
 *  @param array         $tile array usually from get_tile()
 *  @param string|null   $tile_id string used to identify the tile on the page
 */
function tile_freetext(array $tile, string|null $tile_id = null): void
{
    ?>
    <div class="tile-desc tile-freetext">
        <h2><?php echo escape(i18n_get_translated($tile["title"])); ?></h2>
        <p><?php echo escape(i18n_get_translated($tile["txt"])); ?></p>
        <?php
            if ($tile_id !== null) {
                generate_dash_tile_toolbar($tile, $tile_id, $tile['resource_count'] !== 0);
            }
        ?>
    </div>
    <?php
}

/*
 * Search linked tiles
 *
 */
function tile_search_thumbs($tile, $tile_id, $tile_width, $tile_height, $promoted_image = false)
{
    ?>
        <div class="tile-desc">
            <?php
            if (!empty($tile["title"])) { ?>
                <h2>
                    <?php echo escape(i18n_get_translated($tile["title"]));?>
                </h2>
                <?php
            } elseif (!empty($tile["txt"])) { ?>
                <h2>
                    <?php echo escape(i18n_get_translated($tile["txt"]));?>
                </h2>
                <?php
            }
            if (!empty($tile["title"]) && !empty($tile["txt"])) { ?>
                <p><?php echo escape(i18n_get_translated($tile["txt"]));?></p>
                <?php
            }
            generate_dash_tile_toolbar($tile, $tile_id);
            ?>
        </div>
    <?php
    tltype_srch_generate_js_for_background_and_count($tile, $tile_id, (int) $tile_width, (int) $tile_height, (int) $promoted_image);
}

function tile_search_multi_or_blank($tile, $tile_id, $tile_width, $tile_height)
{
    ?>
        <div class="tile-multi">
            <div class="tile-sub-multi">
            </div>
        </div>
        <div class="tile-desc">
            <?php
            if (!empty($tile["title"])) { ?>
                <h2>
                    <?php echo escape(i18n_get_translated($tile["title"]));?>
                </h2>
                <?php
            } elseif (!empty($tile["txt"])) { ?>
                <h2>
                    <?php echo escape(i18n_get_translated($tile["txt"]));?>
                </h2>
                <?php
            }

            if (!empty($tile["title"]) && !empty($tile["txt"])) { ?>
                <p><?php echo escape(i18n_get_translated($tile["txt"]));?></p>
                <?php
            }
            generate_dash_tile_toolbar($tile, $tile_id);
            ?>
        </div>
    <?php

    tltype_srch_generate_js_for_background_and_count($tile, $tile_id, (int) $tile_width, (int) $tile_height, 0);
}

function tile_featured_collection_thumbs($tile, $tile_id, $tile_width, $tile_height, $promoted_image)
{
    global $baseurl_short, $lang, $view_title_field;

    if ($promoted_image > 0) {
        $promoted_image_data = get_resource_data($promoted_image);
    }
    if (isset($promoted_image_data) && $promoted_image_data !== false) {
        $preview_resource = $promoted_image_data;
    } else {
        $preview_resource = null;
    }

    $preview_resource_mod = hook('modify_promoted_image_preview_resource_data', '', array($promoted_image));
    if ($preview_resource_mod !== false) {
        $preview_resource = $preview_resource_mod;
    }

    $no_preview = false;

    if (
        $preview_resource !== null
        && !resource_has_access_denied_by_RT_size($preview_resource['resource_type'], 'pre')
        && file_exists(get_resource_path($preview_resource['ref'], true, 'pre', false, 'jpg', -1, 1, false))
    ) {
        $preview_path = get_resource_path($preview_resource['ref'], false, 'pre', false, 'jpg', -1, 1, false);
    } else {
        $preview_path  = null;
    }

    if ($preview_path !== null) {
        ?>
            <img 
                alt="<?php echo escape(i18n_get_translated(($promoted_image_data["field" . $view_title_field] ?? ""))); ?>"
                src="<?php echo $preview_path; ?>" 
                class="thmbs-tile-img"
            />
        <?php
    } else {
        ?>
            <div class="tile-placeholder">
                <div class="thumbs-tile-image"></div>
            </div>
        <?php
    }
    ?>
        <div class="tile-desc">
            <?php 
                if ('' != $tile['title']) {
                    echo '<h2>' . escape(i18n_get_translated($tile['title'])) . '</h2>';
                } elseif ('' != $tile['txt']) {
                    echo '<h2>' . escape(i18n_get_translated($tile['txt'])) . '</h2>';
                }
                if ('' != $tile['title'] && '' != $tile['txt']) {
                    echo '<p>' . escape(i18n_get_translated($tile['txt'])) . '</p>';
                }
                generate_dash_tile_toolbar($tile, $tile_id);
            ?>
        </div>
    <?php
}

function tile_featured_collection_multi($tile, $tile_id, $tile_width, $tile_height, $promoted_image)
{
    global $baseurl_short, $lang, $view_title_field;

    $link_parts = explode('?', $tile['link']);
    parse_str(str_replace('&amp;', '&', $link_parts[1]), $link_parts);

    $parent = (isset($link_parts["parent"]) ? (int) validate_collection_parent(array("parent" => (int) $link_parts["parent"])) : 0);
    $resources = dash_tile_featured_collection_get_resources($parent, array("limit" => 4));

    if (count($resources) <= 1) {
        return tile_featured_collection_thumbs($tile, $tile_id, $tile_width, $tile_height, $resources[0]['ref'] ?? 0);
    }
    $preview_paths = [];
    foreach (array_rand($resources, min(count($resources), 3)) as $random_picked_resource_key) {
        $resource = $resources[$random_picked_resource_key];
        if (
            !resource_has_access_denied_by_RT_size($resource['resource_type'], 'pre')
            && file_exists(get_resource_path($resource['ref'], true, 'pre', false, 'jpg', -1, 1, false))
        ) {
            $preview_paths[] = 
                ['path' => get_resource_path($resource['ref'], false, 'pre', false, 'jpg', -1, 1, false),
                'title' => escape(i18n_get_translated(($resource["field" . $view_title_field] ?? "")))];
        } else {
            $preview_paths[] = 
                ['path' => null, 
                'title' => escape(i18n_get_translated(($resource["field" . $view_title_field] ?? "")))];
        }
    }
    usort($preview_paths, function ($a, $b) {
        return ($a['path'] === null) <=> ($b['path'] === null);
    });
    ?>
        <div class="tile-multi"> 
            <?php
                for ($i = 0;$i <= 2; $i++) {            
                    if ($preview_paths[$i]['path'] !== null && $i == 0) {
                        ?>
                            <img
                                alt="<?php echo escape($preview_paths[$i]['title']); ?>"
                                src="<?php echo $preview_paths[$i]['path']; ?>">
                        <?php
                    } elseif ($i == 0) { 
                        ?>
                            <div class="tile-placeholder">
                                <div class="thumbs-tile-image" alt="<?php echo escape($preview_paths[$i]['title']); ?>"></div>
                            </div>
                        <?php
                    } else {
                        if ($i == 1) {
                            ?>
                            <div class="tile-sub-multi">
                            <?php
                        }
                            if ($preview_paths[$i]['path'] !== null) {
                                ?>
                                    <img
                                        alt="<?php echo escape($preview_paths[$i]['title']); ?>"
                                        src="<?php echo $preview_paths[$i]['path']; ?>">
                                <?php
                            } else {
                                ?>
                                    <div alt="<?php echo escape($preview_paths[$i]['title']); ?>"></div>
                                <?php
                            }
                        if ($i == 2) {
                            ?>
                            </div>
                            <?php
                        }
                    }
                }
            ?>
        </div>
        <div class="tile-desc">
            <h2>
                <?php
                if ('' != $tile['title']) {
                    echo escape(i18n_get_translated($tile['title']));
                } elseif ('' != $tile['txt']) {
                    echo escape(i18n_get_translated($tile['txt']));
                }
                ?>
            </h2>
            <?php
            if ('' != $tile['title'] && '' != $tile['txt']) {
                ?>
                <p><?php echo escape(i18n_get_translated($tile['txt'])); ?></p>
                <?php
            }
            generate_dash_tile_toolbar($tile, $tile_id);
            ?>
        </div>
    <?php
}