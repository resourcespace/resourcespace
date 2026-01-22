<?php

// Note: It is safe to run this script at any time as it will work on differential data if migration interrupted


$tables = ps_query("SHOW TABLES");
if (!in_array("resource_data", array_column($tables, "Tables_in_" . $mysql_db))) {
    // Migration only required if resource_data table exists
    return true;
}

// ---------------------------------------------------------------------------------------------------------------------
// Step 1.  Convert any missing fixed field type options to nodes (where not already deprecated)
// ---------------------------------------------------------------------------------------------------------------------

// IMPORTANT! - Uncomment this line if you want to force migration of fixed field values
// ps_query("update resource_type_field set options=replace(options,'!deprecated,','')");

$check_options_column = ps_query('SHOW COLUMNS FROM `resource_type_field` LIKE \'OPTIONS\'');
if (count($check_options_column) == 0) {
    return true;
}

$resource_type_fields = ps_query('SELECT * FROM `resource_type_field` WHERE `type` IN (' .
    ps_param_insert(count($FIXED_LIST_FIELD_TYPES)) .
    ") AND NOT `options` LIKE '!deprecated%' ORDER BY `ref`", ps_param_fill($FIXED_LIST_FIELD_TYPES, "i"));

foreach ($resource_type_fields as $resource_type_field) {
    echo "Migrating resource_type_field {$resource_type_field['ref']}:{$resource_type_field['name']}" . PHP_EOL;
    ob_flush();
    migrate_resource_type_field_check($resource_type_field);
}

// ---------------------------------------------------------------------------------------------------------------------
// Step 2.  Migrate any missing resource_data fixed fields only when not already existing in resource_node table
// ---------------------------------------------------------------------------------------------------------------------

foreach ($resource_type_fields as $resource_type_field) {
    $out = "Migrating resource_data {$resource_type_field['ref']}:{$resource_type_field['name']}";
    set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, $out);
    $resource_data_entries = ps_query("SELECT `resource`,`value` FROM `resource_data` WHERE  resource_type_field=?", array("i",$resource_type_field['ref']));
    $out .= ' (' . count($resource_data_entries) . ' rows found)';
    echo str_pad($out, 100, ' ');
    ob_flush();
    $sql = "INSERT INTO `resource_node`(`resource`,`node`,`hit_count`,`new_hit_count`)
      SELECT
        `resource_data`.`resource`,
        `node`.`ref`,
        max(`resource_keyword`.`hit_count`),
        max(`resource_keyword`.`new_hit_count`)
      FROM
        `resource_data`
      JOIN
        `node`
      ON
        NOT EXISTS(SELECT * FROM `resource_node` WHERE `resource`=`resource_data`.`resource` AND `node`=`node`.`ref`) AND
        `node`.`resource_type_field`=? AND
        `resource_data`.`value` REGEXP CONCAT('[\\\^\\\|\\\;,]+', `node`.`name`, '[\\\$\\\|\\\;,]*')
      LEFT OUTER JOIN
        `keyword`
      ON
        `keyword`.`keyword`=`node`.`name`
      LEFT OUTER JOIN
        `resource_keyword`
      ON
        `resource_keyword`.`keyword`=`keyword`.`ref` AND `resource_keyword`.`resource`=`resource_data`.`resource`
      WHERE
        `resource_data`.`resource_type_field`=?
      GROUP BY
        `resource_data`.`resource`,
        `node`.`ref`";
    ps_query($sql, array("i",$resource_type_field['ref'],"i",$resource_type_field['ref']));
    echo sql_affected_rows();
    echo " rows inserted." . PHP_EOL;
    ob_flush();
}
