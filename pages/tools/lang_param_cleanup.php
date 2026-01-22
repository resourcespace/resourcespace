<?php

include "../../include/boot.php";
command_line_only();

$plugins = scandir("../../plugins");
array_shift($plugins);
array_shift($plugins); // Discard first two which are "." and ".."
$plugins[] = "";
$plugins = array_reverse($plugins);

$count = 0;

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

    // Fetch a list of valid parameters using en.php as the base
    $base = file_get_contents($basefile);
    preg_match_all("/\[([a-zA-Z0-9_]*)\]/", $base, $params_correct);

    foreach ($languages as $language => $lang_name) {
        $lang = array();
        $langfile = "../../" . $plugin_path . "languages/" . $language . ".php";

        if (!file_exists($langfile)) {
            continue;
        }

        $compare = file_get_contents($langfile);
        preg_match_all("/\[([a-zA-Z0-9_]*)\]/", $compare, $params);

        // Which are wrong?
        $wrong = array_diff($params[0], $params_correct[0]);

        if (count($wrong) > 0) {
            $replaced = $compare;
            foreach ($wrong as $w) {
                if (!is_numeric(str_replace(["[","]"], "", $w))) {
                    $replaced = str_replace($w, "[badparam]", $replaced);
                    $count++;
                }
            }
            file_put_contents($langfile, $replaced);
        }
    }
}
echo "Replaced $count incorrect language strings with \"[badparam]\"";
