<?php

include '../../../include/boot.php';

$url = getval('url', '');
if (trim($url) !== '') {
    global $baseurl;

    $parts = parse_url($url);
    if (strpos($baseurl, $parts['host']) === false) {
        exit();
    }
    parse_str($parts['query'], $params);
    redirect(generateURL($baseurl . '/pages/download.php', 
    [
        'ref'        => $params['ref'],
        'size'       => $params['size'],
        'ext'        => $params['ext'],
        'access_key' => $params['access_key']
    ]));
}