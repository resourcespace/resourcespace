<?php

include "../../include/boot.php";
command_line_only();

// Report on all language entries that are present in language files that are not present in the en.php file.

$plugins = scandir("../../plugins");
array_shift($plugins);
array_shift($plugins); // Discard first two which are "." and ".."
$plugins[] = ""; // Add an extra row to signify the base languages (not in a plugin)
$plugins = array_reverse($plugins);
$orphans = 0;

foreach ($plugins as $plugin) {
    $plugin_path = "";
    if ($plugin != "") {
        $plugin_path = "plugins/" . $plugin . "/";
    }

    // Get a baseline
    $lang = array();
    $basefile = "../../" . $plugin_path . "languages/en.php";
    if (!file_exists($basefile)) {
        continue;
    } // This plugin does not have any translations.
    include $basefile;
    $lang_en = $lang;

    foreach ($languages as $language => $lang_name) {
        if (in_array($language, array("en","en-US"))) {
            continue;
        }

        // Process a language
        $lang = array();
        $langfile = "../../" . $plugin_path . "languages/" . $language . ".php";

        // Include to get the lang array for this language
        include $langfile;

        // Work out what is surplus
        $missing = array_diff(array_keys($lang), array_keys($lang_en));

        foreach ($missing as $mkey) {
            if (substr($mkey, 0, 7) != "plugin-") {
                echo $plugin . ":" . $language . ":" . $mkey . "\n";
                $orphans++;
            }
        }
    }
}
echo $orphans . " orphaned languages entries found\n";
