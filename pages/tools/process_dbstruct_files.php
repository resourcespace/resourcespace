<?php

# Force the processing of dbstruct files to apply any database changes for ResourceSpace base code and any active plugins.
# !! Use with caution !! - Some database changes e.g. adding columns or indexes may take some time to complete which could impact system performance / availability.
# It is recommended to run this out of hours or when the system is in light use.

include "../../include/boot.php";
command_line_only();

check_db_structs();

echo "Completed" . PHP_EOL;
