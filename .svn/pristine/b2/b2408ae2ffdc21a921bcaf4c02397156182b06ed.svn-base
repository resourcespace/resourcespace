<?php

/**
 * boot.php
 *
 * Connects to the database, loads all the necessary things all pages need such as configuration, plugins, languages.
 */

# Include the most commonly used functions
include_once __DIR__ . '/definitions.php';
include_once __DIR__ . '/version.php';
include_once __DIR__ . '/general_functions.php';
include_once __DIR__ . '/database_functions.php';
include_once __DIR__ . '/search_functions.php';
include_once __DIR__ . '/do_search.php';
include_once __DIR__ . '/resource_functions.php';
include_once __DIR__ . '/collections_functions.php';
include_once __DIR__ . '/language_functions.php';
include_once __DIR__ . '/message_functions.php';
include_once __DIR__ . '/node_functions.php';
include_once __DIR__ . '/encryption_functions.php';
include_once __DIR__ . '/render_functions.php';
include_once __DIR__ . '/user_functions.php';
include_once __DIR__ . '/debug_functions.php';
include_once __DIR__ . '/log_functions.php';
include_once __DIR__ . '/file_functions.php';
include_once __DIR__ . '/config_functions.php';
include_once __DIR__ . '/plugin_functions.php';
include_once __DIR__ . '/migration_functions.php';
include_once __DIR__ . '/metadata_functions.php';
include_once __DIR__ . '/job_functions.php';
include_once __DIR__ . '/tab_functions.php';
include_once __DIR__ . '/mime_types.php';
include_once __DIR__ . '/CommandPlaceholderArg.php';
include_once __DIR__ . '/annotation_functions.php';
require_once __DIR__ . '/../vendor/autoload.php';

# Switch on output buffering.
ob_start(null, 4096);

$pagetime_start = microtime();
$pagetime_start = explode(' ', $pagetime_start);
$pagetime_start = $pagetime_start[1] + $pagetime_start[0];

if ((!isset($suppress_headers) || !$suppress_headers) && !isset($nocache)) {
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");    // Date in the past
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");  // always modified
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Cache-Control: post-check=0, pre-check=0", false);
}

set_error_handler("errorhandler");

// Check the PHP version.
if (PHP_VERSION_ID < PHP_VERSION_SUPPORTED) {
    exit("PHP version not supported. Your version: " . PHP_VERSION_ID . ", minimum supported: " . PHP_VERSION_SUPPORTED);
}

# *** LOAD CONFIG ***
# Load the default config first, if it exists, so any new settings are present even if missing from config.php
if (file_exists(__DIR__ . "/config.default.php")) {
    include __DIR__ . "/config.default.php";
}
if (file_exists(__DIR__ . "/config.deprecated.php")) {
    include __DIR__ . "/config.deprecated.php";
}

# Load the real config
if (!(file_exists(__DIR__ . "/config.php") && filesize(__DIR__ . "/config.php") > 0)) {
    header("Location: pages/setup.php");
    die(0);
}
include __DIR__ . "/config.php";

// Set exception_ignore_args so that if $log_error_messages_url is set it receives all the necessary
// information to perform troubleshooting
ini_set("zend.exception_ignore_args", "Off");

error_reporting($config_error_reporting);

// Check this is a real browser. This is intentionally before DB connection (and before remote config which will connect to the DB) as with a DDOS
// scenario we want to be exiting as early as possible if the cookie is not present.
if ($browser_check) {browser_check();}

# -------------------------------------------------------------------------------------------
# Remote config support - possibility to load the configuration from a remote system.
#
debug('[boot.php] Remote config support...');
debug('[boot.php] isset($remote_config_url) = ' . json_encode(isset($remote_config_url)));
debug('[boot.php] isset($_SERVER["HTTP_HOST"]) = ' . json_encode(isset($_SERVER["HTTP_HOST"])));
debug('[boot.php] getenv("RESOURCESPACE_URL") != "") = ' . json_encode(getenv("RESOURCESPACE_URL") != ""));
if (isset($remote_config_url, $remote_config_key) && (isset($_SERVER["HTTP_HOST"]) || getenv("RESOURCESPACE_URL") != "")) {
    debug("[boot.php] \$remote_config_url = {$remote_config_url}");
    sql_connect(); # Connect a little earlier
    if (isset($_SERVER['HTTP_HOST'])) {
        $host = $_SERVER['HTTP_HOST'];
    } else {
        // If running scripts from command line the host will not be available and will need to be set as an environment variable
        // e.g. export RESOURCESPACE_URL="www.yourresourcespacedomain.com";cd /var/www/pages/tools; php update_checksums.php
        $host = getenv("RESOURCESPACE_URL");
    }
    $hostmd = md5($host);
    debug("[boot.php] \$host = {$host}");
    debug("[boot.php] \$hostmd = {$hostmd}");

    # Look for configuration for this host (supports multiple hosts)
    $remote_config_sysvar = "remote-config-" . $hostmd; # 46 chars (column is 50)
    $remote_config = get_sysvar($remote_config_sysvar);
    $remote_config_expiry = get_sysvar("remote_config-exp" .  $hostmd, 0);
    if ($remote_config !== false && $remote_config_expiry > time() && !isset($_GET["reload_remote_config"])) {
        # Local cache exists and has not expired. Use this copy.
        debug("[boot.php] Using local cached version of remote config. \$remote_config_expiry = {$remote_config_expiry}");
    } elseif (function_exists('curl_init')) {
        # Cache not present or has expired.
        # Fetch new config and store. Set a very low timeout of 2 seconds so the config server going down does not take down the site.
        # Attempt to fetch the remote contents but suppress errors.
        if (isset($remote_config_function) && is_callable($remote_config_function)) {
            $rc_url = $remote_config_function($remote_config_url, $host);
        } else {
            $rc_url = $remote_config_url . "?host=" . urlencode($host) . "&sign=" . md5($remote_config_key . $host);
        }

        $ch = curl_init();
        $checktimeout = 2;
        curl_setopt($ch, CURLOPT_URL, $rc_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $checktimeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $checktimeout);
        curl_setopt(
            $ch,
            CURLOPT_USERAGENT,
            sprintf('ResourceSpace/%s Remote config for %s', mb_strcut($productversion, 4), $host)
        );
        $r = curl_exec($ch);

        if (!curl_errno($ch)) {
            # Fetch remote config was a success.
            # Validate the return to make sure it's an expected config file
            # The last 33 characters must be a hash and the sign of the previous characters.
            if (isset($remote_config_decode) && is_callable($remote_config_decode)) {
                $r = $remote_config_decode($r);
            }
            $sign = substr($r, -32); # Last 32 characters is a signature

            $r = substr($r, 0, strlen($r) - 33);

            if ($sign === md5($remote_config_key . $r)) {
                $remote_config = $r;
                set_sysvar($remote_config_sysvar, $remote_config);
            } else {
                # Validation of returned config failed. Possibly the remote config server is misconfigured or having issues.
                # Do nothing; proceed with old config and try again later.
                debug('[boot.php][warn] Failed to authenticate the signature of the remote config');
            }
        } else {
            # The attempt to fetch the remote configuration failed.
            # Do nothing; the cached copy will be used and we will try again later.
            $errortext = curl_strerror(curl_errno($ch));
            debug("[boot.php][warn] Remote config check failed from '"  . $remote_config_url . "' : " . $errortext . " : " . $r);
        }
        curl_close($ch);

        set_sysvar("remote_config-exp" .  $hostmd, time() + (60 * 10)); # Load again (or try again if failed) in ten minutes
    }

    # Load and use the config
    eval($remote_config);
    // Cleanup
    unset($remote_config_function, $remote_config_url, $remote_config_key);
}

if ($system_download_config_force_obfuscation && !defined("SYSTEM_DOWNLOAD_CONFIG_FORCE_OBFUSCATION")) {
    // If this has been set in config.php it cannot be overridden by re.g group overrides
    define("SYSTEM_DOWNLOAD_CONFIG_FORCE_OBFUSCATION", true);
}
#
# End of remote config support
# ---------------------------------------------------------------------------------------------

// Remove stream wrappers that aren't needed to reduce security vulnerabilities.
$wrappers = stream_get_wrappers();
foreach (UNREGISTER_WRAPPERS as $unregwrapper) {
    if (in_array($unregwrapper, $wrappers)) {
        stream_wrapper_unregister($unregwrapper);
    }
}

if (!isset($suppress_headers) || !$suppress_headers) {
    $default_csp_fa = "'self'";
    if ($csp_frame_ancestors === [] && isset($xframe_options) && $xframe_options !== '') {
        // Set CSP frame-ancestors based on legacy $xframe_options config
        switch ($xframe_options) {
            case "DENY":
                $frame_ancestors = ["'none'"];
                break;
            case (bool) strpos($xframe_options, "ALLOW-FROM"):
                $frame_ancestors = explode(" ", substr($xframe_options, 11));
                break;
            default:
                $frame_ancestors = [$default_csp_fa];
                break;
        }
    } else {
        $frame_ancestors = $csp_frame_ancestors;
    }

    if (in_array("'none'", $frame_ancestors)) {
        $frame_ancestors = ["'none'"];
    } else {
        array_unshift($frame_ancestors, $default_csp_fa);
    }

    header('Content-Security-Policy: frame-ancestors ' . implode(" ", array_unique(trim_array($frame_ancestors))));
}

if ($system_down_redirect && getval('show', '') === '') {
    redirect($baseurl . '/pages/system_down.php?show=true');
}

# Set time limit
set_time_limit($php_time_limit);

# Set the storage directory and URL if not already set.
$storagedir ??= dirname(__DIR__) . '/filestore';
$storageurl ??= "{$baseurl}/filestore";

// Reset prepared statement cache before reconnecting
unset($prepared_statement_cache);
sql_connect();

// Set system to read only mode
if (isset($system_read_only) && $system_read_only) {
    $global_permissions_mask = "a,t,c,d,e0,e1,e2,e-1,e-2,i,n,h,q,u,dtu,hdta";
    $global_permissions_mask .= ',ert' . implode(',ert', array_column(get_all_resource_types(), 'ref'));
    $global_permissions = "p";
    $mysql_log_transactions = false;
    $enable_collection_copy = false;
}

# Automatically set a HTTPS URL if running on the SSL port.
if (isset($_SERVER["SERVER_PORT"]) && $_SERVER["SERVER_PORT"] == 443) {
    $baseurl = str_replace("http://", "https://", $baseurl);
}

# Set a base URL part consisting of the part after the server name, i.e. for absolute URLs and cookie paths.
$baseurl = str_replace(" ", "%20", $baseurl);
$bs = explode("/", $baseurl);
$bs = array_slice($bs, 3);
$baseurl_short = "/" . join("/", $bs) . (count($bs) > 0 ? "/" : "");

# statistics
$querycount = 0;
$querytime = 0;
$querylog = array();

# -----------LANGUAGES AND PLUGINS-------------------------------
$legacy_plugins = $plugins; # Make a copy of plugins activated via config.php
# Check that manually (via config.php) activated plugins are included in the plugins table.
foreach ($plugins as $plugin_name) {
    if (
        $plugin_name != ''
        && ps_value("SELECT inst_version AS value FROM plugins WHERE name=?", array("s",$plugin_name), '', "plugins") == ''
    ) {
            # Installed plugin isn't marked as installed in the DB.  Update it now.
            # Check if there's a plugin.yaml file to get version and author info.
            $p_y = get_plugin_yaml($plugin_name, false);
            # Write what information we have to the plugin DB.
            ps_query(
                "REPLACE plugins(inst_version, author, descrip, name, info_url, update_url, config_url, priority, disable_group_select, title, icon) VALUES (?,?,?,?,?,?,?,?,?,?,?)",
                array
                    (
                    "s",$p_y['version'],
                    "s",$p_y['author'],
                    "s",$p_y['desc'],
                    "s",$plugin_name,
                    "s",$p_y['info_url'],
                    "s",$p_y['update_url'],
                    "s",$p_y['config_url'],
                    "s",$p_y['default_priority'],
                    "s",$p_y['disable_group_select'],
                    "s",$p_y['title'],
                    "s",$p_y['icon']
                )
            );
            clear_query_cache("plugins");
    }
}
# Need verbatim queries for this query
$active_plugins = get_active_plugins();

$active_yaml = array();
$plugins = array();
foreach ($active_plugins as $plugin) {
    # Check group access && YAML, only enable for global access at this point
    $py = get_plugin_yaml($plugin["name"], false);
    array_push($active_yaml, $py);
    if ($py['disable_group_select'] || $plugin['enabled_groups'] == '') {
        # Add to the plugins array if not already present which is what we are working with
        $plugins[] = $plugin['name'];
    }
}

for ($n = count($active_plugins) - 1; $n >= 0; $n--) {
    $plugin = $active_plugins[$n];
    # Check group access && YAML, only enable for global access at this point
    $py = get_plugin_yaml($plugin["name"], false);
    if ($py['disable_group_select'] || $plugin['enabled_groups'] == '') {
        include_plugin_config($plugin['name'], $plugin['config'], $plugin['config_json']);
    }
}

// Load system wide config options from database and then store them to distinguish between the system wide and user preference
process_config_options(array());
$system_wide_config_options = get_defined_vars();

# Include the appropriate language file
$pagename = safe_file_name(str_replace(".php", "", pagename()));

// Allow plugins to set $language from config as we cannot run hooks at this point
if (!isset($language)) {
    $language = setLanguage();
}

# Fix due to rename of US English language file
if (isset($language) && $language == "us") {
    $language = "en-US";
}

# Always include the english pack (in case items have not yet been translated)
include __DIR__ . "/../languages/en.php";
if ($language != "en") {
    if (substr($language, 2, 1) != '-') {
        $language = substr($language, 0, 2);
    }

    $use_error_exception_cache = $GLOBALS["use_error_exception"] ?? false;
    $GLOBALS["use_error_exception"] = true;
    try {
        include __DIR__ . "/../languages/" . safe_file_name($language) . ".php";
    } catch (Throwable $e) {
        debug("Unable to include language file $language.php");
    }
    $GLOBALS["use_error_exception"] = $use_error_exception_cache;
}

# Register all plugins
for ($n = 0; $n < count($plugins); $n++) {
    if (!isset($plugins[$n])) {
        continue;
    }
    register_plugin($plugins[$n]);
}

# Register their languages in reverse order
for ($n = count($plugins) - 1; $n >= 0; $n--) {
    if (!isset($plugins[$n])) {
        continue;
    }
    register_plugin_language($plugins[$n]);
}

global $suppress_headers;
# Set character set.
if (($pagename != "download") && ($pagename != "graph") && !$suppress_headers) {
    header("Content-Type: text/html; charset=UTF-8");
} // Make sure we're using UTF-8.
#------------------------------------------------------

# ----------------------------------------------------------------------------------------------------------------------
# Basic CORS and CSRF protection
#
if (($iiif_enabled || hook('directdownloadaccess')) && $pagename == "download") {
    // Required as direct links to media files may be served through download.php
    // and may fail without the Access-Control-Allow-Origin header being set
    $CORS_whitelist[] = $_SERVER['HTTP_ORIGIN'] ?? ($_SERVER['HTTP_REFERER'] ?? "");
}
if ($CSRF_enabled && PHP_SAPI != 'cli' && !$suppress_headers && !in_array($pagename, $CSRF_exempt_pages)) {
    /*
    Based on OWASP: General Recommendations For Automated CSRF Defense
    (https://www.owasp.org/index.php/Cross-Site_Request_Forgery_(CSRF)_Prevention_Cheat_Sheet)
    ==================================================================
    # Verifying Same Origin with Standard Headers
    There are two steps to this check:
    1. Determining the origin the request is coming from (source origin)
    2. Determining the origin the request is going to (target origin)

    # What to do when Both Origin and Referer Headers Aren't Present
    If neither of these headers is present, which should be VERY rare, you can either accept or block the request.
    We recommend blocking, particularly if you aren't using a random CSRF token as your second check. You might want to
    log when this happens for a while and if you basically never see it, start blocking such requests.

    # Verifying the Two Origins Match
    Once you've identified the source origin (from either the Origin or Referer header), and you've determined the target
    origin, however you choose to do so, then you can simply compare the two values and if they don't match you know you
    have a cross-origin request.
    */
    $CSRF_source_origin = '';
    $CSRF_target_origin = parse_url($baseurl, PHP_URL_SCHEME) . '://' . parse_url($baseurl, PHP_URL_HOST);
    $CORS_whitelist     = array_merge(array($CSRF_target_origin), $CORS_whitelist);

    // Determining the origin the request is coming from (source origin)
    if (isset($_SERVER['HTTP_ORIGIN'])) {
        $CSRF_source_origin = $_SERVER['HTTP_ORIGIN'];
    }

    if ($CSRF_source_origin === '') {
        debug('WARNING: Automated CSRF protection could not detect "Origin" or "Referer" headers in the request!');
        debug("CSRF: Logging attempted request: {$_SERVER['REQUEST_URI']}");

        // If source origin cannot be obtained, set to base URL. The reason we can do this is because we have a second
        // check on the CSRF Token, so if this is a malicious request, the CSRF Token validation will fail.
        // This can also be a genuine request when users go to ResourceSpace straight to login/ home page.
        $CSRF_source_origin = $baseurl;
    }

    $CSRF_source_origin = parse_url($CSRF_source_origin, PHP_URL_SCHEME) . '://' . parse_url($CSRF_source_origin, PHP_URL_HOST);

    debug("CSRF: \$CSRF_source_origin = {$CSRF_source_origin}");
    debug("CSRF: \$CSRF_target_origin = {$CSRF_target_origin}");

    // Whitelist match?
    $cors_is_origin_allowed=cors_is_origin_allowed($CSRF_source_origin, $CORS_whitelist);

    // Verifying the Two Origins Match
    if (
        !hook('modified_cors_process')
        && $CSRF_source_origin !== $CSRF_target_origin && !$cors_is_origin_allowed
    ) {
        debug("CSRF: Cross-origin request detected and not white listed!");
        debug("CSRF: Logging attempted request: {$_SERVER['REQUEST_URI']}");

        http_response_code(403);
        exit();
    }

    // Add CORS headers.
    if ($cors_is_origin_allowed) {
        debug("CORS: Origin: {$CSRF_source_origin}");
        debug("CORS: Access-Control-Allow-Origin: {$CSRF_source_origin}");

        header("Origin: {$CSRF_target_origin}");
        header("Access-Control-Allow-Origin: {$CSRF_source_origin}");
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
        header("Access-Control-Allow-Headers: Authorization, Content-Type");

        // Handle preflight requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }
    header('Vary: Origin');
}
#
# End of basic CORS and automated CSRF protection
# ----------------------------------------------------------------------------------------------------------------------

set_watermark_image();

// Facial recognition setup
if ($facial_recognition) {
    include_once __DIR__ . '/facial_recognition_functions.php';
    $facial_recognition_active = initFacialRecognition();
} else {
    $facial_recognition_active = false;
}
if (!$disable_geocoding) {
    include_once __DIR__ . '/map_functions.php';
}

# Pre-load all text for this page.
global $site_text;
lang_load_site_text($lang, $pagename, $language);

# Blank the header insert
$headerinsert = "";

# Load the sysvars into an array. Useful so we can check migration status etc.
# Needs to be actioned before the 'initialise' hook or plugins can't use get_sysvar()
$systemvars = ps_query("SELECT name, value FROM sysvars", array(), "sysvars");
$sysvars = array();
foreach ($systemvars as $systemvar) {
    $sysvars[$systemvar["name"]] = $systemvar["value"];
}

# Initialise hook for plugins
hook("initialise");

# Load the language specific stemming algorithm, if one exists
$stemming_file = __DIR__ . "/../lib/stemming/" . safe_file_name($defaultlanguage) . ".php"; # Important - use the system default language NOT the user selected language, because the stemmer must use the system defaults when indexing for all users.
if (file_exists($stemming_file)) {
    include_once $stemming_file;
}

# Global hook cache and related hits counter
$hook_cache = array();
$hook_cache_hits = 0;

# Build array of valid upload paths
$valid_upload_paths = $valid_upload_paths ?? [];
$valid_upload_paths[] = $storagedir;
if (!empty($syncdir)) {
    $valid_upload_paths[] = $syncdir;
}
if (!empty($batch_replace_local_folder)) {
    $valid_upload_paths[] = $batch_replace_local_folder;
}
if (isset($tempdir)) {
    $valid_upload_paths[] = $tempdir;
}

// IMPORTANT: make sure the upgrade.php is the last line in this file
include_once __DIR__ . '/../upgrade/upgrade.php';
