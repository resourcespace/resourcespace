<?php

function wordpress_sso_redirect($getdetails=false,$knownuser=false)
    {
    global $wordpress_sso_url, $username; 
    setcookie("wordpress_sso","",0,"/"); // Clear any existing cookie
    # Set a unique id for this logon attempt so any response can be used only once
    if ($knownuser && $username!="")
        {
        // Update the user account with the request id so it can be detected on return
        $requestid=uniqid("wp_sso");
        ps_query("UPDATE user SET wp_authrequest = ? WHERE username = ?", array("s", $requestid, "s", $username));
        debug("wordpress_sso - sending second request to WP, setting request ID for $username in database");
        }
    else
        {
        debug("wordpress_sso - sending first request to WP, no username, no request ID");
        $requestid="";
        }
    # Now get the requested path if first redirect
    $_SERVER['REQUEST_URI'] = ( isset($_SERVER['REQUEST_URI']) ?
    $_SERVER['REQUEST_URI'] : $_SERVER['SCRIPT_NAME'] . ( isset($_SERVER
    ['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : ''));
    $path=$_SERVER["REQUEST_URI"];
    $path=str_replace("ajax","ajax_disabled",$path); // Disable forwarding of the AJAX parameter if this was an AJAX load, otherwise the redirected page will be missing the header/footer.
    
    // clear old parameters from query string so they don't interfere if step 2
    $path=str_replace("requestid","unusedvar",$path);
    $path=str_replace("url","unusedvar",$path);
    $path=str_replace("wordpress_sso_user","unusedvar",$path);
    if ($getdetails)
        {
        $redirecturl=$wordpress_sso_url . "/wp-admin/?rsauth=true&requestid=$requestid&getdetails=true&url=" . urlencode($path);
        }
    else
        {
        $redirecturl=$wordpress_sso_url . "/wp-admin/?rsauth=true&requestid=$requestid&url=" . urlencode($path); // Include the requested path so WordPress can redirect back once authenticated
        }
    header("Location: $redirecturl"); /* Redirect browser */
    exit();
    }
    
function wordpress_sso_fail()
    {
    debug("wordpress_sso - failed authentication");
    setcookie("wordpress_sso","",0,"/");
    redirect("/plugins/wordpress_sso/pages/nouser.php");
    exit();
    }