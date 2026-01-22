<?php
/**
 * Adds a custom panel to the resource view page displaying detected faces and tagging options.
 *
 * This hook is is triggered during the `view.php` rendering process.
 * It queries the `resource_face` table for any faces detected in the current resource, and if found,
 * renders a table showing cropped face previews along with a dropdown to tag the face with a named person.
 * It also includes a link to perform a face similarity search (`!face<ID>`).
 *
 * Image dimensions are used to correctly crop and scale face previews for display using CSS background properties.
 * Tagging actions invoke the `faces_tag` API via JavaScript to persist the selected person name (node) for each face.
 *
 * @return bool  Returns false to allow further custom panels to be rendered after this one.
 *
 * @global array  $lang              Global language strings.
 * @global int    $ref               Resource ID being viewed.
 * @global string $baseurl           Base URL of the ResourceSpace installation.
 * @global int    $faces_tag_field   The metadata field used to tag faces with person names.
 *
 * @uses ps_query()
 * @uses get_resource_path()
 * @uses getimagesize()
 * @uses get_resource_nodes()
 * @uses generateURL()
 * @uses generate_csrf_js_object()
 */
function HookFacesViewCustompanels()
{
    global $lang,$ref,$baseurl,$faces_tag_field;
    $edit_access = get_edit_access($ref);
    $nodes = get_resource_nodes($ref, $faces_tag_field, true);

    if (checkperm("faces-v")) {
        return false;
    }

    $faces = ps_query("select ref,det_score,bbox,node from resource_face where resource=? order by ref", ["i",$ref]);
    if (count($faces) == 0) {
        return false;
    } // No face vectors yet.

    $face_path = get_resource_path($ref, true, "scr", false, "jpg");
    $face_url  = get_resource_path($ref, false, "scr", false, "jpg");
    if (!file_exists($face_path)) {
        $face_path = get_resource_path($ref, true, "", false, "jpg");
        $face_url  = get_resource_path($ref, false, "", false, "jpg");
    }
    if (!file_exists($face_path)) {
        $face_path = get_resource_path($ref, true, "", false, "jpeg");
        $face_url  = get_resource_path($ref, false, "", false, "jpeg");
    }
    if (!file_exists($face_path)) {
        // No suitable image exists
        return false;
    }

    // Get dimensions of the image
    list($image_width, $image_height) = getimagesize($face_path);
    ?>
    <div class="RecordBox">
        <div class="RecordPanel">
            <div class="Title"><?php echo escape($lang["faces-detected-faces"]); ?></div>
            <div class="Listview">
                <table class="ListviewStyle">
                    <tr>
                        <th><?php echo escape($lang["faces-detected-face"]); ?></th>
                        <th><?php echo escape($lang["faces-confidence"]); ?></th>
                        <th><?php echo escape($lang["faces-name"]); ?></th>
                        <th><?php echo escape($lang["actions"]); ?></th>
                    </tr>
                    <?php
                    foreach ($faces as $face) {
                        $bbox = json_decode($face["bbox"], true);
                        if (!is_array($bbox) || count($bbox) !== 4) {
                            continue;
                        }

                        list($x1, $y1, $x2, $y2) = $bbox;

                        // Clamp and calculate face box size
                        $x1 = max(0, min($image_width, $x1));
                        $y1 = max(0, min($image_height, $y1));
                        $x2 = max(0, min($image_width, $x2));
                        $y2 = max(0, min($image_height, $y2));

                        $face_width = $x2 - $x1;
                        $face_height = $y2 - $y1;

                        // Ensure the DISPLAYED face is at least 100px wide
                        $min_display_width = 100;
                        $scale = $face_width < $min_display_width ? $min_display_width / $face_width : 1.0;

                        // Scaled face box size
                        $crop_width = $face_width * $scale;
                        $crop_height = $face_height * $scale;

                        // Background properties
                        $bg_pos_x = -$x1 * $scale;
                        $bg_pos_y = -$y1 * $scale;
                        $bg_size_x = $image_width * $scale;
                        $bg_size_y = $image_height * $scale;

                        $style = sprintf(
                            "width:%.0fpx;height:%.0fpx;background-image:url('%s');background-position:%.0fpx %.0fpx;background-size:%.0fpx %.0fpx;background-repeat:no-repeat;border:1px solid #ccc;",
                            $crop_width,
                            $crop_height,
                            $face_url,
                            $bg_pos_x,
                            $bg_pos_y,
                            $bg_size_x,
                            $bg_size_y
                        );
                        ?>
                        <tr>
                            <td>
                                <div style="<?php echo $style; ?>"></div>
                            </td>
                            <td><?php echo round($face["det_score"] * 100, 2) ?>%</td>
                            <td>
                                <?php if (!$edit_access) {
                                    $value = "";
                                    foreach ($nodes as $node) {
                                        if ($face["node"] == $node["ref"]) {
                                            $value = $node["translated_name"];
                                        }
                                    }
                                    echo escape($value);
                                } else {
                                // Render dynamic keywords field
                                    $field = get_resource_type_field($faces_tag_field);
                                    if (!$field || $field['type'] != FIELD_TYPE_DYNAMIC_KEYWORDS_LIST) {
                                        echo escape($lang["faces-tag-field-not-set"]);
                                    } else {
                                        $field['node_options'] = get_nodes($field['ref'], null, false);
                                        $name = "face_" . $face["ref"];
                                        $selected_nodes = array($face["node"]);
                                        $multiple = false;
                                        include dirname(__FILE__, 4) . '/pages/edit_fields/9.php';
                                    }
                                } ?>
                            </td>
                            <td>
                                <?php $search_url = generateURL("{$baseurl}/pages/search.php", array("search" => "!face" . $face["ref"])); ?>
                                <a href="<?php echo $search_url ?>" onClick="return CentralSpaceLoad(this,true);">
                                    <i class="icon-search"></i>&nbsp;<?php echo escape($lang["faces-find-matching"]); ?>
                                </a>
                            </td>
                        </tr>
                    <?php } ?>
                </table>
            </div>
        </div>
    </div>

    <script>
        /**
         * Assigns a metadata node (tag) to a detected face using the ResourceSpace API in native mode.
         *
         * @param {number} face - The ID of the face to tag.
         * @param {number} node - The node ID to assign to the face.
         */
        function FacesUpdateTag(resource, face,node) {
            api("faces_set_node",{'resource': resource, 'face': face, 'node': node},null,<?php echo generate_csrf_js_object('faces_tag'); ?>);
        }

        /**
         * Assigns a metadata node (tag) to a detected face using the ResourceSpace API in native mode.
         *
         * @param {number} face - The ID of the face to tag.
         * @param {number} node - The node ID to assign to the face.
         */
        function FacesUpdateTag(resource, face,node) {
            api("faces_set_node",{'resource': resource, 'face': face, 'node': node},null,<?php echo generate_csrf_js_object('faces_tag'); ?>);
        }

        // Catch the AutoSave() call from the included dynamic keywords field and save all selected values
        function AutoSave(field) {
            // Blank the existing field.
            api("update_field",{'resource': <?php echo escape($ref) ?>, 'field': <?php echo escape($faces_tag_field) ?>, 'value': ''},function () {SaveFaces();},<?php echo generate_csrf_js_object('faces_autosave'); ?>);
        }

        function SaveFaces() {
            <?php foreach ($faces as $face) { ?>
                // Find selected value
                parentDiv = document.getElementById('face_<?php echo escape($face["ref"]) ?>_selected');
                var children = parentDiv.querySelectorAll('.keywordselected');

                if (children.length === 0) {
                    // No keyword selected - reset
                    FacesUpdateTag(<?php echo escape($ref) ?>, <?php echo escape($face["ref"]) ?>, 0);
                } else if (children.length > 1) {
                    alert('<?php echo escape($lang["faces-oneface"]) ?>');
                    // Remove all but the first keywordselected element
                    for (var i = 1; i < children.length; i++) {
                        children[i].remove();
                    }
                } else {
                    var firstChild = children[0];
                    var node = firstChild.id.match(/\d+$/); // Match numeric suffix at end
                    FacesUpdateTag(<?php echo escape($ref) ?>, <?php echo escape($face["ref"]) ?>, node[0]);
                }
            <?php } ?>
        }
    </script>
    <?php
    return false; # Allow further custom panels
}
