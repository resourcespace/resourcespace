<?php

$_SERVER["HTTP_HOST"] = $argv[2];
require __DIR__ . "/../../include/boot.php";
command_line_only();

set_time_limit(0);

if (defined('STDIN')) {
    if (empty($argv[1])) {
        exit("Resource Refs missing");
    }

    $rlist = explode(",", $argv[1]);
} else {
    if (getval("resource_refs", "") === "") {
        exit("Resource Refs missing");
    }

    $rlist = getval("resource_refs", "");
}

#Cleanse Input
foreach ($rlist as $k => $v) {
    if (!is_numeric($v)) {
        unset($rlist[$k]);
        continue;
    }

    if (ps_value("SELECT count(ref) AS `value` FROM resource WHERE ref = ?", array("i",$v), 0) != 1) {
        unset($rlist[$k]);
    }
}

for ($n = 0; $n < count($rlist); $n++) {
    for ($m = 0; $m < count($rlist); $m++) {
        // Don't relate a resource to itself
        if ($rlist[$n] != $rlist[$m]) {
            $resource_n = intval($rlist[$n]);
            $resource_m = intval($rlist[$m]);

            $sql = "SELECT count(1) AS `value`
                    FROM resource_related
                    WHERE resource = ?
                    AND related = ?
                    LIMIT 1";

            if (ps_value($sql, array("i",$resource_n, "i",$resource_m), 0) != 1) {
                ps_query("INSERT INTO resource_related (resource, related)
                          VALUES (?,?)", array("i",$resource_n, "i",$resource_m));
            }
        }
    }
}

echo "Completed Relating Resources\n";
