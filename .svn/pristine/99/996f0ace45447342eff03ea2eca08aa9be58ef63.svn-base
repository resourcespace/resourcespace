<?php

// Creates or updates the database help files, ready for manual completion by adding a note to each column.
include "../../include/boot.php";
command_line_only();

$folder = __DIR__ . "/../../dbstruct/";

$dir = scandir($folder);
foreach ($dir as $file) {
    if (substr($file, 0, 6) == "table_") {
        // Found a table. Work out table name.
        $s = strpos($file, ".");
        $table = substr($file, 6, $s - 6);
        echo "Processing: " . $table . "\n";

        // Create the help file if it doesn't exist.
        $helpfile = $folder . "help_" . $table . ".txt";
        if (!file_exists($helpfile)) {
            touch($helpfile);
            file_put_contents($helpfile, "(table description goes here)\n", FILE_APPEND);
        }

        // Load the columns already there
        $existingcols = file($helpfile);

        // Check all the columns are there; add those that arent
        $cols = file($folder . $file);
        foreach ($cols as $col) {
            $col_split = explode(",", $col);
            $colname = $col_split[0];

            // Look for the column in the existing columns
            $found = false;
            foreach ($existingcols as $existingcol) {
                $existingcol_split = explode(",", $existingcol);
                if ($existingcol_split[0] == $colname) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                // Column was not in the list; add it
                file_put_contents($helpfile, $colname . "," . $col_split[1] . ",\n", FILE_APPEND);
            }
        }
    }
}
