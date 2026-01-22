<?php

/**
 * Initial setup page.
 */

include_once '../include/file_functions.php';
include_once '../include/definitions.php';
include_once '../include/general_functions.php';
include_once '../include/user_functions.php';
include_once '../include/login_functions.php';
include_once '../include/render_functions.php';

/**
 * Santitizes input from a given request key.
 *
 * @param string $key _REQUEST key to sanitize and return
 * @return string Santized _REQUEST key.
 **/
function get_post($key)
{
    return isset($_REQUEST[$key]) ? filter_var($_REQUEST[$key], FILTER_SANITIZE_FULL_SPECIAL_CHARS) : "";
}

/**
 * Returns true if a given $_REQUEST key is set.
 *
 * @param string $key _REQUEST key to test.
 * @return bool
 */
function get_post_bool($key)
{
    return isset($_REQUEST[$key]);
}

/**
 * Trims whitespace and trailing slash.
 *
 * @param string $data
 * @return string
 */
function sslash($data)
{
    return rtrim($data, '/');
}

/**
 * Opens an HTTP request to a host to determine if the url is reachable.
 *
 * Returns true if url is reachable.
 *
 * @param string $url
 * @return bool
 */
function url_exists($url)
{
    $parsed_url = parse_url($url);
    $host = isset($parsed_url['host']) ? $parsed_url['host'] : "localhost";
    $path = isset($parsed_url['path']) ? $parsed_url['path'] : "";
    $port = isset($parsed_url['port']) ? $parsed_url['port'] : "80";
    $scheme = isset($parsed_url['scheme']) ? $parsed_url['scheme'] : "http";

    if (empty($path)) {
        $path = "/";
    }

    $hostprefix = "";

    if ($scheme == "https") {
        $port = 443;
        $hostprefix = "ssl://";
    } elseif (!isset($port) || $port == 0) {
        $port = 80;
    }

    // Build HTTP 1.1 request header.
    $headers =  "GET $path HTTP/1.1\r\n" .
                "Host: $host\r\n" .
                "User-Agent: RS-Installation/1.0\r\n\r\n";
    $fp = fsockopen($hostprefix . $host, $port, $errno, $errmsg, 5); //5 second timeout.  Assume that if we can't open the socket connection quickly the host or port are probably wrong.

    if (!$fp) {
        return false;
    }

    fwrite($fp, $headers);

    while (!feof($fp)) {
        $resp = fgets($fp, 4096);

        if (strstr($resp, 'HTTP/1.')) {
            fclose($fp);
            $tmp = explode(' ', $resp);
            $response_code = $tmp[1];
            return $response_code == 200;
        }
    }

    fclose($fp);
    return false;
}

/**
 * Sets the language to be used.
 *
 * @param string $defaultlanguage
 * @return array
 */
function set_language($defaultlanguage)
{
    global $languages;
    global $storagedir, $applicationname, $homeanim_folder; # Used in the language files.
    $defaultlanguage = safe_file_name($defaultlanguage);

    if (file_exists("../languages/en.php")) {
        include "../languages/en.php";
        /** @var array $lang */
    }

    if (
        $defaultlanguage == ''
        && isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) //See if we can auto-detect the most likely language.  The user can override this.
    ) {
            $httplanguage = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
        if (array_key_exists($httplanguage[0], $languages)) {
            $defaultlanguage = $httplanguage[0];
        }
    }

    if (
        $defaultlanguage != 'en'
        && file_exists("../languages/" . $defaultlanguage . ".php")
    ) {
            include "../languages/" . $defaultlanguage . ".php";
            /** @var array $lang */
    }

    return $lang;
}

//Development Mode:  Set to true to change the config.php check to devel.config.php and output to devel.config.php instead.  Also displays the config file output in a div at the bottom of the page.
$develmode = false;
if ($develmode) {
    $outputfile = '../include/devel.config.php';
} else {
    $outputfile = '../include/config.php';
}

// Define some vars to prevent warnings (quick fix)
$configstoragelocations = false;
$storageurl = "";
$storagedir = ""; # This variable is used in the language files.

include '../include/config.default.php';
include '../include/config.deprecated.php';
$defaultlanguage = get_post('defaultlanguage');
$lang = set_language($defaultlanguage);
$google_vision_enable = get_post_bool('google_vision_enable');

/* Process AJAX request to check password */
if (get_post_bool('ajax')) {
    $response['success'] = false;
    $response['error']   = '';

    $admin_password             = html_entity_decode(get_post('admin_password'));
    $password_validation_result = check_password($admin_password);

    if ('' !== $admin_password && true === $password_validation_result) {
        $response['success'] = true;
    } elseif ('' !== $admin_password && is_string($password_validation_result) && '' !== $password_validation_result) {
        $response['error'] = $password_validation_result;
    }

    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}
?>

<html>
    <head>
        <title><?php echo escape($lang["setup-rs_initial_configuration"]); ?></title>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <link href="../css/global.css?csr=5" rel="stylesheet" type="text/css" /> 
        <link href="../css/light.css?csr=5" rel="stylesheet" type="text/css" /> 
        <script src="..<?php echo $jquery_path; ?>"></script>
        <!--- Lucide for icons-->
        <link rel="stylesheet" href="../lib/lucide/lucide.css">
        <script> 
            $(document).ready(function() {
                $('p.iteminfo').hide();
                $('.starthidden').hide();
                $('#tabs div.tabs').hide();
                $('#tabs div:first').show();
                $('#tabs ul li:first').addClass('active');

                $('#tabs ul li a').click(function() {
                    $('#tabs ul li').removeClass('active'); 
                    $(this).parent().addClass('active'); 
                    var currentTab = $(this).attr('href');
                    $("#tabs div.tabs:visible").slideUp("slow",function() {
                        $(currentTab).slideDown("slow"); 
                    });
                    return false;       
                });

                $('#configstoragelocations').each(function() {
                    if (this.checked != true){
                        $('#storageurl').prop("disabled",true);
                        $('#storagedir').prop("disabled",true);
                    } else {
                        $('#remstorageoptions').show();
                    }
                });

                $('#configstoragelocations').click(function() {
                    if (this.checked == true) {
                        $('#storageurl').removeAttr("disabled");
                        $('#storagedir').removeAttr('disabled');
                        $('#remstorageoptions').slideDown("slow");
                    } else{
                        $('#storageurl').prop("disabled",true);
                        $('#storagedir').prop("disabled",true);
                        $('#remstorageoptions').slideUp("slow");
                    }
                });

                $('p.iteminfo').click(function() {
                    $('p.iteminfo').hide("slow");
                });

                $('.mysqlconn').keyup(function(event) {
                    $('#al-testconn').fadeIn("fast", test_db(event.target));
                });

                // Check admin password security requirements/ policy -- client side
                $('#admin_password').keyup(function() {
                    $('#admin_test').fadeIn('slow', function() {
                        var post_url  = 'setup.php';
                        var post_data = {
                            ajax: true,
                            admin_password: $('#admin_password').val()
                        };

                        $.post(post_url, post_data, function(response) {
                            $('#admin_password').removeClass('ok');
                            $('#admin_password').addClass('warn');

                            if (response.success === true) {
                                $('#admin_password').addClass('ok');
                                $('#admin_password_error').hide();
                            } else if(response.success === false && response.error !== '') {
                                $('#admin_password_error').text(response.error);
                                $('#admin_password_error').show();
                            }

                            $('#admin_test').hide();

                        }, 'json');
                    });
                });

                $('a.iflink ').click(function() {
                    $('p.iteminfo').hide("slow");
                    var currentItemInfo = $(this).attr('href');
                    $(currentItemInfo).show("fast");
                    return false;
                });

                $('#mysqlserver').keyup();
            });

            function test_db(targetEl) {
                var db_user = $('#mysqlusername');
                var db_pass = $('#mysqlpassword');

                if ($(targetEl).data("connection_mode") == "read_only") {
                    db_user = $('#mysql_read_only_username');
                    db_pass = $('#mysql_read_only_password');
                }

                $.ajax({
                    url: "dbtest.php",
                    async: true,
                    dataType: "text",
                    data: {
                        mysqlserver: $('#mysqlserver').val(),
                        mysqlusername: db_user.val(),
                        mysqlpassword: db_pass.val(),
                        mysqldb: $('#mysqldb').val()
                    },
                    success: function(data,type){
                        if (data==200) {
                            $('#mysqlserver').removeClass('warn');
                            db_user.removeClass('warn');
                            db_pass.removeClass('warn');
                            $('#mysqldb').addClass('ok');
                            $('#mysqlserver').addClass('ok');
                            db_user.addClass('ok');
                            db_pass.addClass('ok');
                            $('#mysqldb').removeClass('warn');
                        } else if (data==201) {
                            $('#mysqlserver').removeClass('warn');
                            db_user.removeClass('ok');
                            db_pass.removeClass('ok');
                            $('#mysqldb').removeClass('ok');
                            $('#mysqldb').removeClass('warn');
                            $('#mysqlserver').addClass('ok');
                            db_user.addClass('warn');
                            db_pass.addClass('warn');
                        } else if (data==203) {
                            $('#mysqlserver').removeClass('warn');
                            db_user.removeClass('warn');
                            db_pass.removeClass('warn');
                            $('#mysqldb').removeClass('ok');
                            $('#mysqldb').addClass('warn');
                            $('#mysqlserver').addClass('ok');
                            db_user.addClass('ok');
                            db_pass.addClass('ok');
                        } else {
                            $('#mysqlserver').removeClass('ok');
                            db_user.removeClass('ok');
                            db_pass.removeClass('ok');
                            $('#mysqldb').removeClass('ok');
                            $('#mysqldb').removeClass('warn');
                            $('#mysqlserver').addClass('warn');
                            db_user.removeClass('warn');
                            db_pass.removeClass('warn');
                        }

                        $('#al-testconn').hide();
                    },
                    error: function() {
                        $('#mysqlserver').addClass('warn');
                        db_user.addClass('warn');
                        db_pass.addClass('warn');
                        $('#al-testconn').hide();
                    }
                });
            }
        </script> 
        
        <style type="text/css"> 
            #setup-container {overflow: auto; height: 100%;}
            #wrapper{ margin:0 auto;width:600px; }
            #intro {  margin-bottom: 40px; font-size:100%; background: #F7F7F7; text-align: left; padding: 40px; }
            #introbottom { padding: 10px; clear: both; text-align:center;}
            #preconfig {  float: right;background: #F1F1F1; padding: 25px;border-radius: 10px;box-shadow: #d7d7d7 1px 1px 9px;}
            #preconfig h2 { border-bottom: 1px solid #ccc;  width: 100%;}
            #preconfig p { font-size:110%; padding:0; margin:0; margin-top: 5px;}
            #preconfig p.failure{ color: #f00; font-weight: bold; }
            #structural_plugins {margin-bottom: 40px;font-size: 100%;background: #F7F7F7;text-align: left;padding: 20px;}
            #structural_plugins h2{margin-bottom: 10px;}
            .templateitem{padding:10px; padding-left:40px;}
            .templateitem label{width: 220px;display: block;float: left;}
            .templateitem input{display: block;float: left;}
            .templateitem .desc{padding:0 10px;display: block;float: left;clear:left; padding-left:28px;}
            .templateitem a.moreinfo{color:#72A939;padding-left: 0;}
            .structurepluginradio{margin-right:10px;}
            .settings { background: #F7F7F7; clear: both; padding: 20px; text-align: left;width:80%;margin:0 auto 0 auto; border-radius: 10px;}
            p.iteminfo{ background: #e3fefa; width: 60%; color: #000; padding: 4px; margin: 10px; clear:both; }
            strong { padding:0 5px; color: #F00; font-weight: bold; }
            a.iflink { color: #FFF; padding: 4px; margin-left: 4px; border-radius: 4px; background-color: #2E99E6;} 
            #defaultlanguage{font-size: 1em;}
            #baseurl, #admin_email, #emailfrom {border: 1px solid rgba(0,0,0,0.25);}
            input.warn { border: 2px solid #f00; }
            input.ok{ border:2px solid #0f0; }
            input#submit { margin: 30px; font-size:120%; }
            div.configitem { padding-top:10px; padding-left:40px; padding-bottom: 5px; border-bottom: 1px solid #555555; clear:left;}
            label { padding-right: 10px; width: 30%; font-weight: bold; }
            div.configitem label { width:400px; display:block; float:left;}
            div.advsection{ margin-bottom: 20px; }
            .ajloadicon { padding-left:4px; }
            h2#dbaseconfig{  min-height: 32px;}
            .erroritem{ background: #fcc; border: 2px solid #f00; color: #000; padding: 10px; margin: 7px; font-weight:bold;}
            .erroritem.p { margin: 0; padding:0px;padding-bottom: 5px;}
            .warnitem{ background: #FFFFB3; border: 2px solid #FFFF33; color: #000; padding: 10px; margin: 7px; font-weight:bold;}
            .warnitem.p { margin: 0; padding:0px;padding-bottom: 5px;}
            #errorheader { font-size: 110%; margin-bottom: 20px; background: #fcc; border: 1px solid #f00; color: #000; padding: 10px; font-weight: bold; }
            #configoutput { background: #777; color: #fff; text-align: left; padding: 20px; }
            #warnheader { font-size: 110%; margin-bottom: 20px; background: #FFFFB3; border: 1px solid #FFFF33; color: #000; padding: 10px; font-weight: bold; }
            .language {clear:both; text-align:center; padding:20px;}
        </style> 
    </head>
    <body class="SlimHeader">
        <div id="setup-container">
            <div id="Header" style="height: 40px;">
                <div class="HeaderImgLink">
                    <img alt="" src="../gfx/titles/title-black.svg" id="HeaderImg" />
                </div>
                <div id="HeaderNav1" class="HorizontalNav ">
                    <ul></ul>
                </div>
                <div id="HeaderNav2" class="HorizontalNav HorizontalWhiteNav">
                    <ul></ul>
                </div> 
                <div class="clearer"></div>
            </div>

            <?php
            // Check if config file already exists and die with an error if it does.
            if (file_exists($outputfile) && filesize($outputfile) > 0) {
                ?>
                <div id="errorheader"><?php echo strip_tags_and_attributes($lang["setup-alreadyconfigured"]);?></div> 
                </body>
                </html>
                <?php
                die(0);
            }

            if (!(isset($_REQUEST['submit']))) {
                // No Form Submission, lets setup some defaults
                if (!isset($storagedir) | $storagedir == "") {
                    $storagedir = __DIR__ . "/../filestore";
                    # Updates $lang with $storagedir which is used in some strings.
                    $lang = set_language($defaultlanguage); 
                }

                if (isset($_SERVER['HTTP_HOST'])) {
                    # Set HTTPS URL if necessary
                    $urlprefix = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
                    $port = (isset($_SERVER["SERVER_PORT"]) && !in_array($_SERVER["SERVER_PORT"], array(80,443))) ? (":" . $_SERVER["SERVER_PORT"]) : "";
                    $baseurl = $urlprefix . "://" . $_SERVER['HTTP_HOST'] . substr($_SERVER['PHP_SELF'], 0, strlen($_SERVER['PHP_SELF']) - 16) . $port;
                } else {
                    // Set the baseurl to the machine hostname.
                    $baseurl = 'http://' . php_uname('n'); 
                }

                // Setup search paths (Currently only Linux/Mac OS X)
                $os = php_uname('s');
                if ($os == 'Linux' || $os == "Darwin") {
                    $search_paths[] = '/usr/bin';
                    $search_paths[] = '/sw/bin';
                    $search_paths[] = '/usr/share/bin';
                    $search_paths[] = '/usr/local/bin';
                    $search_paths[] = '/opt/local/bin';
                } elseif (stristr($os, 'windows')) {
                    // Enable config_windows if we're on windows
                    $config_windows = true;
                }

                if (isset($search_paths)) {
                    $imagemagick_path = "";
                    $ghostscript_path = "";
                    $ffmpeg_path = "";
                    $exiftool_path = "";
                    $pdftotext_path = "";

                    foreach ($search_paths as $path) {
                        if (file_exists($path . '/convert')) {
                            $imagemagick_path = $path;
                        }
                        if (file_exists($path . '/gs')) {
                            $ghostscript_path = $path;
                        }
                        if (file_exists($path . '/ffmpeg') || file_exists($path . '/avconv')) {
                            $ffmpeg_path = $path;
                        }
                        if (file_exists($path . '/exiftool')) {
                            $exiftool_path = $path;
                        }
                        if (file_exists($path . '/pdftotext')) {
                            $pdftotext_path = $path;
                        }
                    }
                } else {
                    $imagemagick_path = "";
                    $ghostscript_path = "";
                    $ffmpeg_path      = "";
                    $exiftool_path    = "";
                    $pdftotext_path   = "";
                    $mysql_bin_path   = "";
                }

                $admin_fullname = 'Admin user';
                $admin_email    = '';
                $admin_username = 'admin';
                $admin_password = '';

                $db_connection_modes = array(
                    "read_write" => array(
                        "mysql_username" => "",
                        "mysql_password" => "",
                    ),
                    "read_only" => array(
                        "mysql_username" => "",
                        "mysql_password" => "",
                    )
                );
            } else {
                // Form was submitted, lets do it!
                // Generate config.php Header
                // Note: The opening php tag is missing and is added when the file is written.
                // This allows the config to be displayed in the bottom div when in development mode.
                $config_windows = get_post_bool('config_windows');
                $exe_ext = $config_windows ? '.exe' : '';
                $config_output = "";
                $config_output .= "###############################\r\n";
                $config_output .= "## ResourceSpace\r\n";
                $config_output .= "## Local Configuration Script\r\n";
                $config_output .= "###############################\r\n\r\n";
                $config_output .= "# All custom settings should be entered in this file.\r\n";
                $config_output .= "# Options may be copied from config.default.php and configured here.\r\n\r\n";

                // Structural plugin
                $structural_plugin = get_post('structureplugin');
                if (!empty($structural_plugin)) {
                    $config_output .= "\r\n# Initial Structural Plugin used: " . $structural_plugin . "\r\n\r\n\r\n";
                }

                //Grab MySQL settings
                $mysql_server = get_post('mysql_server');
                $mysql_db = get_post('mysql_db');

                $db_connection_modes = array(
                    "read_write" => array(
                        "mysql_username" => trim(get_post("mysql_username")),
                        "mysql_password" => trim(get_post("mysql_password")),
                    ),
                    "read_only" => array(
                        "mysql_username" => trim(get_post("read_only_db_username")),
                        "mysql_password" => trim(get_post("read_only_db_password")),
                    ),
                );

                $mysql_config_output = "";

                foreach ($db_connection_modes as $db_connection_mode => $db_credentials) {
                    $mysql_username = $db_credentials["mysql_username"];
                    $mysql_password = $db_credentials["mysql_password"];

                    // read-only credentials are optional
                    if ($db_connection_mode == "read_only" && ($mysql_username == "" || $mysql_password == "")) {
                        continue;
                    }

                    // Check connection
                    $mysqli_connection = mysqli_connect($mysql_server, $mysql_username, $mysql_password);
                    if ($mysqli_connection === false) {
                        switch (mysqli_errno($mysqli_connection)) {
                            case 1045:  //User login failure.
                                $errors['databaselogin'] = true;
                                break;
                            default: //Must be a server problem.
                                $errors['databaseserver'] = true;
                                break;
                        }
                    }

                    // Check MySQL version
                    $mysqlversion = mysqli_get_server_info($mysqli_connection);
                    $mysqlversion_parts = explode(".", $mysqlversion);
                    $mysqlversion_majorminor = floatval($mysqlversion_parts[0] . (isset($mysqlversion_parts[1]) ? "." . $mysqlversion_parts[1] : ""));

                    if ($mysqlversion_majorminor < 5) {
                        $errors['databaseversion'] = true;
                        break;
                    }

                    // Check DB access
                    if (mysqli_select_db($mysqli_connection, $mysql_db) === false) {
                        $errors['databasedb'] = true;
                        break;
                    }

                    // Check DB permissions
                    if ($db_connection_mode == "read_write") {
                        if (mysqli_query($mysqli_connection, "CREATE table configtest(test varchar(30))")) {
                            mysqli_query($mysqli_connection, "DROP table configtest");
                        } else {
                            $errors['databaseperms'] = true;
                            break;
                        }
                    }

                    if (isset($errors)) {
                        $errors['database'] = mysqli_error($mysqli_connection);
                        break;
                    }

                    $config_var_username = ($db_connection_mode == "read_only" ? "read_only_db_username" : "mysql_username");
                    $config_var_password = ($db_connection_mode == "read_only" ? "read_only_db_password" : "mysql_password");

                    $mysql_config_output .= "\${$config_var_username} = '{$mysql_username}';\r\n";
                    $mysql_config_output .= "\${$config_var_password} = '{$mysql_password}';\r\n";
                }

                if (!isset($errors)) {
                    $config_output .= "# MySQL database settings\r\n";
                    $config_output .= "\$mysql_server = '$mysql_server';\r\n";
                    $config_output .= $mysql_config_output;
                    $config_output .= "\$mysql_db = '$mysql_db';\r\n";
                    $config_output .= "\r\n";
                }

                // Check MySQL bin path (not required)
                $mysql_bin_path = sslash(get_post('mysql_bin_path'));

                if ((isset($mysql_bin_path)) && ($mysql_bin_path != '')) {
                    if (stripos($mysql_bin_path . '/mysqldump' . $exe_ext, 'phar://') !== false) {
                        exit($lang["setup-err_phar_injection"]);
                    }
                    if (!file_exists($mysql_bin_path . '/mysqldump' . $exe_ext)) {
                        $errors['mysqlbinpath'] = true;
                    } else {
                        $config_output .= "\$mysql_bin_path = '$mysql_bin_path';\r\n\r\n";
                    }
                }

                // Check baseurl (required)
                $baseurl = sslash(get_post('baseurl'));

                # In certain PHP versions there is a bug in filter_var using FILTER_VALIDATE_URL causing correct URLs containing a hyphen to fail.
                if (filter_var("http://www.filter-test.com", FILTER_VALIDATE_URL)) {
                    # The filter is working.
                    $filterresult = filter_var($baseurl, FILTER_VALIDATE_URL);
                } else {
                    # The filter is not working, use the hostname of the $baseurl and replace the problematic characters.
                    $testbaseurl = str_replace(
                        parse_url($baseurl, PHP_URL_HOST),
                        str_replace(
                            array("_", "-"),
                            array("^", "x"), # _ is not allowed for hostname, - is allowed
                            parse_url($baseurl, PHP_URL_HOST)
                        ),
                        $baseurl
                    );
                    $filterresult = filter_var($testbaseurl, FILTER_VALIDATE_URL);
                }

                if ((isset($baseurl)) && ($baseurl != '') && ($baseurl != 'http://my.site/resourcespace') && ($filterresult)) {
                    //Check that the base url seems correct by attempting to fetch the license file
                    if (url_exists($baseurl . '/license.txt')) {
                        $config_output .= "# Base URL of the installation\r\n";
                        $config_output .= "\$baseurl = '$baseurl';\r\n\r\n";
                    } else { //Under certain circumstances this test may fail, but the URL is still correct, so warn the user.
                        $warnings['baseurlverify'] = true;
                    }
                } else {
                    $errors['baseurl'] = true;
                }

                $admin_fullname = get_post('admin_fullname');
                $admin_email    = get_post('admin_email');
                $admin_username = get_post('admin_username');
                $admin_password = html_entity_decode(get_post('admin_password'));

                if ('' === trim($admin_fullname)) {
                    $errors['admin_fullname'] = true;
                }

                if ('' === trim($admin_email) || ('' !== trim($admin_email) && !filter_var($admin_email, FILTER_VALIDATE_EMAIL))) {
                    $errors['admin_email'] = true;
                } else {
                    // Email_notify is not used much now so we default it to the admin e-mail address.
                    $config_output .= "# Email settings\r\n";
                    $config_output .= "\$email_notify = '$admin_email';\r\n";
                }

                // Check password
                $password_validation_result = check_password($admin_password);
                if ('' === $admin_password) {
                    $errors['admin_password'] = 'Super Admin password cannot be empty!';
                } elseif ('' !== $admin_password && is_string($password_validation_result) && '' !== $password_validation_result) {
                    $errors['admin_password'] = $password_validation_result;
                }

                // Verify email addresses are valid
                $email_from = get_post('email_from');
                if ('' != $email_from) {
                    if (filter_var($email_from, FILTER_VALIDATE_EMAIL)) {
                        $config_output .= "\$email_from = '$email_from';\r\n";
                    } else {
                        $errors['email_from'] = true;
                    }
                } else {
                    $errors['email_from'] = true;
                }

                // Set random keys. These used to be requested on the setup form but there was no reason to ask the user for these.
                $scramble_key = generateSecureKey(64);

                $config_output .= "# Secure keys\r\n";
                $config_output .= "\$scramble_key = '{$scramble_key}';\r\n";
                $config_output .= "\$api_scramble_key = '" . generateSecureKey(64) . "';\r\n\r\n";
                $config_output .= "# Paths\r\n";

                //Verify paths actually point to a useable binary
                $imagemagick_path = sslash(get_post('imagemagick_path'));
                $ghostscript_path = sslash(get_post('ghostscript_path'));
                $ffmpeg_path = sslash(get_post('ffmpeg_path'));
                $exiftool_path = sslash(get_post('exiftool_path'));
                $pdftotext_path = sslash(get_post('pdftotext_path'));

                if ($imagemagick_path != '') {
                    if (stripos($imagemagick_path . '/convert' . $exe_ext, 'phar://') !== false) {
                        exit($lang["setup-err_phar_injection"]);
                    }
                    if (!file_exists($imagemagick_path . '/convert' . $exe_ext)) {
                        $errors['imagemagick_path'] = true;
                    } else {
                        $config_output .= "\$imagemagick_path = '$imagemagick_path';\r\n";
                    }
                }

                if ($ghostscript_path != '') {
                    if (stripos($ghostscript_path . '/gs' . $exe_ext, 'phar://') !== false) {
                        exit($lang["setup-err_phar_injection"]);
                    }
                    if (!file_exists($ghostscript_path . '/gs' . $exe_ext)) {
                        $errors['ghostscript_path'] = true;
                    } else {
                        $config_output .= "\$ghostscript_path = '$ghostscript_path';\r\n";
                    }
                }

                if ($ffmpeg_path != '') {
                    if (
                        stripos($ffmpeg_path . '/ffmpeg' . $exe_ext, 'phar://') !== false ||
                        stripos($ffmpeg_path . '/avconv' . $exe_ext, 'phar://') !== false
                    ) {
                        exit($lang["setup-err_phar_injection"]);
                    }
                    if (
                        !file_exists($ffmpeg_path . '/ffmpeg' . $exe_ext) &&
                        !file_exists($ffmpeg_path . '/avconv' . $exe_ext)
                    ) {
                        $errors['ffmpeg_path'] = true;
                    } else {
                        $config_output .= "\$ffmpeg_path = '$ffmpeg_path';\r\n";
                    }
                }

                if ($exiftool_path != '') {
                    if (stripos($exiftool_path . '/exiftool' . $exe_ext, 'phar://') !== false) {
                        exit($lang["setup-err_phar_injection"]);
                    }
                    if (!file_exists($exiftool_path . '/exiftool' . $exe_ext)) {
                        $errors['exiftool_path'] = true;
                    } else {
                        $config_output .= "\$exiftool_path = '$exiftool_path';\r\n";
                    }
                }

                if ($pdftotext_path != '') {
                    if (stripos($pdftotext_path . '/pdftotext' . $exe_ext, 'phar://') !== false) {
                        exit($lang["setup-err_phar_injection"]);
                    }

                    if (!file_exists($pdftotext_path . '/pdftotext' . $exe_ext)) {
                        $errors['pdftotext_path'] = true;
                    } else {
                        $config_output .= "\$pdftotext_path = '$pdftotext_path';\r\n\r\n";
                    }
                }

                if (isset($_REQUEST['applicationname'])) {
                    $applicationname = get_post('applicationname');
                    $config_output .= "\$applicationname = '$applicationname';\r\n";
                }

                if ($config_windows) {
                    $config_output .= "\$config_windows = true;\r\n";
                }

                if ($defaultlanguage != 'en') {
                    $config_output .= "\$defaultlanguage = '$defaultlanguage';\r\n";
                }

                $storagedir = __DIR__ . "/../filestore";
                $configstoragelocations = false;

                $use_smtp = get_post('use_smtp');

                if ($use_smtp) {
                    $smtp_secure = get_post('smtp_secure');
                    $smtp_host = get_post('smtp_host');
                    $smtp_port = get_post('smtp_port');
                    $smtp_auth = get_post('smtp_auth');
                    $smtp_username = get_post('smtp_username');
                    $smtp_password = get_post('smtp_password');
                    $config_output .= "#SMTP settings\r\n";
                    $config_output .= "\$use_smtp = true;\r\n";
                    $config_output .= "\$use_phpmailer = true;\r\n";
                    $config_output .= "\$smtp_secure = '$smtp_secure';\r\n";
                    $config_output .= "\$smtp_host = '$smtp_host';\r\n";
                    $config_output .= "\$smtp_port = $smtp_port;\r\n";

                    if ($smtp_auth) {
                        $config_output .= "\$smtp_auth = true;\r\n";
                        $config_output .= "\$smtp_username = '$smtp_username';\r\n";
                        $config_output .= "\$smtp_password = '$smtp_password';\r\n";
                    }

                    $config_output .= " \r\n";
                }

                // Scramble slideshow folder path
                $homeanim_folder_name = "slideshow";

                if (isset($scramble_key) && $scramble_key != "") {
                    $nonce = generateSecureKey(24);
                    $homeanim_folder_hash = substr(md5("{$nonce}_slideshow_{$scramble_key}"), 0, 15);
                    $homeanim_folder_name = "slideshow_{$homeanim_folder_hash}";
                    $homeanim_folder = "filestore/system/{$homeanim_folder_name}";

                    $config_output .= "\$homeanim_folder = '{$homeanim_folder}';\r\n";
                }

                # Append defaults for new systems.
                $config_output .= file_get_contents(__DIR__ . "/../include/config.new_installs.php");
            }
            
            // Output Section
            if ((isset($_REQUEST['submit'])) && (!isset($errors)) && (!isset($warnings))) {
                // Form submission was a success.  Output the config file and refrain from redisplaying the form.
                $fhandle = fopen($outputfile, 'w') or die("Error opening output file.  (This should never happen, we should have caught this before we got here)");
                fwrite($fhandle, "<?php\r\n" . $config_output); // NOTE: php opening tag is prepended to the output.
                fclose($fhandle);

                // Check database structure now
                $suppress_headers = true;
                include_once '../include/boot.php';
                // Always show detailed errors during setup process.
                $show_detailed_errors = true; 
                check_db_structs();

                // set the current upgrade level to current one specified in definitions.php
                if (!get_sysvar(SYSVAR_CURRENT_UPGRADE_LEVEL)) {
                    set_sysvar(SYSVAR_CURRENT_UPGRADE_LEVEL, SYSTEM_UPGRADE_LEVEL);
                }

                if (!empty($structural_plugin) && !$develmode) {
                    $suppress_headers = true;
                    include_once "../include/boot.php";
                    // BUILD Data from plugin
                    global $mysql_db, $resource_field_column_limit;

                    # Check for path
                    $path = "../plugins/" . $structural_plugin . "/dbstruct/";
                    if (realpath($path) === false || !is_dir($path)) {
                        return trigger_error("Attempted path traversal, path was: '{$path}'");
                    }

                    # Tables first.
                    # Load existing tables list
                    $ts = ps_query("show tables", array(), false, -1, false);
                    $tables = array();

                    for ($n = 0; $n < count($ts); $n++) {
                        $tables[] = $ts[$n]["Tables_in_" . $mysql_db];
                    }

                    $dh = opendir($path);

                    while (($file = readdir($dh)) !== false) {
                        if (substr($file, 0, 5) == "data_") {
                            $table = str_replace(".txt", "", substr($file, 5));
                            ps_query("TRUNCATE $table", array());
                            # Add initial data
                            $data = $file;

                            if (file_exists($path . "/" . $data)) {
                                $f = fopen($path . "/" . $data, "r");
                                while (($row = fgetcsv($f, 5000)) !== false) {
                                    for ($n = 0; $n < count($row); $n++) {
                                        if ($row[$n] == "''") {
                                            $row[$n] = null;
                                        }
                                    }
                                    ps_query("insert into $table values (" . ps_param_insert(count($row)) . ")", array(ps_param_fill($row, "s")), false, -1, false);
                                }
                            }

                            # Check all indices exist
                            # Load existing indexes
                            $existing = ps_query("show index from $table", array(), false, -1, false);

                            $file = str_replace("data_", "index_", $file);
                            if (file_exists($path . "/" . $file)) {
                                $done = array(); # List of indices already processed.
                                $f = fopen($path . "/" . $file, "r");

                                while (($col = fgetcsv($f, 5000)) !== false) {
                                    # Look for this index in the existing indices.
                                    $found = false;
                                    for ($n = 0; $n < count($existing); $n++) {
                                        if ($existing[$n]["Key_name"] == $col[2]) {
                                            $found = true;
                                        }
                                    }

                                    if (!$found && !in_array($col[2], $done)) {
                                        # Add this index.
                                        # Fetch list of columns for this index
                                        $cols = array();
                                        $f2 = fopen($path . "/" . $file, "r");

                                        while (($col2 = fgetcsv($f2, 5000)) !== false) {
                                            if ($col2[2] == $col[2]) {
                                                $cols[] = $col2[4];
                                            }
                                        }

                                        $sql = "create index " . $col[2] . " on $table (" . join(",", $cols) . ")";
                                        ps_query($sql, array(), false, -1, false);
                                        $done[] = $col[2];
                                    }
                                }
                            }
                        }
                    }
                }
                // Sign all field specific php code (macros, value filter etc.) included with the default metadata fields.
                resign_all_code(false, false);

                // Copy slideshow images under filestore in order to avoid
                // overwriting them when doing svn update
                $homeanim_folder = 'gfx/homeanim';

                // Make sure there is a target location
                $to_folder = "{$storagedir}/system/{$homeanim_folder_name}";
                if (!(file_exists($to_folder) && is_dir($to_folder))) {
                    mkdir($to_folder, 0777, true);
                }

                $web_root = dirname(__DIR__);
                $homeanim_folder_path = "{$web_root}/{$homeanim_folder}";

                include_once "{$web_root}/include/slideshow_functions.php";

                $found_files = array();
                $files = new \DirectoryIterator($homeanim_folder_path);
                foreach ($files as $file) {
                    if ($file->isDot() || !$file->isFile()) {
                        continue;
                    }

                    $found_files[] = $file->getFilename();
                }

                // Sort ASC the files before inserting into database
                natsort($found_files);
                $found_files = array_values($found_files);

                foreach ($found_files as $index => $file) {
                    // New installs have login_background enabled
                    $login_show = 0;
                    if ($index == 0) {
                        $login_show = 1;
                    }

                    $filename = pathinfo($file, PATHINFO_FILENAME);

                    $new_slideshow_image = set_slideshow($filename, null, 1, $login_show);

                    $from_file = "{$homeanim_folder_path}/{$file}";
                    $to_file   = "{$to_folder}/{$file}";

                    if (!(file_exists($from_file) && copy($from_file, $to_file))) {
                        trigger_error("Unable to copy image from '{$from_file}' to '{$to_file}' for slideshow #{$new_slideshow_image}");
                    }
                }

                if ($google_vision_enable) {
                    $google_vision_api_key = getval('google_vision_key', '');

                    // Activate and get default config
                    activate_plugin("google_vision");
                    $plugin_config  = get_plugin_config("google_vision");
                    $plugin_config["google_vision_api_key"] = $google_vision_api_key;
                    set_plugin_config("google_vision", $plugin_config);
                }

                // Create user
                // Set a password
                $password_hash = rs_password_hash("RS{$admin_username}{$admin_password}");

                // Existing user?
                $user_count = ps_value("SELECT count(*) value FROM user WHERE username = ?", array("s", $admin_username), 0);
                if (0 == $user_count) {
                    // No existing matching user. Insert.
                    // Note: First user should always be part of Super Admin, hence user group is set to 3
                    $sql_query = "INSERT INTO user (username, password, fullname, email, usergroup) VALUES (?, ?, ?, ?, 3)";
                    $sql_query_params = array("s", $admin_username, "s", $password_hash, "s", $admin_fullname, "s", $admin_email);
                } else {
                    // Existing user found. Update password. This is a useful mechanism for regaining access when a system is being set up again.
                    $sql_query = "UPDATE user set password = ? where username = ?";
                    $sql_query_params = array("s", $password_hash, "s", $admin_username);
                }

                // Perform the insert / update
                ps_query($sql_query, $sql_query_params);

                ?>
                <div id="intro">
                    <h1><?php echo escape($lang["setup-successheader"]); ?></h1>
                    <p><?php echo escape($lang["setup-successdetails"]); ?></p>
                    <p><?php echo escape($lang["setup-successnextsteps"]); ?></p>
                    <ul>
                        <li><?php echo escape($lang["setup-successremovewrite"]); ?></li>
                        <li><?php echo strip_tags_and_attributes($lang["setup-visitwiki"]); ?></li>
                        <li>
                            <a href="<?php echo $baseurl;?>/login.php"><?php echo escape($lang["setup-login_to"] . " " . $applicationname); ?></a>
                            <ul>
                                <li><?php echo escape($lang["username"] . ': ' . $admin_username); ?></li>
                                <li><?php echo escape($lang["password"] . ': ' . $admin_password); ?></li>
                            </ul>
                        </li>
                    </ul>
                </div>
                <?php
            } else {
                ?>
                <form action="setup.php" method="POST">
                    <?php echo $config_windows ? '<input type="hidden" name="config_windows" value="true"/>' : ''; ?>
                    <div id="intro">
                        <div id="preconfig">
                            <h2><?php echo escape($lang["installationcheck"]); ?></h2>

                            <?php
                            $continue = true;
                            if (version_compare(PHP_VERSION, '8.1.0', '<')) {
                                $result   = $lang["status-fail"] . ": " . str_replace('?', '8.1.0', $lang['shouldbeversion']);
                                $pass     = false;
                                $continue = false;
                            } else {
                                $result = $lang["status-ok"];
                                $pass = true;
                            }
                            ?>

                            <p class="<?php echo $pass ? '' : 'failure'; ?>">
                                <?php echo escape(str_replace("?", "PHP", $lang["softwareversion"]) . ": " . PHP_VERSION) . (!$pass ? '<br />' : ' ') . "(" . escape($result) . ")"; ?>
                            </p>
                            <p>
                                <?php echo escape(str_replace("%phpinifile", php_ini_loaded_file(), $lang["php-config-file"])); ?>
                            </p>

                            <?php
                            if (function_exists('gd_info')) {
                                $gdinfo = gd_info();

                                if (is_array($gdinfo)) {
                                    $version = $gdinfo["GD Version"];
                                    $result = $lang["status-ok"];
                                    $pass = true;
                                }
                            } else {
                                $version = $lang["status-notinstalled"];
                                $result = $lang["status-fail"];
                                $pass = false;
                                $continue = false;
                            }
                            ?>

                            <p class="<?php echo $pass ? '' : 'failure'; ?>">
                                <?php echo escape(str_replace("?", "GD", $lang["softwareversion"]) . ": " . $version) . ($pass != true ? '<br />' : ' ') . "(" . escape($result) . ")"; ?>
                            </p>

                            <?php
                            $memory_limit = ini_get("memory_limit");
                            if (ResolveKB($memory_limit) < (200 * 1024)) {
                                $result = $lang["status-warning"] . ": " . str_replace("?", "200M", $lang["shouldbeormore"]);
                                $pass = false;
                            } else {
                                $result = $lang["status-ok"];
                                $pass = true;
                            }
                            ?>

                            <p class="<?php echo $pass ? '' : 'failure'; ?>">
                                <?php echo escape(str_replace("?", "memory_limit", $lang["phpinivalue"]) . ": " . $memory_limit) . (!$pass ? '<br />' : ' ') . "(" . escape($result) . ")"; ?>
                            </p>

                            <?php
                            $post_max_size = ini_get("post_max_size");
                            if (ResolveKB($post_max_size) < (100 * 1024)) {
                                $result = $lang["status-warning"] . ": " . str_replace("?", "100M", $lang["shouldbeormore"]);
                                $pass = false;
                            } else {
                                $result = $lang["status-ok"];
                                $pass = true;
                            }
                            ?>

                            <p class="<?php echo $pass ? '' : 'failure'; ?>">
                                <?php echo escape(str_replace("?", "post_max_size", $lang["phpinivalue"]) . ": " . $post_max_size) . (!$pass ? '<br />' : ' ') . "(" . escape($result) . ")"; ?>
                            </p>

                            <?php
                            $upload_max_filesize = ini_get("upload_max_filesize");
                            if (ResolveKB($upload_max_filesize) < (100 * 1024)) {
                                $result = $lang["status-warning"] . ": " . str_replace("?", "100M", $lang["shouldbeormore"]);
                                $pass = false;
                            } else {
                                $result = $lang["status-ok"];
                                $pass = true;
                            }
                            ?>

                            <p class="<?php echo $pass ? '' : 'failure'; ?>">
                                <?php echo escape(str_replace("?", "upload_max_filesize", $lang["phpinivalue"]) . ": " . $upload_max_filesize) . (!$pass ? '<br />' : ' ') . "(" . escape($result) . ")"; ?>
                            </p>

                            <?php
                            $success = is_writable('../include');
                            if ($success === false) {
                                $result = $lang["status-fail"] . ": " . $lang["setup-include_not_writable"];
                                $pass = false;
                                $continue = false;
                            } else {
                                $result = $lang["status-ok"];
                                $pass = true;
                            }
                            ?>

                            <p class="<?php echo $pass ? '' : 'failure';?>">
                                <?php echo escape($lang["setup-checkconfigwrite"]) . (!$pass ? '<br />' : ' ') . "(" . escape($result) . ")"; ?>
                            </p>

                            <?php
                            if (!file_exists($storagedir)) {
                                try {
                                    mkdir($storagedir, 0777);
                                } catch (Exception $e) {
                                    // Next check will now fail
                                }
                            }

                            $success = is_writable($storagedir);
                            if ($success === false) {
                                $result = $lang["status-warning"] . ": " . realpath($storagedir) . $lang["nowriteaccesstofilestore"];
                                $pass = false;
                            } else {
                                $result = $lang["status-ok"];
                                $pass = true;
                            }
                            ?>

                            <p class="<?php echo $pass ? '' : 'failure'; ?>">
                                <?php echo escape($lang["setup-checkstoragewrite"]) . (!$pass ? '<br />' : ' ') . "(" . escape($result) . ")"; ?>
                            </p>
                        </div>
                        <h1><?php echo escape($lang["setup-welcome"]); ?></h1>
                        <p><?php echo strip_tags_and_attributes($lang["setup-introtext"]); ?></p>
                        <p><?php echo strip_tags_and_attributes($lang["setup-visitwiki"]); ?></p>
                        <div class="language" style="clear: none;text-align: left;padding: 0px;">
                            <label for="defaultlanguage"><?php echo escape($lang["language"]); ?>:</label>
                            <select id="defaultlanguage" name="defaultlanguage">
                                <?php
                                foreach ($languages as $code => $text) {
                                    echo "<option value=\"$code\"";
                                    if ($code == $defaultlanguage) {
                                        echo ' selected';
                                    }
                                    echo ">$text</option>";
                                }
                                ?>
                            </select>
                            <input type="submit" id="changelanguage" name="changelanguage" value="<?php echo escape($lang["action-changelanguage"]); ?>"/>
                        </div>
                        <div id="introbottom">
                            <?php if ($continue === false) { ?>
                                <strong><?php echo strip_tags_and_attributes($lang["setup-checkerrors"]); ?></strong>
                            <?php } else { ?>
                                <script type="text/javascript">
                                    $(document).ready(function() {
                                        $('#tabs').show();
                                    });
                                </script>
                            <?php } ?>
                        </div>
                    </div>

                    <?php
                    include "../include/plugin_functions.php";
                    $plugins_dir = __DIR__ . "/../plugins/";
                    # Build an array of available plugins.
                    $dirh = opendir($plugins_dir);
                    $plugins_avail = array();

                    while (false !== ($file = readdir($dirh))) {
                        if (is_dir($plugins_dir . $file) && $file[0] != '.') {
                            # Look for a <pluginname>.yaml file.
                            $plugin_yaml = get_plugin_yaml($file, false);

                            if (
                                isset($plugin_yaml["category"])
                                    && $plugin_yaml["category"] == "structural"
                                    && isset($plugin_yaml["info_url"])
                                    && isset($plugin_yaml["setup_desc"])
                                    && isset($plugin_yaml["name"])
                            ) {
                                foreach ($plugin_yaml as $key => $value) {
                                    $plugins_avail[$file][$key] = $value ;
                                }
                            }

                            # Include all plugin language files
                            $langpath = $plugins_dir . $file . "/languages/";

                            if (file_exists($langpath . "en.php")) {
                                include $langpath . "en.php";
                            }

                            if ($defaultlanguage != "en") {
                                if (
                                    substr($defaultlanguage, 2, 1) == '-'
                                    && substr($defaultlanguage, 0, 2) != 'en'
                                    && file_exists($langpath . safe_file_name(substr($defaultlanguage, 0, 2)) .  ".php")
                                ) {
                                    include $langpath . safe_file_name(substr($defaultlanguage, 0, 2)) . ".php";
                                }

                                if (file_exists($langpath . safe_file_name($defaultlanguage) . ".php")) {
                                    include $langpath . safe_file_name($defaultlanguage) . ".php";
                                }
                            }
                        }
                    }

                    closedir($dirh);

                    if (!empty($plugins_avail)) {
                        ksort($plugins_avail);
                        ?>
                        <div id="structural_plugins">
                            <h2><?php echo escape($lang["setup-structuralplugins"]); ?></h2>
                            <?php
                            $default = (isset($structural_plugin) && !empty($structural_plugin)) ? $structural_plugin : "general_structure";
                            foreach ($plugins_avail as $plugin) { ?>
                                <div class="templateitem">
                                    <input 
                                        class="structurepluginradio" 
                                        type="radio" 
                                        id="structureplugin-<?php echo $plugin["name"]; ?>" 
                                        name="structureplugin" 
                                        <?php
                                        if ($plugin["name"] == $default) {
                                            echo "checked";
                                        }
                                        ?>
                                        value="<?php echo $plugin["name"];?>"
                                    />
                                    <label for="structureplugin-<?php echo $plugin["name"]; ?>"><?php echo ucfirst(preg_replace("/(_|-)/", " ", $plugin["name"]));?></label>
                                    <span class="desc">
                                        <?php
                                        echo $plugin["setup_desc"];
                                        if (substr($plugin["setup_desc"], -1) != ".") {
                                            echo ".";
                                        }

                                        if (isset($plugin["info_url"]) && !empty($plugin["info_url"])) { ?>
                                            <a class="moreinfo" target="_blank" href="<?php echo $plugin["info_url"]; ?>">
                                                <?php echo escape($lang["more-information"]) . "..."; ?>
                                            </a>
                                            <?php
                                        } ?>
                                    </span>                 
                                    <div style="clear:both;"></div>     
                                </div>
                                <?php
                            } ?>
                            <div style="clear:both;"></div>
                        </div>
                        <?php
                    }
                    
                    if (isset($errors)) { ?>   
                        <div id="errorheader"><?php echo escape($lang["setup-errorheader"]);?></div>
                        <?php
                    }
                    
                    if (isset($warnings)) { ?> 
                        <div id="warnheader"><?php echo escape($lang["setup-warnheader"]);?></div>
                        <?php
                    } ?>

                    <div class="settings">
                        <h2 id="dbaseconfig">
                            <?php echo escape($lang["setup-dbaseconfig"]);?><i class="starthidden ajloadicon icon-loader lucide--spin" id="al-testconn"></i>
                        </h2>
                        <?php if (isset($errors['database'])) { ?>
                            <div class="erroritem">
                                <?php
                                echo escape($lang["setup-mysqlerror"]);
                                if (isset($errors['databaseversion'])) {
                                    echo escape($lang["setup-mysqlerrorversion"]);
                                }
                                if (isset($errors['databaseserver'])) {
                                    echo escape($lang["setup-mysqlerrorserver"]);
                                }
                                if (isset($errors['databaselogin'])) {
                                    echo escape($lang["setup-mysqlerrorlogin"]);
                                }
                                if (isset($errors['databasedb'])) {
                                    echo escape($lang["setup-mysqlerrordbase"]);
                                }
                                if (isset($errors['databaseperms'])) {
                                    echo escape($lang["setup-mysqlerrorperms"]);
                                }
                                ?>
                                <p><?php echo $errors['database'];?></p>
                            </div>
                        <?php } ?>
                                
                        <div class="configitem">
                            <label for="mysqlserver"><?php echo escape($lang["setup-mysqlserver"]);?></label>
                            <input class="mysqlconn" type="text" required id="mysqlserver" name="mysql_server" value="<?php echo escape($mysql_server);?>"/>
                            <strong>*</strong>
                            <a class="iflink" href="#if-mysql-server">?</a>
                            <p class="iteminfo" id="if-mysql-server"><?php echo strip_tags_and_attributes($lang["setup-if_mysqlserver"]);?></p>
                        </div>
                        <div class="configitem">
                            <label for="mysqlusername"><?php echo escape($lang["setup-mysqlusername"]); ?></label>
                            <input class="mysqlconn"
                                type="text"
                                required
                                id="mysqlusername"
                                name="mysql_username"
                                value="<?php echo escape($db_connection_modes["read_write"]["mysql_username"]); ?>"
                                data-connection_mode="read_write"/>
                            <strong>*</strong>
                            <a class="iflink" href="#if-mysql-username">?</a>
                            <p class="iteminfo" id="if-mysql-username"><?php echo escape($lang["setup-if_mysqlusername"]);?></p>        
                        </div>
                        <div class="configitem">
                            <label for="mysqlpassword"><?php echo escape($lang["setup-mysqlpassword"]);?></label>
                            <input class="mysqlconn"
                                type="password"
                                id="mysqlpassword"
                                name="mysql_password"
                                value="<?php echo escape($db_connection_modes["read_write"]["mysql_password"]); ?>"
                                data-connection_mode="read_write"/>
                            <a class="iflink" href="#if-mysql-password">?</a>
                            <p class="iteminfo" id="if-mysql-password"><?php echo escape($lang["setup-if_mysqlpassword"]);?></p>
                        </div>
                        <div class="configitem">
                            <label for="mysql_read_only_username"><?php echo escape($lang["setup-mysql_read_only_username"]); ?></label>
                            <input id="mysql_read_only_username"
                                class="mysqlconn"
                                type="text"
                                name="read_only_db_username"
                                value="<?php echo escape($db_connection_modes["read_only"]["mysql_username"]); ?>"
                                data-connection_mode="read_only">
                            <a class="iflink" href="#if-mysql-read-only-username">?</a>
                            <p class="iteminfo" id="if-mysql-read-only-username"><?php echo escape($lang["setup-if_mysql_read_only_username"]); ?></p>        
                        </div>
                        <div class="configitem">
                            <label for="mysql_read_only_password"><?php echo escape($lang["setup-mysql_read_only_password"]); ?></label>
                            <input id="mysql_read_only_password"
                                class="mysqlconn"
                                type="password"
                                name="read_only_db_password"
                                value="<?php echo escape($db_connection_modes["read_only"]["mysql_password"]); ?>"
                                data-connection_mode="read_only">
                            <a class="iflink" href="#if-mysql-read-only-password">?</a>
                            <p class="iteminfo" id="if-mysql-read-only-password"><?php echo escape($lang["setup-if_mysql_read_only_password"]); ?></p>
                        </div>
                        <div class="configitem">
                            <label for="mysqldb"><?php echo escape($lang["setup-mysqldb"]);?></label>
                            <input id="mysqldb" class="mysqlconn" type="text" required name="mysql_db" value="<?php echo escape($mysql_db);?>"/>
                            <a class="iflink" href="#if-mysql-db">?</a>
                            <p class="iteminfo" id="if-mysql-db"><?php echo escape($lang["setup-if_mysqldb"]);?></p>
                        </div>
                        
                        <div class="configitem">
                            <?php if (isset($errors['mysqlbinpath'])) {?>
                                <div class="erroritem"><?php echo escape($lang["setup-err_mysqlbinpath"]);?></div>
                            <?php } ?>
                            <label for="mysqlbinpath"><?php echo escape($lang["setup-mysqlbinpath"]);?></label>
                            <input id="mysqlbinpath" type="text" name="mysql_bin_path" value="<?php echo escape($mysql_bin_path);?>"/>
                            <a class="iflink" href="#if-mysql-bin-path">?</a>
                            <p class="iteminfo" id="if-mysql-bin-path"><?php echo escape($lang["setup-if_mysqlbinpath"]);?></p>
                        </div>
                    
                        <p class="configsection">
                            <h2>
                                <?php echo escape($lang["setup-generalsettings"]);?>
                                <img alt="" id="admin_test" class="starthidden ajloadicon" src="../gfx/ajax-loader.gif"/>
                            </h2>
                            <div class="configitem">
                                <label for="applicationname"><?php echo escape($lang["setup-applicationname"]);?></label>
                                <input id="applicationname" type="text" name="applicationname" value="<?php echo escape($applicationname);?>"/>
                                <a class="iflink" href="#if-applicationname">?</a>
                                <p class="iteminfo" id="if-applicationname"><?php echo escape($lang["setup-if_applicationname"]); ?></p>
                            </div>
                            <div class="configitem">
                                <?php if (isset($errors['baseurl'])) {?>
                                    <div class="erroritem"><?php echo escape($lang["setup-err_baseurl"]); ?></div>
                                <?php } ?>
                                <?php if (isset($warnings['baseurlverify'])) {?>
                                    <div class="warnitem"><?php echo escape($lang["setup-err_baseurlverify"]); ?></div>
                                <?php } ?>
                                <label for="baseurl"><?php echo escape($lang["setup-baseurl"]);?></label>
                                <input id="baseurl" type="url" name="baseurl" required value="<?php echo escape($baseurl);?>"/>
                                <strong>*</strong>
                                <a class="iflink" href="#if-baseurl">?</a>
                                <p class="iteminfo" id="if-baseurl"><?php echo escape($lang["setup-if_baseurl"]);?></p>
                            </div>
                            <div class="configitem">
                                <?php if (isset($errors['admin_fullname'])) { ?>
                                    <div class="erroritem"><?php echo escape($lang['setup-admin_fullname_error']); ?></div>
                                <?php } ?>
                                <label for="admin_fullname"><?php echo escape($lang['setup-admin_fullname']); ?></label>
                                <input id="admin_fullname" class="admin_credentials" type="text" name="admin_fullname" value="<?php echo escape($admin_fullname); ?>"/>
                            </div>
                            <div class="configitem">
                                <?php if (isset($errors['admin_email'])) { ?>
                                    <div class="erroritem"><?php echo escape($lang['setup-emailerr']); ?></div>
                                <?php } ?>
                                    <label for="admin_email"><?php echo escape($lang['setup-admin_email']); ?></label>
                                    <input id="admin_email" class="admin_credentials" type="email" name="admin_email" required value="<?php echo $admin_email; ?>"/>
                                    <strong>*</strong>
                            </div>
                            <div class="configitem">
                                <label for="admin_username"><?php echo escape($lang['setup-admin_username']); ?></label>
                                <input id="admin_username" class="admin_credentials" type="text" name="admin_username" required value="<?php echo escape($admin_username); ?>"/>
                                <strong>*</strong><a class="iflink" href="#if-admin-username">?</a>
                                <p id="if-admin-username" class="iteminfo"><?php echo escape($lang['setup-if_admin_username']); ?></p>
                            </div>
                            <div class="configitem">
                                <div id="admin_password_error" class="erroritem" <?php echo !isset($errors['admin_password']) ? 'style="display: none;"' : ''; ?>>
                                    <?php echo isset($errors['admin_password']) ? $errors['admin_password'] : ''; ?>
                                </div>
                                <label for="admin_password"><?php echo escape($lang['setup-admin_password']); ?></label>
                                <input id="admin_password" class="admin_credentials" type="password" name="admin_password" required value="<?php echo escape($admin_password); ?>"/>
                                <strong>*</strong>
                                <a class="iflink" href="#if-admin-password">?</a>
                                <p id="if-admin-password" class="iteminfo"><?php echo escape($lang['setup-if_admin_password']); ?></p>
                            </div>
                            <div class="configitem">
                                <?php if (isset($errors['email_from'])) { ?>
                                    <div class="erroritem"><?php echo escape($lang["setup-emailerr"]);?></div>
                                <?php } ?>
                                    <label for="emailfrom"><?php echo escape($lang["setup-emailfrom"]);?></label>
                                    <input id="emailfrom" type="email" required name="email_from" value="<?php echo escape($email_from);?>"/>
                                    <strong>*</strong>
                                    <a class="iflink" href="#if-emailfrom">?</a>
                                    <p id="if-emailfrom" class="iteminfo"><?php echo escape($lang["setup-if_emailfrom"]);?></p>
                            </div>
                        </p>

                        <p class="configsection">
                            <h2><?php echo escape($lang["setup-paths"]); ?></h2>
                            <p><?php echo escape($lang["setup-pathsdetail"]); ?></p>
                            <div class="configitem">
                                <?php if (isset($errors['imagemagick_path'])) { ?>
                                    <div class="erroritem"><?php echo escape($lang["setup-err_path"]); ?> 'convert'.</div>
                                <?php } ?>
                                <label for="imagemagickpath"><?php echo str_replace("%bin", "ImageMagick/GraphicsMagick", $lang["setup-binpath"]) . ":"; ?></label>
                                <input id="imagemagickpath" type="text" name="imagemagick_path" value="<?php echo escape($imagemagick_path); ?>"/>
                            </div>
                            <div class="configitem">
                                <?php if (isset($errors['ghostscript_path'])) { ?>
                                    <div class="erroritem"><?php echo escape($lang["setup-err_path"]); ?> 'gs'.</div>
                                <?php } ?>
                                <label for="ghostscriptpath"><?php echo str_replace("%bin", "Ghostscript", $lang["setup-binpath"]) . ":"; ?></label>
                                <input id="ghostscriptpath" type="text" name="ghostscript_path" value="<?php echo escape($ghostscript_path); ?>"/>
                            </div>
                            <div class="configitem">
                                <?php if (isset($errors['ffmpeg_path'])) { ?>
                                    <div class="erroritem"><?php echo escape($lang["setup-err_path"]);?> 'ffmpeg'.</div>
                                <?php } ?>
                                <label for="ffmpegpath"><?php echo str_replace("%bin", "FFMpeg/libav", $lang["setup-binpath"]) . ":"; ?></label>
                                <input id="ffmpegpath" type="text" name="ffmpeg_path" value="<?php echo escape($ffmpeg_path); ?>"/>
                            </div>
                            <div class="configitem">
                                <?php if (isset($errors['exiftool_path'])) { ?>
                                    <div class="erroritem"><?php echo escape($lang["setup-err_path"]);?> 'exiftool'.</div>
                                <?php } ?>
                                <label for="exiftoolpath"><?php echo str_replace("%bin", "Exiftool", $lang["setup-binpath"]) . ":"; ?></label>
                                <input id="exiftoolpath" type="text" name="exiftool_path" value="<?php echo escape($exiftool_path); ?>"/>
                            </div>
                            <div class="configitem">
                                <?php if (isset($errors['pdftotext_path'])) { ?>
                                    <div class="erroritem"><?php echo escape($lang["setup-err_path"]); ?> 'pdftotext'.</div>
                                <?php } ?>
                                <label for="pdftotextpath"><?php echo str_replace("%bin", "PDFtotext", $lang["setup-binpath"]) . ":"; ?></label>
                                <input id="pdftotextpath" type="text" name="pdftotext_path" value="<?php echo escape($pdftotext_path); ?>"/>
                            </div>
                        </p>

                        <h2><?php echo escape($lang["setup-smtp-settings"]); ?></h2>
                        <div class="advsection" id="smtpsettings">
                            <div class="configitem">
                                <label for="use_smtp"><?php echo escape($lang["usesmtp"]) . ":"; ?></label>
                                <input id="use_smtp" name="use_smtp" type="checkbox" <?php echo $use_smtp ? "checked" : ""; ?>/>
                                <a class="iflink" href="#if-usesmtp">?</a>
                                <p class="iteminfo" id="if-usesmtp"><?php echo escape($lang["setup-if-usesmtp"]);?></p>
                            </div>
                            <div id="use-SMTP-settings">
                                <div class="configitem">
                                    <label for="smtp_secure"><?php echo escape($lang["smtpsecure"]) . ":"; ?></label>
                                    <input id="smtp_secure" name="smtp_secure" type="text" value="<?php echo escape($smtp_secure); ?>" />
                                    <a class="iflink" href="#if-smtpsecure">?</a>
                                    <p class="iteminfo" id="if-smtpsecure"><?php echo escape($lang["setup-if-smtpsecure"]); ?></p>
                                </div>
                                <div class="configitem">
                                    <label for="smtp_host"><?php echo escape($lang["smtphost"]) . ":"; ?></label>
                                    <input id="smtp_host" name="smtp_host" type="text" value="<?php echo escape($smtp_host); ?>"/>
                                    <a class="iflink" href="#if-smtphost">?</a>
                                    <p class="iteminfo" id="if-smtphost"><?php echo escape($lang["setup-if-smtphost"]); ?></p>
                                </div>
                                <div class="configitem">
                                    <label for="smtp_port"><?php echo escape($lang["smtpport"]) . ":"; ?></label>
                                    <input id="smtp_port" name="smtp_port" type="text" value="<?php echo escape($smtp_port); ?>"/>
                                    <a class="iflink" href="#if-smtpport">?</a>
                                    <p class="iteminfo" id="if-smtpport"><?php echo escape($lang["setup-if-smtpport"]); ?></p>
                                </div>
                                <div class="configitem">
                                    <label for="smtp_auth"><?php echo escape($lang["smtpauth"]) . ":"; ?></label>
                                    <input id="smtp_auth" name="smtp_auth" type="checkbox" checked />
                                    <a class="iflink" href="#if-smtpauth">?</a>
                                    <p class="iteminfo" id="if-smtpauth"><?php echo escape($lang["setup-if-smtpauth"]); ?></p>
                                </div>
                                <div class="configitem">
                                    <label for="smtp_username"><?php echo escape($lang["smtpusername"]) . ":"; ?></label>
                                    <input id="smtp_username" name="smtp_username" type="text" value="<?php echo escape($smtp_username); ?>"/>
                                    <a class="iflink" href="#if-smtpusername">?</a>
                                    <p class="iteminfo" id="if-smtpusername"><?php echo escape($lang["setup-if-smtpusername"]); ?></p>
                                </div>
                                <div class="configitem">
                                    <label for="smtp_password"><?php echo escape($lang["smtppassword"]) . ":"; ?></label>
                                    <input id="smtp_password" name="smtp_password" type="password" value="<?php echo escape($smtp_password); ?>"/>
                                    <a class="iflink" href="#if-smtppassword">?</a>
                                    <p class="iteminfo" id="if-smtppassword"><?php echo escape($lang["setup-if-smtppassword"]); ?></p>
                                </div>
                            </div>
                        </div>

                        <h2><?php echo escape($lang["pluginssetup"]); ?></h2>
                        <div class="advsection" id="plugin_settings">
                            <div class="configitem">
                                <label for="google_vision_enable"><?php echo escape($lang["setup_google_vision_enable"]); ?></label>
                                <input id="google_vision_enable" name="google_vision_enable" type="checkbox" <?php echo $google_vision_enable ? "checked" : ""; ?>/>
                                <a class="iflink" href="https://www.resourcespace.com/knowledge-base/plugins/google-vision" target="_blank">?</a>
                            </div>
                            <div id="plugin_google_vision_settings">
                                <div class="configitem">
                                    <label for="google_vision_key"><?php echo escape($lang["google_vision_api_key"]) . ":"; ?></label>
                                    <input id="google_vision_key" name="google_vision_key" type="text" value="<?php echo escape(get_post('google_vision_key')); ?>" />
                                </div>
                            </div>
                        </div>

                        <input type="submit" id="submit" name="submit" value="<?php echo escape($lang["setup-begin_installation"]);?>"/>
                    </div>
                </form>

                <script>
                    jQuery("#use_smtp").click(function() {
                        if (jQuery(this).prop("checked")) {
                            jQuery("#use-SMTP-settings").show(300);
                        } else {
                            jQuery("#use-SMTP-settings").hide(300);
                        }
                    });

                    if (!jQuery("#use_smtp").prop("checked")) {
                        jQuery("#use-SMTP-settings").hide();
                    }

                    jQuery("#google_vision_enable").click(function() {
                        if (jQuery(this).prop("checked")) {
                            jQuery("#plugin_google_vision_settings").show(300);
                        } else {
                            jQuery("#plugin_google_vision_settings").hide(300);
                        }
                    });

                    if (!jQuery("#google_vision_enable").prop("checked")) {
                        jQuery("#plugin_google_vision_settings").hide();
                    }
                </script>
                <?php
            }
            
            if (($develmode) && isset($config_output)) { ?>
                <div id="configoutput">
                    <h1><?php echo escape($lang["setup-configuration_file_output"]) . ":"; ?></h1>
                    <pre><?php echo escape($config_output); ?></pre>
                </div>
                <?php
            } ?>
        </div>
    </body>
</html>
