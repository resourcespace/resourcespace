<?php

#
# purge_xml_metadump.php
#
#
# delete all XML metadump files in filestore
#
include "../../include/boot.php";
include "../../include/image_processing.php";
command_line_only();

$sql = "";
$params = [];
if (getval("ref", "") != "") {
    $sql = "where r.ref= ?";
    $params[] = 'i';
    $params[] = getval('ref', '', true);
}

set_time_limit(60 * 60 * 5);
echo "\nRemoving XML metadata dump files...</strong>\n\n";

$start = getval('start', '0');
if (!is_numeric($start)) {
    $start = 0;
}

$resources = ps_query("select r.ref,u.username,u.fullname from resource r left outer join user u on r.created_by=u.ref $sql order by ref", $params);
for ($n = $start; $n < count($resources); $n++) {
    $ref = $resources[$n]["ref"];
    $metadump_path = dirname(get_resource_path($ref, true, "pre", true));
    $metadump = $metadump_path . "/metadump.xml";
    $index = $n + 1;
    if (file_exists($metadump)) {
        echo "Deleting metadump.xml for $ref ($index/" . count($resources) . ")... ";
        unlink($metadump);
        echo "Done\n";
    } else {
        echo "No file existing for $ref ($index/" . count($resources) . ")\n";
    }
    flush();
}
