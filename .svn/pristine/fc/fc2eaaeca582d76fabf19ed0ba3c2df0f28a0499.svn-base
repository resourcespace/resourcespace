<?php

use Montala\ResourceSpace\CommandPlaceholderArg;

/**
 * Get (viewable) "annotate_fields" config. The configs' value is validated (e.g. view access, supported type,
 * active RTF, excluded RT, etc.).
 */
function get_annotate_fields(): array
{
    return array_unique(
        array_merge(
            // Zero is a pseudo-RTF used for comment (text) annotations (i.e not field bound)
            $GLOBALS['annotate_text_adds_comment'] && (!$GLOBALS['annotate_public_view'] || ($GLOBALS['k'] ?? '') === '')
                ? [0]
                : [],
            array_intersect(
                array_filter($GLOBALS['annotate_fields'], metadata_field_view_access(...)),
                get_all_viable_annotate_metadata_fields($GLOBALS['annotate_exclude_restypes'])
            )
        ),
        SORT_NUMERIC
    );
}

/**
 * Get all metadata fields which could be used for the annotate feature. Removes inapplicable fields when excluding
 * specific resource types.
 *
 * @param list<int> $exclude_resource_types List of Resource types to exclude
 */
function get_all_viable_annotate_metadata_fields(array $exclude_resource_types): array
{
    $resource_types = [];
    if ($exclude_resource_types !== []) {
        $all_resource_types = array_column(get_all_resource_types(), 'ref');
        $resource_types = array_values(array_diff($all_resource_types, $exclude_resource_types));
    }

    return array_column(
        get_resource_type_fields(
            restypes: $resource_types,
            field_order_by: 'ref',
            field_sort: 'asc',
            find: '',
            fieldtypes: get_valid_annotate_field_types(),
            include_inactive: false
        ),
        'ref'
    );
}

/**
* Get annotation by ID
*
* @param integer $ref Annotation ID
*
* @return array
*/
function getAnnotation($ref)
{
    if (0 >= $ref) {
        return array();
    }

    $return = ps_query("SELECT " . columns_in("annotation") . " FROM annotation WHERE ref = ?", array("i",$ref));

    if (0 < count($return)) {
        $return = $return[0];
    }

    return $return;
}

/**
* General annotations search functionality
*
* @uses ps_query()
*
* @param integer $resource
* @param integer $resource_type_field
* @param integer $user
* @param integer $page
*
* @return array
*/
function getAnnotations($resource = 0, $resource_type_field = 0, $user = 0, $page = 0)
{
    if (!is_numeric($resource) || !is_numeric($resource_type_field) || !is_numeric($user) || !is_numeric($page)) {
        return array();
    }

    $sql_where_clause    = '';
    $parameters = array();

    if (0 < $resource) {
        $sql_where_clause = " resource = ?";
        $parameters = array("i",$resource);
    }

    if (0 < $resource_type_field) {
        if ('' != $sql_where_clause) {
            $sql_where_clause .= ' AND';
        }

        $sql_where_clause .= " resource_type_field = ?";
        $parameters = array_merge($parameters, array("i",$resource_type_field));
    }

    if (0 < $user) {
        if ('' != $sql_where_clause) {
            $sql_where_clause .= ' AND';
        }

        $sql_where_clause .= " user = ?";
        $parameters = array_merge($parameters, array("i",$user));
    }

    if (0 < $page) {
        if ('' != $sql_where_clause) {
            $sql_where_clause .= ' AND';
        }

        $sql_where_clause .= " page = ?";
        $parameters = array_merge($parameters, array("i",$page));
    }

    if ('' != $sql_where_clause) {
        $sql_where_clause = "WHERE {$sql_where_clause}";
    }

        return ps_query("SELECT " . columns_in("annotation") . " FROM annotation {$sql_where_clause}", $parameters);
}

/**
* Get number of annotations available for a resource.
*
* Note: multi page resources will show the total number (ie. all pages)
*
* @uses ps_value()
*
* @param integer $resource Resource ID
*
* @return integer
*/
function getResourceAnnotationsCount($resource)
{
    if (!is_numeric($resource) || 0 >= $resource) {
        return 0;
    }

    return (int) ps_value("SELECT count(ref) AS `value` FROM annotation WHERE resource = ?", array("i",$resource), 0);
}

/**
* Get annotations for a specific resource
*
* @param integer $resource Resource ID
* @param integer $page     Page number of a document. Non documents will have 0
*
* @return array
*/
function getResourceAnnotations($resource, $page = 0)
{
    if (0 >= $resource) {
        return array();
    }

    $parameters = array("i",$resource);
    $sql_page_filter = 'AND `page` IS NULL';

    if (0 < $page) {
        $sql_page_filter = "AND `page` IS NOT NULL AND `page` = ?";
        $parameters = array_merge($parameters, array("i",$page));
    }

    return ps_query(
        sprintf(
            'SELECT %s, c.body AS "text"
                 FROM annotation AS a
            LEFT JOIN `comment` AS c ON a.ref = c.annotation
                WHERE resource = ?
                %s',
            columns_in('annotation', 'a'),
            $sql_page_filter
        ),
        $parameters
    );
}

/**
* Create an array of Annotorious annotation objects which can be JSON encoded and passed
* directly to Annotorious
*
* @param integer $resource Resource ID
* @param integer $page     Page number of a document
* @param array{k?: string} $ctx Environment context (e.g. external share)
*
* @return array
*/
function getAnnotoriousResourceAnnotations($resource, $page = 0, array $ctx = [])
{
    global $baseurl, $annotate_text_adds_comment, $annotate_show_author;

    $annotations = array();
    $can_view_fields = canSeeAnnotationsFields();

    /*
    Build an annotations array of Annotorious annotation objects

    NOTE: src attribute is generated per resource (dummy source) to avoid issues when source is
    loaded from download.php
    */
    foreach (getResourceAnnotations($resource, $page) as $annotation) {
        if (in_array($annotation['resource_type_field'], $can_view_fields)) {
            $annotations[] = array(
                'src'    => "{$baseurl}/annotation/resource/{$resource}",
                'text' => $annotate_text_adds_comment ? (string) $annotation['text'] : '',
                'shapes' => array(
                    array(
                        'type'     => 'rect',
                        'geometry' => array(
                            'x'      => (float) $annotation['x'],
                            'y'      => (float) $annotation['y'],
                            'width'  => (float) $annotation['width'],
                            'height' => (float) $annotation['height'],
                        )
                    )
                ),
                'editable' => annotationEditable($annotation, $ctx),

                // Custom ResourceSpace properties for Annotation object
                'ref' => (int) $annotation['ref'],
                'resource' => (int) $annotation['resource'],
                'resource_type_field' => (int) $annotation['resource_type_field'],
                'page' => (int) $annotation['page'],
                'tags' => getAnnotationTags($annotation),
                'author' => $annotate_show_author && ($user_data = get_user($annotation['user'])) && $user_data !== false
                    ? ($user_data['fullname'] ?: $user_data['username'])
                    : '',
            );
        }
    }

    return $annotations;
}

/**
 * Check if an annotation can be editable (add/ edit + remove) by the user. Please note that Annotorious JS library is
 * treating edit & remove as the same under the "editable" property.
 *
 * @uses checkPermission_anonymoususer()
 *
 * @param array $annotation
 * @param array{k?: string} $ctx Environment context (e.g. external share)
 */
function annotationEditable(array $annotation, array $ctx): bool
{
    debug(sprintf('[annotations][fct=annotationEditable] $annotation = %s', json_encode($annotation)));
    global $userref, $annotate_text_adds_comment, $annotate_public_view;

    // Read-only annotations when:
    if (
        // - allowed to view publicly, in an external share context
        ($annotate_public_view && ($ctx['k'] ?? '') !== '')
        // - the resource is inapplicable because its type is excluded (via $annotate_exclude_restypes)
        || !resource_can_be_annotated($annotation['resource'])
    ) {
        return false;
    }

    $add_operation = !isset($annotation['user']);

    // Text (comment) annotations
    if ((int) $annotation['resource_type_field'] === 0) {
        return $annotate_text_adds_comment
            ? (($add_operation || checkperm('o')) && !checkPermission_anonymoususer())
            : false;
    }

    /*
    # Field bound annotations

    Non-admin edit authorisation is valid when:
        - user is just adding a new (field bound) annotation
        - when editing/removing an existing annotation, the annotation was created by the same user
    */
    $non_admin_athz = ($add_operation || $userref == $annotation['user']);
    $field_edit_access = metadata_field_edit_access($annotation['resource_type_field']);
    $resource_edit_access = get_edit_access($annotation['resource']);

    // Anonymous users cannot edit by default. They can only edit if they are allowed CRUD operations
    if (checkPermission_anonymoususer()) {
        return $non_admin_athz && $field_edit_access && $resource_edit_access;
    }

    return (checkperm('a') || $non_admin_athz) && $field_edit_access && $resource_edit_access;
}

/**
* Get all tags of an annotation. Checks if a tag is attached to the resource,
* allowing the user to search by it which is represented by the virtual column
* "tag_searchable"
*
* @param array $annotation
*
* @return array
*/
function getAnnotationTags(array $annotation)
{
    $resource_ref   = $annotation['resource'];
    $annotation_ref = $annotation['ref'];

    $parameters = array("i", $resource_ref, "i", $annotation_ref);
    return ps_query("
            SELECT " . columns_in("node", "n") . ",
                   (SELECT 'yes' FROM resource_node WHERE resource = ? AND node = ref) AS tag_searchable
              FROM node AS n
             WHERE ref IN (SELECT node FROM annotation_node WHERE annotation = ?);", $parameters);
}

/**
* Delete annotation
*
* @see getAnnotation()
*
* @uses annotationEditable()
* @uses getAnnotationTags()
* @uses delete_resource_nodes()
* @uses db_begin_transaction()
* @uses db_end_transaction()
*
* @param array $annotation Annotation array as returned by getAnnotation()
* @param array{k?: string} $ctx Environment context (e.g. external share)
*
* @return boolean
*/
function deleteAnnotation(array $annotation, array $ctx)
{
    if (!annotationEditable($annotation, $ctx)) {
        return false;
    }

    $annotation_ref = $annotation['ref'];
    $parameters = array("i",$annotation_ref);

    $nodes_to_remove = array();
    foreach (getAnnotationTags($annotation) as $tag) {
        $nodes_to_remove[] = $tag['ref'];
    }

    db_begin_transaction("deleteAnnotation");

    if (0 < count($nodes_to_remove)) {
        delete_resource_nodes($annotation['resource'], $nodes_to_remove);
    }

    ps_query("DELETE FROM annotation_node WHERE annotation = ?", $parameters);
    ps_query("DELETE FROM annotation WHERE ref = ?", $parameters);

    db_end_transaction("deleteAnnotation");

    return true;
}

/**
* Create new annotations based on Annotorious annotation
*
* NOTE: Annotorious annotation shape is an array but at the moment they use only the first shape found
*
* @param array $annotation
* @param array{k?: string} $ctx Environment context (e.g. external share)
*
* @return boolean|integer Returns false on failure OR the ref of the newly created annotation
*/
function createAnnotation(array $annotation, array $ctx)
{
    debug(sprintf('[annotations][fct=createAnnotation] Param $annotation = %s', json_encode($annotation)));
    global $userref;

    if (!annotationEditable($annotation, $ctx)) {
        debug('[annotations][fct=createAnnotation][warn] annotation not editable');
        return false;
    }
    debug('[annotations][fct=createAnnotation] attempting to create annotation...');

    // ResourceSpace specific properties
    $resource = $annotation['resource'];
    $resource_type_field = (int) $annotation['resource_type_field'];
    $page = (isset($annotation['page']) && 0 < $annotation['page'] ? $annotation['page'] : null);
    // Text (comment) annotations and tagging (bound) fields are mutually exclusive
    $comment_text_mode = $GLOBALS['annotate_text_adds_comment'] && $resource_type_field === 0;
    $tags = !$comment_text_mode && isset($annotation['tags']) ? $annotation['tags'] : [];
    $text = $comment_text_mode && isset($annotation['text']) ? trim($annotation['text']) : '';

    // Annotorious annotation
    $x      = $annotation['shapes'][0]['geometry']['x'];
    $y      = $annotation['shapes'][0]['geometry']['y'];
    $width  = $annotation['shapes'][0]['geometry']['width'];
    $height = $annotation['shapes'][0]['geometry']['height'];

    ps_query(
        'INSERT INTO annotation (resource, resource_type_field, user, x, y, width, height, page) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        [
            'i', $resource,
            'i', $resource_type_field,
            'i', $userref,
            'd', $x,
            'd', $y,
            'd', $width,
            'd', $height,
            'i', $page,
        ]
    );
    $annotation_ref = sql_insert_id();
    debug('[annotations][fct=createAnnotation] annotation_ref = ' . json_encode($annotation_ref));

    if (0 == $annotation_ref) {
        debug('[annotations][fct=createAnnotation][warn] Unable to create annotation');
        return false;
    }

    if ($resource_type_field === 0 && $text !== '') {
        $_POST['resource_ref'] = $resource;
        $_POST['annotation_ref'] = $annotation_ref;
        $_POST['body'] = $text;
        comments_submit();
    }

    // Prepare tags before association by adding new nodes to dynamic keywords list (if permissions allow it)
    $prepared_tags = prepareTags($tags);

    // Add any tags associated with it
    if (0 < count($tags)) {
        addAnnotationNodes($annotation_ref, $prepared_tags);
        add_resource_nodes($resource, array_column($prepared_tags, 'ref'), false);
    }

    return $annotation_ref;
}

/**
* Update (field bound) annotation and its tags, if applicable.
* Text (comment) annotatins can't be updated (same as the comments logic for those with o perm).
*
* @uses annotationEditable()
* @uses getAnnotationTags()
* @uses delete_resource_nodes()
* @uses addAnnotationNodes()
* @uses add_resource_nodes()
* @uses db_begin_transaction()
* @uses db_end_transaction()
*
* @param array $annotation
* @param array{k?: string} $ctx Environment context (e.g. external share)
*/
function updateAnnotation(array $annotation, array $ctx): bool
{
    debug(sprintf('[annotations][fct=updateAnnotation] Param $annotation = %s', json_encode($annotation)));
    if (!isset($annotation['ref']) || !annotationEditable($annotation, $ctx)) {
        return false;
    }

    global $userref;

    // ResourceSpace specific properties
    $annotation_ref      = $annotation['ref'];
    $resource_type_field = (int) $annotation['resource_type_field'];
    $resource            = $annotation['resource'];
    $page                = (isset($annotation['page']) && 0 < $annotation['page'] ? $annotation['page'] : null);
    // Text (comment) annotations and tagging (bound) fields are mutually exclusive
    $comment_text_mode = $resource_type_field === 0;
    $tags = !$comment_text_mode && isset($annotation['tags']) ? $annotation['tags'] : [];

    if ($comment_text_mode) {
        // Text (comment) annotations are not to be updated (same as the comments logic for those with o perm)
        return false;
    }

    // Annotorious annotation
    $x      = $annotation['shapes'][0]['geometry']['x'];
    $y      = $annotation['shapes'][0]['geometry']['y'];
    $width  = $annotation['shapes'][0]['geometry']['width'];
    $height = $annotation['shapes'][0]['geometry']['height'];

    ps_query(
        'UPDATE annotation SET resource_type_field = ?, user = ?, x = ?, y = ?, width = ?, height = ?, page = ? WHERE ref = ?',
        [
            'i', $resource_type_field,
            'i', $userref,
            'd', $x,
            'd', $y,
            'd', $width,
            'd', $height,
            'i', $page,
            'i', $annotation_ref,
        ]
    );

    // Delete existing associations
    $nodes_to_remove = array();
    foreach (getAnnotationTags($annotation) as $tag) {
        $nodes_to_remove[] = $tag['ref'];
    }

    db_begin_transaction("updateAnnotation");

    if (0 < count($nodes_to_remove)) {
        delete_resource_nodes($resource, $nodes_to_remove);
    }

    ps_query("DELETE FROM annotation_node WHERE annotation = ?", ['i', $annotation_ref]);

    // Add any tags associated with this annotation
    if (0 < count($tags)) {
        // Prepare tags before association by adding new nodes to
        // dynamic keywords list (if permissions allow it)
        $prepared_tags = prepareTags($tags);

        // Add new associations
        addAnnotationNodes($annotation_ref, $prepared_tags);
        add_resource_nodes($resource, array_column($prepared_tags, 'ref'), false);
    }

    db_end_transaction("updateAnnotation");

    return true;
}

/**
* Add relations between annotation and nodes
*
* @param integer $annotation_ref The annotation ID in ResourceSpace
* @param array   $nodes          List of node structures {@see get_nodes()}. Only the "ref" property is required.
*
* @return boolean
*/
function addAnnotationNodes($annotation_ref, array $nodes)
{
    if (0 === count($nodes)) {
        return false;
    }

    $query_insert_values = '';
    $parameters = [];

    foreach ($nodes as $node) {
        $query_insert_values .= ',(?, ?)';
        $parameters = array_merge($parameters, ['i', $annotation_ref, 'i', $node['ref']]);
    }
    $query_insert_values = substr($query_insert_values, 1);

    ps_query("INSERT INTO annotation_node (annotation, node) VALUES  {$query_insert_values}", $parameters);

    return true;
}

/**
* Utility function which allows annotation tags to be prepared (i.e make sure they are all valid nodes)
* before creating associations between annotations and tags
*
* @uses checkperm()
* @uses get_resource_type_field()
* @uses set_node()
* @uses get_node()
*
* @param array $dirty_tags Original array of tags. These can be (in)valid tags/ new tags.
*                          IMPORTANT: a tag should have the same structure as a node
*
* @return array
*/
function prepareTags(array $dirty_tags)
{
    if (0 === count($dirty_tags)) {
        return array();
    }

    global $annotate_fields;

    $clean_tags = array();

    foreach ($dirty_tags as $dirty_tag) {
        // Check minimum required information for a node
        if (
            !isset($dirty_tag['resource_type_field'])
            || 0 >= $dirty_tag['resource_type_field']
            || !in_array($dirty_tag['resource_type_field'], $annotate_fields)
        ) {
            continue;
        }

        if (!isset($dirty_tag['name']) || '' == $dirty_tag['name']) {
            continue;
        }

        // No access to field? Next...
        if (!metadata_field_view_access($dirty_tag['resource_type_field'])) {
            continue;
        }

        // New node?
        if (is_null($dirty_tag['ref']) || (is_string($dirty_tag['ref']) && '' == $dirty_tag['ref'])) {
            $dirty_field_data = get_resource_type_field($dirty_tag['resource_type_field']);

            // Only dynamic keywords lists are allowed to create new options from annotations if permission allows it
            if (
                !(
                    FIELD_TYPE_DYNAMIC_KEYWORDS_LIST == $dirty_field_data['type']
                    && !checkperm("bdk{$dirty_tag['resource_type_field']}")
                )
            ) {
                continue;
            }

            // Create new node but avoid duplicates
            $new_node_id = set_node(null, $dirty_tag['resource_type_field'], $dirty_tag['name'], null, null);
            if (false !== $new_node_id && is_numeric($new_node_id)) {
                $dirty_tag['ref'] = $new_node_id;

                $clean_tags[] = $dirty_tag;
            }

            continue;
        }

        // Existing tags
        $found_node = array();
        if (
            get_node((int) $dirty_tag['ref'], $found_node)
            && $found_node['resource_type_field'] == $dirty_tag['resource_type_field']
        ) {
                $clean_tags[] = $found_node;
        }
    }

    return $clean_tags;
}


/**
* Add annotation count to a search result set
*
* @param array $items      Array of search results returned by do_search()
*
*/
function search_add_annotation_count(&$result)
{
    $annotations = ps_query(
        "SELECT resource, count(*) as annocount
           FROM annotation
          WHERE resource IN (" . ps_param_insert(count($result)) . ")
       GROUP BY resource",
        ps_param_fill(array_column($result, "ref"), "i")
    );
    $res_annotations = array_column($annotations, "annocount", "resource");
    foreach ($result as &$resource) {
        $resource["annotation_count"] = $res_annotations[$resource["ref"]] ?? 0;
    }
}

/**
 * Get all translations relevant to the Annotorious (RSTagging) plugin.
 * @param array<string, string> $map Language strings map
 */
function get_annotorious_lang(array $map): array
{
    return array_intersect_key(
        $map,
        [
            'annotorious_add_a_comment' => 1,
            'annotorious_type_to_search_field' => 1,
        ]
    );
}

/** Get all ResourceSpace config options relevant for the Annotorious (RSTagging) plugin functionality. */
function get_annotorious_resourcespace_config(): array
{
    return array_intersect_key(
        $GLOBALS,
        [
            'annotate_text_adds_comment' => 1,
            'annotate_public_view' => 1,
            'annotate_show_author' => 1,
        ]
    );
}

/** Get list of allowed field types to be configured for the "annotate_fields" option */
function get_valid_annotate_field_types(): array
{
    // IMPORTANT: due to their multiplicity nature, annotations can't bind to single value fields (e.g. text, dropdown)
    return [FIELD_TYPE_DYNAMIC_KEYWORDS_LIST, FIELD_TYPE_CHECK_BOX_LIST];
}

/**
 * Get annotate file path (old annotate plugin logic)
 * @param int $ref
 * @param bool $getfilepath
 * @param string $extension
 */
function get_annotate_file_path($ref, $getfilepath, $extension): string
{
    global $username, $scramble_key, $baseurl, $annotateid;
    $annotateid = getval("annotateid", $annotateid); //or if sent through a request
    if ($getfilepath) {
        $path = get_temp_dir(false, '') . "/annotate_" . $ref . "_" . md5($username . $annotateid . $scramble_key) . "." . $extension;
    } else {
        $path = generateURL(
            $baseurl . "/pages/download.php",
            [
            "tempfile" => "annotate_" . (int)$ref . "_" . $annotateid . "." . $extension,
            "noattach" => "true"
            ]
        );
    }
    return $path;
}

/**
 * Create annotations PDF (old annotate plugin logic)
 * @param int $ref
 * @param bool $is_collection
 * @param string $size
 * @param bool $cleanup
 * @param bool $preview
 */
function create_annotated_pdf($ref, $is_collection = false, $size = "letter", $cleanup = false, $preview = false)
{
    # function to create annotated pdf of resources or collections.
    # This leaves the pdfs and jpg previews in filestore/annotate so that they can be grabbed later.
    # $cleanup will result in a slightly different path that is not cleaned up afterwards.

    global $contact_sheet_preview_size,$lang,$userfullname,$view_title_field,$baseurl,$imagemagick_path,$imagemagick_colorspace,$previewpage,$access;
    $date = date("m-d-Y h:i a");

    include_once RESOURCESPACE_BASE_PATH . '/include/image_processing.php';

    $pdfstoragepath = get_annotate_file_path($ref, true, "pdf");
    $jpgstoragepath = get_annotate_file_path($ref, true, "jpg");
    $pdfhttppath = get_annotate_file_path($ref, false, "pdf");

    if ($is_collection) {
        $collectiondata = get_collection($ref);
        $resources = do_search("!collection$ref");
    } else {
        $resourcedata = get_resource_data($ref);
        $resources = do_search("!list$ref");
    }
    search_add_annotation_count($resources);

    $size = mb_strtolower($size);
    if (count($resources) == 0) {
        echo "nothing";
        exit();
    }
    if ($size == "a4") {
        $width = 210 / 25.4;
        $height = 297 / 25.4;
    } // convert to inches
    if ($size == "a3") {
        $width = 297 / 25.4;
        $height = 420 / 25.4;
    }
    if ($size == "letter") {
        $width = 8.5;
        $height = 11;
    }
    if ($size == "legal") {
        $width = 8.5;
        $height = 14;
    }
    if ($size == "tabloid") {
        $width = 11;
        $height = 17;
    }
    #configuring the sheet:
    $pagesize[0] = $width;
    $pagesize[1] = $height;
    debug("width = {$width}");
    debug("height = {$height}");

    $mypdf = annotation_pdf_class();
    $pdf = new $mypdf("portrait", "in", $size, true, 'UTF-8', false);
    $pdf->SetFont('helvetica', '', 8);
    // set document information
    $pdf->SetCreator(PDF_CREATOR);
    $pdf->SetAuthor($userfullname);
    if ($is_collection) {
        $pdf->SetTitle(i18n_get_collection_name($collectiondata) . ' ' . $date);
    } else {
        $pdf->SetTitle(i18n_get_translated($resourcedata['field' . $view_title_field]) . ' ' . $date);
    }
    $pdf->SetSubject($lang['annotate_annotations_label']);
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    $pdf->setMargins(.5, .5, .5);
    $page = 1;
    $totalpages = 1;
    $m = 1;
    do // Do the following for each pdf page
        {
        // Add a page for each resource
        for ($n = 0; $n < count($resources); $n++) {
            $pdf->AddPage();
            $currentpdfpage = $pdf->getPage();
            $resourcedata = $resources[$n];
            $ref = $resources[$n]['ref'];
            debug("ref = {$ref}");
            debug("currentpdfpage = {$currentpdfpage}");
            $access = get_resource_access($resources[$n]['ref']); // feed get_resource_access the resource array rather than the ref, since access is included.
            $use_watermark = check_use_watermark();

            $imgpath = get_resource_path($ref, true, "hpr", false, "jpg", -1, $page, $use_watermark);
            if (!file_exists($imgpath)) {
                $imgpath = get_resource_path($ref, true, "lpr", false, "jpg", -1, $page, $use_watermark);
            }
            if (!file_exists($imgpath)) {
                $imgpath = get_resource_path($ref, true, "scr", false, "jpg", -1, $page, $use_watermark);
            }
            if (!file_exists($imgpath)) {
                $imgpath = get_resource_path($ref, true, "", false, "jpg", -1, $page, $use_watermark);
            }
            if (!file_exists($imgpath)) {
                $imgpath = get_resource_path($ref, true, "pre", false, "jpg", -1, $page, $use_watermark);
            }
            if (!file_exists($imgpath)) {
                continue;
            }
            $imagesize = getimagesize($imgpath);

            $whratio = $imagesize[0] / $imagesize[1];

            if ($whratio < 1) {
                $imageheight = $height - 4; // vertical images can take up half the page
                $whratio = $imagesize[0] / $imagesize[1];
                $imagewidth = $imageheight * $whratio;
            }

            if ($whratio >= 1 || $imagewidth > $width + 1) {
                $imagewidth = $width - 1; // horizontal images are scaled to width - 1 in
                $hwratio = $imagesize[1] / $imagesize[0];
                $imageheight = $imagewidth * $hwratio;
            }

            $image_x = (($width - 1) / 2) - (($imagewidth - 1) / 2); # Center image horizontally
            $image_y = 1;

            $pdf->Text(.5, .5, i18n_get_translated($resourcedata['field' . $view_title_field]) . ' ' . $date);
            $pdf->Image($imgpath, $image_x, $image_y, $imagewidth, $imageheight, 'jpg', "{$baseurl}/?r={$ref}");

            // set color for background
            $pdf->SetFillColor(255, 255, 200);

            $style = array('width' => 0.01, 'cap' => 'butt', 'join' => 'round' ,'dash' => '0', 'color' => array(100,100,100));
            $style1 = array('width' => 0.04, 'cap' => 'butt', 'join' => 'round', 'dash' => '0', 'color' => array(255, 255, 0));
            $ypos = $imageheight + 1.5;
            $pdf->SetY($ypos);
            unset($notes);
            if ($resources[$n]['annotation_count'] != 0) {
                $notes = getResourceAnnotations(
                    $ref,
                    $page > 1 ? $page : 0 # Supporting legacy logic (from annotate plugin). See upgrade script #28
                );
                $notepages = 1; // Normally notes will all fit on one page, but may not
                foreach ($notes as $note) {
                    // If the notes took us to a new page, return to the image page before marking annotation
                    if ($notepages > 1) {
                        debug('Return to the image page');
                        $pdf->setPage($currentpdfpage);
                    }

                    $note['value'] = $note['text'] == ''
                        ? implode("\n", array_map(prefix_value('- '), array_column(getAnnotationTags($note), 'name')))
                        : $note['text'];

                    // Convert normalised (0-1) coordinates
                    $note_x = $note['x'] * $imagewidth;
                    $note_y = $note['y'] * $imageheight;
                    $note_width = $note['width'] * $imagewidth;
                    $note_height = $note['height'] * $imageheight;

                    $pdf->SetLineStyle($style1);
                    $pdf->Rect($image_x + $note_x, $image_y + $note_y, $note_width, $note_height);
                    $pdf->Rect($image_x + $note_x, $image_y + $note_y, .1, .1, 'DF', $style1, array(255,255,0));
                    $ypos = $pdf->GetY();
                    $pdf->Text($image_x + $note_x - .01, $image_y + $note_y - .01, $m, false, false, true, 0, 0, 'L');

                    $pdf->SetY($ypos);
                    $note_user = get_user($note['user']);
                    $pdf->SetLineStyle($style);

                    // If the notes went over the page, we  went back to image for annotation, so we need to return to the page with the last row of the table before adding next row
                    if ($notepages > 1) {
                        $pdf->setPage($currentpdfpage + ($notepages - 1));
                    }

                    $pdf->multiRow($m, trim($note['value']) . " @" . $note_user['fullname']);
                    // Check if this new table row has moved us to a new page, in which case we need to record this and go back to image page before the next annotation
                    if (isset($notepos)) {
                        debug('The new (notes) table row moved us to a new page');
                        $lastnotepos = $notepos;
                        debug("lastnotepos = {$lastnotepos}");
                    }
                    $notepos = $pdf->GetY();
                    if (isset($lastnotepos) && $notepos < $lastnotepos) {
                        unset($lastnotepos);
                        $notepages++;
                        debug('$notepages++');
                    }
                    $ypos = $ypos + .5;
                    $m++;
                }
            }
        }
        // Check if there is another page?
        if (file_exists(get_resource_path($ref, true, "scr", false, "jpg", -1, $page + 1, $use_watermark, ""))) {
            unset($notepos);
            unset($lastnotepos);
            $totalpages++;
            debug('$totalpages++');
        }

        $page++;
        debug('$page++');
    } while ($page <= $totalpages);

    // reset pointer to the last page
    $pdf->lastPage();

    #Make AJAX preview?:
    if ($preview && isset($imagemagick_path)) {
        if (file_exists($jpgstoragepath)) {
            unlink($jpgstoragepath);
        }
        if (file_exists($pdfstoragepath)) {
            unlink($pdfstoragepath);
        }
        echo $pdf->GetPage(); // for paging
        $pdf->Output($pdfstoragepath, 'F');
        # Set up
        putenv("MAGICK_HOME=" . $imagemagick_path);
        $ghostscript_fullpath = get_utility_path("ghostscript");
        run_command(
            "{$ghostscript_fullpath} -sDEVICE=jpeg -dFirstPage=previewpage -o -r100 -dLastPage=previewpage"
            . " -sOutputFile=jpgstoragepath pdfstoragepath",
            false,
            [
                'previewpage' => (int) $previewpage,
                'jpgstoragepath' => new CommandPlaceholderArg($jpgstoragepath, 'is_safe_basename'),
                'pdfstoragepath' => new CommandPlaceholderArg($pdfstoragepath, 'is_safe_basename'),
            ]
        );

        $convert_fullpath = get_utility_path("im-convert");
        if (!$convert_fullpath) {
            exit("Could not find ImageMagick 'convert' utility at location '$command'");
        }

        run_command(
            "{$convert_fullpath} -resize contact_sheet_preview_size -quality 90 -colorspace imagemagick_colorspace"
            . " jpgstoragepath jpgstoragepath",
            false,
            [
                'contact_sheet_preview_size' => new CommandPlaceholderArg(
                    $contact_sheet_preview_size,
                    'is_valid_contact_sheet_preview_size'
                ),
                'imagemagick_colorspace' => $imagemagick_colorspace,
                'jpgstoragepath' => new CommandPlaceholderArg($jpgstoragepath, 'is_safe_basename'),

            ]
        );
        return true;
    }

    if (!$is_collection) {
        $filename = $lang['annotate_annotations_label'] . "-" . i18n_get_translated($resourcedata["field" . $view_title_field]);
    } else {
        $filename = $lang['annotate_annotations_label'] . "-" . i18n_get_collection_name($collectiondata);
    }

    if ($cleanup) {
        // cleanup
        if (file_exists($pdfstoragepath)) {
            unlink($pdfstoragepath);
        }
        if (file_exists($jpgstoragepath)) {
            unlink($jpgstoragepath);
        }
        $pdf->Output($filename . ".pdf", 'D');
    } else {
        // in this case it's not cleaned up automatically, but rather left in place for later use of the path.

        $pdf->Output($pdfstoragepath, 'F');
        echo $pdfhttppath;
    }
}

/** Utility function to generate a one-off TCPDF variant (with multiRow method) */
function annotation_pdf_class()
{
    include_once dirname(__DIR__) . '/lib/html2pdf/vendor/tecnickcom/tcpdf/tcpdf.php';
    return new class extends TCPDF {
        public function multiRow($left, $right)
        {

            $page_start = $this->getPage();
            $y_start = $this->GetY();

            // write the left cell
            $this->MultiCell(.5, 0, $left, 1, 'C', 1, 2, '', '', true, 0);

            $page_end_1 = $this->getPage();
            $y_end_1 = $this->GetY();

            $this->setPage($page_start);

            // write the right cell
            $right = str_replace("<br />", "\n", $right);
            $this->MultiCell(0, 0, $right, 1, 'L', 0, 1, $this->GetX(), $y_start, true, 0);

            $page_end_2 = $this->getPage();
            $y_end_2 = $this->GetY();

            // set the new row position by case
            if (max($page_end_1, $page_end_2) == $page_start) {
                $ynew = max($y_end_1, $y_end_2);
            } elseif ($page_end_1 == $page_end_2) {
                $ynew = max($y_end_1, $y_end_2);
            } elseif ($page_end_1 > $page_end_2) {
                $ynew = $y_end_1;
            } else {
                $ynew = $y_end_2;
            }

            $this->setPage(max($page_end_1, $page_end_2));
            $this->SetXY($this->GetX(), $ynew);
        }
    };
}

/**
 * Check if a resource can be annotated
 * @param int $ref Resource ID
 */
function resource_can_be_annotated(int $ref): bool
{
    $resource_data = get_resource_data($ref);
    return $resource_data !== false && resource_type_applicable_for_annotations($resource_data['resource_type']);
}

/**
 * Check if a given resource type is applicable for annotations
 * @param int $ref Resource type ID
 */
function resource_type_applicable_for_annotations(int $ref): bool
{
    return !in_array($ref, $GLOBALS['annotate_exclude_restypes']);
}
