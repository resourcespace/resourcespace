<?php

command_line_only();

// Set up a test user to receive notifications
$notifyuser = new_user("notifyuser005000");
$language   = "fr";
$emailadress = "notifyuser5000@test.resourcespace.com";
$usergroup  = 3;
$approved   = 1;
$params = ["i",$approved,"s",$emailadress,"i",$usergroup,"s",$language,"i",$notifyuser];
ps_query("UPDATE user SET approved = ?,email = ?,usergroup = ?,lang = ? WHERE ref = ?", $params);

$msgurl = $baseurl . "/tests/test.php";

// Create message
$message = new ResourceSpaceUserNotification();

// Add language string as subject
$message->set_subject("lang_youraccountdetails");

// Append text to subject, with replace string
$message->append_subject(" - %%REF", ["%%REF"], [$notifyuser]);

// Add i18n string as text
$text = "i18n_~en:English~fr:French";
$message->set_text($text);

// Append plain text
$message->append_text(" : appended text");

$message->user_preference =  ["user_pref_system_management_notifications" => ["requiredvalue" => true,"default" => true]];
$message->url = $msgurl;

$result = send_user_notification([$notifyuser], $message);
// Subtest A - system notification with French translation
if (
    !is_array($result)
     || !isset($result["messages"][0])
     || $result["messages"][0]["user"] != $notifyuser
     || $result["messages"][0]["message"] != "French : appended text"
) {
    echo "ERROR - SUBTEST A\n";
    return false;
}

// Subtest B - email notification
// Check it has the correct subject, body and URL
set_config_option($notifyuser, "email_user_notifications", true);
$result = send_user_notification([$notifyuser], $message);
if (
    !is_array($result)
     || !isset($result["emails"][0])
     || $result["emails"][0]["email"] != $emailadress
     || $result["emails"][0]["subject"] != "Vos informations de connexion - " . $notifyuser
     || strpos($result["emails"][0]["body"], "French : appended text") === false
     || strpos($result["emails"][0]["body"], $msgurl) === false
) {
    echo "ERROR - SUBTEST B\n";
    return false;
}

// Subtest C - Check user preference to disable notifications
set_config_option($notifyuser, "user_pref_system_management_notifications", false);
$result = send_user_notification([$notifyuser], $message);

if (
    !is_array($result)
     || isset($result["emails"][0])
     || isset($result["messages"][0])
) {
    echo "ERROR - SUBTEST C\n";
    return false;
}
