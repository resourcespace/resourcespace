<?php
# English
# Language File for the SimpleLDAP Plugin
# -------
$lang['simpleldap_ldaptype'] = "Directory Provider";
$lang['ldapserver'] = "LDAP Server/URL e.g.ldaps://hostname:port";
$lang['ldap_encoding'] = "Data encoding received from the LDAP Server (set if not UTF-8 and data is not displayed properly - e.g display name)";
$lang['domain'] = "AD Domain, if multiple separate with semi-colons";
$lang['emailsuffix'] = "Email suffix - used if no email attribute data found";
$lang['port'] = "Port. Only used if server above is not the full LDAP URL";
$lang['basedn'] = "Base DN. If users are in multiple DNs,separate with semi-colons";
$lang['loginfield'] = "Login Field";
$lang['usersuffix'] = "User Suffix (a dot will be added in front of the suffix)";
$lang['groupfield'] = "Group Field";
$lang['createusers'] = "Create Users";
$lang['fallbackusergroup'] = "Fallback User Group";
$lang['ldaprsgroupmapping'] = "LDAP-ResourceSpace Group Mapping";
$lang['ldapvalue'] = "LDAP Value";
$lang['rsgroup'] = "ResourceSpace Group";
$lang['addrow'] = "Add Row";
$lang['email_attribute'] = "Attribute to use for email address";
$lang['phone_attribute'] = "Attribute to use for telephone number";
$lang['simpleldap_telephone'] = "Telephone";
$lang['simpleldap_unknown'] = "unknown";
$lang['simpleldap_update_group'] = "Update user group at each logon. If not using AD groups to determine access, set this to false so that users can be manually promoted ";
$lang['simpleldappriority']="Priority (higher number will take precedence)";
$lang['simpleldap_create_new_match_email'] = "Email-match: Check if LDAP email matches an existing RS account email and adopt that account. Will work even if 'Create users' is disabled";
$lang['simpleldap_allow_duplicate_email'] ="Allow new accounts to be created if there are existing accounts with the same email address? (this is overridden if email-match is set above and one match is found)";
$lang['simpleldap_multiple_email_match_subject'] ="ResourceSpace - conflicting email login attempt";
$lang['simpleldap_multiple_email_match_text'] ="A new LDAP user has logged in but there is already more than one account with the same email address: ";
$lang['simpleldap_notification_email']="Notification address e.g. if duplicate email addresses are registered. If blank none will be sent.";
$lang['simpleldap_duplicate_email_error']="There is an existing account with the same email address. Please contact your administrator.";
$lang['simpleldap_userlimit_error']="User could not be created as user limit has been reached. Please contact your administrator.";
$lang['simpleldap_no_group_match_subject']="ResourceSpace - new user with no group mapping";
$lang['simpleldap_no_group_match']="A new user has logged on but there is no ResourceSpace group mapped to any directory group to which they belong.";
$lang['simpleldap_usermemberof'] = "The user is a member of the following directory groups: -";
$lang['simpleldap_test'] = "Test LDAP configuration";
$lang['simpleldap_testing'] = "Testing LDAP configuration";
$lang['simpleldap_connection'] = "Connection to LDAP server";
$lang['simpleldap_bind'] = "Bind to LDAP server";
$lang['simpleldap_username'] = "Username/User DN";
$lang['simpleldap_password'] = "Password";
$lang['simpleldap_test_auth'] = "Test authentication";
$lang['simpleldap_domain'] = "Domain";
$lang['simpleldap_displayname'] = "Display name";
$lang['simpleldap_memberof'] = "Member of";
$lang["simpleldap_test_title"] = "Test";
$lang["simpleldap_result"] = "Result";
$lang["simpleldap_retrieve_user"] = "Retrieve user details";
$lang["simpleldap_extension_required"] = "The PHP LDAP module must be enabled for this plugin to work";
$lang["simpleldap_usercomment"] = "Created by SimpleLDAP plugin.";
$lang["simpleldap_usermatchcomment"] = "Updated to LDAP user by SimpleLDAP.";
$lang["origin_simpleldap"] = "SimpleLDAP plugin";
$lang["simpleldap_LDAPTLS_REQCERT_never_label"] = "Do not check the FQDN of the server against the CN of the certificate";

$lang["page-title_simpleldap_ajax_test_auth"]   = "Test LDAP Authentication";
$lang["page-title_simpleldap_setup"]            = "Setup Simple LDAP Plugin";