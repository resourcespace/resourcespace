<?php

/**
* Metadata related functions
*
* Functions related to resource metadata in general
*
* @package ResourceSpace\Includes
*/

/**
* Run FITS on a file and get the output back
*
* @uses get_utility_path()
* @uses run_command()
*
* @param string $file_path Physical path to the file
*
* @return bool | SimpleXMLElement
*/
function runFitsForFile($file_path)
{
    global $fits_path;

    $fits              = get_utility_path('fits');
    $fits_path_escaped = escapeshellarg($fits_path);
    $file              = escapeshellarg($file_path);

    if (false === $fits) {
        debug('ERROR: FITS library could not be located!');
        return false;
    }

    putenv("LD_LIBRARY_PATH={$fits_path_escaped}/tools/mediainfo/linux");

    $return = run_command("{$fits} -i {$file} -xc");
    if (trim($return) != "") {
        return new SimpleXMLElement($return);
    }
    return false;
}

/**
* Get metadata value for a FITS field
*
* @param SimpleXMLElement $xml  FITS metadata XML
* @param string $fits_field A ResourceSpace specific FITS field mapping which allows ResourceSpace to know exactly where
*                               to look for that value in XML by converting it to an XPath query string.
* Example:
* video.mimeType would point to
*
* <metadata>
*   <video>
*     [...]
*     <mimeType toolname="MediaInfo" toolversion="0.7.75" status="SINGLE_RESULT">video/quicktime</mimeType>
*     [...]
*   </video>
* </metadata>
*
* @return string
*/
function getFitsMetadataFieldValue(SimpleXMLElement $xml, $fits_field)
{
    // IMPORTANT: Not using "fits" namespace (or any for that matter) will yield no results
    // TODO: since there can be multiple namespaces (especially if run with -xc options) we might need to implement the
    // ability to use namespaces directly from RS FITS Field.
    $xml->registerXPathNamespace('fits', 'http://hul.harvard.edu/ois/xml/ns/fits/fits_output');

    // Convert fits field mapping from rs format to namespaced XPath format
    // Example rs field mapping for an xml element value
    //   rs field is one.two.three which converts to an xpath filter of //fits:one/fits:two/fits:three
    // Example rs field mapping for an xml attribute value (attributes are not qualified by the namespace)
    //   rs attribute is one.two.three/@four which converts to an xpath filter of //fits:one/fits:two/fits:three/@four
    $fits_path = explode('.', $fits_field);
    // Reassemble with the namespace
    $fits_filter  = "//fits:" . implode('/fits:', $fits_path);

    $result = $xml->xpath($fits_filter);

    if (!isset($result) || false === $result || 0 === count($result)) {
        return '';
    }

    // First result entry carries the element or attribute value
    if (isset($result[0]) && !is_array($result[0])) {
        return $result[0];
    }

    return '';
}

/**
* Extract FITS metadata from a file for a specific resource.
*
* @uses get_resource_data()
* @uses ps_query()
* @uses runFitsForFile()
* @uses getFitsMetadataFieldValue()
* @uses update_field()
*
* @param string         $file_path Path to the file from which you will extract FITS metadata
* @param integer|array  $resource  Resource ID or resource array (as returned by get_resource_data())
*
* @return boolean
*/
function extractFitsMetadata($file_path, $resource)
{
    if (get_utility_path('fits') === false) {
        return false;
    }

    if (!file_exists($file_path)) {
        return false;
    }

    if (!is_array($resource) && !is_numeric($resource)) {
        return false;
    }

    if (!is_array($resource) && is_numeric($resource) && 0 < $resource) {
        $resource = get_resource_data($resource);
    }

    $resource_type = $resource['resource_type'];

    // Get a list of all the fields that have a FITS field set
    $allfields = get_resource_type_fields($resource_type);
    $rs_fields_to_read_for = array_filter($allfields, function ($field) {
        return trim((string)$field["fits_field"]) != "";
    });

    if (0 === count($rs_fields_to_read_for)) {
        return false;
    }

    // Run FITS and extract metadata
    $fits_xml            = runFitsForFile($file_path);
    if (!$fits_xml) {
        return false;
    }
    $fits_updated_fields = array();

    foreach ($rs_fields_to_read_for as $rs_field) {
        $fits_fields = explode(',', (string)$rs_field['fits_field']);

        foreach ($fits_fields as $fits_field) {
            $fits_field_value = getFitsMetadataFieldValue($fits_xml, $fits_field);

            if ('' == $fits_field_value) {
                continue;
            }

            update_field($resource['ref'], $rs_field['ref'], $fits_field_value);

            $fits_updated_fields[] = $rs_field['ref'];
        }
    }

    if (0 < count($fits_updated_fields)) {
        return true;
    }

    return false;
}

/**
* Check date conforms to "yyyy-mm-dd hh:mm" format or any valid partital of that e.g. yyyy-mm.
*
* @uses check_date_parts()
*
* @param string         string form of the date to check
*
* @return string
*/
function check_date_format($date)
{
    global $lang;

    if (is_null($date)) {
        $date = "";
    } else {
        $date = trim($date);
    }

    // Check the format of the date to "yyyy-mm-dd hh:mm:ss"
    if (
        (preg_match("/^([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2}):([0-9]{2})$/", $date, $parts))
        // Check the format of the date to "yyyy-mm-dd hh:mm"
        || (preg_match("/^([0-9]{4})-([0-9]{2})-([0-9]{2}) ([0-9]{2}):([0-9]{2})$/", $date, $parts))
        // Check the format of the date to "yyyy-mm-dd"
        || (preg_match("/^([0-9]{4})-([0-9]{2})-([0-9]{2})$/", $date, $parts))
    ) {
        // If dates come in the form of YYYY-MM-00 or YYYY-00-00 Pad out the date to allow for validation 
        if (($parts[2] === '00' && $parts[3] === '00') || ($parts[3] === '00' && $parts[2] !== '00')) {
            $parts[2] === '00' ? $parts[2] = '01' : '';
            $parts[3] === '00' ? $parts[3] = '01' : '';
        }
        if (!checkdate($parts[2], $parts[3], $parts[1])) {
            return str_replace("%date%", $date, $lang["invalid_date_error"]);
        }
        return str_replace("%date%", $date, check_date_parts($parts));
    }

    // Check the format of the date to "yyyy-mm" pads with 01 to ensure validity
    elseif (preg_match("/^([0-9]{4})-([0-9]{2})$/", $date, $parts)) {
        array_push($parts, '01');
        return str_replace("%date%", $date, check_date_parts($parts));
    }
    // Check the format of the date to "yyyy" pads with 01 to ensure validity
    elseif (preg_match("/^([0-9]{4})$/", $date, $parts)) {
        array_push($parts, '01', '01');
        return str_replace("%date%", $date, check_date_parts($parts));
    }

    // If it matches nothing return unknown format error
    return str_replace("%date%", $date, $lang["unknown_date_format_error"]);
}

/**
* Check datepart conforms to its formatting and error out each section accordingly
*
* @param array         array of the date parts
*
* @return string
*/
function check_date_parts($parts)
{
    global $lang;

    // Initialise error list holder
    $invalid_parts = array();

    // Check day part
    if (!checkdate('01', $parts[3], '2000')) {
        array_push($invalid_parts, 'day');
    }
    // Check day month
    if (!checkdate($parts[2], '01', '2000')) {
        array_push($invalid_parts, 'month');
    }
    // Check year part
    if (!checkdate('01', '01', $parts[1])) {
        array_push($invalid_parts, 'year');
    }
    // Check time part
    if (
        isset($parts[4])
        && isset($parts[5])
        && !strtotime($parts[4] . ':' . $parts[5])
    ) {
            array_push($invalid_parts, 'time');
    }

    // No errors found return false
    if (empty($invalid_parts)) {
        return false;
    }
    // Return errors found
    else {
        return str_replace("%parts%", implode(", ", $invalid_parts), $lang["date_format_error"]);
    }
}

/**
* updates the value of fieldx field further to a metadata field value update
*
* @param integer $metadata_field_ref - metadata field ref
*
*/
function update_fieldx(int $metadata_field_ref): void
{
    global $NODE_FIELDS;

    if ($metadata_field_ref > 0 && in_array($metadata_field_ref, get_resource_table_joins())) {
        $fieldinfo = get_resource_type_field($metadata_field_ref);
        $allresources = ps_array("SELECT ref value FROM resource WHERE ref>0 ORDER BY ref ASC", []);
        if (in_array($fieldinfo['type'] ?? [], $NODE_FIELDS)) {
            if ($fieldinfo['type'] === FIELD_TYPE_CATEGORY_TREE) {
                $all_tree_nodes_ordered = get_cattree_nodes_ordered($fieldinfo['ref'], null, true);
                // remove the fake "root" node which get_cattree_nodes_ordered() is adding since we won't be using get_cattree_node_strings()
                array_shift($all_tree_nodes_ordered);
                $all_tree_nodes_ordered = array_values($all_tree_nodes_ordered);

                foreach ($allresources as $resource) {
                    // category trees are using full paths to node names
                    $resource_nodes = array_keys(get_cattree_nodes_ordered($fieldinfo['ref'], $resource, false));
                    $node_names_paths = [];
                    foreach ($resource_nodes as $node_ref) {
                        $node_names_paths[] = implode(
                            '/',
                            array_column(compute_node_branch_path($all_tree_nodes_ordered, $node_ref), 'name')
                        );
                    }

                    update_resource_field_column(
                        $resource,
                        $metadata_field_ref,
                        implode($GLOBALS['field_column_string_separator'], $node_names_paths)
                    );
                }
            } else {
                foreach ($allresources as $resource) {
                    $resnodes = get_resource_nodes($resource, $metadata_field_ref, true);
                    uasort($resnodes, 'node_orderby_comparator');
                    $resvals = array_column($resnodes, "name");
                    $resdata = implode($GLOBALS['field_column_string_separator'], $resvals);
                    update_resource_field_column($resource, $metadata_field_ref, $resdata);
                }
            }
        } else {
            foreach ($allresources as $resource) {
                update_resource_field_column($resource, $metadata_field_ref, get_data_by_field($resource, $metadata_field_ref));
            }
        }
    }
}

/**
 * Extract and store dimensions, resolution, and unit (if available) from exif data
 * Exiftool output format (tab delimited): widthxheight resolution unit (e.g., 1440x1080 300 inches)
 *
 * @param  string   $file_path         Path to the original file.
 * @param  int      $ref               Reference of the resource.
 * @param  boolean  $remove_original   Option to remove the original record. Used by update_resource_dimensions.php
 *
 * @return void
 */
function exiftool_resolution_calc($file_path, $ref, $remove_original = false)
{
    $exiftool_fullpath = get_utility_path("exiftool");
    $command = $exiftool_fullpath . " -s -s -s %s ";
    $command .= escapeshellarg($file_path);
    $exif_output = run_command(sprintf($command, "-composite:imagesize"));

    if ($exif_output != '') {
        if ($remove_original) {
            ps_query("DELETE FROM resource_dimensions WHERE resource= ?", ['i', $ref]);
        }
        $wh = explode("x", $exif_output);
        if (count($wh) > 1) {
            $width = $wh[0];
            $height = $wh[1];
            $filesize = filesize_unlimited($file_path);
            $sql_insert = "insert into resource_dimensions (resource,width,height,file_size";
            $sql_params = [
                's', $ref,
                'i', $width,
                'i', $height,
                's', $filesize
            ];

            $exif_resolution = run_command(sprintf($command, '-xresolution'));
            if (is_numeric($exif_resolution) && $exif_resolution > 0) {
                $sql_insert .= ',resolution';
                $sql_params[] = 'd';
                $sql_params[] = $exif_resolution;
            }

            $exif_unit = run_command(sprintf($command, '-resolutionunit'));
            if ($exif_unit != '') {
                $sql_insert .= ',unit';
                $sql_params[] = 's';
                $sql_params[] = $exif_unit;
            }

            $sql_insert .= ")";
            $sql_values = "values (" . ps_param_insert((count($sql_params) / 2)) . ")";
            $sql = $sql_insert . $sql_values;
            ps_query($sql, $sql_params);
        }
    }
}

/**
 * Return array containing data for all required fields that apply to the given resource type.
 *
 * @param  int  $resource_type   Resource type reference.
 */
function get_required_fields(int $resource_type): array
{
    if (isset($GLOBALS['get_required_fields'][$resource_type])) {
        return $GLOBALS['get_required_fields'][$resource_type];
    }

    $allfields = get_resource_type_fields([$resource_type]);

    $result = array_values(array_filter($allfields, function($field) {
        return $field['required'] == 1;
        }));
    $GLOBALS['get_required_fields'][$resource_type] = $result;

    return $result;
}


/**
 * For a given resource, return an array of data for required metadata field references which have not been completed.
 * Required fields shown by a display condition will only be considered required if the display condition is met.
 *
 * @param  int|array  $resource   Integer representing the resource reference or array of resource data from get_resource_data().
 */
function missing_fields_check(int|array $resource): array
{
    if (!is_array($resource)) {
        $resource = get_resource_data($resource);
    }

    $required_fields = get_required_fields($resource['resource_type']);

    $required_fields_displayed = array();
    $display_condition_required_fields = array();
    foreach ($required_fields as $required_field) {
        if (isset($required_field['display_condition']) && strpos($required_field['display_condition'], '=') !== false) {
            # These required fields will only be checked if they are displayed based upon display conditions.
            $display_condition_required_fields[] = $required_field;
        } else {
            # No display condition so required field is always shown.
            $required_fields_displayed[] = $required_field;
        }
    }

    if (count($display_condition_required_fields) > 0) {
        foreach (get_resource_field_data($resource['ref'], false, false) as $field_data) {
            $all_fields_data[$field_data['name']] = $field_data['value'];
        }

        # Evaluate display conditions
        foreach ($display_condition_required_fields as $display_condition_field) {
            foreach(explode(';', $display_condition_field['display_condition']) as $condition) {
                $parts = explode('=', $condition);
                $field_condition = $parts[0];
                $validvalues = array_map("i18n_get_translated", explode("|", $parts[1]));
                if (isset($all_fields_data[$field_condition]) && in_array($all_fields_data[$field_condition], $validvalues)) {
                    $required_fields_displayed[] = $display_condition_field;
                    break;
                }
            }
        }
    }

    $required_fields = $required_fields_displayed;

    # Provide translated titles for later displaying in error messages.
    $required_fields = array_map(function($v) { $v['title'] = i18n_get_translated($v["title"]); return $v; }, $required_fields);

    if (count($required_fields) === 0) {
        return array();
    }

    $all_resource_nodes = get_resource_nodes($resource['ref'], null, true);
    $all_resource_nodes = array_unique(array_column($all_resource_nodes, 'resource_type_field'));

    $missing = array_diff(array_column($required_fields, 'ref'), $all_resource_nodes);

    if (count($missing) > 0) {
        return array_values(array_filter($required_fields, function($field) use ($missing) {return in_array($field['ref'], $missing);}));
    }

    return array();
}

/**
 * Considers if checking of missed required fields is required when a resource changes archive state. We'll always allow moving
 * to the $resource_deletion_state and Pending Submission state (-2). Exceptions made to archive states in rse_workflow will be applied.
 * Return will consist of an empty array where no checking is needed or an array of metadata field data where the field is required
 * but was not completed.
 * 
 * @param  int|array  $resource        Resource ref or array of resource data, likely from get_resource_data().
 * @param  int        $archive_state   Destination archive state.
 */
function update_archive_required_fields_check(int|array $resource, int $archive_state): array
{
    global $resource_deletion_state;

    if (is_array($resource)) {
        $resource_ref = $resource['ref'];
    } else {
        $resource_ref = $resource;
    }

    if (isset($GLOBALS['update_archive_required_fields_check'][$resource_ref])) {
        return $GLOBALS['update_archive_required_fields_check'][$resource_ref];
    }

    if (in_array($archive_state, array(-2, $resource_deletion_state))) {
        # No check required moving to these archive states.
        return array();
    }

    if (!isset($GLOBALS['update_archive_required_fields_check_hook'][$archive_state])) {
        $check_not_required = hook(('archive_skip_required_fields'), '', array($archive_state));
        $GLOBALS['update_archive_required_fields_check_hook'][$archive_state] = $check_not_required;
    } else {
        $check_not_required = $GLOBALS['update_archive_required_fields_check_hook'][$archive_state];
    }

    if ($check_not_required) {
        # Workflow states destination archive allows for required fields checking to be skipped.
        return array();
    }

    $result = missing_fields_check($resource);
    $GLOBALS['update_archive_required_fields_check'][$resource_ref] = $result;
    return $result;
}