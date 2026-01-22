<?php
function HookMuseumplusViewRenderfield($field, $resource)
    {
    global $baseurl, $search, $ref, $lang, $museumplus_secondary_links_field;

    $resource_association = mplus_get_associated_module_conf(array($resource['ref']), false);
    if(empty($resource_association))
        {
        return false;
        }
    $rs_uid_field = $resource_association[$resource['ref']]['rs_uid_field'];
    $applicable_resource_types = $resource_association[$resource['ref']]['applicable_resource_types'];
    $module_name = $resource_association[$resource['ref']]['module_name'];

    $field_ref = (isset($field['ref']) ? $field['ref'] : 0);
    $field_value = (isset($field['value']) ? trim($field['value']) : '');

    if(!in_array($resource['resource_type'], $applicable_resource_types) || $field_ref == 0 || $field_value === '')
        {
        return false;
        }

    // Generate and render the secondary MPlus links
    if($museumplus_secondary_links_field == $field_ref)
        {
        $rendered_secondary_links = array();
        $sec_links = explode(',', $field_value);
        foreach($sec_links as $sec_link_str)
            {
            $sec_link_str = trim($sec_link_str);
            if($sec_link_str === '')
                {
                continue;
                }

            $sec_link_parts = explode(':', $sec_link_str);
            $sec_link_module = (isset($sec_link_parts[0]) ? $sec_link_parts[0] : '');
            $sec_link_id = (isset($sec_link_parts[1]) && is_numeric($sec_link_parts[1]) ? $sec_link_parts[1] : 0);

            $mplus_module_record_url = mplus_generate_module_record_url($sec_link_module, (int) $sec_link_id);

            if($mplus_module_record_url === '')
                {
                continue;
                }

            $rendered_secondary_links[] = sprintf('<a href="%s" target="_blank">%s</a>', $mplus_module_record_url, escape($sec_link_str));
            }

        if(!empty($rendered_secondary_links))
            {
            ?>
            <div class="item">
                <h3><?php echo escape($field['title']); ?></h3>
                <p><?php echo implode(', ', $rendered_secondary_links); ?></p>
            </div>
            <div class="clearerleft"></div>
            <?php

            return true;
            }
        
        return false;
        }

    if($field_ref == $rs_uid_field)
        {
        $mpid = $field_value;
        if($mpid === '')
            {
            return false;
            }
        $value = $mpid;

        // Generate the MuseumPlus URL for this module record only if we have a valid association
        $computed_md5 = mplus_compute_data_md5([$resource['ref'] => $mpid], $module_name);
        $mplus_resource_validation_data = mplus_resource_get_data([$resource['ref']]);
        foreach($mplus_resource_validation_data as $resource_data)
            {
            if(
                $resource['ref'] == $resource_data['ref']
                && $computed_md5[$resource['ref']] === $resource_data['museumplus_data_md5']
                && $resource_data['museumplus_data_md5'] !== ''
                && $resource_data['museumplus_technical_id'] !== ''
            )
                {
                $mplus_module_url = mplus_generate_module_record_url($module_name, (int) $resource_data['museumplus_technical_id']);
                break;
                }
            }
        ?>
        <div class="itemNarrow">
            <h3><?php echo escape($field['title']); ?></h3>
            <p><?php echo escape($value); ?></p>
            <?php
            if(isset($mplus_module_url) && $mplus_module_url !== '')
                {
                ?>
                <p>
                    <a href="<?php echo $mplus_module_url; ?>" target="_blank"><?php echo escape($lang['museumplus_view_in_museumplus']); ?></a>
                </p>
                <?php
                }
                ?>
        </div>
        <?php

        return true;
        }

    return false;
    }
