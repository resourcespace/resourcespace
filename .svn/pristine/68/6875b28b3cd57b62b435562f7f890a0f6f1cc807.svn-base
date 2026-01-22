<?php

/**
 * Publish resources in the array to falcon.io
 *
 * @param  array                $resources Array of resources to publish. Must include named "ref" key with resource ID as value as per search results
 * @param  string               $template_text Text to use for template description. If not passed will use value from configured $falcon_link_text_field
 * @param  string               $template_tags Comma separated list of tags to add to Falcon template. If not passed will use values from configured $falcon_link_tag_fields
 *
 * @return array                "success" => Overall outcome of publish action
 *                              "errors"  => Array of error messages
 *                              "results" => Array of resource IDs and associated status messages
 */
function falcon_link_publish($resources, $template_text, $template_tags)
{
    global $lang, $userref, $username, $baseurl_short, $baseurl, $hide_real_filepath;
    global $falcon_base_url, $falcon_link_api_key, $falcon_link_text_field, $falcon_link_tag_fields, $falcon_link_id_field;
    global $falcon_link_default_tag, $falcon_link_share_user, $falcon_link_filter;

    $result = array("success" => false,"errors" => array(),"results" => array());
    debug("falcon_link: falcon_link_publish (resources=" . implode(",", array_column($resources, "ref")) . ", template_text='" . $template_text . "', template_tags='" . $template_tags . "')");

    if (!is_array($resources) || count($resources) < 1) {
        $result["success"] = false;
        $result["errors"][] = $lang["falcon_link_error_no_resources"];
        return $result;
    }

    $skip = array();
    foreach ($resources as $resource) {
        $ref = $resource["ref"];

        // Check if already published
        $falconid = get_data_by_field($ref, $falcon_link_id_field);
        if (trim($falconid) != "") {
            debug("falcon_link: falcon_link_publish - resource already published. Resource:" . $ref . ", Falcon id: " . $falconid);
            $published[$ref] = $falconid;
            $skip[] = $ref;
            continue;
        }

        // Check that files actually exists
        if (!isset($resource['file_extension'])) {
            $resourcedata = get_resource_data($ref);
        } else {
            $resourcedata = $resource;
        }

        $check = get_resource_path($ref, true, '', false, $resourcedata['file_extension']);
        if (!file_exists($check)) {
            debug("falcon_link: falcon_link_publish - resource file not found . Resource:" . $ref);
            // Error - file does not exist
            $result["results"][$ref] = $lang["resourcenotfound"];
            $skip[] = $ref;
            continue;
        }

        // Check that resource can be published
        if (trim($falcon_link_filter) != "") {
            $metadata = get_resource_field_data($ref, false, false);
            $matchedfilter = false;

            for ($n = 0; $n < count($metadata); $n++) {
                $name = $metadata[$n]["name"];
                $value = $metadata[$n]["value"];
                if ($name != "") {
                    $match = filter_match($falcon_link_filter, $name, $value);
                    if ($match == 1) {
                        $matchedfilter = false;
                        break;
                    }
                    if ($match == 2) {
                        $matchedfilter = true;
                    }
                }
            }
            if (!$matchedfilter) {
                $skip[] = $ref;
                $result["results"][$ref] = $lang["falcon_link_access_denied"];
                $result["errors"][] = $lang["falcon_link_access_denied"];
                return $result;
            }
        }
    }
    $falcon_errors = array();

    $hide_real_filepath = true; // Set so that Falcon doesn't use the real filestore path. This allows access to be revoked from ResourceSpace if necessary

    foreach ($resources as $resource) {
        $ref = $resource["ref"];

        if (in_array($ref, $skip)) {
            debug("falcon_link: skipping " . $ref);
            continue;
        }

        $key                = generate_resource_access_key($ref, $userref, 0, 0, $falcon_link_share_user);
        $resource_url       = get_resource_path($ref, false, '', false, $resourcedata['file_extension']) . "&k=" . $key;
        $filename           = get_download_filename($ref, '', -1, $resourcedata['file_extension']);
        $upload_text        = ($template_text == "") ? get_data_by_field($ref, $falcon_link_text_field) : $template_text;

        if (trim($template_tags) == "") {
            $upload_tags = "";
            foreach ($falcon_link_tag_fields as $falcon_link_tag_field) {
                $resource_keywords  =  get_data_by_field($ref, $falcon_link_tag_field);
                $upload_tags     .=  ($upload_tags != "" ? "," : "") . $resource_keywords;
            }
        } else {
            $upload_tags = $template_tags;
        }

        if (trim($falcon_link_default_tag) != "") {
            $upload_tags .= (trim($upload_tags) != "" ? "," : "") . str_replace("[ID]", $ref, $falcon_link_default_tag);
        }

        $falcon_query_params = array(
        'apikey'    => $falcon_link_api_key
        );

        $falcon_post_params = json_encode(array(
            'tags'      => explode(",", $upload_tags),
            'content'   => array(
                'picture' => array(
                    'message'           => $upload_text,
                    'url'               => $resource_url,
                    'originalPicture'   => $resource_url,
                    'fileName'          => $filename
                    )
                )
            ));

        $faction_url = generateURL($falcon_base_url . "/publish/publishing/template", $falcon_query_params);

        $curl = curl_init($faction_url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json;charset=utf-8"));
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $falcon_post_params);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);

        debug("falcon_link: falcon_link_publish. Resource:" . $ref . " - Sending request");
        $curl_response  = curl_exec($curl);
        $curl_info      = curl_getinfo($curl);

        if ($curl_info['http_code'] != 201) {
            debug("falcon_link: falcon_link_publish. Resource:" . $ref . " - Publish failed. Info: " . print_r($curl_info, true));

            if (!in_array($lang["falcon_link_error_falcon_api"], $falcon_errors)) {
                $falcon_errors[] = $lang["falcon_link_error_falcon_api"];
            }

            $result["results"][$ref] = $lang["falcon_link_error_falcon_api_detailed"] . " '" . strip_tags($curl_response) . "'";

            if ($curl_info['http_code'] == 502) {
                $result["results"][$ref] .= ". " . $lang["falcon_link_error_falcon_check_tags"];
            }
            continue;
        }

        $response = json_decode($curl_response, true);
        $falconid = $response['id'];
        $result["results"][$ref] = $lang["falcon_link_publish_success"] . " (" . $falconid . ")";
        debug("falcon_link: falcon_link_publish. Resource:" . $ref . " - Successfully published. Falcon id# " . $falconid);
        resource_log($ref, LOG_CODE_UNSPECIFIED, '', $lang['falcon_link_log_publish'] . " (" . $falconid . ")");
        update_field($ref, $falcon_link_id_field, $falconid);
    }

    if (count($falcon_errors) > 0) {
        $result["success"] = false;
        $result["errors"] = array_merge($result["errors"], $falcon_errors);
        return $result;
    }

    $result["success"] = true;
    return $result;
}

/**
 * Archive resources in falcon.io
 *
 * @param  array                $resources Array of resources to archive. Must include named "ref" key with resource ID as value as per search results
 *
 * @return array                "success" => Overall outcome of archive action
 *                              "errors"  => Array of error messages
 *                              "results" => Array of resource IDs and associated status messages
 */
function falcon_link_archive($resources)
{
    global $lang, $userref, $username, $baseurl_short, $baseurl, $hide_real_filepath, $falcon_link_share_user;
    global $falcon_base_url, $falcon_link_api_key, $falcon_link_text_field, $falcon_link_tag_fields, $falcon_link_id_field;

    $result = array("success" => false,"errors" => array());
    debug("falcon_link: falcon_link_archive (resources=" . implode(",", array_column($resources, "ref")) . "')");

    if (!is_array($resources) || count($resources) < 1) {
        $result["success"] = false;
        $result["errors"][] = $lang["falcon_link_error_no_resources"];
        return $result;
    }

    $falcon_errors = array();

    foreach ($resources as $resource) {
        $ref = $resource["ref"];
        $falconid = get_data_by_field($ref, $falcon_link_id_field);

        // Check that file has been published, if not record the fact to report back
        if (trim($falconid) == "") {
            debug("falcon_link: falcon_link_publish - resource has not been published . Resource:" . $ref);
            $result["results"][$ref] = $lang["falcon_link_resource_not_published"];
            continue;
        }

        $falcon_query_params = array(
        'apikey'    => $falcon_link_api_key
        );

        $falcon_post_params = json_encode(array());
        $faction_url = generateURL($falcon_base_url . "/publish/publishing/template/" . $falconid . "/archive", $falcon_query_params);
        $curl = curl_init($faction_url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array("Content-Type: application/json;charset=utf-8"));
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $falcon_post_params);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 1);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);

        debug("falcon_link: falcon_link_archive. Resource:" . $ref . " - Sending request");
        $curl_response  = curl_exec($curl);
        $curl_info      = curl_getinfo($curl);

        if ($curl_info['http_code'] != 200) {
            debug("falcon_link: falcon_link_archive. Resource:" . $ref . " - archive failed. Info: " . print_r($curl_info, true));
            $falcon_errors[] = $lang["falcon_link_error_falcon_api"] . " '" . strip_tags($curl_response) . "'";
            $result["results"][$ref] = $lang["falcon_link_error_falcon_api"];
            continue;
        }

        update_field($ref, $falcon_link_id_field, "");
        resource_log($ref, LOG_CODE_UNSPECIFIED, '', $lang['falcon_link_log_archive'] . " (" . $falconid . ")");
        $result["results"][$ref] = $lang["falcon_link_archived"];
    }

    if (count($falcon_errors) > 0) {
        $result["success"] = false;
        $result["errors"] = array_merge($result["errors"], $falcon_errors);
    }

    $result["success"] = true;
    return $result;
}
