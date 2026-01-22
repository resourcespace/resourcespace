<?php

function HookEmbeddocumentAllModified_cors_process()
{
    global $baseurl;

    $baseurl_path = parse_url($baseurl, PHP_URL_PATH);
    $request_URI  = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';

    if (!is_null($baseurl_path)) {
        $request_URI = str_replace($baseurl_path, '', $request_URI);
    }

    $whitelist_plugin = strpos($request_URI, "/plugins/embeddocument/");

    if ($whitelist_plugin !== false && $whitelist_plugin == 0) {
        return true;
    }

    return false;
}
