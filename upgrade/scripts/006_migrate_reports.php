<?php

include_once __DIR__ . "/../../include/migration_functions.php";

// Migrate reports away from requiring substitutions at execution time, which was very hacky.
// Automatically upgrade reports to use new correct syntax.

$reports = ps_query("select * from report order by ref");
foreach ($reports as $report) {
    $sql = $report["query"];
    $ref = $report["ref"];

    echo "Migrating report: " . $ref . "\n";

    global $view_title_field;
    $view_title_field_subquery = "
        (
            SELECT rd.value 
              FROM resource_data AS rd 
             WHERE rd.resource = r.ref 
               AND rd.resource_type_field = {$view_title_field}
             LIMIT 1
         )";
    if (strpos($sql, 'r.') === false || $ref == 8) {
        $view_title_field_subquery = str_replace('r.ref', 'ref', $view_title_field_subquery);
    }

    #Attempt to cater for user created copies of shipped reports which may still reference r.view_title_field
    if (strpos($sql, 'view_title_field_subselect') === false) {
        $sql = str_replace("r.view_title_field", "view_title_field_subselect", $sql);
    }

    if (strpos($sql, 'view_title_field_subselect') === false) {
        $sql = str_replace("view_title_field", "view_title_field_subselect", $sql);
    }

    #back compatibility for three default reports, to replace "title" with the view_title_field.
    #all reports should either use r.title or view_title_field when referencing the title column on the resource table.
    if ($ref == 7 || $ref == 8 || $ref == 9) {
        $sql = str_replace(",title", ",view_title_field_subselect", $sql);
    }

    #Attempt to cater for user created copies of shipped reports which may still reference r.title
    $sql = str_replace("r.title", "view_title_field_subselect", $sql);

    #Now crystallize the view title subselect reference with the actual subselect necessary to fulfil it
    $sql = str_replace("view_title_field_subselect", $view_title_field_subquery, $sql);

    ps_query("update report set query=? where ref=?", array("s",$sql,"i",$ref)); // Note $sql here is the report to be inserted - it's not executed here
}
