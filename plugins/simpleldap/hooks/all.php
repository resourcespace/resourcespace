<?php

include_once __DIR__ . "/../include/simpleldap_functions.php";

function HookSimpleldapAllExternalauth($uname, $pword){
    if (!function_exists('ldap_connect'))
        {
        return false;
        }
    global $lang, $simpleldap, $username, $password_hash, $email_attribute, $phone_attribute;
    
    // oops - the password is getting escaped earlier in the process, and we don't want that 
    // when it goes to the ldap server. So remove the slashes for this purpose.
    $pword = stripslashes($pword);
    
    $auth = false;
    $authreturn=array();
    if ($uname != "" && $pword != "")
        {
        $userinfo = simpleldap_authenticate($uname, $pword);

        debug("LDAP: \$userinfo = " . print_r($userinfo, true));

        if ($userinfo) { $auth = true; }
        } 


        
    if ($auth)
        {
        $usersuffix    = $simpleldap['usersuffix'];
        $addsuffix     = ($usersuffix=="") ? "" : (substr($usersuffix,0,1)=="." ? "" : ".") . $usersuffix;
        $username      = $uname . $addsuffix;
        $password_hash = rs_password_hash("RSLDAP" . $uname . $addsuffix . $pword);
        $user          = ps_query("SELECT ref, approved, account_expires FROM user WHERE username = ?", ['s', $username]);
        
        $email         = $userinfo["email"];
        $phone         = $userinfo["phone"];
        $displayname   = $userinfo['displayname'];

        debug ("LDAP - got user details email: " . $email . ", telephone: " . $phone);

        // figure out group
        $group = $simpleldap['fallbackusergroup'];
        $groupmatch="";
        $grouplist = ps_query("select * from simpleldap_groupmap");
        if (count($grouplist)>0 && $userinfo['group']!="")
            {
            for ($i = 0; $i < count($grouplist); $i++)
                {
                if (($userinfo['group'] == $grouplist[$i]['ldapgroup']) && is_numeric($grouplist[$i]['rsgroup']))
                    {
                    $group = $grouplist[$i]['rsgroup'];
                    $groupmatch=$userinfo['group'];
                    }
                }
            }
                    

        if (count($user) > 0)
            {
            $userid = $user[0]["ref"];
            $expires = $user[0]["account_expires"];
            if ($expires != "" && $expires != "0000-00-00 00:00:00" && strtotime($expires)<=time())
                {
                $result['error']=$lang["accountexpired"];
                return $result;
                }
            if ($user[0]["approved"] != 1)
                {
                return false;
                }
                
            // user exists, so update info
            if($simpleldap['update_group'])
                {
                ps_query("update user set origin='simpleldap', password = ?, usergroup = ?, fullname= ?, email= ?, telephone= ? where ref = ?",
                    [
                    's', $password_hash,
                    'i', $group,
                    's', $displayname,
                    's', $email,
                    's', $phone,
                    'i', $userid
                    ]
                );
                
                }
            else
                {
                ps_query("update user set origin='simpleldap', password = ?, fullname= ?, email= ?, telephone= ? where ref = ?",
                [
                    's', $password_hash,
                    's', $displayname,
                    's', $email,
                    's', $phone,
                    'i', $userid
                ]
                );
                }
            return true;
            }
        else
            {
            // user authenticated, but does not exist, so adopt/create if necessary
            if ($simpleldap['createusers'] || $simpleldap['create_new_match_email'])
                {   
                $email_matches= ps_query("select ref, username, fullname from user where email= ?", ['s', $email]);             
                                                
                if(count($email_matches)>0)
                    {               
                    if(count($email_matches)==1 && $simpleldap['create_new_match_email'])
                        {
                        // We want adopt this matching account - update the username and details to match the new login credentials
                        debug("LDAP - user authenticated with matching email for existing user . " . $email . ", updating user account " . $email_matches[0]["username"] . " to new username " . $username);
                        if($simpleldap['update_group'])
                            {
                            ps_query("update user set origin='simpleldap',username= ?, password= ?, fullname= ?,email= ?,telephone= ?,usergroup= ?,comments=concat(comments,'\n', ?) where ref= ?",
                                [
                                's', $username,
                                's', $password_hash,
                                's', $displayname,
                                's', $email,
                                's', $phone,
                                'i', $group,
                                's', date("Y-m-d") . " " . $lang["simpleldap_usermatchcomment"] ,
                                'i', $email_matches[0]["ref"]
                                ]
                            );
                            }
                        else
                            {
                            ps_query("update user set origin='simpleldap',username= ?, password= ?, fullname= ?,email= ?,telephone= ?,comments=concat(comments,'\n', ?) where ref= ?",
                                [
                                's', $username,
                                's', $password_hash,
                                's', $displayname,
                                's', $email,
                                's', $phone,
                                's', date("Y-m-d") . " " . $lang["simpleldap_usermatchcomment"] ,
                                'i', $email_matches[0]["ref"]
                                ]
                            );
                            }
                        return true;
                        }
                        
                    if (isset($simpleldap['notification_email']) && $simpleldap['notification_email']!="")
                        {
                        // Already account(s) with this email address, notify the administrator
                        global $lang, $baseurl, $email_from;
                        debug("LDAP - user authenticated with matching email for existing users: " . $email);
                        $emailtext=$lang['simpleldap_multiple_email_match_text'] . " " . $email . "<br /><br />";
                        $emailtext.="<table class=\"InfoTable\" border=1>";
                        $emailtext.="<tr><th>" . $lang["property-name"] . "</th><th>" . $lang["property-reference"] . "</th><th>" . $lang["username"] . "</th></tr>";
                        foreach($email_matches as $email_match)
                            {
                            $emailtext.="<tr><td><a href=\"" . $baseurl . "/?u=" . $email_match["ref"] .  "\" target=\"_blank\">" . $email_match["fullname"] . "</a></td><td><a href=\"" . $baseurl . "/?u=" . $email_match["ref"] .  "\" target=\"_blank\">" . $email_match["ref"] . "</a></td><td>" . $email_match["username"] . "</td></tr>\n";
                            }
                        
                        $emailtext.="</table>";
                        send_mail($simpleldap['notification_email'],$lang['simpleldap_multiple_email_match_subject'],$emailtext,$email_from);
                        }
                            
                
                    if(!$simpleldap['allow_duplicate_email'])
                        {
                        // We are blocking accounts with the same email
                        $authreturn["error"]=$lang['simpleldap_duplicate_email_error'];
                        return $authreturn;
                        }                                       
                    }

                if(!$simpleldap['createusers'])
                    {
                    return false;
                    }
            
                // Create the user
                $ref=new_user($username);
                if (!$ref) { echo "returning false!"; exit; return false;} // this shouldn't ever happen
                if ($ref === -2) {
                    debug("LDAP - Error - User could not be created, User limit exceeded");
                    $authreturn["error"]=$lang['simpleldap_userlimit_error'];
                    return $authreturn;
                }
                
                if($groupmatch=="" && isset($simpleldap['notification_email']) && $simpleldap['notification_email']!="")
                    {
                    global $lang, $baseurl, $email_from;
                    // send email advising that a new user has been created but that there is no mapping for the groups
                    debug("LDAP - new user but no mapping configured");
                    $emailtext=$lang['simpleldap_no_group_match'] . "<br /><br />";
                    $emailtext.= "<a href=\"" . $baseurl . "/?u=" . $ref .  "\" target=\"_blank\">" . $displayname . " (" . $email . ")</a><br /><br />";
                    $emailtext.= $lang['simpleldap_usermemberof'] . "<br /><br />";
                    if(is_array($userinfo["memberof"]))
                        {
                        $emailtext.="<ul>";
                        foreach($userinfo["memberof"] as $memberofgroup)
                            {
                            $emailtext.= "<li>" . $memberofgroup . "</li>";
                            }   
                        $emailtext.="</ul>";
                        }
                    send_mail($simpleldap['notification_email'],$lang['simpleldap_no_group_match_subject'],$emailtext,$email_from);
                    }
                
                
                // Update with information from LDAP    
                $rsgroupname=ps_value("select name value from usergroup where ref=?", array("i",$group), '');
                ps_query("update user set origin='simpleldap', password= ?, fullname= ?,email= ?,telephone= ?,usergroup= ?,comments= ? where ref= ?",
                    [
                    's', $password_hash,
                    's', $displayname,
                    's', $email,
                    's', $phone,
                    'i', $group,
                    's', $lang["simpleldap_usercomment"] . (($groupmatch!="")?"\r\nLDAP group: " . $groupmatch:"") . "\r\nAdded to RS group " . $rsgroupname . "(" . $group . ")",
                    'i', $ref
                    ]
                );
                        
                
                return true;
                }
            else
                {
                // user creation is disabled, so return false
                return false;
                }

            }
    

    } else {
        // user is not authorized
        return false;
    }


}
        

