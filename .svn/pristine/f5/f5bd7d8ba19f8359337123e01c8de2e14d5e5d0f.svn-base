<?php

$suppress_headers = true;

if (!$iiif_enabled || !isset($iiif_identifier_field) || !is_numeric($iiif_identifier_field) || !isset($iiif_userid) || !is_numeric($iiif_userid) || !isset($iiif_description_field)) {
    exit($lang["iiif_disabled"]);
}

$iiif_user = get_user($iiif_userid);
if ($iiif_user === false) {
    iiif_error(500, ['Invalid $iiif_userid.']);
}

// Creating $userdata for use in do_search()
$userdata[0] = $iiif_user;

setup_user($iiif_user);
$rootlevel = $baseurl_short . "iiif/";
$rooturl = $baseurl . "/iiif/";
$rootimageurl = $baseurl . "/iiif/image/";
$request_url = strtok($_SERVER["REQUEST_URI"], '?');
$path = substr($request_url, strpos($request_url, $rootlevel) + strlen($rootlevel));
$xpath = explode("/", $path);
$getext = "";
$response = array();
$validrequest = false;
$iiif_headers = array();
$errors = array();
if (count($xpath) == 1 && $xpath[0] == "") {
    # Root level request - send information file only
    $response["@context"] = "http://iiif.io/api/presentation/2/context.json";
    $response["@id"] = $rooturl;
    $response["@type"] = "sc:Manifest";
    $response["@label"] = "";
    $response["width"] = 6000;
    $response["height"] = 4000;

    $response["tiles"] = array();
    $response["tiles"][] = array("width" => $preview_tile_size, "height" => $preview_tile_size, "scaleFactors" => $preview_tile_scale_factors);
    $response["profile"] = array("http://iiif.io/api/image/2/level0.json");

    $validrequest = true;
} else {
    if (strtolower($xpath[0]) == "image") {
        // IMAGE REQUEST (http://iiif.io/api/image/2.1/)
        // The IIIF Image API URI for requesting an image must conform to the following URI Template:
        // {scheme}://{server}{/prefix}/{identifier}/{region}/{size}/{rotation}/{quality}.{format}
        $identifier = trim($xpath[1] ?? '');
        if ($identifier === '') {
            iiif_error(400, ['Missing identifier']);
        }

        if (!isset($xpath[2]) || $xpath[2] == "") {
            // Redirect to image information document
            $redirurl = $rootimageurl . $identifier . '/info.json';
            if (function_exists("http_response_code")) {
                http_response_code(303);
            }
            header("Location: " . $redirurl);
            exit();
        }

        $resourceid = $xpath[1];
        if (is_numeric($resourceid)) {
            $resource =  get_resource_data($resourceid);
            $resource_access =  get_resource_access($resourceid);
        } else {
            $resource_access = 2;
        }

        if ($resource_access == 0 && !in_array($resource["file_extension"], config_merge_non_image_types())) {
            // Check resource actually exists and is active
            $fulljpgsize = is_jpeg_extension($resource["file_extension"] ?? "") ? "" : "hpr";
            $useextension = strtolower($resource["file_extension"]) == "jpeg" ? $resource["file_extension"] : "jpg";
            $img_path = get_resource_path($resourceid, true, $fulljpgsize, false, $useextension);
            $image_size = get_original_imagesize($resourceid, $img_path, $useextension);
            if ($image_size === false) {
                $errors[] = "No image available for this identifier";
                iiif_error(404, $errors);
            }
            $imageWidth = (int) $image_size[1];
            $imageHeight = (int) $image_size[2];

            // Get all available sizes
            $sizes = get_image_sizes($resourceid, true, "jpg", false);
            $availsizes = array();
            if ($imageWidth > 0 && $imageHeight > 0) {
                foreach ($sizes as $size) {
                    if (
                        $size['width'] > 0
                        && $size['height'] > 0
                        && $size['width'] <= $iiif_max_width
                        && $size['height'] <= $iiif_max_height
                        && (
                            !$iiif_only_power_of_two_sizes
                            || (is_power_of_two($size['width']) && is_power_of_two($size['height']))
                            || $size['id'] == 'pre'
                        )
                    ) {
                        $availsizes[] = [
                            'id' => $size['id'],
                            'width' => $size['width'],
                            'height' => $size['height'],
                        ];
                    }
                }
            }

            if ($xpath[2] == "info.json") {
                // Image information request. Only fullsize available in this initial version
                $response["@context"] = "http://iiif.io/api/image/2/context.json";
                $response["@id"] = $rootimageurl . $resourceid;

                $response["height"] = $imageHeight;
                $response["width"]  = $imageWidth;

                $response["profile"] = array();
                $response["profile"][] = "http://iiif.io/api/image/2/level0.json";
                if ($iiif_custom_sizes) {
                    $response["profile"][] = array(
                        "formats" => array("jpg"),
                        "qualities" => array("default"),
                        "maxWidth" => $iiif_max_width,
                        "maxHeight" => $iiif_max_height,
                        "supports" => array("sizeByH","sizeByW")
                        );
                } else {
                    $response["profile"][] = array(
                        "formats" => array("jpg"),
                        "qualities" => array("default"),
                        "maxWidth" => $iiif_max_width,
                        "maxHeight" => $iiif_max_height
                        );
                }

                $response["protocol"] = "http://iiif.io/api/image";
                $response["sizes"] = $availsizes;
                if ($preview_tiles) {
                    $response["tiles"] = array();
                    $response["tiles"][] = array("height" => $preview_tile_size, "width" => $preview_tile_size, "scaleFactors" => $preview_tile_scale_factors);
                }
                $iiif_headers[] = 'Link: <http://iiif.io/api/image/2/level0.json>;rel="profile"';
                $validrequest = true;
            } elseif (!isset($xpath[3]) || !isset($xpath[4]) || !isset($xpath[5]) || !isset($xpath[5]) || $xpath[5] != "default.jpg") {
                // Not request for image information document and no sizes specified
                $errors[] = "Invalid image request format.";
                iiif_error(400, $errors);
            } else {
                // Check the request parameters
                $region = $xpath[2];
                $size = $xpath[3];
                $rotation = $xpath[4];
                $formatparts = explode(".", $xpath[5]);
                if (count($formatparts) != 2) {
                    // Format. As we only support IIIF Image level 0 a value of 'jpg' is required
                    $errors[] = "Invalid quality or format requested. Try using 'default.jpg'";
                    iiif_error(400, $errors);
                } else {
                    $quality = $formatparts[0];
                    $format = $formatparts[1];
                }

                // Process requested region
                if (!isset($errorcode) && $region != "full" && $region != "max" && $preview_tiles) {
                    // If the request specifies a region which extends beyond the dimensions reported in the image information document,
                    // then the service should return an image cropped at the image’s edge, rather than adding empty space.
                    // If the requested region’s height or width is zero, or if the region is entirely outside the bounds
                    // of the reported dimensions, then the server should return a 400 status code.

                    $regioninfo = explode(",", $region);
                    $region_filtered = array_filter($regioninfo, 'is_numeric');
                    if (count($region_filtered) != 4) {
                        // Invalid region
                        $errors[]  = "Invalid region requested. Use 'full' or 'x,y,w,h'";
                        iiif_error(400, $errors);
                    } else {
                        $regionx = (int)$region_filtered[0];
                        $regiony = (int)$region_filtered[1];
                        $regionw = (int)$region_filtered[2];
                        $regionh = (int)$region_filtered[3];
                        debug("IIIF region requested: x:" . $regionx . ", y:" . $regiony . ", w:" .  $regionw . ", h:" . $regionh);
                        if (fmod($regionx, $preview_tile_size) != 0 || fmod($regiony, $preview_tile_size) != 0) {
                            // Invalid region
                            $errors[]  = "Invalid region requested. Supported tiles are " . $preview_tile_size . "x" . $preview_tile_size . " at scale factors " . implode(",", $preview_tile_scale_factors) . ".";
                            iiif_error(400, $errors);
                        } else {
                            $tile_request = true;
                        }
                    }
                } else {
                    // Full image requested
                    $tile_request = false;
                }

                // Process size
                if (strpos($size, ",") !== false) {
                    // Currently support 'w,' and ',h' syntax requests
                    $getdims    = explode(",", $size);
                    $getwidth   = (int)$getdims[0];
                    $getheight  = (int)$getdims[1];
                    if ($tile_request) {
                        if (($regionx + $regionw) >= $imageWidth || ($regiony + $regionh) >= $imageHeight) {
                            // Size specified is not the standard tile width, may be right or bottom edge of image
                            $validtileh = false;
                            $validtilew = false;

                            if ($getwidth > 0 && $getheight == 0) {
                                $scale = ceil($regionw / $getwidth);
                            } elseif ($getheight > 0 && $getwidth == 0) {
                                $scale = ceil($regionh / $getheight);
                            } else {
                                $errors[] = "Invalid tile size requested";
                                iiif_error(501, $errors);
                            }

                            if (!in_array($scale, $preview_tile_scale_factors)) {
                                $errors[] = "Invalid tile size requested";
                                iiif_error(501, $errors);
                            }
                        } elseif (
                            ($getwidth == $preview_tile_size && $getheight == 0) ||
                               ($getheight == $preview_tile_size && $getwidth == 0) ||
                               ($getheight == $preview_tile_size && $getwidth == $preview_tile_size)
                        ) {
                            $valid_tile = true;
                            if ($getheight === 0) {
                                $scale = ceil($regionw / $getwidth);
                            } else {
                                $scale = ceil($regionh / $getheight);
                            }

                        } else {
                            $errors[] = "Invalid tile size requested";
                            iiif_error(400, $errors);
                        }

                        $getsize = "tile_" . $scale . "_" . $regionx . "_" . $regiony . "_" . $regionw . "_" . $regionh;
                        $getext = "jpg";

                        debug("IIIF" . $regionx . "_" . $regiony . "_" . $regionw . "_" . $regionh);
                    } else {
                        if ($getheight == 0) {
                            $getheight = floor($getwidth * ($imageHeight / $imageWidth));
                        } elseif ($getwidth == 0) {
                            $getwidth = floor($getheight * ($imageWidth / $imageHeight));
                        }
                        // Establish which preview size this request relates to
                        foreach ($availsizes as $availsize) {
                            debug("IIIF - checking available size for resource " . $resource["ref"]  . ". Size '" . $availsize["id"] . "': " . $availsize["width"] . "x" . $availsize["height"] . ". Requested size: " . $getwidth . "x" . $getheight);
                            if ($availsize["width"] == $getwidth && $availsize["height"] == $getheight) {
                                $getsize = $availsize["id"];
                            }
                        }
                        if (!isset($getsize)) {
                            if (!$iiif_custom_sizes || $getwidth > $iiif_max_width || $getheight > $iiif_max_height) {
                                // Invalid size requested
                                $errors[] = "Invalid size requested";
                                iiif_error(400, $errors);
                            } else {
                                $getsize = "resized_" . $getwidth . "_" . $getheight;
                                $getext = "jpg";
                            }
                        }
                    }
                } elseif ($size == "full"  || $size == "max" || $size == "thm") {
                    if ($tile_request) {
                        if ($size == "full"  || $size == "max") {
                            $getsize = "tile_" . $regionx . "_" . $regiony . "_" . $regionw . "_" . $regionh;
                            $getext = "jpg";
                        } else {
                            $errors[] = "Invalid tile size requested";
                            iiif_error(501, $errors);
                        }
                    } else {
                        // Full/max image region requested
                        if ($iiif_max_width >= $imageWidth && $iiif_max_height >= $imageHeight) {
                            $isjpeg = in_array(strtolower($resource["file_extension"]), array("jpg","jpeg"));
                            $getext = strtolower($resource["file_extension"]) == "jpeg" ? "jpeg" : "jpg";
                            $getsize = $isjpeg ? "" : "hpr";
                        } else {
                            $getext = "jpg";
                            $getsize = count($availsizes) > 0 ? $availsizes[0]["id"] : "thm";
                        }
                    }
                } else {
                    $errors[] = "Invalid size requested";
                    iiif_error(400, $errors);
                }

                if ($rotation != 0) {
                    // Rotation. As we only support IIIF Image level 0 only a rotation value of 0 is accepted
                    $errors[] = "Invalid rotation requested. Only '0' is permitted.";
                    iiif_error(404, $errors);
                }
                if (isset($quality) && $quality != "default" && $quality != "color") {
                    // Quality. As we only support IIIF Image level 0 only a quality value of 'default' or 'color' is accepted
                    $errors[] = "Invalid quality requested. Only 'default' is permitted";
                    iiif_error(404, $errors);
                }
                if (isset($format) && strtolower($format) != "jpg") {
                    // Format. As we only support IIIF Image level 0 only a value of 'jpg' is accepted
                    $errors[] = "Invalid format requested. Only 'jpg' is permitted.";
                    iiif_error(404, $errors);
                }

                if (!isset($errorcode)) {
                    // Request is supported, send the image
                    $imgpath = get_resource_path($resourceid, true, $getsize, false, $getext);
                    if ($tile_request && !file_exists($imgpath)) {
                        // Support older tiles without scale factor in ID that may not have been recreated
                        // Get resource path again as size is used in creating filename hash.
                        $imgpath = get_resource_path($resourceid, true, preg_replace("/(tile_\\d+_)/", "tile_", $getsize), false, $getext);
                    }
                    debug("IIIF: image path: " . $imgpath);
                    if (file_exists($imgpath)) {
                        $imgfound = true;
                    } else {
                        if ($region != "full" && $region != "max") {
                            // Tiles have not yet been created
                            if (is_process_lock('create_previews_' . $resource["ref"] . "_tiles")) {
                                $errors[] = "Requested image is not currently available";
                                iiif_error(503, $errors);
                            }
                            set_process_lock('create_previews_' . $resource["ref"] . "_tiles");
                            $imgfound = @create_previews($resourceid, false, "jpg", false, true, -1, true, false, false, array("tiles"));
                            clear_process_lock('create_previews_' . $resource["ref"] . "_tiles");
                        } else {
                            if (is_process_lock('create_previews_' . $resource["ref"] . "_" . $getsize)) {
                                $errors[] = "Requested image is not currently available";
                                iiif_error(503, $errors);
                            }
                            $imgfound = @create_previews($resourceid, false, "jpg", false, true, -1, true, false, false, array($getsize));
                            clear_process_lock('create_previews_' . $resource["ref"] . "_" . $getsize);
                        }
                    }
                    if ($imgfound) {
                        $validrequest = true;
                        $response_image = $imgpath;
                    } else {
                        $errors[] = "No image available for this identifier";
                        iiif_error(404, $errors);
                    }
                }
            }
            /* IMAGE REQUEST END */
        } else {
            $errors[] = "Missing or invalid identifier";
            iiif_error(404, $errors);
        }
    } // End of image API
    else {
        // Presentation API
        $identifier = $xpath[0];
        if ($identifier != "" && !isset($xpath[1])) {
            // Redirect to image information document
            $redirurl = $_SERVER["REQUEST_URI"] . (!isset($xpath[2]) ? "/" : "") . "manifest";
            if (function_exists("http_response_code")) {
                http_response_code(303); # Send error status
            }
            header("Location: " . $redirurl);
            exit();
        }

        $iiif_field = get_resource_type_field($iiif_identifier_field);
        $iiif_search = $iiif_field["name"] . ":" . $identifier;
        $iiif_results = do_search($iiif_search);

        if (is_array($iiif_results) && count($iiif_results) > 0) {
            if (!isset($xpath[1])) {
                $errors[] = "Bad request. Valid options are 'manifest', 'sequence' or 'canvas' e.g. ";
                $errors[] = "For the manifest: " . $rooturl . $xpath[0] . "/manifest";
                $errors[] = "For a sequence : " . $rooturl . $xpath[0] . "/sequence";
                $errors[] = "For a canvas : " . $rooturl . $xpath[0] . "/canvas/<identifier>";
                iiif_error(404, $errors);
            } else {
                if (!is_array($iiif_results) || count($iiif_results) == 0) {
                    $errors[] = "Invalid identifier: " . $identifier;
                    iiif_error(404, $errors);
                } else {
                    // Add sequence position information
                    $resultcount = count($iiif_results);
                    $iiif_results_with_position = array();
                    $iiif_results_without_position = array();
                    for ($n = 0; $n < $resultcount; $n++) {
                        if (isset($iiif_sequence_field)) {
                            if (isset($iiif_results[$n]["field" . $iiif_sequence_field])) {
                                $sequenceid = $iiif_results[$n]["field" . $iiif_sequence_field];
                            } else {
                                $sequenceid = get_data_by_field($iiif_results[$n]["ref"], $iiif_sequence_field);
                            }
                            $sequence_field = get_resource_type_field($iiif_sequence_field);
                            $sequence_prefix = $sequence_field["name"] . " ";

                            if (!isset($sequenceid) || trim($sequenceid) == "") {
                                // Processing resources without a sequence position separately
                                debug("IIIF: position empty for resource ref " . $iiif_results[$n]["ref"]);
                                $iiif_results_without_position[] = $iiif_results[$n];
                                continue;
                            }

                            debug("IIIF: position $sequenceid found in resource ref " . $iiif_results[$n]["ref"]);
                            $iiif_results[$n]["iiif_position"] = $sequenceid;
                            $iiif_results_with_position[] = $iiif_results[$n];
                        } else {
                            $sequenceid = $n;
                            debug("IIIF: position $position assigned to resource ref " . $iiif_results[$n]["ref"]);
                            $iiif_results[$n]["iiif_position"] = $sequenceid;
                            $iiif_results_with_position[] = $iiif_results[$n];
                        }
                    }

                    // Sort by user supplied position (handle blanks and duplicates)
                    if (isset($iiif_sequence_field)) {
                        # First sort by ref. Any duplicate positions will then be sorted oldest resource first.
                        usort($iiif_results_with_position, function ($a, $b) {
                            return $a['ref'] - $b['ref'];
                        });
                        # Sort resources with user supplied position.
                        usort($iiif_results_with_position, function ($a, $b) {
                            if (is_int_loose($a['iiif_position']) && is_int_loose($b['iiif_position'])) {
                                return $a['iiif_position'] - $b['iiif_position'];
                            } elseif (is_int_loose($a['iiif_position']) || is_int_loose($b['iiif_position'])) {
                                return is_int_loose($a['iiif_position']) ? 1 : -1; // Put strings before numbers
                            }
                            return strcmp($a['iiif_position'], $b['iiif_position']);
                        });

                        if (count($iiif_results_without_position) > 0 && count($iiif_results_with_position) > 0) {
                            # Sort resources without a user supplied position by resource reference.
                            # These will appear at the end of the sequence after those with a user supplied position.
                            # Only applies if some resources have a sequence position else return in search results order per earlier behaviour.
                            usort($iiif_results_without_position, function ($a, $b) {
                                return $a['ref'] - $b['ref'];
                            });
                        }

                        $iiif_results = array_merge($iiif_results_with_position, $iiif_results_without_position);

                        $sorted_final = [];
                        $maxid = 0;
                        foreach ($iiif_results as $index => $resource) {
                            # Update iiif_position after sorting using unique array key, removing potential user entered duplicates in sequence field.
                            # iiif_get_canvases() requires unique iiif_position values.
                            $resourcepos = $resource['iiif_position'] ?? ($maxid + 1);
                            while (isset($sorted_final[$resourcepos])) {
                                $resourcepos++;
                            }

                            debug("IIIF: final position $index given for resource ref " . $resource["ref"] . " sequence id: " . $resourcepos);
                            $sorted_final[$index] = $resource;
                            $sorted_final[$index]["iiif_position"] = $resourcepos;
                            $maxid = max((int) $resourcepos, $maxid);
                        }
                        $iiif_results = $sorted_final;
                    }

                    if ($xpath[1] == "manifest" || $xpath[1] == "") {
                        /* MANIFEST REQUEST - see http://iiif.io/api/presentation/2.1/#manifest */
                        $response["@context"] = "http://iiif.io/api/presentation/2/context.json";
                        $response["@id"] = $rooturl . $identifier . "/manifest";
                        $response["@type"] = "sc:Manifest";

                        // Descriptive metadata about the object/work
                        // The manifest data should be the same for all resources that are returned.
                        // This is the default when using the tms_link plugin for TMS integration.
                        // Therefore we use the data from the first returned result.
                        $iiif_data = get_resource_field_data($iiif_results[0]["ref"]);

                        // Label property
                        foreach ($iiif_results as $iiif_result) {
                            // Keep on until we find a label
                            $iiif_label = get_data_by_field($iiif_results[0]["ref"], $view_title_field);
                            if (trim($iiif_label) != "") {
                                $response["label"] = $iiif_label;
                                break;
                            }
                        }

                        if (!$iiif_label) {
                            $response["label"] = $lang["notavailableshort"];
                        }

                        $response["description"] = get_data_by_field($iiif_results[0]["ref"], $iiif_description_field);

                        // Construct metadata array from resource field data
                        $response["metadata"] = array();
                        $n = 0;
                        foreach ($iiif_data as $iiif_data_row) {
                            if (in_array($iiif_data_row["type"], $FIXED_LIST_FIELD_TYPES)) {
                                // Don't use the data as this has already concatentated the translations, add an entry for each node translation by building up a new array
                                $resnodes = get_resource_nodes($iiif_results[0]["ref"], $iiif_data_row["resource_type_field"], true);
                                if (count($resnodes) == 0) {
                                    continue;
                                }
                                $langentries = array();
                                $nodecount = 0;
                                unset($def_lang);
                                foreach ($resnodes as $resnode) {
                                    debug("IIIF: translating " . $resnode["name"] . " from field '" . $iiif_data_row["title"] . "'");
                                    $node_langs = i18n_get_translations($resnode["name"]);
                                    $transcount = 0;
                                    $defaulttrans = "";
                                    foreach ($node_langs as $nlang => $nltext) {
                                        if (!isset($langentries[$nlang])) {
                                            // This is the first translated node entry for this language. If we already have translations copy the default language array to make sure no nodes with missing translations are lost
                                            debug("IIIF: Adding a new translation entry for language '" . $nlang . "', field '" . $iiif_data_row["title"] . "'");
                                            $langentries[$nlang] = isset($def_lang) ? $def_lang : array();
                                        }
                                        // Add the node text to the array for this language;
                                        debug("IIIF: Adding node translation for language '" . $nlang . "', field '" . $iiif_data_row["title"] . "': " . $nltext);
                                        $langentries[$nlang][] = $nltext;

                                        // Set default text for any translations
                                        if ($nlang == $defaultlanguage || $defaulttrans == "") {
                                            $defaulttrans = $nltext;
                                        }
                                        $transcount++;
                                    }
                                    $nodecount++;

                                    // There may not be translations for all nodes, fill any arrays that don't have an entry with the untranslated versions
                                    foreach ($langentries as $mdlang => $mdtrans) {
                                        debug("IIIF: entry count for " . $mdlang . ":" . count($mdtrans));
                                        debug("IIIF: node count: " . $nodecount);
                                        if (count($mdtrans) != $nodecount) {
                                            debug("IIIF: No translation found for " . $mdlang . ". Adding default translation to language array for field '" . $iiif_data_row["title"] . "': " . $mdlang . ": " . $defaulttrans);
                                            $langentries[$mdlang][] =  $defaulttrans;
                                        }
                                    }

                                    // To ensure that no nodes are lost due to missing translations,
                                    // Save the default language array to make sure we include any untranslated nodes that may be missing when/if we find new languages for the next node

                                    debug("IIIF: Saving default language array for field '" . $iiif_data_row["title"] . "': " . implode(",", $langentries[$defaultlanguage]));
                                    // Default language is the ideal, but if no default language entries for this node have been found copy the first language we have
                                    reset($langentries);
                                    $def_lang = isset($langentries[$defaultlanguage]) ? $langentries[$defaultlanguage] : $langentries[key($langentries)];
                                }


                                $response["metadata"][$n] = array();
                                $response["metadata"][$n]["label"] = $iiif_data_row["title"];
                                $response["metadata"][$n]["value"] = array();

                                // Add each tag
                                $o = 0;
                                foreach ($langentries as $mdlang => $mdtrans) {
                                    debug("IIIF: adding to metadata language array: " . $mdlang . ": " . implode(",", $mdtrans));
                                    $response["metadata"][$n]["value"][$o]["@value"] = implode(",", array_values($mdtrans));
                                    $response["metadata"][$n]["value"][$o]["@language"] = $mdlang;
                                    $o++;
                                }
                                $n++;
                            } elseif (trim((string) $iiif_data_row["value"]) != "") {
                                $response["metadata"][$n] = array();
                                $response["metadata"][$n]["label"] = $iiif_data_row["title"];
                                $response["metadata"][$n]["value"] = $iiif_data_row["value"];
                                $n++;
                            }
                        }

                        $response["description"] = get_data_by_field($iiif_results[0]["ref"], $iiif_description_field);
                        if (isset($iiif_license_field)) {
                            $response["license"] = get_data_by_field($iiif_results[0]["ref"], $iiif_license_field);
                        }

                        // Thumbnail property
                        foreach ($iiif_results as $iiif_result) {
                            // Keep on until we find an image
                            $iiif_thumb = iiif_get_thumbnail($iiif_results[0]["ref"]);
                            if ($iiif_thumb) {
                                $response["thumbnail"] = $iiif_thumb;
                                break;
                            }
                        }

                        if (!$iiif_thumb) {
                            $response["thumbnail"] = $baseurl . "/gfx/no_preview/default.png";
                        }

                        // Sequences
                        $response["sequences"] = array();
                        $response["sequences"][0]["@id"] = $rooturl . $identifier . "/sequence/normal";
                        $response["sequences"][0]["@type"] = "sc:Sequence";
                        $response["sequences"][0]["label"] = "Default order";

                        $response["sequences"][0]["canvases"]  = iiif_get_canvases($identifier, $iiif_results, false);
                        $validrequest = true;
                        /* MANIFEST REQUEST END */
                    } elseif ($xpath[1] == "canvas") {
                        // This is essentially a resource
                        // {scheme}://{host}/{prefix}/{identifier}/canvas/{name}
                        $canvasid = $xpath[2];
                        $allcanvases = iiif_get_canvases($identifier, $iiif_results, true);
                        $response["@context"] =  "http://iiif.io/api/presentation/2/context.json";
                        $response = array_merge($response, $allcanvases[$canvasid]);
                        $validrequest = true;
                    } elseif ($xpath[1] == "sequence") {
                        if (isset($xpath[2]) && $xpath[2] == "normal") {
                            $response["@context"] = "http://iiif.io/api/presentation/2/context.json";
                            $response["@id"] = $rooturl . $identifier . "/sequence/normal";
                            $response["@type"] = "sc:Sequence";
                            $response["label"] = "Default order";
                            $response["canvases"] = iiif_get_canvases($identifier, $iiif_results);
                            $validrequest = true;
                        }
                    } elseif ($xpath[1] == "annotation") {
                        // See http://iiif.io/api/presentation/2.1/#image-resources
                        $annotationid = $xpath[2];

                        // Need to find the resourceid the annotation is linked to
                        if (isset($iiif_results[$annotationid])) {
                            $resourceid = $iiif_results[$annotationid]["ref"];
                            $size_info = array(
                                'identifier' => (strtolower($iiif_results[$annotationid]['file_extension']) != 'jpg') ? 'hpr' : '',
                                'return_height_width' => false,
                            );
                            $validrequest = true;
                        }
                        if ($validrequest) {
                            $response["@context"] = "http://iiif.io/api/presentation/2/context.json";
                            $response["@id"] = $rooturl . $identifier . "/annotation/" . $annotationid;
                            $response["@type"] = "oa:Annotation";
                            $response["motivation"] = "sc:painting";
                            $response["resource"] = iiif_get_image($identifier, $resourceid, $annotationid, $size_info);
                            $response["on"] = $rooturl . $identifier . "/canvas/" . $annotationid;
                        } else {
                            $errors[] = "Invalid annotation identifier: " . $annotationid;
                            iiif_error(404, $errors);
                        }
                    }
                }
            }
        } // End of valid $identifier check based on search results
        else {
            $errors[] = "Invalid identifier: " . $identifier;
            iiif_error(404, $errors);
        }
    }
}
    // Send the data
if ($validrequest) {
    if (function_exists("http_response_code")) {
        http_response_code(200); # Send OK
    }
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Headers: Accept");
    if (isset($response_image) && file_exists($response_image)) {
        // Send the image
        $file_size   = filesize_unlimited($response_image);
        $file_handle = fopen($response_image, 'rb');
        header("Access-Control-Allow-Origin: *");
        header("Access-Control-Allow-Headers: Accept");
        header('Content-Disposition: inline;');
        header('Content-Transfer-Encoding: binary');
        $mime = get_mime_type($response_image)[0];
        header("Content-Type: {$mime}");
        $sent = 0;
        while ($sent < $file_size) {
            echo fread($file_handle, $download_chunk_size);
            ob_flush();
            flush();
            $sent += $download_chunk_size;
            if (0 != connection_status()) {
                break;
            }
        }

        fclose($file_handle);
    } else {
        header("Content-Type: application/ld+json");
        foreach ($iiif_headers as $iiif_header) {
            header($iiif_header);
        }
        if (defined('JSON_PRETTY_PRINT')) {
            echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        } else {
            echo json_encode($response);
        }
    }
    exit();
}
