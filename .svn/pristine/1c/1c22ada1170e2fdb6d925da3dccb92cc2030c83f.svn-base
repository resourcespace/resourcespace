<?php

function meta_get_map()     // returns array of [resource_type][table][attributes], where attributes are (remote_table, remote_ref, required, missing, options[])
{
    global $mysql_db;

    $meta = array();

    $fields = get_resource_type_fields();

    foreach ($fields as $field) {
        # Get translated - support i18n - upload columns must be in user's local language.
        $field['name'] = trim(i18n_get_translated($field['name']));
        $arr_fieldrestypes = explode(",", (string) $field['resource_types']);
        foreach ($arr_fieldrestypes as $fieldrestype) {
            $fieldrestype = (int) $fieldrestype;

            if (!isset($meta[$fieldrestype])) {
                $meta[$fieldrestype] = []; // make meta[<resource_type>] if does not exist
            }
            $meta[$fieldrestype][$field['name']]['remote_ref'] = $field['ref'];
            $meta[$fieldrestype][$field['name']]['nicename'] = $field['title'];
            $meta[$fieldrestype][$field['name']]['required'] = $field['required'];
            $meta[$fieldrestype][$field['name']]['type'] = $field['type'];
            $meta[$fieldrestype][$field['name']]['missing'] = false;
        }
    }

    $columns = ps_query("SELECT upper(column_name) AS name, column_name AS nicename FROM information_schema.columns WHERE table_name = 'resource' AND table_schema = ?", ['s', $mysql_db]);

    foreach (array_keys($meta) as $resource_type) {
        foreach ($columns as $column) {
            if (!isset($meta[$resource_type])) {
                $meta[$resource_type] = array();
            }
            if (isset($meta[$resource_type][$column['name']]) || isset($meta[0][$column['name']])) {
                continue; // important, we do not want to override an existing meta field defined in resource_field_type
            }
            $meta[$resource_type][$column['name']] = array();
            $meta[$resource_type][$column['name']]['remote_table'] = "resource";
            $meta[$resource_type][$column['name']]['remote_ref'] = null;      // not required as mapping to resource table
            $meta[$resource_type][$column['name']]['required'] = ($column == "resource_type");
            $meta[$resource_type][$column['name']]['missing'] = false;
        }
    }

    return $meta;
}
