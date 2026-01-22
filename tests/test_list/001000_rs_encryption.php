<?php

command_line_only();

include_once __DIR__ . '/../../include/encryption_functions.php';

$data = "Test encryption data";
$encryption_key = "654b5005395f10488aae744b8615e007";

$encrypted_data = rsEncrypt($data, $encryption_key);
$plaintext      = rsDecrypt($encrypted_data, $encryption_key);

if ($plaintext === false) {
    return false;
}

if ($data !== $plaintext) {
    return false;
}

return true;
