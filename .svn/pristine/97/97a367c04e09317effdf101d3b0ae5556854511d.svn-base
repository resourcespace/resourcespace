<?php

include "../../include/boot.php";
command_line_only();

# Fetch a list of MySQL processes and kill any that exceed the timeout limit.

# Config vars
$query_timeout = 10; # Timeout in seconds.
$sleep_timeout = 3600;
$mysql_path = "/usr/bin/";
$mysql_command = $mysql_path . "mysqladmin -h $mysql_server -u $mysql_username " . ($mysql_password == "" ? "" : "-p" . $mysql_password);

for ($s = 0; $s < 60; $s += 10) { # Do this once every 10 seconds for a minute, then this can be scheduled as a cron job once per min.
    # Fetch process list
    $list = explode("\n", run_command($mysql_command . " processlist"));

    for ($n = 3; $n < count($list) - 2; $n++) {
        $vals = explode("|", $list[$n]);
        $id = trim($vals[1]);
        $type = trim($vals[5]);
        $time = trim($vals[6]);
        $info = trim($vals[6]) . " : " . trim($vals[7]);
        $query = trim($vals[8]);

        if (
            ($type == "Query") &&
            ($time > $query_timeout) &&
                (
                strpos($query, "select") !== false
                ||
                strpos($query, "create temporary table") !== false
                ) ||
            (($type == "Sleep") &&
            ($time > $sleep_timeout))
        ) {
            # Kill this process.
            echo "killing $id... $info\n";
            run_command($mysql_command . " kill " . $id);
        }
    }

    sleep(10);
}
