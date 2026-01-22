<?php
$museumplus_rs_root = dirname(__FILE__, 4);
include "$museumplus_rs_root/include/boot.php";
include "$museumplus_rs_root/include/authenticate.php";
if(!checkperm('t'))
    {
    http_response_code(401);
    exit('Access denied!');
    }
include_once "../include/museumplus_functions.php";


$errors = [];
$ref = getval('ref', 0, true);

$ramc = mplus_get_associated_module_conf([$ref], true);
$valid_association = mplus_validate_association($ramc, false, true);
if (isset($valid_association['errors']) && !empty($valid_association['errors']))
    {
    $errors[] = $valid_association['errors'][0];
    }

$modules = mplus_flip_struct_by_module($valid_association);
foreach($modules as $module_name => $mdata)
    {
    if (empty($mdata['field_mappings']))
        {
        $errors[] = str_replace('%name', $module_name, $lang['museumplus_error_module_no_field_maps']);
        continue;
        }

    foreach($mdata['resources'] as $resources_chunk)
        {
        $mplus_search = mplus_search(
            $module_name,
            mplus_xml_search_by_fieldpath(MPLUS_FIELD_ID, [$resources_chunk], $mdata['field_mappings'])
        );
        if(empty($mplus_search))
            {
            $errors[] = 'WARN: Search failed for some reason. Check debug log, filter by "[do_http_request][curl]"';
            continue;
            }

        $mplus_search_xml = mplus_get_response_xml($mplus_search);

        // No module items found (if since last successful validation, the module item has been deleted in MuseumPlus)
        $module_node = $mplus_search_xml->getElementsByTagName('module')->item(0);
        if($module_node->hasAttributes() && $module_node->attributes->getNamedItem('totalSize')->value == 0)
            {
            $errors[] = 'Unable to find searched module items';
            continue;
            }

        foreach($mplus_search_xml->getElementsByTagName('moduleItem') as $module_item)
            {
            $mpid = $module_item->getAttribute('id');
            if($mpid === '' || !$module_item->hasChildNodes())
                {
                continue;
                }

            foreach($module_item->childNodes as $child_node)
                {
                if(!in_array($child_node->tagName, ['systemField', 'dataField', 'virtualField']))
                    {
                    continue;
                    }

                $attr_name = $child_node->getAttribute('name');
                if(!in_array($attr_name, array_merge($mdata['field_mappings'], ['__lastModified'])))
                    {
                    continue;
                    }

                $value = $child_node->getElementsByTagName('value')->item(0);
                $module_items_data[$module_name][$mpid][$attr_name] = (!is_null($value) ? $value->nodeValue : '');
                }
            }
        }
    }
$mplus_data = $module_items_data[$ramc[$ref]['module_name']][$valid_association[$ref][MPLUS_FIELD_ID] ?? 0] ?? [];

include '../../../include/header.php';
if (!empty($errors))
    {
    render_top_page_error_style(implode('<br> - ', $errors));
    }
?>
<h2><?php echo escape($lang['museumplus_object_details_title']); ?></h2>
<p><?php printf(
    'Resource #%d associated with module "%s" using UID "%s" found M+ record with technical ID (__id) #%d',
    $ref,
    escape($ramc[$ref]['module_name']),
    escape($ramc[$ref]['field_values'][$ramc[$ref]['rs_uid_field']]),
    $valid_association[$ref][MPLUS_FIELD_ID] ?? 0
); ?></p>
<div id="MuseumPlusDetailContainer" class='Listview'>
    <table>
    <?php
    foreach($mplus_data as $key => $value)
        {
        ?>
        <tr> 
        <td><strong><?php echo escape($key); ?></strong></td>
        <td><?php echo escape($value); ?></td> 
        </tr>
        <?php
        }
        ?>
    </table>
</div>
<?php
include '../../../include/footer.php';
