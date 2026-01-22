<?php

include_once __DIR__ . "/../../include/boot.php";
include __DIR__ . "/../../include/authenticate.php";

$previous_hash  = getval('prevhash', "");

$messages = array();
message_get($messages, $userref, true, true, "DESC", "created", 10);

$etag = md5(json_encode($messages));

if (isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] === $etag) {
    http_response_code(304);
    exit;
}

header('ETag: ' . $etag);
header('Content-Type: application/json');

// Build JSON to return

$return_output = array();

if (count($messages) > 0) {

    foreach ($messages as $message) {

        $userbyname = get_user_by_username($message["owner"]);
        $user = get_user($userbyname);

        if (!$user) {
            $return_output[$message['message_id']]['user_details'] = array('fullname' => $applicationname,'groupname' => '', 'user' => false);
            $return_output[$message['message_id']]['user_profile_image'] = "";
        } else {
            $return_output[$message['message_id']]['user_details']['fullname'] = $user['fullname'];
            $return_output[$message['message_id']]['user_details']['user_name'] = $user['username'];
            $return_output[$message['message_id']]['user_details']['user'] = true;
            $return_output[$message['message_id']]['user_profile_image'] = get_profile_image($user['ref'], false);
        }

        $return_output[$message['message_id']]['unread'] = $message["seen"] == 0;
        
        $message_text_preview = preview_from_text($message['message'], 45, 2, true);
        $message_text_preview = strip_tags_and_attributes($message_text_preview);
        $message_text_preview = nl2br($message_text_preview);

        $return_output[$message['message_id']]['message_text_preview'] = $message_text_preview;

        $return_output[$message['message_id']]['message_ref'] = (int)$message["ref"];
        $return_output[$message['message_id']]['reply'] = (bool)($message["type"] & MESSAGE_ENUM_NOTIFICATION_TYPE_USER_MESSAGE);

        $return_output[$message['message_id']]['created'] = $message["created"];
        $return_output[$message['message_id']]['age']     = date_to_age($message["created"]);
    
    }
}

echo json_encode($return_output);
