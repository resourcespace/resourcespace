<?php

$last_geo_setcoords_from_country  = get_sysvar('last_geo_setcoords_from_country', '1970-01-01');

// Only run if more than 24 hours since last run
if (time() - strtotime($last_geo_setcoords_from_country) < 24 * 60 * 60) {
    echo " - Skipping geo_setcoords_from_country - last run: " . $last_geo_setcoords_from_country . "<br />\n";
    return false;
}

include __DIR__ . "/../../pages/tools/geo_setcoords_from_country.php";
set_sysvar("last_geo_setcoords_from_country", date("Y-m-d H:i:s"));
