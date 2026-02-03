<?php

/**
 * Simple class for IIIF API
 *
 * @internal
 */
final class IIIFRequest
{
    public string $rootlevel;
    public string $rooturl;
    public string $rootimageurl;
    public int $identifier_field;
    public int $description_field;
    public int $sequence_field;
    public string $iiif_sequence_prefix;
    public int $license_field;
    public string $rights_statement;
    public int $title_field;
    public int $max_width;
    public int $max_height;
    public bool $custom_sizes;
    public bool $preview_tiles;
    public int $preview_tile_size;
    public array $preview_tile_scale_factors;
    public array $media_extensions;
    public int $download_chunk_size;
    public array $data;
    public array $headers;
    public array $errors;
    public int $errorcode;
    public array $searchresults;
    public array $processing;
    public bool $only_power_of_two_sizes;

    private array $response;
    private array $request;
    private bool $validrequest;
    private int $imagewidth;
    private int $imageheight;
    private int $getwidth;
    private int $getheight;
    private int $regionx;
    private int $regiony;
    private int $regionw;
    private int $regionh;

    public function __construct($iiif_options)
    {
        foreach ($iiif_options as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
        $this->response = [];
        $this->validrequest = false;
        $this->headers = [];
        $this->errors = [];
    }

    /**
     * Get the IIIF response
     *
     * @return array
     *
     */
    public function getResponse(string $element = ""): array
    {
        return ($element != "" && isset($this->response[$element])) ? $this->response[$element] : $this->response;
    }

    /**
     * Return information from the request
     *
     * @param string $element
     *
     * @return mixed
     *
     */
    public function getRequest(string $element = "")
    {
        return ($element != "" && isset($this->request[$element])) ? $this->request[$element] : $this->request;
    }

    /**
     * Is the current request valid?
     *
     * @return bool
     *
     */
    public function isValidRequest()
    {
        return $this->validrequest;
    }

    /**
     * Send the IIIF information document
     *
     */
    public function infodoc()
    {
        $this->response["@context"] = "http://iiif.io/api/presentation/2/context.json";
        $this->response["id"] = $this->rooturl;
        $this->response["type"] = "sc:Manifest";
        $arr_langdefault = i18n_get_all_translations("iiif");
        foreach ($arr_langdefault as $langcode => $langdefault) {
            $this->response["label"][$langcode] = [$langdefault];
        }
        $this->response["width"] = 6000;
        $this->response["height"] = 4000;
        $this->response["tiles"] = array();
        $this->response["tiles"][] = array("width" => $this->preview_tile_size, "height" => $this->preview_tile_size, "scaleFactors" => $this->preview_tile_scale_factors);
        $this->response["profile"] = array("http://iiif.io/api/image/3/level0.json");
        $this->validrequest = true;
    }

    /**
     * Extract IIIF request details from the URL path
     *
     * @param string    $url    The requested URL
     *
     * @return void
     *
     */
    public function parseUrl($url): void
    {
        $this->request = [];

        $request_url = strtok($url, '?');
        $path = substr($request_url, strpos($request_url, $this->rootlevel) + strlen($this->rootlevel));
        $xpath = explode("/", $path);

        // Set API type
        if (strtolower($xpath[0]) == "image") {
            $this->request["api"] = "image";
        } elseif (count($xpath) > 1 ||  $xpath[0] != "") {
            $this->request["api"] = "presentation";
        } else {
            $this->request["api"]  = "root";
            return;
        }

        if ($this->request["api"] == "image") {
            // For image need to extract: -
            // - Resource ID
            // - type (manifest)
            // - region
            // - size
            // - rotation
            // - quality
            // - format
            $this->request["id"] = trim($xpath[1] ?? '');
            $this->request["region"] = trim($xpath[2] ?? '');
            $this->request["size"] = trim($xpath[3] ?? '');
            $this->request["rotation"] = trim($xpath[4] ?? '');
            $this->request["filename"] = trim($xpath[5] ?? '');

            if ($this->request["id"]  === '') {
                $this->errors[] = 'Missing identifier';
                $this->triggerError(400);
            }

            if ($this->request["region"] == "") {
                // Redirect to image information document
                $redirurl = $this->rootimageurl . $this->request["id"] . '/info.json';
                if (function_exists("http_response_code")) {
                    http_response_code(303);
                }
                header("Location: " . $redirurl);
                exit();
            }
            // Check the request parameters
            elseif ($this->request["region"] != "info.json") {
                if (
                    $this->request["size"] == ""
                    || !is_int_loose($this->request["rotation"])
                    || $this->request["filename"] != "default.jpg"
                ) {
                    // Not request for image information document and no sizes specified
                    $this->errors[] = "Invalid image request format.";
                    $this->triggerError(400);
                }

                $formatparts = explode(".", $this->request["filename"]);
                if (count($formatparts) != 2) {
                    // Format. As we only support IIIF Image level 0 a value of 'jpg' is required
                    $this->errors[] = ["Invalid quality or format requested. Try using 'default.jpg'"];
                    $this->triggerError(400);
                } else {
                    $this->request["quality"] = $formatparts[0];
                    $this->request["format"] = $formatparts[1];
                }
            }
        } elseif ($this->request["api"] == "presentation") {
            // Presentation -  need
            // - identifier
            // - type (manifest/canvas/sequence/annotation
            // - typeid (manifest/canvas/sequence/annotation

            $this->request["id"] = trim($xpath[0] ?? '');
            $this->request["type"] = trim($xpath[1] ?? '');
            $this->request["typeid"] = trim($xpath[2] ?? '');
        }
    }

    /**
    * Find all the resources to generate an array of all the canvases for the identifier ready for JSON encoding
    *
    * @param boolean $sequencekeys      Get the array with each key matching the value set in the metadata field $iiif_sequence_field. By default the array will be sorted but have a 0 based index
    *
    * @return void
    *
    */
    public function getCanvases($sequencekeys = false): void
    {
        $canvases = [];
        foreach ($this->searchresults as $index => $iiif_result) {
            if (in_array(strtolower($iiif_result["file_extension"] ?? ""), $this->media_extensions)) {
                $size = "";
                $media_path = get_resource_path($iiif_result["ref"], true, $size, false, $iiif_result["file_extension"]);
            } else {
                $size = $this->largest_jpg_size($iiif_result);
                $media_path = get_resource_path($iiif_result["ref"], true, $size, false);
            }
            if (!file_exists($media_path)) {
                // If configured, try and use a preview from a related resource
                $pullresource = related_resource_pull($iiif_result);
                if ($pullresource !== false) {
                    $this->processing["resource"] = $pullresource["ref"];
                    $this->processing["size_info"] = [
                        'identifier' => $this->largest_jpg_size($pullresource),
                        'return_height_width' => false,
                        ];
                }
            }
            $canvas = $this->generateCanvas($index);
            if ($canvas) {
                $canvases[$index] = $canvas;
            }
        }

        if ($sequencekeys) {
            // keep the sequence identifiers as keys so a required canvas can be accessed by sequence id
            $this->response["items"] = $canvases;
        }
        ksort($canvases);
        foreach ($canvases as $canvas) {
            $this->response["items"][] =  $canvas;
        }
    }

    /**
    * Get  thumbnail information for the specified resource id ready for IIIF JSON encoding
    *
    * @uses get_resource_path()
    * @uses getimagesize()
    *
    * @param int $resourceid    Resource ID

    * @return array|bool        Thumbnail image data, false if not found
    */
    public function getThumbnail(int $resourceid)
    {
        $img_path = get_resource_path($resourceid, true, 'thm', false);
        if (!file_exists($img_path)) {
            return false;
        }

        $thumbnail = [];
        $thumbnail["id"] = $this->rootimageurl . $resourceid . "/full/thm/0/default.jpg";
        $thumbnail["type"] = "Image";
        $thumbnail["format"] = "image/jpeg";

        // Get the size of the images
        $GLOBALS["use_error_exception"] = true;
        try {
            list($tw,$th) = getimagesize($img_path);
            $thumbnail["height"] = (int) $th;
            $thumbnail["width"] = (int) $tw;
        } catch (Exception $e) {
            $returned_error = $e->getMessage();
            debug("getThumbnail: Unable to get image size for file: $img_path  -  $returned_error");
            // Use defaults
            $thumbnail["height"] = 150;
            $thumbnail["width"] = 150;
        }
        unset($GLOBALS["use_error_exception"]);

        $thumbnail["service"] = [$this->generateImageService($resourceid)];
        return $thumbnail;
    }

    /**
    * Get the media file for the specified identifier canvas and resource id
    *
    * @param integer $resourceid  Resource ID
    * @param array $size          ResourceSpace size information. Required information: identifier and whether it
    *                             is required to return the height & width (e.g annotations don't require this info).
    *                             Please note the identifier - use 'hpr' if the original file is not a JPG file AND
    *                             the extension is not in the $iiif_media_extensions arrays.
    *                             Example:
    *                             $size_info = array(
    *                               'identifier'          => 'hpr',
    *                               'return_height_width' => true
    *                             );
    *
    * @return bool|array          Array holding image file data. Returns false if no image available.
    */
    public function get_media(int $resource, array $size_info)
    {
        // Quick validation of the size_info param
        if (empty($size_info) || (!isset($size_info['identifier']) && !isset($size_info['return_height_width']))) {
            return false;
        }
        $size = $size_info['identifier'];
        $return_height_width = $size_info['return_height_width'];

        $resdata = get_resource_data($resource);
        if (in_array($resdata["file_extension"], array_merge($this->media_extensions))) {
            $media_path = get_resource_path($resource, true, $size, false, $resdata["file_extension"]);
        } else {
            $useextension = strtolower($resdata["file_extension"] ?? '') == "jpeg" ? $resdata["file_extension"] : "jpg";
            $media_path = get_resource_path($resource, true, $size, false, $useextension);
        }

        if (!file_exists($media_path)) {
            // If configured, try and use a preview from a related resource
            $resdata = get_resource_data($resource);
            $pullresource = related_resource_pull($resdata);
            if ($pullresource !== false) {
                $resource = $pullresource["ref"];
                $media_path = get_resource_path($resource, true, $this->largest_jpg_size($pullresource), false);
            } else {
                return false;
            }
        }

        $media = [];
        if (in_array($resdata["file_extension"], array_merge($this->media_extensions))) {
            $media["duration"] = get_video_duration($media_path); // Also works for audio
            $accesskey = generate_temp_download_key($GLOBALS["userref"], $resource, "");
            $url = $GLOBALS["baseurl"] . "/pages/download.php";
            $params = [
                "ref" => $resource,
                "ext" => $resdata["file_extension"],
                "noattach" => true,
                "access_key" => $accesskey,
            ];
            $media["id"] = generateURL($url, $params);
            $media["type"] = in_array(
                strtolower($resdata["file_extension"]),
                array_merge($GLOBALS["ffmpeg_audio_extensions"], ["mp3"])
            ) ? "Sound" : "Video";

            /** {@see include/mime_types.php} */
            $found_types = get_mime_types_by_extension($resdata['file_extension']);
            $media["format"] = $found_types === [] ? 'application/octet-stream' : reset($found_types);

            $size = "";
            $iiif_thumb = $this->getThumbnail($resource);
            if ($iiif_thumb) {
                $media["thumbnail"][] = $iiif_thumb;
            }
        } else {
            $media["id"] = $this->rootimageurl . $resource . "/full/max/0/default.jpg";
            $media["type"] = "Image";
            $media["format"] = "image/jpeg";
            $media["service"] = [$this->generateImageService($resource)];
        }
        if ($return_height_width) {
            $media_size = get_original_imagesize($resource, $media_path, $resdata["file_extension"]);
            $media["height"] = intval($media_size[2]);
            $media["width"] = intval($media_size[1]);
        }
        return $media;
    }

    /**
    * Handle a IIIF error.
    *
    * @param  integer $errorcode The error code
    *
    * @return void
    */
    public function triggerError($errorcode = 404)
    {
        if (function_exists("http_response_code")) {
            http_response_code($errorcode); # Send error status
        }
        echo json_encode($this->errors);
        exit();
    }

    /**
     * Process a IIIF presentation request
     * @param object    $iiif   The current IIIF request object generated in api/iiif/handler.php
     *
     * @return void
     *
     */
    public function processPresentationRequest(): void
    {
        $this->getResources();

        if (is_array($this->searchresults) && count($this->searchresults) > 0) {
            if ($this->request["type"] == "manifest" || $this->request["type"] == "") {
                $this->generateManifest();
                $this->validrequest = true;
            } elseif ($this->request["type"] == "canvas") {
                $this->getResourceFromPosition($this->request["typeid"]);

                $this->response = $this->generateCanvas($this->request["typeid"]);
                $this->validrequest = true;
            } elseif ($this->request["type"] == "annotationpage") {
                $this->getResourceFromPosition($this->request["typeid"]);
                $this->response = $this->generateAnnotationPage($this->request["typeid"]);
                $this->validrequest = true;
            } elseif ($this->request["type"] == "annotation") {
                $this->getResourceFromPosition($this->request["typeid"]);
                $this->response = $this->generateAnnotation($this->request["typeid"]);
                $this->validrequest = true;
            }
        } // End of valid $identifier check based on search results
        else {
            $this->errorcode = 404;
            $this->errors[] = "Invalid identifier: " . $this->request["id"];
        }
    }

    /**
     * Generate the top level manifest - see http://iiif.io/api/presentation/3.0/#manifest
     *
     * @return void
    */
    public function generateManifest(): void
    {
        global $lang, $defaultlanguage;
        $this->response["@context"] = "http://iiif.io/api/presentation/3/context.json";
        $this->response["id"] = $this->rooturl . $this->request["id"] . "/manifest";
        $this->response["type"] = "Manifest";

        // Descriptive metadata about the object/work
        // The manifest data should be the same for all resources that are returned.
        // This is the default when using the tms_link plugin for TMS integration.
        // Therefore we use the data from the first returned result.
        $dataresource = reset($this->searchresults);
        $this->data = get_resource_field_data($dataresource["ref"]);

        // Label property
        foreach ($this->searchresults as $iiif_result) {
            // Keep on until we find a label
            $iiif_label = get_data_by_field($iiif_result["ref"], $this->title_field);
            if (trim($iiif_label) != "") {
                $i18n_values = i18n_get_translations($iiif_label);
                foreach ($i18n_values as $langcode => $langstring) {
                    $this->response["label"][$langcode] = [$langstring];
                }
                break;
            }
        }
        if (!$iiif_label) {
            $this->response["label"][$defaultlanguage] = [$lang["notavailableshort"]];
        }

        foreach ($this->searchresults as $iiif_result) {
            $description = get_data_by_field($iiif_result["ref"], $this->description_field);
            if (trim($description) != "") {
                $i18n_values = i18n_get_translations($description);
                foreach ($i18n_values as $langcode => $langstring) {
                    $this->response["summary"][$langcode] = [$langstring];
                }
                break; // Only metadata from one resource is required
            }
        }
        // Construct metadata array from resource field data
        $this->generateMetadata();
        if ($this->license_field != 0) {
            $licensevals = get_data_by_field($dataresource["ref"], $this->license_field, false);
            if (count($licensevals) > 0) {
                // Get all field title translations
                $licensefield = get_resource_type_field($this->license_field);
                $liclabel_int = i18n_get_translations($licensefield["title"]);
                $reqstatements = ["label" => [],"value" => []];
                foreach ($licensevals as $licenseval) {
                    $licensevals_int = i18n_get_translations($licenseval["name"]);
                    foreach ($licensevals_int as $langcode => $langstring) {
                        if (!isset($reqstatements["label"][$langcode])) {
                            // Translated node names may include languages that are not available for the field title
                            $reqstatements["label"][$langcode][] = $liclabel_int[$langcode] ?? $licensefield["title"];
                        }
                        $reqstatements["value"][$langcode][] = $langstring;
                    }
                }

                $this->response["requiredStatement"] = $reqstatements;
            }
        }
        if (isset($this->rights_statement) && $this->rights_statement != "") {
            $this->response["rights_statement"] = $this->rights_statement;
        }

        // Thumbnail property
        $this->response["thumbnail"] = [];
        foreach ($this->searchresults as $iiif_result) {
            // Keep on until we find an image
            $iiif_thumb = $this->getThumbnail($dataresource["ref"]);
            if ($iiif_thumb) {
                $this->response["thumbnail"][] = $iiif_thumb;
                break;
            }
        }

        // Default behavior property - not currently configurable
        $this->response["behavior"] = ["individuals"];

        // Default viewingDirection property - not currently configurable
        $this->response["viewingDirection"] = "left-to-right";

        $this->getCanvases(false);
    }

    /**
     * Generate a canvas
     *
     * @param int           $position   The canvas identifier
     *
     * @return array|bool   $canvas     Canvas data for presentation API response, false if no image is available
     *
     */
    public function generateCanvas(int $position)
    {
        // This is essentially a resource
        // {scheme}://{host}/{prefix}/{identifier}/canvas/{name}
        $canvas = [];
        $resource = $this->searchresults[$position] ?? [];
        if (empty($resource)) {
            debug("IIIF: generateCanvas() Not a valid canvas identifier:" . $position);
            return false;
        }
        $useimage = $resource;
        if ((int)$resource['has_image'] === 0) {
            // If configured, try and use a preview from a related resource
            debug("No image for IIIF request - check for related resources");
            $pullresource = related_resource_pull($resource);
            if ($pullresource !== false) {
                $useimage = $pullresource;
            }
        }

        if (in_array(strtolower($useimage['file_extension'] ?? ""), $this->media_extensions)) {
            $size = '';
            $media_path = get_resource_path($useimage["ref"], true, $size, false, $useimage["file_extension"]);
        } else {
            $size = $this->largest_jpg_size($useimage);
            $useextension = strtolower((string) $useimage["file_extension"]) == "jpeg" ? $useimage["file_extension"] : "jpg";
            $media_path = get_resource_path($useimage["ref"], true, $size, false, $useextension);
        }
        if (!file_exists($media_path)) {
            debug("IIIF: generateCanvas() No image available for identifier:" . $position);
            return false;
        }
        $sequence_field = get_resource_type_field($this->sequence_field);
        $sequenceid = $resource["iiif_position"];
        debug("IIIF: Found resource " . $resource['ref'] . " in position " . $position . ", sequence ID: " . $sequenceid);
        $sequence_prefix = "";
        if (isset($this->iiif_sequence_prefix)) {
            $sequence_prefix  = $this->iiif_sequence_prefix === "" ? $sequence_field["title"] . " " : $this->iiif_sequence_prefix;
        }
        $sequence_val = $sequenceid;
        $canvas["id"] = $this->rooturl . $this->request["id"] . "/canvas/" . $position;
        $canvas["type"] = "Canvas";
        $canvas["label"] = [];
        $arr_18n_pos_labels = i18n_get_translations($sequence_val);
        $arr_18n_pos_prefixes = i18n_get_translations($sequence_prefix);
        if (count($arr_18n_pos_prefixes) > 1 || count($arr_18n_pos_labels) > 1) {
            foreach (array_unique(array_merge(array_keys($arr_18n_pos_prefixes), array_keys($arr_18n_pos_labels))) as $langcode) {
                $prefix =  $arr_18n_pos_prefixes[$langcode] ?? ($arr_18n_pos_prefixes[$GLOBALS["defaultlanguage"]] ?? reset($arr_18n_pos_prefixes));
                $labelvalue =  $arr_18n_pos_labels[$langcode] ?? ($arr_18n_pos_labels[$GLOBALS["defaultlanguage"]] ?? reset($arr_18n_pos_labels));
                $canvas["label"][$langcode] = [$prefix . $labelvalue];
            }
        } else {
            $canvas["label"]["none"] = [$sequence_prefix . $sequence_val];
        }

        // Get the size of the images
        $image_size = get_original_imagesize($useimage["ref"], $media_path, $useimage["file_extension"]);
        $canvas["height"] = intval($image_size[2]);
        $canvas["width"] = intval($image_size[1]);

        // Get the (optional) Canvas resource thumbnail - https://iiif.io/api/presentation/3.0/#a-summary-of-property-requirements 
        $iiif_thumb = $this->getThumbnail($resource['ref']);
        if ($iiif_thumb) {
            $canvas['thumbnail'][] = $iiif_thumb;
        }

        // Add image (only 1 per canvas currently supported)
        $this->getResourceFromPosition($position);
        $canvas["items"][] = $this->generateAnnotationPage($position);

        return $canvas;
    }

    /**
     * Generate the AnnotationPage elements
     *
     * @param int       $position   The annotation position
     *
     * @return array    Array of annotation pages
     *
     */
    public function generateAnnotationPage(int $position = 0): array
    {
        $annotationpage = [];
        $annotationpage["id"] = $this->rooturl . $this->request["id"] . "/annotationpage/" . $position;
        $annotationpage["type"] = "AnnotationPage";
        $annotationpage["items"] = [];
        $annotationpage["items"][] = $this->generateAnnotation($position);
        return $annotationpage;
    }

    /**
     * Generate the Annotation elements
     *
     * @return array    Array of annotations
     */
    public function generateAnnotation(int $position = 0): array
    {
        $annotation["id"] = $this->rooturl . $this->request["id"] . "/annotation/" . $position;
        $annotation["type"] = "Annotation";
        $annotation["motivation"] = "painting";
        $annotation["body"] = $this->get_media($this->processing["resource"], $this->processing["size_info"]);
        $annotation["target"] = $this->rooturl . $this->request["id"] . "/canvas/" . $position;
        return $annotation;
    }

    /**
     * Generates the IIIF response for the current IIIF object (presentation API)
     *
     *
     * @return void
     */
    public function generateMetadata(): void
    {
        $metadata = [];
        $n = 0;
        foreach ($this->data as $iiif_data_row) {
            if (in_array($iiif_data_row["type"], $GLOBALS["FIXED_LIST_FIELD_TYPES"])) {
                // Don't use the data as this has already concatenated the translations, add an entry for each node translation by building up a new array
                $resnodes = get_resource_nodes(reset($this->searchresults)["ref"], $iiif_data_row["resource_type_field"], true);
                if (count($resnodes) == 0) {
                    continue;
                }
                // Add all translated field names
                $metadata[$n] = [];
                $metadata[$n]["label"] = [];
                $i18n_titles = i18n_get_translations($iiif_data_row["title"]);
                foreach ($i18n_titles as $langcode => $langstring) {
                    $metadata[$n]["label"][$langcode] = [$langstring];
                }

                // Add all translated node names
                $arr_showlangs = [];
                $arr_alllangstrings = [];
                $arr_lang_default = [];
                foreach ($resnodes as $resnode) {
                    $node_langs_avail = [];
                    $i18n_names = i18n_get_translations($resnode["name"]);
                    // Set default in case no translation available for any languages
                    $defaultnodename = $i18n_names[$GLOBALS["defaultlanguage"]] ?? reset($i18n_names);
                    $arr_lang_default[] =  $defaultnodename;
                    foreach ($i18n_names as $langcode => $langstring) {
                        $node_langs_avail[] = $langcode;
                        if (!isset($arr_alllangstrings[$langcode])) {
                            // This is the first time this language has been found for this field
                            // Initialise the language by copying the default array of values found so far
                            $arr_alllangstrings[$langcode] = $arr_lang_default;
                        }
                        // Add to array
                        $arr_alllangs[$langcode][] = $langstring;
                        $arr_showlangs[] = $langcode;
                    }

                    // Check that this node string has been added for all translations found so far
                    foreach ($arr_alllangstrings as $langcode => $strings) {
                        if (!in_array($langcode, $node_langs_avail)) {
                            $arr_alllangstrings[$langcode][] = $defaultnodename;
                        }
                    }
                }
                $metadata[$n]["value"] = [];
                foreach ($arr_alllangstrings as $langcode => $strings) {
                    $metadata[$n]["value"][$langcode] = [implode(NODE_NAME_STRING_SEPARATOR, $strings)];
                }
            } elseif (trim((string) $iiif_data_row["value"]) !== "") {
                $metadata[$n] = [];
                $metadata[$n]["label"] = [];
                $i18n_titles = i18n_get_translations($iiif_data_row["title"]);
                foreach ($i18n_titles as $langcode => $langstring) {
                    $metadata[$n]["label"][$langcode] = [$langstring];
                }
                $metadata[$n]["value"] = [];
                $i18n_titles = i18n_get_translations($iiif_data_row["value"]);
                foreach ($i18n_titles as $langcode => $langstring) {
                    $metadata[$n]["value"][$langcode] = [$langstring];
                }
                $n++;
            }
        }
        $this->response["metadata"] = $metadata;
    }

    /**
     * Process the IIIF Image API request - see http://iiif.io/api/image/3.0/
     * The IIIF Image API URI for requesting an image must conform to the following URI Template:
     *  {scheme}://{server}{/prefix}/{identifier}/{region}/{size}/{rotation}/{quality}.{format}
     *
     * @return void
     *
     */
    public function processImageRequest(): void
    {
        $this->request["getext"] = "jpg";
        if ($this->request["id"] === '') {
            $this->errors[] = 'Missing identifier';
            $this->triggerError(400);
        }

        if ($this->request["region"] == "") {
            // Redirect to image information document
            $redirurl = $this->rootimageurl . $this->request["id"] . '/info.json';
            if (function_exists("http_response_code")) {
                http_response_code(303);
            }
            header("Location: " . $redirurl);
            exit();
        }

        if (is_numeric($this->request["id"])) {
            $resource =  get_resource_data($this->request["id"]);
            $resource_access =  get_resource_access($this->request["id"]);
        } else {
            $resource_access = 2;
        }
        if (
            $resource_access == 0
            && !in_array($resource["file_extension"], array_diff(config_merge_non_image_types(), $this->media_extensions))
        ) {
            // Check resource actually exists and is active
            if (in_array($resource["file_extension"], $this->media_extensions)) {
                $fulljpgsize = "pre";
            } else {
                $fulljpgsize = $this->largest_jpg_size($resource);
            }
            $useextension = strtolower((string) $resource["file_extension"]) == "jpeg" ? $resource["file_extension"] : "jpg";
            $img_path = get_resource_path($this->request["id"], true, $fulljpgsize, false, $useextension);
            $image_size = get_original_imagesize($this->request["id"], $img_path, $useextension);
            if ($image_size === false) {
                $this->errors[] = "No image available for this identifier";
                $this->triggerError(404);
            }
            $this->imagewidth = (int) $image_size[1];
            $this->imageheight = (int) $image_size[2];

            // Get all available sizes
            $sizes = get_image_sizes($this->request["id"], true, "jpg", false);
            $availsizes = [];
            if ($this->imagewidth > 0 && $this->imageheight > 0) {
                foreach ($sizes as $size) {
                    if (
                        $size['width'] > 0
                        && $size['height'] > 0
                        && $size['width'] <= $this->max_width
                        && $size['height'] <= $this->max_height
                        && (
                            !$this->only_power_of_two_sizes
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

            if ($this->request["region"] == "info.json") {
                // Image information request. Only fullsize available in this initial version
                $this->response["@context"] = "http://iiif.io/api/image/3/context.json";
                $this->response["extraFormats"] = [
                        "jpg",
                    ];
                $this->response["extraQualities"] = [
                    "default",
                    ];
                $this->response["id"] = $this->rootimageurl . $this->request["id"];

                $this->response["height"] = $this->imageheight;
                $this->response["width"]  = $this->imagewidth;

                $this->response["type"] = "ImageService3";
                $this->response["profile"] = "level0";
                $this->response["maxWidth"] = $this->max_width;
                $this->response["maxHeight"] = $this->max_height;
                if ($this->custom_sizes) {
                    $this->response["extraFeatures"] = ["sizeByH","sizeByW","sizeByWh"];
                }

                $this->response["protocol"] = "http://iiif.io/api/image";
                $this->response["sizes"] = $availsizes;
                if ($this->preview_tiles) {
                    $this->response["tiles"] = [];
                    $this->response["tiles"][] = array("height" => $this->preview_tile_size, "width" => $this->preview_tile_size, "scaleFactors" => $this->preview_tile_scale_factors);
                }
                $this->headers[] = 'Link: <http://iiif.io/api/image/3/level0.json>;rel="profile"';
                $this->validrequest = true;
            } else {
                // Process requested region
                if (!isset($this->errorcode) && $this->request["region"] != "full" && $this->request["region"] != "max" && $this->preview_tiles) {
                    // If the request specifies a region which extends beyond the dimensions reported in the image information document,
                    // then the service should return an image cropped at the image’s edge, rather than adding empty space.
                    // If the requested region’s height or width is zero, or if the region is entirely outside the bounds
                    // of the reported dimensions, then the server should return a 400 status code.

                    $regioninfo = explode(",", $this->request["region"]);
                    $region_filtered = array_filter($regioninfo, 'is_numeric');
                    if (count($region_filtered) != 4) {
                        // Invalid region
                        $this->errors[]  = "Invalid region requested. Use 'full' or 'x,y,w,h'";
                        $this->triggerError(400);
                    } else {
                        $this->regionx = (int)$region_filtered[0];
                        $this->regiony = (int)$region_filtered[1];
                        $this->regionw = (int)$region_filtered[2];
                        $this->regionh = (int)$region_filtered[3];
                        debug("IIIF: region requested: x:" . $this->regionx . ", y:" . $this->regiony . ", w:" .  $this->regionw . ", h:" . $this->regionh);
                        if (fmod($this->regionx, $this->preview_tile_size) != 0 || fmod($this->regiony, $this->preview_tile_size) != 0) {
                            // Invalid region
                            $this->errors[]  = "Invalid region requested. Supported tiles are " . $this->preview_tile_size . "x" . $this->preview_tile_size . " at scale factors " . implode(",", $this->preview_tile_scale_factors) . ".";
                            $this->triggerError(400);
                        } else {
                            $tile_request = true;
                        }
                    }
                } else {
                    // Full image requested
                    $tile_request = false;
                }

                // Process size
                if (strpos($this->request["size"], ",") !== false) {
                    // Currently support 'w,' and ',h' syntax requests
                    $getdims    = explode(",", $this->request["size"]);
                    $this->getwidth   = (int)$getdims[0];
                    $this->getheight  = (int)$getdims[1];
                    if ($tile_request) {
                        if (!$this->isValidTileRequest()) {
                            $this->errors[] = "Invalid tile size requested";
                            $this->triggerError(400);
                        }
                        if ($this->getheight === 0) {
                            $scale = ceil($this->regionw / $this->getwidth);
                        } else {
                            $scale = ceil($this->regionh / $this->getheight);
                        }
                        $this->request["getsize"] = "tile_" . $scale . "_" . $this->regionx . "_" . $this->regiony . "_" . $this->regionw . "_" . $this->regionh;
                        debug("IIIF: " . $this->regionx . "_" . $this->regiony . "_" . $this->regionw . "_" . $this->regionh);
                    } else {
                        if ($this->getheight == 0) {
                            $this->getheight = floor($this->getwidth * ($this->imageheight / $this->imagewidth));
                        } elseif ($this->getwidth == 0) {
                            $this->getwidth = floor($this->getheight * ($this->imagewidth / $this->imageheight));
                        }
                        // Establish which preview size this request relates to
                        foreach ($availsizes as $availsize) {
                            debug("IIIF: checking available size for resource " . $resource["ref"]  . ". Size '" . $availsize["id"] . "': " . $availsize["width"] . "x" . $availsize["height"] . ". Requested size: " . $this->getwidth . "x" . $this->getheight);
                            if ($availsize["width"] == $this->getwidth && $availsize["height"] == $this->getheight) {
                                $this->request["getsize"] = $availsize["id"];
                            }
                        }
                        if (!isset($this->request["getsize"])) {
                            if (!$this->custom_sizes || $this->getwidth > $this->max_width || $this->getheight > $this->max_height) {
                                // Invalid size requested
                                $this->errors[] = "Invalid size requested";
                                $this->triggerError(400);
                            } else {
                                $this->request["getsize"] = "resized_" . $this->getwidth . "_" . $this->getheight;
                            }
                        }
                    }
                } elseif ($this->request["size"] == "full"  || $this->request["size"] == "max" || $this->request["size"] == "thm") {
                    if ($tile_request) {
                        if ($this->request["size"] == "full"  || $this->request["size"] == "max") {
                            $scale = ceil($this->regionw / $this->preview_tile_size);
                            $this->request["getsize"] = "tile_" . $scale . "_" . $this->regionx . "_" . $this->regiony . "_" . $this->regionw . "_" . $this->regionh;
                            $this->request["getext"] = "jpg";
                        } else {
                            $this->errors[] = "Invalid tile size requested";
                            $this->triggerError(501);
                        }
                    } else {
                        // Full/max image region requested
                        if ($this->max_width >= $this->imagewidth && $this->max_height >= $this->imageheight) {
                            $this->request["getext"] = strtolower($resource["file_extension"]) == "jpeg" ? "jpeg" : "jpg";
                            if (in_array($resource["file_extension"], $this->media_extensions)) {
                                // The largest available size for these is 'pre'
                                $this->request["getsize"] = "pre";
                            } else {
                                $this->request["getsize"] = $this->largest_jpg_size($resource);
                            }
                        } else {
                            $this->request["getext"] = "jpg";
                            $this->request["getsize"] = count($availsizes) > 0 ? $availsizes[0]["id"] : "thm";
                        }
                    }
                } else {
                    $this->errors[] = "Invalid size requested";
                    $this->triggerError(400);
                }

                if ($this->request["rotation"] != 0) {
                    // Rotation. As we only support IIIF Image level 0 only a rotation value of 0 is accepted
                    $this->errors[] = "Invalid rotation requested. Only '0' is permitted.";
                    $this->triggerError(404);
                }
                if (isset($this->request["quality"]) && $this->request["quality"] != "default" && $this->request["quality"] != "color") {
                    // Quality. As we only support IIIF Image level 0 only a quality value of 'default' or 'color' is accepted
                    $this->errors[] = "Invalid quality requested. Only 'default' is permitted";
                    $this->triggerError(404);
                }
                if (isset($this->request["format"]) && strtolower($this->request["format"]) != "jpg") {
                    // Format. As we only support IIIF Image level 0 only a value of 'jpg' is accepted
                    $this->errors[] = "Invalid format requested. Only 'jpg' is permitted.";
                    $this->triggerError(404);
                }

                if (!isset($this->errorcode)) {
                    // Request is supported, send the image
                    $imgpath = get_resource_path($this->request["id"], true, $this->request["getsize"], false, $this->request["getext"]);
                    if ($tile_request && !file_exists($imgpath)) {
                        // Support older tiles without scale factor in ID that may not have been recreated
                        // Get resource path again as size is used in creating filename hash.
                        $imgpath = get_resource_path($this->request["id"], true, preg_replace("/(tile_\\d+_)/", "tile_", $this->request["getsize"]), false, $this->request["getext"]);
                    }
                    $imgfound = false;
                    debug("IIIF: image path: " . $imgpath);
                    if (file_exists($imgpath)) {
                        $imgfound = true;
                    } elseif ($this->custom_sizes && ($this->request["region"] == "full" || $this->request["region"] == "max")) {
                        if (is_process_lock('create_previews_' . $resource["ref"] . "_" . $this->request["getsize"])) {
                            $this->errors[] = "Requested image is not currently available";
                            $this->triggerError(503);
                        }
                        $GLOBALS["use_error_exception"] = true;
                        try {
                            $imgfound = create_previews($this->request["id"], false, "jpg", false, true, -1, true, false, false, array($this->request["getsize"]));
                            clear_process_lock('create_previews_' . $resource["ref"] . "_" . $this->request["getsize"]);
                        } catch (Exception $e) {
                            debug("IIIF: error - " . $e->getMessage());
                            $imgfound = false;
                        }
                        unset($GLOBALS["use_error_exception"]);
                    }
                    if ($imgfound) {
                        $this->validrequest = true;
                        $this->response["image"] = $imgpath;
                    } else {
                        $this->errorcode = "404";
                        $this->errors[] = "No image available for this identifier";
                    }
                }
            }
            /* IMAGE REQUEST END */
        } else {
            $this->errors[] = "Missing or invalid identifier";
            $this->triggerError(404);
        }
    }

    /**
     * Send the requested image to the IIIF client
     *
     * @return void
     */
    public function renderImage(): void
    {
        // Send the image
        $file_size   = filesize_unlimited($this->response["image"]);
        $file_handle = fopen($this->response["image"], 'rb');
        header("Access-Control-Allow-Origin: *");
        header('Content-Disposition: inline;');
        header('Content-Transfer-Encoding: binary');
        $mime = get_mime_type($this->response["image"])[0];
        header("Content-Type: {$mime}");
        $sent = 0;
        while ($sent < $file_size) {
            echo fread($file_handle, $this->download_chunk_size);
            ob_flush();
            flush();
            $sent += $this->download_chunk_size;
            if (0 != connection_status()) {
                break;
            }
        }
        fclose($file_handle);
    }

    /**
     * Find all resources associated with the given identifier and adds to the $iiif object
     *
     * @return void
     *
     */
    public function getResources(): void
    {
        $iiif_field = get_resource_type_field($this->identifier_field);
        $iiif_search = $iiif_field["name"] . ":" . $this->request["id"];
        $results = do_search($iiif_search);
        if (is_array($results)) {
            $this->searchresults = $results;
        } else {
            $this->searchresults = [];
        }

        // Add sequence position information
        $resultcount = count($this->searchresults);
        $iiif_results_with_position = [];
        $iiif_results_without_position = [];
        for ($n = 0; $n < $resultcount; $n++) {
            if ($this->sequence_field != 0) {
                if (isset($this->searchresults[$n]["field" . $this->sequence_field])) {
                    $sequenceid = $this->searchresults[$n]["field" . $this->sequence_field];
                } else {
                    $sequenceid = get_data_by_field($this->searchresults[$n]["ref"], $this->sequence_field);
                }

                if (!isset($sequenceid) || trim($sequenceid) == "") {
                    // Processing resources without a sequence position separately
                    debug("IIIF:  position empty for resource ref " . $this->searchresults[$n]["ref"]);
                    $iiif_results_without_position[] = $this->searchresults[$n];
                    continue;
                }

                debug("IIIF:  position $sequenceid found in resource ref " . $this->searchresults[$n]["ref"]);
                $this->searchresults[$n]["iiif_position"] = $sequenceid;
                $iiif_results_with_position[] = $this->searchresults[$n];
            } else {
                $sequenceid = $n;
                debug("IIIF:  position $sequenceid assigned to resource ref " . $this->searchresults[$n]["ref"]);
                $this->searchresults[$n]["iiif_position"] = $sequenceid;
                $iiif_results_with_position[] = $this->searchresults[$n];
            }
        }

        // Sort by user supplied position (handle blanks and duplicates)
        if ($this->sequence_field != 0) {
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

            $this->searchresults = array_merge($iiif_results_with_position, $iiif_results_without_position);
            $sorted_final = [];
            $maxid = 0;
            foreach ($this->searchresults as $index => $resource) {
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

            $this->searchresults = $sorted_final;
        }
    }

    /**
     * Update the $iiif object with the current resource at the given canvas position
     *
     * @param int       $position   The annotation position
     *
     * @return void
     *
     */
    public function getResourceFromPosition($position): void
    {
        $this->processing = [];
        // Need to find the resourceid the annotation is linked to
        if (isset($this->searchresults[$position])) {
            $this->processing["resource"] = $this->searchresults[$position]["ref"];
            if (in_array(strtolower($this->searchresults[$position]['file_extension'] ?? ""), $this->media_extensions)) {
                $identifier = '';
            } else {
                $identifier = $this->largest_jpg_size($this->searchresults[$position]);
            }
            $this->processing["size_info"] = array(
                'identifier' => $identifier,
                'return_height_width' => true,
            );
        }
    }

    /**
     * Generate the image API data
     *
     * @param int       $resourceid     Resource ID
     *
     * @return array
     *
     */
    public function generateImageService(int $resourceid): array
    {
        $service = [];
        $service["id"] = $this->rootimageurl . $resourceid;
        $service["type"] = "ImageService3";
        $service["profile"] = "level0";
        return $service;
    }

    /**
     * Is the tile request valid
     *
     * @return bool
     *
     */
    public function isValidTileRequest(): bool
    {
        if (
            ($this->getwidth == $this->preview_tile_size && $this->getheight == 0) // "w,"
            || ($this->getheight == $this->preview_tile_size && $this->getwidth == 0) // ",h"
            || ($this->getheight == $this->preview_tile_size && $this->getwidth == $this->preview_tile_size) // "w,h"
        ) {
            // Standard tile widths
            return true;
        } elseif (
            ($this->regionx + $this->regionw) === ($this->imagewidth)
            || ((int)$this->regiony + (int)$this->regionh) === ((int)$this->imageheight)
        ) {
            // Check this is a valid scale from the width/height requested.
            // If using just e.g. "x," or ",y" then default to 1)
            $hscale = $this->getwidth > 0 ? ceil($this->regionw / $this->getwidth) : 1;
            $vscale = $this->getheight > 0 ? ceil($this->regionh / $this->getheight) : 1;
            if (
                ($this->getwidth === 0 || $this->getheight === 0 || $hscale == $vscale)
                && count(array_diff([$hscale,$vscale], $this->preview_tile_scale_factors)) == 0
            ) {
                return true;
            }
        }
        debug('IIIF invalid tile request');
        return false;
    }

    /**
     * Indicate whether the response is an image file
     *
     * @return bool
     *
     */
    public function is_image_response()
    {
        return isset($this->response["image"]);
    }

    /**
     * Get the largest resource JPG size available for a given resource in search result set
     *
     * @param array $resource   Array of resource data from do_search()
     *
     * @return string           Size to use - 'hpr', or '' to use original size
     *
     */
    public function largest_jpg_size($resource)
    {
        return is_jpeg_extension($resource["file_extension"] ?? "") ? "" : "hpr";
    }
}

// Start of IIIF v2.1 functions. These should be replaced with new code or removed when no longer required

/**
* Get an array of all the canvases for the identifier ready for JSON encoding
*
* @uses get_data_by_field()
* @uses get_original_imagesize()
* @uses get_resource_type_field()
* @uses get_resource_path()
* @uses iiif_get_thumbnail()
* @uses iiif_get_image()
*
* @param integer $identifier        IIIF identifier (this associates resources via the metadata field set as $iiif_identifier_field
* @param array $iiif_results        Array of ResourceSpace search results that match the $identifier, sorted
* @param boolean $sequencekeys      Get the array with each key matching the value set in the metadata field $iiif_sequence_field. By default the array will be sorted but have a 0 based index
*
* @return array
*/
function iiif_get_canvases($identifier, $iiif_results, $sequencekeys = false)
{
    global $rooturl,$iiif_sequence_field;

    $canvases = array();
    foreach ($iiif_results as $index => $iiif_result) {
        $useimage = $iiif_result;
        if ((int)$iiif_result['has_image'] === 0) {
            // If configured, try and use a preview from a related resource
            debug("IIIF: No image for IIIF request - check for related resources");
            $pullresource = related_resource_pull($iiif_result);
            if ($pullresource !== false) {
                $useimage = $pullresource;
            }
        }
        $size = is_jpeg_extension($useimage["file_extension"] ?? "") ? "" : "hpr";
        $useextension = strtolower((string) $useimage["file_extension"]) == "jpeg" ? $useimage["file_extension"] : "jpg";
        $img_path = get_resource_path($useimage["ref"], true, $size, false, $useextension);
        if (!file_exists($img_path)) {
            continue;
        }
        $sequenceid = $iiif_result["iiif_position"];
        $sequence_field = get_resource_type_field($iiif_sequence_field);
        $sequence_prefix = "";
        if (isset($GLOBALS["iiif_sequence_prefix"])) {
            $sequence_prefix  = $GLOBALS["iiif_sequence_prefix"] === "" ? $sequence_field["title"] . " " : $GLOBALS["iiif_sequence_prefix"];
        }

        $canvases[$index]["@id"] = $rooturl . $identifier . "/canvas/" . $index;
        $canvases[$index]["@type"] = "sc:Canvas";
        $canvases[$index]["label"] = $sequence_prefix . $sequenceid;

        // Get the size of the images
        $image_size = get_original_imagesize($useimage["ref"], $img_path);
        $canvases[$index]["height"] = intval($image_size[2]);
        $canvases[$index]["width"] = intval($image_size[1]);

        // "If the largest image's dimensions are less than 1200 pixels on either edge, then the canvas dimensions
        // should be double those of the image." - From http://iiif.io/api/presentation/2.1/#canvas
        if ($image_size[1] < 1200 || $image_size[2] < 1200) {
            $image_size[1] = $image_size[1] * 2;
            $image_size[2] = $image_size[2] * 2;
        }

        $canvases[$index]["thumbnail"] = iiif_get_thumbnail($useimage["ref"]);

        // Add image (only 1 per canvas currently supported)
        $canvases[$index]["images"] = array();
        $size_info = array(
            'identifier' => $size,
            'return_height_width' => false,
            'original_file_extension' => $useextension
        );
        $canvases[$index]["images"][] = iiif_get_image($identifier, $useimage["ref"], $index, $size_info);
    }

    if ($sequencekeys) {
        // keep the sequence identifiers as keys so a required canvas can be accessed by sequence id
        return $canvases;
    }

    ksort($canvases);
    $return = array();
    foreach ($canvases as $canvas) {
        $return[] = $canvas;
    }
    return $return;
}

/**
* Get  thumbnail information for the specified resource id ready for IIIF JSON encoding
*
* @uses get_resource_path()
* @uses getimagesize()
*
* @param integer $resourceid        Resource ID
*
* @return array
*/
function iiif_get_thumbnail($resourceid)
{
    global $rootimageurl;

    $img_path = get_resource_path($resourceid, true, 'thm', false);
    if (!file_exists($img_path)) {
        // If configured, try and use a preview from a related resource
        $resdata = get_resource_data($resourceid);
        $pullresource = related_resource_pull($resdata);
        if ($pullresource !== false) {
            $resourceid = $pullresource["ref"];
            $img_path = get_resource_path($resourceid, true, "thm", false);
        }
    }

    if (!file_exists($img_path)) {
        return false;
    }

    $thumbnail = array();
    $thumbnail["@id"] = $rootimageurl . $resourceid . "/full/thm/0/default.jpg";
    $thumbnail["@type"] = "dctypes:Image";

    // Get the size of the images
    $GLOBALS["use_error_exception"] = true;
    try {
        list($tw,$th) = getimagesize($img_path);
        $thumbnail["height"] = (int) $th;
        $thumbnail["width"] = (int) $tw;
    } catch (Exception $e) {
        $returned_error = $e->getMessage();
        debug("getThumbnail: Unable to get image size for file: $img_path  -  $returned_error");
        // Use defaults
        $thumbnail["height"] = 150;
        $thumbnail["width"] = 150;
    }
    unset($GLOBALS["use_error_exception"]);

    $thumbnail["format"] = "image/jpeg";

    $thumbnail["service"] = array();
    $thumbnail["service"]["@context"] = "http://iiif.io/api/image/2/context.json";
    $thumbnail["service"]["@id"] = $rootimageurl . $resourceid;
    $thumbnail["service"]["profile"] = "http://iiif.io/api/image/2/level1.json";
    return $thumbnail;
}

/**
* Get the image for the specified identifier canvas and resource id
*
* @uses get_original_imagesize()
* @uses get_resource_path()
*
* @param integer $identifier  IIIF identifier (this associates resources via the metadata field set as $iiif_identifier_field
* @param integer $resourceid  Resource ID
* @param string $position     The canvas identifier, i.e position in the sequence. If $iiif_sequence_field is defined
* @param array $size          ResourceSpace size information. Required information: identifier and whether it
*                             requires to return height & width back (e.g annotations don't require it).
*                             Please note for the identifier - we use 'hpr' if the original file is not a JPG file it
*                             will be the value of this metadata field for the given resource
*                             Example:
*                             $size_info = array(
*                               'identifier'          => 'hpr',
*                               'return_height_width' => true
*                             );
*
* @return array
*/
function iiif_get_image($identifier, $resourceid, $position, array $size_info)
{
    global $rooturl,$rootimageurl;

    // Quick validation of the size_info param
    if (empty($size_info) || (!isset($size_info['identifier']) && !isset($size_info['return_height_width']))) {
        return false;
    }

    $size = $size_info['identifier'];
    $return_height_width = $size_info['return_height_width'];

    $useextension = $size_info['original_file_extension'] ?? 'jpg';
    $img_path = get_resource_path($resourceid, true, $size, false, $useextension);
    if (!file_exists($img_path)) {
        return false;
    }

    $image_size = get_original_imagesize($resourceid, $img_path);

    $images = array();
    $images["@context"] = "http://iiif.io/api/presentation/2/context.json";
    $images["@id"] = $rooturl . $identifier . "/annotation/" . $position;
    $images["@type"] = "oa:Annotation";
    $images["motivation"] = "sc:painting";

    $images["resource"] = array();
    $images["resource"]["@id"] = $rootimageurl . $resourceid . "/full/max/0/default.jpg";
    $images["resource"]["@type"] = "dctypes:Image";
    $images["resource"]["format"] = "image/jpeg";

    $images["resource"]["height"] = intval($image_size[2]);
    $images["resource"]["width"] = intval($image_size[1]);

    $images["resource"]["service"] = array();
    $images["resource"]["service"]["@context"] = "http://iiif.io/api/image/2/context.json";
    $images["resource"]["service"]["@id"] = $rootimageurl . $resourceid;
    $images["resource"]["service"]["profile"] = "http://iiif.io/api/image/2/level1.json";
    $images["on"] = $rooturl . $identifier . "/canvas/" . $position;

    if ($return_height_width) {
        $images["height"] = intval($image_size[2]);
        $images["width"] = intval($image_size[1]);
    }

    return $images;
}

/**
 * Handle a IIIF error.
 *
 * @param  integer $errorcode The error code
 * @param  array $errors An array of errors
 * @return void
 */
function iiif_error($errorcode = 404, $errors = array())
{
    if (function_exists("http_response_code")) {
        http_response_code($errorcode); # Send error status
    }
    echo json_encode($errors);
    exit();
}

// End of IIIF v2.1 functions.

function is_power_of_two(int $x): bool
{
    return $x > 0 && (($x & ($x - 1)) === 0);
}
