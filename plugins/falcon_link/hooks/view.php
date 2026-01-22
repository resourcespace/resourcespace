<?php

function HookFalcon_linkViewAfterresourceactions()
{
    # Adds a Falcon link to the view page.
    global $baseurl, $usergroup, $lang, $ref, $access, $resource, $falcon_link_restypes,$fields, $falcon_link_template_url;
    global $falcon_link_permitted_extensions, $falcon_link_id_field, $falcon_link_usergroups, $falcon_link_filter;

    if (in_array($usergroup, $falcon_link_usergroups) && $access == 0 && in_array($resource["resource_type"], $falcon_link_restypes) && in_array(strtolower($resource["file_extension"]), $falcon_link_permitted_extensions)) {
        $falconid = get_data_by_field($ref, $falcon_link_id_field);
        if (trim($falconid) == "") {
            if (trim($falcon_link_filter) != "") {
                if (!is_array($fields)) {
                    // Get the metadata is available
                    $metadata = get_resource_field_data($ref, false, false);
                } else {
                    $metadata = $fields;
                }

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
                    return false;
                }
            }

            echo "<li><a href='$baseurl/plugins/falcon_link/pages/falcon_link.php?resource=$ref&falcon_action=publish' onclick='CentralSpaceLoad(this,true);'><i class='icon-share'></i>&nbsp;" . $lang["falcon_link_publish"] . "</a></li>";
        } else {
            $falconurl = str_replace("[id]", $falconid, $falcon_link_template_url);
            echo "<li><a href='" . escape($falconurl) . "' target = '_blank' ><i class='icon-external-link'></i>&nbsp;" . escape($lang["falcon_link_view_in_falcon"]) . "</a></li>";
            echo "<li><a href='$baseurl/plugins/falcon_link/pages/falcon_link.php?resource=" . escape($ref) . "&falcon_action=archive' title='" . escape($lang["falcon_link_view_in_falcon"]) . "' onclick='CentralSpaceLoad(this,true);'><i class='icon-archive'></i>&nbsp;" . $lang["falcon_link_archive"] . "</a></li>";
        }
    }
}

function HookFalcon_linkViewRenderfield($field)
{
    global $falcon_link_id_field, $falcon_link_template_url, $search, $lang;

    if ($field["ref"] == $falcon_link_id_field) {
        $value = $field["value"];
        $falconurl = str_replace("[id]", $value, $falcon_link_template_url);
        $title = escape($field["title"]);
        ?>
        <div class="itemNarrow">
            <h3><?php echo escape($title); ?></h3>
            <p>
                <a href="<?php echo escape($falconurl); ?>" title="<?php echo escape($lang["falcon_link_view_in_falcon"]); ?>" target="_blank" ><?php echo escape($value); ?>
                </a>
            </p>
        </div>
        <?php
        return true;
    }
    return false;
}
