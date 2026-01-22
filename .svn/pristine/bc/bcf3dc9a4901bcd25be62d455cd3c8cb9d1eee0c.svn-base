<?php

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, "Start migrating tab names to system tab records...");

// Ensure the DB is in the right state
$resource_type_field_structure = array_column(ps_query('DESCRIBE resource_type_field', [], '', -1, false), 'Field');
$resource_type_structure = array_column(ps_query('DESCRIBE resource_type', [], '', -1, false), 'Field');
if (!(in_array('tab', $resource_type_field_structure) && in_array('tab', $resource_type_structure))) {
    logScript('Checking DB structs to add tab column to resource_type_field or resource_type table(s)...');
    check_db_structs(false);
}

$all_tab_names_data = array();
if (count(ps_query("SHOW COLUMNS FROM resource_type LIKE 'tab_name';")) === 1) {
    $all_tab_names_data = ps_query("SELECT 'resource_type' AS entity, ref, tab_name FROM resource_type WHERE tab_name IS NOT NULL AND trim(tab_name) <> '';");
}

if (count(ps_query("SHOW COLUMNS FROM resource_type_field LIKE 'tab_name';")) === 1) {
    $all_tab_names_data = array_merge($all_tab_names_data, ps_query("SELECT 'resource_type_field' AS entity, ref, tab_name FROM resource_type_field WHERE tab_name IS NOT NULL AND trim(tab_name) <> '';"));
}

logScript('Found #' . count($all_tab_names_data) . ' tab names');

$system_tabs = array_column(get_all_tabs(), 'name', 'ref');
logScript('Found #' . count($system_tabs) . ' system tabs');

$tabs_to_migrate = [
    /*
    Example structure
    system_tab_id => [

        # List of resource type fields that will be linked with the system tab record
        'resource_type_field' => [],

        # List of resource types that will be linked with the system tab record
        'resource_type' => [],
    ]
    */
];
foreach ($all_tab_names_data as $all_tab_name_data) {
    $tab_name = $all_tab_name_data['tab_name'];
    $entity = $all_tab_name_data['entity'];
    logScript("Processing $entity: #{$all_tab_name_data['ref']} -- $tab_name");

    if (!in_array($tab_name, $system_tabs)) {
        $system_tab_ref = bypass_permissions(['a'], 'create_tab', [['name' => $tab_name]]);
        $system_tabs[$system_tab_ref] = $tab_name;
        logScript("Created new system tab #$system_tab_ref");
    } else {
        $system_tab_ref = array_search($tab_name, $system_tabs);
    }

    $tabs_to_migrate[$system_tab_ref][$entity][] = $all_tab_name_data['ref'];
}


// Update resource_type_field & resource_type records to get associated with their system tabs
foreach ($tabs_to_migrate as $tab_ref => $entity_links) {
    if (isset($entity_links['resource_type_field']) && is_array($entity_links['resource_type_field'])) {
        $entity_links_rtfs = $entity_links['resource_type_field'];
        ps_query(
            'UPDATE resource_type_field SET tab = ? WHERE ref IN (' . ps_param_insert(count($entity_links_rtfs)) . ')',
            array_merge(['i', $tab_ref], ps_param_fill($entity_links_rtfs, 'i'))
        );

        // Log this activity
        array_walk($entity_links_rtfs, function ($ref) use ($tab_ref, $system_tabs, $lang) {
            $log_note = sprintf($lang['tabs_migration_log_note'], $system_tabs[$tab_ref]);
            log_activity($log_note, LOG_CODE_EDITED, $tab_ref, 'resource_type_field', 'tab', $ref, null, 0);
        });
    }

    if (isset($entity_links['resource_type']) && is_array($entity_links['resource_type'])) {
        $entity_links_rts = $entity_links['resource_type'];
        ps_query(
            'UPDATE resource_type SET tab = ? WHERE ref IN (' . ps_param_insert(count($entity_links_rts)) . ')',
            array_merge(['i', $tab_ref], ps_param_fill($entity_links_rts, 'i'))
        );

        // Log this activity
        array_walk($entity_links_rts, function ($ref) use ($tab_ref, $system_tabs, $lang) {
            $log_note = sprintf($lang['tabs_migration_log_note'], $system_tabs[$tab_ref]);
            log_activity($log_note, LOG_CODE_EDITED, $tab_ref, 'resource_type', 'tab', $ref, null, 0);
        });
    }
}

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, "Finished migrating tab names to system tab records!");
