<?php

global $scramble_key, $usersession;
$cachepath = get_temp_dir() . DIRECTORY_SEPARATOR . "tus" . DIRECTORY_SEPARATOR . md5($scramble_key . $usersession) . DIRECTORY_SEPARATOR;
return [
    /**
     * File cache configs.
     * IMPORTANT: not recommended for production! (source: https://github.com/ankitpokhrel/tus-php/blob/main/README.md)
     */
    'file' => [
        'dir' => $cachepath,
        'name' => 'tus_php.server.cache',
    ],

    /*
    Redis - by default will assume a local instance.
    If you need to change the default connection parameters please see vendor/ankitpokhrel/tus-php/src/Config/server.php
    */
];
