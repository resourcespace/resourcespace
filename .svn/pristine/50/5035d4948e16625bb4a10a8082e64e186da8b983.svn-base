<?php

// Add the new reports created for report-from-search functionality
$path = __DIR__ . "/../../dbstruct/data_report.txt";

ps_query("select ref,support_non_correlated_sql from report limit 1", []); // Ensure new column created first.

$f = fopen($path, "r");
while (($row = fgetcsv($f, 5000)) !== false) {
    # Escape values

    // Pull off the ref - not inserted but used to check we're inserting the new reports only.
    $ref = $row[0];
    array_shift($row);

    for ($n = 0; $n < count($row); $n++) {
        if ($row[$n] == "''") {
            $row[$n] = null;
        }
    }
    if (in_array($ref, array(22,23,24))) {
        $sql = "insert into report (ref, name, query, support_non_correlated_sql) values (null,?,?,?)";
        $sql_params = [];
        foreach ($row as $value) {
            $sql_params[] = "s";
            $sql_params[] = $value;
        }
        ps_query($sql, $sql_params, false, -1, false);
    }
}
