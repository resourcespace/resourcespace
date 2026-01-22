<?php

include_once __DIR__ . "/../../include/boot.php";

// Support externally shared images on view page or search page (map view)
$k = getval("k", "");
$resource = getval("resource", "", true, "is_positive_int_loose");
$collection = getval("collection", "", true, "is_positive_int_loose");

if (($k == "") || (!check_access_key($resource, $k) && !check_access_key_collection($collection, $k))) {
    include __DIR__ . "/../../include/authenticate.php";
}

$provider   = trim(getval("provider", ""));
$variant    = trim(getval("variant", ""));

$provider   = safe_file_name($provider);
$variant    = safe_file_name($variant);

# Originally adapted from
# http://wiki.openstreetmap.org/wiki/ProxySimplePHP
# The main benefit is for SSL sites which don't want to be making HTTP calls which result in content warnings

// Check that requested tile is valid
$validatecode = $provider . ($variant != "" ? ("." . $variant) : "");
$valid_variants = [];
if (isset($geo_leaflet_sources)) {
    foreach ($geo_leaflet_sources as $geo_leaflet_source) {
        $code = $geo_leaflet_source["code"] ?? "";
        $valid_variants[] = $code;
        if (isset($geo_leaflet_source["variants"])) {
            foreach ($geo_leaflet_source["variants"] as $variantid => $variantnames) {
                $valid_variants[] = $code . "." . $variantid;
            }
        }
    }
}

if (
    !$geo_tile_caching
    || !in_array($validatecode, $valid_variants)
) {
    http_response_code(403);
    exit(escape($lang["error-permissiondenied"]));
}

if (isset($geo_tile_cache_directory) && $geo_tile_cache_directory != "") {
    $tilecache = $geo_tile_cache_directory;
} else {
    $tilecache = get_temp_dir() . "/tiles";
}

if ($provider != "") {
    $tilecache .= "/" . $provider;
}

if ($variant != "") {
    $tilecache .= "/" . $variant;
}

if (!is_dir($tilecache)) {
    if (file_exists($tilecache)) {
        unlink($tilecache);
    }
    mkdir($tilecache, 0777, true);
}

$ttl = 86400; //cache timeout in seconds

$x = intval(getval('x', 0, true));
$y = intval(getval('y', 0, true));
$z = intval(getval('z', 0, true));

$file = $tilecache . "/{$z}_{$x}_$y.png";
$gettile = true;
$allowed_types = ['image/png', 'image/jpeg'];

while (
    (
        !is_file($file)
        || (filemtime($file) < time() - $geo_tile_cache_lifetime)
        || array_intersect($allowed_types, get_mime_type($file)) === []
    )
    && $gettile
) {
    if (isset($geo_leaflet_sources) && count($geo_leaflet_sources) > 0) {
        $geo_tile_urls = [];
        foreach ($geo_leaflet_sources as $geo_leaflet_source) {
            // If no provider is specified, default to the first one defined
            if ($provider == "") {
                $provider = $geo_leaflet_source["code"];
            }
            $geo_tile_urls[$geo_leaflet_source["code"]] = [];
            $geo_tile_urls[$geo_leaflet_source["code"]]["url"] = $geo_leaflet_source["url"];
            $geo_tile_urls[$geo_leaflet_source["code"]]["subdomains"] = isset($geo_leaflet_source["subdomains"]) ? $geo_leaflet_source["subdomains"] : "dd";
            $geo_file_extension = isset($geo_leaflet_source["extension"]) ? $geo_leaflet_source["extension"] : "";
            $geo_tile_urls[$geo_leaflet_source["code"]] ["extension"] = $geo_file_extension;
            foreach ($geo_leaflet_source["variants"] as $mapvariant => $varopts) {
                if (isset($varopts["url"])) {
                    $varcode = $geo_leaflet_source["code"] . "_" . mb_strtolower($mapvariant);
                    $geo_tile_urls[$varcode]["url"] = $varopts["url"];
                    $geo_tile_urls[$varcode]["subdomains"] = isset($geo_leaflet_source["subdomains"]) ? $geo_leaflet_source["subdomains"] : "#";
                    $geo_tile_urls[$varcode]["extension"] = $geo_file_extension;
                }
            }
        }
        if ($provider != "" && isset($geo_tile_urls[$provider])) {
            $url        = $geo_tile_urls[$provider]["url"];
            $subdomains = isset($geo_tile_urls[$provider]["subdomains"]) ? $geo_tile_urls[$provider]["subdomains"] : "#";
            $extension  = $geo_tile_urls[$provider]["extension"];
            if ($variant != "" && isset($geo_tile_urls[$provider . "_" . mb_strtolower($variant)])) {
                $url        = $geo_tile_urls[$provider . "_" . mb_strtolower($variant)]["url"];
                $subdomains = $geo_tile_urls[$provider . "_" . mb_strtolower($variant)]["subdomains"];
            }
            while (strlen($subdomains) > 0) {
                // Get a random subdomain
                $subidx = substr($subdomains, 0, 1);
                //$url = $subdomains[$subidx] . "." . $url;
                // Replace placeholders in URL
                $find = array("{x}","{y}","{z}","{ext}");
                $replace = array($x,$y,$z,$extension);
                if ($subidx != "#") {
                    $find[]     = "{s}";
                    $replace[]  = $subidx;
                }

                $url = str_replace($find, $replace, $url);
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_USERAGENT, $geo_tile_user_agent);
                curl_setopt($ch, CURLOPT_REFERER, $baseurl);

                $cresponse = curl_exec($ch);
                $cerror = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $headersize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
                $header = substr($cresponse, 0, $headersize);
                $body = substr($cresponse, $headersize);
                curl_close($ch);

                if ($cerror == 200) {
                    debug("Successfully retrieved tile from " . $url);
                    file_put_contents($file, $body);
                    $gettile = false;
                    $gettile = false;
                } else {
                    debug("failed to retrieve tile from " . $url . ". Response: " . $cresponse);
                }
                $gettile = false;
                // Remove this subdomain server from the array
                $subdomains = substr($subdomains, 1);
            }
        }
    } else {
        debug('$geo_leaflet_sources is not configured');
        $gettile = false;
    }
}

if (!is_file($file) || array_intersect($allowed_types, get_mime_type($file)) === []) {
    // No tiles available at requested resolution
    http_response_code(404);
    exit($lang["error-geotile-server-error"]);
}

$exp_gmt = gmdate("D, d M Y H:i:s", time() + $ttl * 60) . " GMT";
$mod_gmt = gmdate("D, d M Y H:i:s", filemtime($file)) . " GMT";
header("Expires: " . $exp_gmt);
header("Last-Modified: " . $mod_gmt);
header("Cache-Control: public, max-age=" . $ttl * 60);
header('Content-Type: image/png');
readfile($file);

exit();
