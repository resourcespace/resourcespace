<?php

/*
Script designed to purge users based on multiple conditions.
At the moment, only based on the user group but can be easily improved with more options
IMPORTANT: make sure to update the help section if adding new options!
*/
include __DIR__ . '/../../include/boot.php';
command_line_only();

// Separate output of this script from any initialisation messages
echo PHP_EOL;

// CLI options check
$cli_short_options = 'h';
$cli_long_options  = array(
    'help',
    'usergroup:'
);
foreach (getopt($cli_short_options, $cli_long_options) as $option_name => $option_value) {
    if (in_array($option_name, array('h', 'help'))) {
        echo 'Try running "php purge_users.php --usergroup=[user group ID]"' . PHP_EOL;
        exit(0);
    }

    if ('usergroup' == $option_name) {
        $usergroup = $option_value;
    }
}

$params = array();

if (isset($usergroup)) {
    $purge_condition = "usergroup = ?";
    $params[] = "i";
    $params[] = $usergroup;
}

if (!isset($purge_condition)) {
    echo "No purge condition found! At least one MUST be met!" . PHP_EOL;
    exit(1);
}

echo "Deleting users..." . PHP_EOL;

$purge_sql = "DELETE FROM user WHERE {$purge_condition}";
ps_query($purge_sql, $params);

echo "Done!";
echo PHP_EOL;
exit(0);
