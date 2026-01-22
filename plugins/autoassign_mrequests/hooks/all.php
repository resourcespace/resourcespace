<?php

include_once __DIR__ . '/../include/autoassign_general.php';

function HookAutoassign_mrequestsAllAutoassign_individual_requests($user_ref, $collection_ref, $message, $manage_collection_request)
{
    global $manage_request_admin, $assigned_to_user, $notify_manage_request_admin, $request_query;

    // Do not process this any further as this only handles individual resource requests
    if ($manage_collection_request) {
        return true;
    }

    $resources              = get_collection_resources($collection_ref);
    $resource_data          = get_resource_field_data($resources[0], false, true, null, false, false, false, false); // in this case it should only have one resource
    $mapped_fields          = get_mapped_fields();
    $assigned_administrator = 0;
    $resource_nodes         = get_resource_nodes($resources[0]);

    // Process each non node based metadata field value pair for the resource being requested, looking for an assignee
    foreach ($resource_data as $r_data) {
        // Is the metadata field the subject of a mapping
        if (in_array($r_data['ref'], $mapped_fields)) {
            // Return the assignee for the mapping if one exists for the field value pair
            get_mapped_user_by_field($r_data['ref'], $r_data['value']) != 0 ? $assigned_administrator = get_mapped_user_by_field($r_data['ref'], $r_data['value']) : 0;
            // If an assignee was found then terminate loop otherwise move on to next field value pair
            if ($assigned_administrator !== 0) {
                break;
            }
        }
    }

    $node_fields_list = array();
    $current_node = array();

    foreach ($resource_nodes as $node) {
        get_node($node, $current_node);
        array_push($node_fields_list, $current_node);
    }

    // Process each node based metadata field value pair for the resource being requested, looking for an assignee
    foreach ($node_fields_list as $r_node) {
        // Is the metadata field the subject of a mapping
        if (in_array($r_node['resource_type_field'], $mapped_fields)) {
            // Return the assignee for the mapping if one exists for the field value pair
            get_mapped_user_by_field($r_node['resource_type_field'], $r_node['name']) != 0 ? $assigned_administrator = get_mapped_user_by_field($r_node['resource_type_field'], $r_node['name']) : 0;
            // If an assignee was found then terminate loop otherwise move on to next field value pair
            if ($assigned_administrator !== 0) {
                break;
            }
        }
    }

    if ($assigned_administrator === 0) {
        return false;
    }

    $request_query = new PreparedStatementQuery();
    $request_query->sql = "INSERT INTO request(user, collection, created, request_mode, `status`, comments, assigned_to)
                                  VALUES (?, ?, NOW(), 1, 0, ?, ?)";
    $request_query->parameters = array("i",$user_ref, "i",$collection_ref, "s",$message->get_text(), "i",$assigned_administrator);

    $assigned_to_user = get_user($assigned_administrator);
    if (!$assigned_to_user) {
        return false;
    }

    $notify_manage_request_admin = true;

    // If we've got this far, make sure auto assigning managed requests based on resource types won't overwrite this
    $manage_request_admin = array();  // Initialise the global array instead of attempting to unset it which does not work

    return true;
}

function HookAutoassign_mrequestsAllAutoassign_collection_requests($user_ref, $collection_data, $message, $manage_collection_request)
{
    global $manage_request_admin, $assigned_to_user, $baseurl, $applicationname,
           $request_query, $notify_manage_request_admin, $username, $user_mail_template;

    // Do not process this any further as this should only handle collection requests
    if (!$manage_collection_request) {
        return false;
    }

    $resources                             = get_collection_resources($collection_data['ref']);
    $mapped_fields                         = get_mapped_fields();
    $collection_resources_by_assigned_user = array();
    $collections                           = array();

    // Build the collections map between assigned user and resources the collection should contain
    foreach ($resources as $resource) {
        // Don't use permissions as requesting user may not have access to field
        $resource_data          = get_resource_field_data($resource, false, false, null, false, false, false, false);
        $assigned_administrator = 0;
        $resource_not_assigned  = true;

        foreach ($resource_data as $r_data) {
            if (in_array($r_data['ref'], $mapped_fields)) {
                $assigned_administrator = get_mapped_user_by_field($r_data['ref'], $r_data['value']);

                if ($assigned_administrator === 0) {
                    debug("autoassign_mrequests: Assigning resource " . $resource . " to unmanaged");
                    $collection_resources_by_assigned_user['not_managed'][] = $resource;
                } else {
                    debug("autoassign_mrequests: Assigning resource " . $resource . " to " . $assigned_administrator);
                    $collection_resources_by_assigned_user[$assigned_administrator][] = $resource;
                }

                $resource_not_assigned = false;
                break;
            }
        }

        if ($resource_not_assigned && !isset($manage_request_admin)) {
            $collection_resources_by_assigned_user['not_managed'][] = $resource;
        }
    }

    // Create collections based on who is supposed to handle the request
    foreach ($collection_resources_by_assigned_user as $assigned_user_id => $collection_resources) {
        if ($assigned_user_id === 'not_managed') {
            $collections['not_managed'] = create_collection($user_ref, $collection_data['name'] . ' request for unmanaged resources');
            foreach ($collection_resources as $collection_resource_id) {
                add_resource_to_collection($collection_resource_id, $collections['not_managed']);
            }
            continue;
        }

        $user = get_user($assigned_user_id);
        $collections[$assigned_user_id] = create_collection($user_ref, $collection_data['name'] . ' request - managed by ' . $user['email']);
        foreach ($collection_resources as $collection_resource_id) {
            add_resource_to_collection($collection_resource_id, $collections[$assigned_user_id]);
        }

        // Attach assigned admin to this collection
        add_collection($assigned_user_id, $collections[$assigned_user_id]);
    }

    if (!empty($collections)) {
        foreach ($collections as $assigned_to => $collection_id) {
            $request_query = new PreparedStatementQuery();
            $request_query->sql = "INSERT INTO request(user, collection, created, request_mode, `status`, comments, assigned_to)
                                    VALUES (?, ?, NOW(), 1, 0, ?, ?)";
            $request_query->parameters = array("i",$user_ref, "i",$collection_id, "s",$message->get_text(), "i",$assigned_to);
            if ($assigned_to === 'not_managed') {
                if (is_array($assigned_to_user) && isset($assigned_to_user["ref"])) {
                    $assigned_to = [$assigned_to_user["ref"]];
                    debug("autoassign_mrequests: Send collection " . $collection_id . " to " . $assigned_to_user["ref"]);
                } else {
                    // No valid user assigned by $manage_request_admin
                    $assigned_to = get_notification_users("RESOURCE_ACCESS");
                    $request_query->sql = "INSERT INTO request(user, collection, created, request_mode, `status`, comments)
                    VALUES (?, ?, NOW(), 1, 0, ?)";
                    $request_query->parameters = array("i",$user_ref, "i",$collection_id, "s",$message->get_text());
                    debug("autoassign_mrequests: Send collection " . $collection_id . " to default notify admins");
                }
            } else {
                // Change assigned user into an array
                $assigned_to = [$assigned_to];
            }

            ps_query($request_query->sql, $request_query->parameters);
            $request = sql_insert_id();
            $eventdata = [
                "type"  => MANAGED_REQUEST,
                "ref"   => $request,
                ];

            // Send message for this request
            $request_url = $baseurl . "/?q=" . $request;
            $templatevars['request_id']    = $request;
            $templatevars['requesturl']    = $request_url;
            $templatevars['requestreason'] = $message->get_text();
            $adminmessage = new ResourceSpaceUserNotification();
            $adminmessage->set_subject($applicationname . ": ");
            $adminmessage->append_subject("lang_requestassignedtoyou");
            $adminmessage->set_text("lang_requestassignedtoyoumail");
            $adminmessage->append_text("<br/><br/>");
            $adminmessage->append_text("lang_username");
            $adminmessage->append_text(": " . $username . "<br/>");
            // Add core message text (reason, custom fields etc.)
            $adminmessage->append_text_multi($message->get_text(true));
            $adminmessage->url = $request_url;
            $adminmessage->eventdata = $eventdata;
            $adminmessage->templatevars = $templatevars;
            $adminmessage->eventdata = $eventdata;
            send_user_notification($assigned_to, $adminmessage);
        }

        $collection_url = $baseurl . "/?c=" . $collection_data['ref'];
        $usermessage = new ResourceSpaceUserNotification();
        $usermessage->set_subject($applicationname . ": ");
        $usermessage->append_subject("lang_requestsent");
        $usermessage->set_text("lang_requestsenttext");
        $usermessage->append_text("<br/><br/>");
        $usermessage->append_text_multi($message->get_text(true));
        $usermessage->append_text("<br/><br/>");
        $usermessage->append_text("lang_clicktoviewresource");
        $usermessage->url = $collection_url;
        $templatevars['requesturl'] = $collection_url;
        $usermessage->template = $user_mail_template;
        $usermessage->templatevars = $templatevars;
        send_user_notification([$user_ref], $usermessage);
        $notify_manage_request_admin = false;
    }

    // If we've got this far, disable features which may conflict
    $manage_request_admin = array();
    $GLOBALS['owner_field'] = 0;

    return true;
}

function HookAutoassign_mrequestsAllBypass_end_managed_collection_request($manage_individual_requests, $collection_id, $request_query, $message, $templatevars, $assigned_to_user, $admin_mail_template, $user_mail_template)
{
    global $applicationname, $baseurl, $email_from, $resource_type_request_emails_and_email_notify, $lang, $username, $userref, $notify_manage_request_admin, $resource_type_request_emails;

    // Collection level requests have already been created and e-mails sent so skip this step
    if (!$manage_individual_requests) {
        // Because we are bypassing the end of managed_collection_request function we need to return true
        return true;
    }

    // If we don't have an assigned user, it probably means system is misconfigured so go ahead and run this normally via
    // RS own logic for dealing with requests.
    if (is_null($assigned_to_user) || !$assigned_to_user) {
        return false;
    }

    // Create resource level request using SQL which was setup earlier in resource level hook or regular processing
    ps_query($request_query->sql, $request_query->parameters);
    $request = sql_insert_id();
    $eventdata = [
        "type"  => MANAGED_REQUEST,
        "ref"   => $request,
        ];
    $request_url = $baseurl . "/?q=" . $request;

    // Update message with request url specific to this collection
    $templatevars['request_id']    = $request;
    $templatevars['requesturl']    = $request_url;
    $templatevars['requestreason'] = $message->get_text();

    $adminmessage = new ResourceSpaceUserNotification();
    $adminmessage->set_subject($applicationname . ": ");
    $adminmessage->append_subject("lang_requestassignedtoyou");
    $adminmessage->set_text("lang_requestassignedtoyoumail");
    $adminmessage->append_text("<br/><br/>");
    $adminmessage->append_text("lang_username");
    $adminmessage->append_text(": " . $username . "<br/>");
    $coremessage_arr = $message->get_text(true);
    if (is_array($coremessage_arr) && count($coremessage_arr) > 0) {
        foreach ($coremessage_arr as $messagepart) {
            $adminmessage->append_text($messagepart[0], $messagepart[1], $messagepart[2]);
        }
    }
    $adminmessage->url = $request_url;
    $adminmessage->eventdata = $eventdata;
    $adminmessage->templatevars = $templatevars;

    // Attach assigned admin to this collection
    add_collection($assigned_to_user['ref'], $collection_id);
    if ($notify_manage_request_admin) {
        send_user_notification([$assigned_to_user['ref']], $adminmessage);
        $notification_sent = true;
    }

    $notify_users = [];
    $notify_emails = [];
    # Check if alternative request email notification address is set, only valid if collection contains resources of the same type
    if (isset($resource_type_request_emails)) {
        $parameters = array("i",$collection_id);
        $requestrestypes = ps_array("SELECT r.resource_type AS value FROM collection_resource cr LEFT JOIN resource r ON cr.resource=r.ref WHERE cr.collection = ?", $parameters);
        $requestrestypes = array_unique($requestrestypes);
        if (count($requestrestypes) == 1 && isset($resource_type_request_emails[$requestrestypes[0]])) {
            // Is this a system user? If so we can send a notification instead of an email
            $emailusers = get_user_by_email($resource_type_request_emails[$requestrestypes[0]]);
            if (is_array($emailusers) && count($emailusers) > 0) {
                $notify_users = array_merge($notify_users, $emailusers);
            } else {
                $notify_emails[] = $resource_type_request_emails[$requestrestypes[0]];
            }
        }
    }

    if (!$notification_sent && (!isset($resource_type_request_emails) || $resource_type_request_emails_and_email_notify)) {
        $admin_notify_users = get_notification_users("RESOURCE_ACCESS");
        $notify_users = array_merge($notify_users, $admin_notify_users);

        # Send the e-mails and/or notification messages
        $adminmessage = new ResourceSpaceUserNotification();
        $adminmessage->set_subject($applicationname . ": ");
        $adminmessage->append_subject("lang_requestcollection");
        $adminmessage->append_subject(" - " . $collection_id);
        $adminmessage->template = $admin_mail_template;
        $adminmessage->templatevars = $templatevars;
        foreach ($notify_emails as $notify_email) {
            // These are not system users so emails must be sent
            send_mail($notify_email, $applicationname . ": " . $lang["requestcollection"] . " - $collection_id", $adminmessage->get_text(), $email_from, $email_from, $admin_mail_template, $templatevars);
        }
        send_user_notification($notify_users, $adminmessage);
    }

    $collection_url = $baseurl . "/?c=" . $collection_id;
    $usermessage = new ResourceSpaceUserNotification();
    $usermessage->set_subject($applicationname . ": ");
    $usermessage->append_subject("lang_requestsent");
    $usermessage->set_text("lang_requestsenttext");
    $usermessage->append_text("<br/><br/>");
    $usermessage->append_text_multi($message->get_text(true));
    $usermessage->append_text("<br/><br/>");
    $usermessage->append_text("lang_viewrequesturl");
    $usermessage->url = $collection_url;
    $templatevars['requesturl'] = $collection_url;
    $usermessage->template = $user_mail_template;
    $usermessage->templatevars = $templatevars;
    send_user_notification([$userref], $usermessage);
    return true;
}

function HookAutoassign_mrequestsAllExport_add_tables()
{
    return array("assign_request_map" => array());
}

function HookAutoassign_mrequestsAllOn_delete_user($ref)
{
    # The user has been deleted so any mappings for them also need to be removed.
    ps_query("DELETE FROM assign_request_map WHERE `user_id` = ?", array("i", $ref));
}
