<?php
# Alternative files listing
$alt_access = hook("altfilesaccess");

if ($access == 0 || $alt_files_visible_when_restricted) {
    $alt_access = true; # open access (not restricted)
}

if ($alt_access) {
    global $request_adds_to_collection;
    $alt_order_by = "";
    $alt_sort = "";

    if ($alt_types_organize) {
        $alt_order_by = "alt_type";
        $alt_sort = "asc";
    }

    if (!isset($altfiles)) {
        $altfiles = get_alternative_files($ref, $alt_order_by, $alt_sort);
    }

    $last_alt_type = "-";

    for ($n = 0; $n < count($altfiles); $n++) {
        $alt_type = $altfiles[$n]['alt_type'];

        if ($alt_types_organize) {
            if ($alt_type != $last_alt_type) {
                $alt_type_header = $alt_type;

                if ($alt_type_header == "") {
                    $alt_type_header = $lang["alternativefiles"];
                }
                ?>

                <tr class="DownloadDBlend">
                    <td colspan="2" id="altfileheader">
                        <h2><?php echo $alt_type_header; ?></h2>
                    </td>
                </tr>

                <?php
            }
            $last_alt_type = $alt_type;
        } elseif ($n == 0) {
            ?>
            <tr>
                <td colspan="2" id="altfileheader"><?php echo escape($lang["alternativefiles"]); ?></td>
            </tr>
            <?php
        }

        global $alt_thm;
        $alt_thm = '';
        $alt_pre = '';

        if ($alternative_file_previews) {
            $use_watermark = check_use_watermark();

            if (file_exists(get_resource_path($ref, true, 'col', false, 'jpg', true, 1, $use_watermark, '', $altfiles[$n]['ref']))) {
                # Get web path for thumb (pass creation date to help cache refresh)
                $alt_thm = get_resource_path($ref, false, 'col', false, 'jpg', true, 1, $use_watermark, $altfiles[$n]['creation_date'], $altfiles[$n]['ref']);
            }

            if (file_exists(get_resource_path($ref, true, 'pre', false, 'jpg', true, 1, $use_watermark, '', $altfiles[$n]['ref']))) {
                # Get web path for preview (pass creation date to help cache refresh)
                $alt_pre = get_resource_path($ref, false, 'pre', false, 'jpg', true, 1, $use_watermark, $altfiles[$n]['creation_date'], $altfiles[$n]['ref']);
            }
        }

        $enable_alt_file_preview_mouseover = $alt_pre != '' && $alternative_file_previews_mouseover;
        $rowspan = 1;

        if (
            in_array(strtolower($altfiles[$n]["file_extension"] ?? ""), VIEW_IN_BROWSER_EXTENSIONS)
            && resource_download_allowed($ref, "", $resource["resource_type"], $altfiles[$n]["ref"])
        ) {
            $rowspan = 2;
        }
        ?>

        <tr class="DownloadDBlend" id="alt_file_preview_<?php echo $altfiles[$n]['ref']; ?>">
            <?php if ($enable_alt_file_preview_mouseover) { ?>
                <script>
                    jQuery(document).ready(function() {
                        orig_preview = jQuery('#previewimage').attr('src');
                        orig_height = jQuery('#previewimage').height();
                        jQuery("#alt_file_preview_<?php echo $altfiles[$n]['ref']; ?>").mouseenter(function() {
                            orig_preview = jQuery('#previewimage').attr('src');
                            orig_height = jQuery('#previewimage').height();
                            jQuery('#previewimage').attr('src','<?php echo $alt_pre ?>');
                            if (orig_height != 0) {
                                jQuery('#previewimage').height(orig_height); 
                            }
                        }).mouseleave(function() {
                            jQuery('#previewimage').attr('src',orig_preview);
                        });
                    });
                </script>
            <?php } ?>

            <td class="DownloadFileName AlternativeFile" rowspan="<?php echo escape((string)$rowspan);?>">
                <?php
                if (
                    !hook("renderaltthumb", "", [$n,$altfiles[$n]])
                    && $alt_thm != ""
                ) {
                    $url = generateURL(
                        $baseurl_short . 'pages/preview.php',
                        [
                            'ref'           => $ref,
                            'alternative'   => $altfiles[$n]['ref'],
                            'k'             => $k,
                            'search'        => $search,
                            'offset'        => $offset,
                            'order_by'      => $order_by,
                            'sort'          => $sort,
                            'archive'       => $archive
                        ]
                    ) . '&' . hook("previewextraurl");
                    ?>
                    <div class="AlternativeFileImage">
                        <a href="<?php echo $url; ?>">
                            <img
                                alt="<?php echo escape(i18n_get_translated($altfiles[$n]['name'] ?? "")); ?>"
                                src="<?php echo $alt_thm; ?>"
                                class="AltThumb"
                            >
                        </a>
                    </div>
                    <?php
                }
                ?>
                <div class="AlternativeFileText">
                    <h2><?php echo escape($altfiles[$n]["name"]); ?></h2>
                    <p><?php echo escape($altfiles[$n]["description"]); ?></p>
                    <p><?php echo escape(str_replace('&nbsp;', ' ', formatfilesize($altfiles[$n]["file_size"]))); ?></p>
                </div>
            </td>

            <?php
            if ($access == 0 && resource_download_allowed($ref, "", $resource["resource_type"], $altfiles[$n]["ref"])) { ?>
                <td <?php hook("modifydownloadbutton") ?> class="DownloadButton">
                    <?php
                    if ($terms_download || $save_as) {
                        if (!hook("downloadbuttonreplace")) {
                            ?>
                            <a
                                <?php if (!hook("downloadlink", "", array("ref=" . $ref . "&alternative=" . $altfiles[$n]["ref"] . "&k=" . $k . "&ext=" . $altfiles[$n]["file_extension"]))) { ?>
                                    href="<?php echo generateURL($baseurl . "/pages/terms.php", $urlparams, array("url" => generateURL($baseurl_short . "pages/download_progress.php", $urlparams, array("alternative" => $altfiles[$n]["ref"],"ext" => $altfiles[$n]["file_extension"])))); ?>"
                                <?php } ?>
                                onclick="return CentralSpaceLoad(this,true);"
                            >
                                <?php echo escape($lang["action-download"]); ?>
                            </a>
                            <?php
                        }
                    } elseif ($download_usage) {
                    // download usage form displayed - load into main window
                        ?>
                        <a
                            <?php if (!hook("downloadlink", "", array("ref=" . $ref . "&alternative=" . $altfiles[$n]["ref"] . "&k=" . $k . "&ext=" . $altfiles[$n]["file_extension"]))) { ?>
                                href="<?php echo generateURL($baseurl . '/pages/download_usage.php', $urlparams, array('ext' => $altfiles[$n]['file_extension'],'alternative' => $altfiles[$n]['ref'])); ?>"
                            <?php } ?>
                        >
                            <?php echo escape($lang["action-download"]); ?>
                        </a>
                        <?php
                    } else {
                        ?>
                        <a
                            <?php if (!hook("downloadlink", "", array("ref=" . $ref . "&alternative=" . $altfiles[$n]["ref"] . "&k=" . $k . "&ext=" . $altfiles[$n]["file_extension"]))) {
                                echo 'href="#" onclick="directDownload(\'' . generateURL($baseurl . '/pages/download_progress.php', $urlparams, array('ext' => $altfiles[$n]['file_extension'],'alternative' => $altfiles[$n]['ref'])) . "', this); toastNotification('Success', '" . escape($lang["downloadinprogress"]) . "');\"";
                            } ?>
                        >
                            <?php echo escape($lang["action-download"]); ?>
                        </a>
                        <?php
                    } ?>
                </td>
                <?php
            } elseif (checkperm("q")) {
                ?>
                <td class="DownloadButton">
                    <?php if ($request_adds_to_collection && ($k == "" || $internal_share_access) && !checkperm('b')) {
                        // We can't add to a collection if we are accessing an external share, unless we are a logged in user
                        echo add_to_collection_link($ref, "alert('" . escape($lang["requestaddedtocollection"]) . "');");
                    } else {
                        ?>
                        <a href="<?php echo generateURL($baseurl . "/pages/resource_request.php", $urlparams) ?>" onclick="return CentralSpaceLoad(this,true);">
                        <?php
                    }

                    echo escape($lang["action-request"]); ?>
                    </a>
                </td>
                <?php
            } else {
                ?>
                <td class="DownloadButton DownloadDisabled"><?php echo escape($lang["access1"]); ?></td>
                <?php
            }
            ?>
        </tr>

        <?php if ($rowspan === 2) { ?>
            <tr class="DownloadDBlend">
                <?php
                $preview_url = generateURL($baseurl . "/pages/download.php", ["ref" => $ref, "ext" => $altfiles[$n]["file_extension"], "alternative" => $altfiles[$n]["ref"], "noattach" => "true", 'k' => $k]);

                if ($terms_download) {
                    $preview_url = generateURL($baseurl . "/pages/terms.php", ['ref' => $ref, 'url' => $preview_url, 'alternative' => $altfiles[$n]['ref'], 'k' => $k]);
                } elseif ($download_usage) {
                    $preview_url = generateURL($baseurl . "/pages/download_usage.php", ['ref' => $ref, 'url' => $preview_url, 'alternative' => $altfiles[$n]['ref'], 'k' => $k]);
                }
                ?>
                <td colspan="2" class="DownloadButton">
                    <a href="<?php echo $preview_url;?>" target="_blank"><?php echo escape($lang["view_in_browser"]);?></a>
                </td>
            </tr>
            <?php
        }
    }
}
# --- end of alternative files listing