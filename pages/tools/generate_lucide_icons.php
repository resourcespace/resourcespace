<?php

// Generate a PHP array of Lucide icon names based on lib/lucide/info.json
// The script should be run every time Lucide is upgraded.

include "../../include/boot.php";
command_line_only();

$info_json = file_get_contents("../../lib/lucide/info.json");
$info_decoded = json_decode($info_json);

$icon_classes = "<?php\n\n" . '$lucide_icons = array(' . "\n";

foreach ($info_decoded as $icon_key => $icon_value) {
    $icon_classes .= '    "' . $icon_key . '",' . "\n";
}

$icon_classes .= ");";

echo "Updating lib/lucide/icon_classes.php\n";
file_put_contents("../../lib/lucide/icon_classes.php", $icon_classes);
