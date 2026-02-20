<?php

function HookWordpress_ssoAllProvideusercredentials()
    {
    include_once __DIR__."/../include/wordpress_sso_functions.php";

    #use standard authentication if available
    if (isset($_COOKIE["user"]) && $_COOKIE["user"]!="|") {return true;}

    global $username,$hashsql,$session_hash,$baseurl,$lang,$wordpress_sso_url, $wordpress_sso_secret;
    global $wordpress_sso_auto_create, $wordpress_sso_auto_approve, $wordpress_sso_auto_create_group,$user_select_sql;
    global $session_autologout, $allow_password_change;

    $session_hash="";
    @$_COOKIE["user"]="|";

    $wordpress_sso="";
    if (isset($_COOKIE["wordpress_sso"])) {$wordpress_sso=$_COOKIE["wordpress_sso"];}

    if ($wordpress_sso=="") // No cookie, go to WP or get from query string 
        {
        debug("wordpress_sso - no wordpress_sso cookie found, checking query string to see if passed a response from server");
        // Don't escape this yet as the hash is calculated by Wordpress without it
        $wordpress_user=getval("wordpress_sso_user",""); 
        if ($wordpress_user=="")
            {               
            # No wordpress_sso cookie or querystring parameter, redirect back for the first time to wordpress path to initiate wordpress login - step 1 (find username)
            debug("wordpress_sso - nothing in query string, redirecting to server, step 1");
            wordpress_sso_redirect(false,false);
            }
        else // We have got user details in the query string, check they are valid
            {
            $requestid=getval("requestid","");
            $s=explode("|",$wordpress_user);
            debug("wordpress_sso - received response from wordpress server: " . $wordpress_user . " request ID: " . $requestid);
            if (count($s)==3 || count($s)==5)
                {
                $username=$s[0];
                if ($username==""){wordpress_sso_fail();}
                $hash=$s[1];
                $requesthash=$s[2];
                if (isset($s[3])){$wpemail=$s[3];}else{$wpemail="";}
                if (isset($s[4])){$wpdisplayname=$s[4];}else{$wpdisplayname="";}
                $today = date("Ymd");
                $currentrequest = ps_value("SELECT wp_authrequest AS value FROM user WHERE username = ?", array("s", $username), "");

                if ($requesthash !== md5($baseurl . $wordpress_sso_secret . $username . $today . $requestid)) // Invalid hash. Failed authentication and came from WordPress so no point redirecting.
                    {
                    wordpress_sso_fail();
                    }

                // Valid response, check if user exists
                $c = ps_value("SELECT count(*) value FROM user WHERE username = ?", array("s", $username), 0);
                if ($c==0) // No user 
                    {
                    if ($wordpress_sso_auto_create) // create user if enabled
                        {
                        if ($wpemail=="") // no email, need to go back and get details from Wordpress
                            {
                            debug("wordpress_sso - need to get email details. Redirecting to WordPress");
                            wordpress_sso_redirect(true,false);
                            }
                        debug("wordpress_sso: Auto creating user ({$username} - {$wpemail})");
                        ps_query("INSERT INTO user (username, password, origin, fullname, email, usergroup, comments, approved) values (?, ?,'wordpress_sso', ?, ?, ?, ?, ?)",
                          array("s", $username, "s", $hash, "s", $wpdisplayname, "s", $wpemail, "s", $wordpress_sso_auto_create_group, "s", $lang['wordpress_sso_auto_created'], "i", (($wordpress_sso_auto_approve) ? 1 : 0)));
                        }
                    else // not current user, need to redirect
                        {
                        wordpress_sso_fail();
                        }
                    }
                debug("wordpress_sso - Found user matching: " . $username);
                // Check that current request       
                if ($currentrequest!=$requestid || $requestid=="") // This request is either not set or does not match the last one created, was saved before redirect so go back to WordPress (step 2)
                {
                if ($requestid!=""){debug("wordpress_sso - failed to match request ID. Current user request:  " . $currentrequest . " Received request ID: " . $requestid);}  
                wordpress_sso_redirect(false,true);
                }

                //Set cookie and allow login
                setcookie("wordpress_sso",$username . "|" . $hash,0,"/");
                $hashsql="";
                $user_select_sql = new PreparedStatementQuery();
                $user_select_sql->sql = "AND u.username = ?";
                $user_select_sql->parameters = array("s", $username);
                $allow_password_change = false;
                $session_autologout = false;
                return true;
                }
            else // Invalid response from WordPress
                {
                wordpress_sso_fail();
                }
            }      
        }
    else // We have a cookie, check it is valid
        {
        debug("wordpress_sso - checking cookie: " . $wordpress_sso);
        $s=explode("|",$wordpress_sso);
        if (count($s)==2)
            {
            $username=$s[0];
            if ($username=="")                  
                {wordpress_sso_fail();}
            debug("wordpress_sso - wordpress_sso cookie has username");
            $hash=$s[1];    
            $today = date("Ymd");
            if ($hash !== md5($baseurl . $wordpress_sso_secret . $username . $today))
                {       
                // Invalid hash. Redirect to Wordpress to reauthenticate.
                debug("wordpress_sso - wordpress_sso cookie has invalid hash");
                wordpress_sso_redirect(false,false);
                }
            // cookie is valid, check user still exists
            $c = ps_value("select count(*) value from user where username = ?", array("s", $username), 0);
            if ($c==0)
                {
                if ($wordpress_sso_auto_create)
                    {
                    debug("wordpress_sso - need to create new user. Redirecting to get details");
                    wordpress_sso_redirect(true,false);
                    }
                else
                    {
                    debug("wordpress_sso - no ResourceSpace account present and auto creation not enabled. Exiting.");
                    wordpress_sso_fail();
                    }
                }
            debug("wordpress_sso - found matching ResourceSpace user");
            $dummyrequest=uniqid(); # use to prevent subsequent authentication using same querystring
            ps_query("UPDATE user SET wp_authrequest = ? WHERE username = ?", array("s", $dummyrequest, "s", $username));
            setcookie("wordpress_sso_test",$dummyrequest,0,"/");

            //allow login
            $user_select_sql = new PreparedStatementQuery();
            $user_select_sql->sql = "AND u.username = ?";
            $user_select_sql->parameters = array("s", $username);
            $hashsql="";
            return true;
            }
        else // Invalid cookie
            {
            debug("wordpress_sso: invalid cookie");
            wordpress_sso_fail();
            }
        }
    }

function HookWordpress_ssoLoginLoginformlink()
        {
        // Add a link to login.php, which is still used if $wordpress_sso_allow_standard_login is set to true
        global $wordpress_sso_url,$lang;
        ?>
        <p class="external-login-link">
            <a href="<?php echo $wordpress_sso_url; ?>?rsauth=true&url=%2F">
                <i class="icon-log-in"></i><?php echo escape($lang["wordpress_sso_use_wp_login"]); ?>
            </a>
            <br/>
        </p>
        <?php
        }

function HookWordpress_ssoLoginInitialise()
        {
        global $wordpress_sso_url;
        if (getval("logout","")!="" && isset($_COOKIE["wordpress_sso"]))
                {
                redirect("/plugins/wordpress_sso/pages/logout.php");
                exit();
                }
        }

function HookWordpress_ssoTeam_user_editPassword()
    {
    global $ref, $lang;
    $checkwpuser = ps_value("SELECT wp_authrequest AS value FROM user WHERE ref = ?", array("i", (int)$ref), "");
    if  (strlen($checkwpuser)>0)
        {
        ?>
        <input type="hidden" name="password" value="<?php echo escape($lang['hidden']); ?>">
        <?php
        return true;
        }
    return false;
    }

function HookWordpress_ssoTeam_user_editTicktoemailpassword()
    {
    global $ref, $lang;
    $checkwpuser = ps_value("SELECT wp_authrequest AS value FROM user WHERE ref = ?", array("i", (int)$ref), "");
    if  (strlen((string) $checkwpuser)>0)
        {
        return true;
        }
    return false;
    }






