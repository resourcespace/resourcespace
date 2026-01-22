<?php

function get_license_file_path(int $ref)
    {
    global $storagedir,$scramble_key;
    if (!file_exists($storagedir . "/license_files/")) {mkdir($storagedir . "/license_files/",0777);}
    return $storagedir . "/license_files/" . $ref . "_" . md5($scramble_key . $ref) . ".bin";
    }
