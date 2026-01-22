<?php

function get_consent_file_path($ref)
{
    global $storagedir,$scramble_key;

    if (!file_exists($storagedir . "/consent_files/")) {
        mkdir($storagedir . "/consent_files/", 0777);
    }

    return $storagedir . "/consent_files/" . $ref . "_" . md5($scramble_key . $ref) . ".bin";
}
