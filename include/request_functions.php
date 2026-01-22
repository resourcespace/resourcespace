<?php

# Request functions
# Functions to accomodate resource requests

/**
 * Retrieve a resource request record
 *
 * @param  integer $request The request record ID
 * @return mixed False if not found, the resource record (associative array) if found
 */
function get_request($request)
{
    $parameters = array("i",$request);
    $result = ps_query("SELECT u.username,u.fullname,u.email,r.user,r.collection,r.created,r.request_mode,r.status,r.comments,r.expires,r.assigned_to,
                        r.reason,r.reasonapproved,u2.username assigned_to_username 
                FROM request r 
                LEFT OUTER JOIN user u ON r.user=u.ref 
                LEFT OUTER JOIN user u2 ON r.assigned_to=u2.ref WHERE r.ref=?", $parameters);
    if (count($result) == 0) {
        return false;
    } else {
        return $result[0];
    }
}

/**
 * Fetch a list of all requests for a user
 *
 * @param  bool $excludecompleted  Exclude requests that have already been completed
 * @param  bool $returnsql Return the SQL for the execution rather than the results
 * @return mixed
 */
function get_user_requests($excludecompleted = false, $returnsql = false)
{
    global $userref;
    if (!is_numeric($userref)) {
        return false;
    }

    $query_spec = new PreparedStatementQuery();
    $query_spec->parameters = array("i",$userref);
    $query_spec->sql = "select u.username,u.fullname,r.*,if(collection.ref is null,'0',collection.ref) collection_id, 
            (select count(*) from collection_resource cr where cr.collection=r.collection) c 
        from request r 
        left outer join user u on r.user=u.ref 
        left join collection on r.collection = collection.ref 
        where r.user = ?" . ($excludecompleted ? " AND status<>2" : "") . " order by ref desc";
    return $returnsql ? $query_spec : ps_query($query_spec->sql, $query_spec->parameters);
}

/**
 * Handle the posted request form, when saving a request in the admin area.
 *
 * @param  integer $request The request record ID
 * @return boolean  Was this successful?
 */
function save_request($request)
{
    # Use the posted form to update the request
    global $applicationname,$baseurl,$lang,$admin_resource_access_notifications,$userref;

    $status = getval("status", "", true);
    $expires = getval("expires", "");
    $currentrequest = get_request($request);
    $oldstatus = $currentrequest["status"];
    $assigned_to = getval("assigned_to", "");
    $reason = getval("reason", "");
    $reasonapproved = getval("reasonapproved", "");
    $approved_declined = false;

    # --------------------- User Assignment ------------------------
    # Process an assignment change if this user can assign requests to other users
    if ($currentrequest["assigned_to"] != $assigned_to && checkperm("Ra")) {
        if ($assigned_to == 0) {
            # Cancel assignment
            ps_query("UPDATE request SET assigned_to=NULL WHERE ref = ?", array("i",$request));
        } else {
            # Update and notify user
            ps_query("UPDATE request SET assigned_to = ? WHERE ref = ?", array("i",$assigned_to, "i",$request));

            $assigned_to_user = get_user($assigned_to);

            $assignedmessage = new ResourceSpaceUserNotification();
            $assignedmessage->set_subject($applicationname . ": ");
            $assignedmessage->append_subject("lang_requestassignedtoyou");
            $assignedmessage->set_text("lang_requestassignedtoyoumail");
            $assignedmessage->append_text("<br/><br/>");
            $assignedmessage->append_text("lang_username");
            $assignedmessage->append_text(": " . $currentrequest['username'] . "<br/>");
            $assignedmessage->append_text("lang_requestreason");
            $request_reason = substr($currentrequest['comments'], strpos($currentrequest['comments'], ':'));
            $assignedmessage->append_text($request_reason);
            $assignedmessage->user_preference = ["user_pref_resource_access_notifications" => ["requiredvalue" => true,"default" => $admin_resource_access_notifications],"actions_resource_requests" => ["requiredvalue" => false,"default" => true]];
            $assignedmessage->url = $baseurl . "/?q=" . $request;
            $assignedmessage->eventdata = ["type"  => MANAGED_REQUEST,"ref"   => $request];
            send_user_notification([$assigned_to], $assignedmessage);
            // Change text for requesting user
            $assignedmessage->set_text("lang_requestassignedtouser", ["%"], [$assigned_to_user["fullname"] . " (" . $assigned_to_user["email"] . ")" ]);
            send_user_notification([$currentrequest["user"]], $assignedmessage);
        }
    }

    # Has either the status or the expiry date changed?
    if (($oldstatus != $status || $expires != $currentrequest["expires"]) && $status == 1) {
        # --------------- APPROVED -------------
        # Send approval e-mail
        $approved_declined = true;
        $reasonapproved = unescape($reasonapproved);
        $colurl = $baseurl . "/?c=" . $currentrequest["collection"];

        // Don't add event data for the requesting user's notification or message will be deleted immediately
        $approvemessage = new ResourceSpaceUserNotification();
        $approvemessage->set_subject($applicationname . ": ");
        $approvemessage->append_subject("lang_requestcollection");
        $approvemessage->append_subject(" - ");
        $approvemessage->append_subject("lang_resourcerequeststatus1");
        $approvemessage->set_text("lang_requestapprovedmail");
        $approvemessage->append_text("<br /><br />");
        $approvemessage->append_text("lang_approvalreason");
        $approvemessage->append_text(": " . $reasonapproved);
        $approvemessage->url = $colurl;
        $templatevars["message"] = $approvemessage->get_text();
        $templatevars["url"] = $colurl;
        if ($expires != "") {
            # Add expiry time to message.
            $approvemessage->append_text("<br /><br />");
            $approvemessage->append_text("lang_requestapprovedexpires");
            $approvemessage->append_text(" " . nicedate($expires) . "\n");
            $templatevars["expires"] = "<br /><br />" . $lang["requestapprovedexpires"] . " " . nicedate($expires) . "\n";
        } else {
            $templatevars["expires"] = '';
        }
        $approvemessage->template = "requestapprovedmail_email";
        $approvemessage->templatevars = $templatevars;
        send_user_notification([$currentrequest["user"]], $approvemessage);

        # Mark resources as full access for this user
        foreach (get_collection_resources($currentrequest["collection"]) as $resource) {
            open_access_to_user($currentrequest["user"], $resource, $expires);
        }

        # Clear any outstanding notifications about this request that may have been sent to other admins
        message_remove_related(MANAGED_REQUEST, $request);
    } elseif ($oldstatus != $status && $status == 2) {
        # --------------- DECLINED -------------
        # Send declined e-mail
        $approved_declined = true;
        $reason = unescape($reason);
        $colurl = $baseurl . "/?c=" . $currentrequest["collection"];
        $declinemessage = new ResourceSpaceUserNotification();
        $declinemessage->set_subject($applicationname . ": ");
        $declinemessage->append_subject("lang_requestcollection");
        $declinemessage->append_subject(" - ");
        $declinemessage->append_subject("lang_resourcerequeststatus2");
        $declinemessage->set_text("lang_requestdeclinedmail");
        $declinemessage->append_text("<br /><br />");
        $declinemessage->append_text("lang_declinereason");
        $declinemessage->append_text(": " . $reason);
        $declinemessage->url = $colurl;
        $templatevars["message"] = $declinemessage->get_text();
        $templatevars["url"] = $colurl;
        $declinemessage->template = "requestdeclined_email";
        $declinemessage->templatevars = $templatevars;
        send_user_notification([$currentrequest["user"]], $declinemessage);

        # Remove access that my have been granted by an inadvertant 'approved' command.
        foreach (get_collection_resources($currentrequest["collection"]) as $resource) {
            remove_access_to_user($currentrequest["user"], $resource);
        }

        # Clear any outstanding notifications about this request that may have been sent to other admins
        message_remove_related(MANAGED_REQUEST, $request);
    }

    if ($oldstatus != $status && $status == 0) {
        # --------------- PENDING -------------
        # Moved back to pending. Delete any permissions set by a previous 'approve'.
        foreach (get_collection_resources($currentrequest["collection"]) as $resource) {
            remove_access_to_user($currentrequest["user"], $resource);
        }
    }

    # Save status
    $expires_parm = ($expires == "" ? null : $expires);
    $parameters = array("i",$status, "s",$expires_parm, "s",$reason, "s",$reasonapproved, "i",$request);
    ps_query("UPDATE request SET status=?, expires=?, reason=?, reasonapproved=? WHERE ref=?", $parameters);

    # Set user that approved or declined the request
    if ($approved_declined) {
        $parameters = array("i",$userref, "i",$request);
        ps_query("UPDATE request SET approved_declined_by=? WHERE ref=?", $parameters);
    }

    if (getval("delete", "") != "") {
        # Delete the request - this is done AFTER any e-mails have been sent out so this can be used on approval.
        ps_query("DELETE FROM request WHERE ref=?", array("i",$request));

        # Clear any outstanding notifications about this request that may have been sent to other admins
        message_remove_related(MANAGED_REQUEST, $request);
    }

    return true;
}

/**
 * Fetch a list of requests assigned to the logged in user
 *
 * @param  boolean $excludecompleted    Exclude completed requests?
 * @param  boolean $excludeassigned     Exclude assigned requests? (e.g. if the user is able to assign unassigned requests) unless assigned to the logged in user
 * @param  boolean $returnsql           Return SQL query object instead of the results?
 * @return mixed                        Resulting array of requests or an SQL query object
 */
function get_requests($excludecompleted = false, $excludeassigned = false, $returnsql = false)
{
    global $userref;
    $condition = "";

    $parameters = array();

    # Include requests assigned to the user if the user can accept requests (permission "Rb")
    if (checkperm("Rb")) {
        $condition = "WHERE r.assigned_to=?";
        $parameters = array("i",$userref);
    }
    # Include all requests if the user can assign requests (permission "Ra")
    if (checkperm("Ra")) {
        $condition = "";
        $parameters = array();
        # Excluding assigned requests only makes sense if user is able to assign requests
        if ($excludeassigned) {
            $condition = "WHERE (r.assigned_to IS null OR r.assigned_to = ?)"; // Ensure they see requests that have been assigned to them
            $parameters = array("i",$userref);
        }
    }
    # Exclude completed requests if necessary
    if ($excludecompleted) {
        $condition .= (($condition != "") ? " AND" : "WHERE") . " r.status=0";
    }

    $sql = "SELECT u.username,u.fullname,r.*,
          (SELECT count(*) FROM collection_resource cr WHERE cr.collection=r.collection) c,
          u2.username assigned_to_username 
          FROM request r 
          LEFT OUTER JOIN user u ON r.user=u.ref LEFT OUTER JOIN user u2 ON r.assigned_to=u2.ref $condition  ORDER BY status,ref desc";

    $request_query = new PreparedStatementQuery($sql, $parameters);

    if ($returnsql) {
        return $request_query;
    } else {
        return ps_query($request_query->sql, $request_query->parameters);
    }
}

/**
 * Email a collection request to the team responsible for dealing with requests. Request mode 0 only (non managed).
 *
 * @param  integer $ref
 * @param  mixed $details
 * @param  mixed $external_email
 */
function email_collection_request($ref, $details, $external_email): bool
{
    global $applicationname,$email_from,$baseurl,$username,$useremail,$lang,$userref,$resource_type_request_emails,
    $resource_request_reason_required,$resource_type_request_emails_and_email_notify,$admin_resource_access_notifications;

    if (trim($details) == "" && $resource_request_reason_required) {
        return false;
    }

    $message = new ResourceSpaceUserNotification();

    $templatevars['url'] = $baseurl . "/?c=" . $ref;
    $collectiondata = get_collection($ref);
    if (isset($collectiondata["name"])) {
        $templatevars["title"] = $collectiondata["name"];
    }

    # Create a copy of the collection which is the one sent to the team. This is so that the admin
    # user can e-mail back an external URL to the collection if necessary, to 'unlock' full (open) access.
    # The user cannot then gain access to further resources by adding them to their original collection as the
    # shared collection is a copy.
    # A complicated scenario that is best avoided using 'managed requests'.
    $newcopy = create_collection(-2, $lang["requestcollection"]);
    copy_collection($ref, $newcopy);

    // Make sure a collection does not include resources that may have been hidden from the user due
    // to archive state, resource type or access changes and that they are not aware they are requesting.
    // Without this a full copy can confuse the request administrator
    $col_visible = do_search("!collection" . $ref, '', '', '', -1, 'desc', false, 0, false, false, '', false, false, true);
    $colresources = get_collection_resources($ref);
    foreach ($colresources as $colresource) {
        if (!in_array($colresource, array_column($col_visible, "ref"))) {
            remove_resource_from_collection($colresource, $newcopy, false);
        }
    }

    $ref = $newcopy;

    $templatevars["requesturl"] = $baseurl . "/?c=" . $ref;

    if (isset($userref)) {
        $templatevars['username'] = $username . " (" . $useremail . ")";
        $userdata = get_user($userref);
        if ($userdata === false) {
            return false;
        } # Unable to get user credentials
        $templatevars["fullname"] = $userdata["fullname"];
    }

    reset($_POST);
    foreach ($_POST as $key => $value) {
        if (strpos($key, "_label") !== false) {
            # Add custom field
            $setting = trim($_POST[str_replace("_label", "", $key)]);
            if ($setting != "") {
                $message->append_text($value . ": " . $_POST[str_replace("_label", "", $key)] . "<br /><br />");
            }
        }
    }
    if (trim($details) != "") {
        $message->append_text("lang_requestreason");
        $message->append_text(": " . newlines($details) . "<br /><br />");
    }

    # Add custom fields
    global $custom_request_fields,$custom_request_required;
    if (isset($custom_request_fields)) {
        $custom = explode(",", $custom_request_fields);
        # Required fields?
        if (isset($custom_request_required)) {
            $required = explode(",", $custom_request_required);
        }
        for ($n = 0; $n < count($custom); $n++) {
            if (isset($required) && in_array($custom[$n], $required) && getval("custom" . $n, "") == "") {
                return false; # Required field was not set.
            }
            $message->append_text("i18n_" . $custom[$n]);
            $message->append_text(": " . getval("custom" . $n, "") . "<br /><br />");
        }
    }

    $amendedmessage = hook('amend_request_message', '', array($userref, $ref, isset($collectiondata) ? $collectiondata : array(), $message, isset($collectiondata)));

    if ($amendedmessage) {
        $message = $amendedmessage;
    }

    $templatevars["requestreason"] = $message->get_text();

    // Create notification message
    $notification_message = clone $message;
    $notification_message->set_subject($applicationname . ": ");
    $notification_message->append_subject("lang_requestcollection");
    $notification_message->append_subject(" - "  . $ref);
    $introtext[] = ["lang_user_made_request"];
    $introtext[] = ["<br /><br />"];
    if (isset($username)) {
        $introtext[] = ["lang_username"];
        $introtext[] = [": "];
        $introtext[] = [$username];
        $introtext[] = ["<br /><br />"];
    }
    $notification_message->prepend_text_multi($introtext);
    $notification_message->append_text("lang_viewcollection");
    $notification_message->append_text(":");
    $notification_message->url = $templatevars['requesturl'];
    $notification_message->user_preference = ["user_pref_resource_access_notifications" => ["requiredvalue" => true,"default" => $admin_resource_access_notifications]];
    $notification_message->template = "emailcollectionrequest";
    $notification_message->templatevars = $templatevars;

    $notify_users = [];
    $notify_emails = [];
    # Legacy: Check if alternative request email notification address is set, only valid if collection contains resources of the same type
    if (isset($resource_type_request_emails) && !can_use_owner_field()) {
        $requestrestypes = ps_array("SELECT r.resource_type AS value FROM collection_resource cr 
                                    LEFT JOIN resource r ON cr.resource=r.ref WHERE cr.collection = ?", array("i",$ref));
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

    if ((count($notify_users) == 0 && count($notify_emails) == 0) || $resource_type_request_emails_and_email_notify) {
        $admin_notify_users = get_notification_users("RESOURCE_ACCESS");
        $notify_users = array_merge($notify_users, $admin_notify_users);
    }
    $notify_users = array_keys(get_notification_users_by_owner_field($notify_users, $colresources));
    send_user_notification($notify_users, $notification_message);
    foreach ($notify_emails as $notify_email) {
        send_mail($notify_email, $applicationname . ": " . $lang["requestcollection"] . " - $ref", $message->get_text(), $email_from, $email_from, "emailcollectionrequest", $templatevars);
    }

    $userconfirmmessage = clone $message;
    $userconfirmmessage->set_subject($applicationname . ": ");
    $userconfirmmessage->append_subject(" - "  . $ref);
    $userconfirmmessage->prepend_text("<br /><br />");
    $userconfirmmessage->prepend_text("lang_requestsenttext");
    $userconfirmmessage->url = $templatevars['url'];
    $userconfirmmessage->template = "emailusercollectionrequest";
    $userconfirmmessage->templatevars = $templatevars;

    # $userref and $useremail will be that of the internal requestor
    # - We need to send the $userconfirmmessage to the internal requestor saying that their request has been submitted
    if (isset($userref)) {
        send_user_notification([$userref], $userconfirmmessage);
    }

    # $userref and $useremail will be null for external requestor
    # - We can only send an email to the email address provided on the external request
    if (!isset($userref) && filter_var($external_email, FILTER_VALIDATE_EMAIL)) {
        send_mail($external_email, $applicationname . ": " . $lang["requestsent"] . " - $ref", $userconfirmmessage->get_text(), $email_from, null, "emailusercollectionrequest", $templatevars);
    }

    # Increment the request counter for each resource in the requested collection
    ps_query("UPDATE resource set request_count=request_count+1
               where ref in(select cr.resource from collection_resource cr where cr.collection=? and cr.resource = ref)", array("i",$ref));

    return true;
}

/**
 * Request mode 1 - quests are managed via the administrative interface. Sends an e-mail but also logs the request in the request table.
 *
 * @param  mixed $ref
 * @param  mixed $details
 * @param  mixed $ref_is_resource
 *
 * @return boolean
 */
function managed_collection_request($ref, $details, $ref_is_resource = false)
{
    debug_function_call(__FUNCTION__, func_get_args());
    global $applicationname,$email_from,$baseurl,$email_notify,$username,$useremail,$userref,$lang,
        $watermark,$filename_field,$view_title_field,$access,$resource_type_request_emails,
        $resource_type_request_emails_and_email_notify, $manage_request_admin,$resource_request_reason_required,
        $admin_resource_access_notifications, $notify_manage_request_admin,
        $assigned_to_user;

    if (trim($details) == "" && $resource_request_reason_required) {
        return false;
    }

    # Has a resource reference (instead of a collection reference) been passed?
    # Manage requests only work with collections. Create a collection containing only this resource.

    $admin_mail_template = "emailcollectionrequest";
    $user_mail_template = "emailusercollectionrequest";

    if ($ref_is_resource) {
        $resourcedata = get_resource_data($ref);
        $templatevars['thumbnail'] = get_resource_path($ref, true, "thm", false, "jpg", -1, 1, ($watermark) ? (($access == 1) ? true : false) : false);

        # Allow alternative configuration settings for this resource type
        resource_type_config_override($resourcedata['resource_type']);

        if (!file_exists($templatevars['thumbnail'])) {
            $templatevars['thumbnail'] = "../gfx/no_preview/default.png";
        }
        $templatevars['url'] = $baseurl . "/?r=" . $ref;
        if (isset($filename_field)) {
            $templatevars["filename"] = $lang["fieldtitle-original_filename"] . ": " . get_data_by_field($ref, $filename_field);
        }
        if (isset($resourcedata["field" . $view_title_field])) {
            $templatevars["title"] = $resourcedata["field" . $view_title_field];
        }

        $c = create_collection($userref, $lang["request"] . " " . date("ymdHis"), 0, 0, 0, false, array("type" => COLLECTION_TYPE_REQUEST));
        add_resource_to_collection($ref, $c, true);
        $ref = $c; # Proceed as normal
        $colresources = get_collection_resources($ref);
    } else {
        # Create a copy of the collection to attach to the request so that subsequent collection changes do not affect the request
        $c = create_collection($userref, $lang["request"] . " " . date("ymdHis"), 0, 0, 0, false, array("type" => COLLECTION_TYPE_REQUEST));
        copy_collection($ref, $c);

        // Make sure a collection does not include resources that may have been hidden from the user due
        // to archive state, resource type or access changes and that they are not aware they are requesting.
        // Without this a full copy can confuse the request administrator
        $col_visible = do_search("!collection" . $ref, '', '', '', -1, 'desc', false, 0, false, false, '', false, false, true);
        $colresources = get_collection_resources($ref);

        foreach ($colresources as $colresource) {
            if (!in_array($colresource, array_column($col_visible, "ref"))) {
                remove_resource_from_collection($colresource, $c, true);
            }
        }

        $ref = $c; # Proceed as normal

        $collectiondata = get_collection($ref);
        $templatevars['url'] = $baseurl . "/?c=" . $ref;
        if (isset($collectiondata["name"])) {
            $templatevars["title"] = $collectiondata["name"];
        }
    }

    # Formulate e-mail text
    $templatevars['username'] = $username;
    $templatevars["useremail"] = $useremail;
    $userdata = get_user($userref);
    $templatevars["fullname"] = $userdata["fullname"];

    // set up notification object
    $message = new ResourceSpaceUserNotification();

    $templatevars["extra"] = "";
    reset($_POST);
    foreach ($_POST as $key => $value) {
        if (strpos($key, "_label") !== false) {
            # Add custom field
            $setting = trim($_POST[str_replace("_label", "", $key)]);
            if ($setting != "") {
                $message->append_text($value . ": " . $setting . "\n");
                $templatevars["extra"] .= $value . ": " . $setting . "<br /><br />";
            }
        }
    }
    if (trim($details) != "") {
        $message->append_text("lang_requestreason");
        $message->append_text(": " . newlines($details));
        $templatevars["requestreason"] = newlines($details);
    }

    # Add custom fields
    $c = "";
    global $custom_request_fields,$custom_request_required;
    if (isset($custom_request_fields)) {
        $custom = explode(",", $custom_request_fields);

        # Required fields?
        if (isset($custom_request_required)) {
            $required = explode(",", $custom_request_required);
        }

        for ($n = 0; $n < count($custom); $n++) {
            if (isset($required) && in_array($custom[$n], $required) && getval("custom" . $n, "") == "") {
                debug('Required field was not set.');
                return false;
            }
            $message->append_text("<br />\n");
            $message->append_text("i18n_" . $custom[$n]);
            $message->append_text(": " . getval("custom" . $n, ""));
        }
    }

    $assignedmessage = new ResourceSpaceUserNotification();
    $assignedmessage->set_text("lang_requestassignedtoyoumail");
    $assignedmessage->append_text("<br /><br />");
    $assignedmessage->set_subject($applicationname . ": ");
    $assignedmessage->append_subject("lang_requestassignedtoyou");
    // Add core message text (reason, custom fields etc.)
    $assignedmessage->append_text_multi($message->get_text(true));
    $amendedmessage = hook('amend_request_message', '', array($userref, $ref, isset($collectiondata) ? $collectiondata : array(), $message, isset($collectiondata)));
    if ($amendedmessage) {
        $assignedmessage->set_text($amendedmessage);
    }

    # Setup the principal create request SQL
    global $request_query;
    $request_query = new PreparedStatementQuery();
    $request_query->sql = "INSERT INTO request(user, collection, created, request_mode, status, comments) 
                            VALUES (?, ?, NOW(), 1, 0, ?)";
    $request_query->parameters = array("i",$userref, "i",$ref, "s",$message->get_text());

    // Set flag to send default notifications unless we override e.g. by $manage_request_admin
    $send_default_notifications = true;

    $notify_manage_request_admin = false;
    $notification_sent = false;

    // The following hook determines the assigned administrator
    // If there isn't one, then the principal request_query setup earlier remains as-is without an assigned_to
    // If there is one then the hook replaces the request_query with one which has an assigned_to
    hook('autoassign_individual_requests', '', array($userref, $ref, $message, isset($collectiondata)));

    // Regular Processing: autoassign using the resource type - one resource was requested and no plugin is preventing this from running
    if ($ref_is_resource && !is_null($manage_request_admin) && is_array($manage_request_admin) && !empty($manage_request_admin)) {
        debug('Auto-assign (one resource) using the resource type (config: $manage_request_admin)');
        $admin_notify_user = 0;
        $request_resource_type = $resourcedata["resource_type"];
        if (array_key_exists($request_resource_type, $manage_request_admin)) {
            $admin_notify_user = $manage_request_admin[$request_resource_type];

            $request_query->sql = "INSERT INTO request(user, collection, created, request_mode, status, comments, assigned_to)
                                    VALUES (?, ?, NOW(), 1, 0, ?, ?)";
            $request_query->parameters = array("i",$userref, "i",$ref, "s",$message->get_text(), "i",$admin_notify_user);

            // Setup assigned to user for bypass hook later on
            if ($admin_notify_user !== 0) {
                $assigned_to_user = get_user($admin_notify_user);
                $notify_manage_request_admin = true;
            }
        }
    }

    hook('autoassign_collection_requests', '', array($userref, isset($collectiondata) ? $collectiondata : array(), $message, isset($collectiondata)));

    $can_use_owner_field = can_use_owner_field();

    // Regular Processing: autoassign using the resource type - collection request and no plugin is preventing this from running
    if (isset($collectiondata) && !is_null($manage_request_admin) && is_array($manage_request_admin) && !empty($manage_request_admin)) {
        debug('Auto-assign (collection request) using the resource type (config: $manage_request_admin)');
        $all_r_types = get_resource_types();

        $resources = get_collection_resources($collectiondata['ref']);
        $resources = ($resources === false ? array() : $resources);

        // Get distinct resource types found in this collection:
        $collection_resources_by_type = array();
        foreach ($resources as $resource_id) {
            $resource_data = get_resource_data($resource_id);
            // Create a list of resource IDs based on type to separate them into different collections:
            $collection_resources_by_type[$resource_data['resource_type']][] = $resource_id;
        }

        // Split into collections based on resource type:
        foreach ($collection_resources_by_type as $collection_type => $collection_resources) {
            // Store all resources of unmanaged type in one collection which will be sent to the system administrator:
            if (!isset($manage_request_admin[$collection_type])) {
                $collections['not_managed'] = create_collection($userref, $collectiondata['name'] . " " . date("ymdHis"), 0, 0, 0, false, array("type" => COLLECTION_TYPE_REQUEST));
                foreach ($collection_resources as $collection_resource_id) {
                    add_resource_to_collection($collection_resource_id, $collections['not_managed']);
                }
                continue;
            }

            $r_type_index = array_search($collection_type, array_column($all_r_types, 'ref'));
            if ($r_type_index === false) {
                continue;
            }

            $collections[$collection_type] = create_collection($userref, $collectiondata['name'] . ' : ' . $all_r_types[$r_type_index]["name"] . " " . date("ymdHis"), 0, 0, 0, false, array("type" => COLLECTION_TYPE_REQUEST));
            foreach ($collection_resources as $collection_resource_id) {
                // Add collection resources of the given type to the resource type specific collection
                // The col_access_control parameter is true meaning that adding to the collection is permitted for this call
                add_resource_to_collection($collection_resource_id, $collections[$collection_type], false, "", "", true);
            }
        }
        if (isset($collections) && count($collections) > 0) {
            foreach ($collections as $request_resource_type => $collection_id) {
                $assigned_to = '';
                $assigned_to_users = array();
                $assigned_to_user_emails = array();
                if (array_key_exists($request_resource_type, $manage_request_admin)) {
                    $assigned_to_users[] = $manage_request_admin[$request_resource_type];
                } else {
                    // No specific user allocated, get all users, adding $email_notify address if this does not belong to a system user
                    $assigned_to_users = get_notification_users("RESOURCE_ACCESS");
                    $email_notify_is_user = false;
                    foreach ($assigned_to_users as $assigned_to_user) {
                        if ($assigned_to_user['email'] == $email_notify) {
                            $email_notify_is_user = true;
                        }
                    }
                    if (!$email_notify_is_user) {
                        $assigned_to_user_emails[] = $email_notify;
                    }
                }

                if (trim($assigned_to) != '') {
                    $request_query = "INSERT INTO request(user, collection, created, request_mode, `status`, comments, assigned_to)
                                            VALUES (?, ?, NOW(), 1, 0, ?, ?);";
                    $parameters = array("i", $userref, "i",$collection_id, "s",$message->get_text(), "i",$assigned_to);
                } else {
                    $request_query = "INSERT INTO request(user, collection, created, request_mode, `status`, comments)
                                           VALUES (?, ?, NOW(), 1, 0, ?);";
                    $parameters = array("i", $userref, "i",$collection_id, "s",$message->get_text());
                }

                ps_query($request_query, $parameters);
                $request = sql_insert_id();

                $assignedmessage->user_preference = ["user_pref_resource_access_notifications" => ["requiredvalue" => true,"default" => $admin_resource_access_notifications],"actions_resource_requests" => ["requiredvalue" => false,"default" => true]];
                $assignedmessage->url = $baseurl . "/?q=" . $request;
                $assignedmessage->eventdata = ["type"  => MANAGED_REQUEST,"ref"   => $request];
                send_user_notification($assigned_to_users, $assignedmessage);
            } # End for each collection

            $notify_manage_request_admin = false;
            $notification_sent = true;
            $send_default_notifications = false;
        } else {
            # No collections
            return false;
        }
    } // End of default manager (regular processing)
    else {
        if (hook('bypass_end_managed_collection_request', '', array(!isset($collectiondata), $ref, $request_query, $message, $templatevars, $assigned_to_user, $admin_mail_template, $user_mail_template))) {
            return true;
        } else // Back to regular processing
            {
            ps_query($request_query->sql, $request_query->parameters);
            $request = sql_insert_id();
        }
    }

    hook("afterrequestcreate", "", array($request));

    if ($send_default_notifications) {
        debug('Send default notifications');
        # Automatically notify the admin who was assigned the request if we set this earlier:
        $templatevars["request_id"] = $request;
        $templatevars["requesturl"] = $baseurl . "/?q=" . $request;

        $admin_notify_message = new ResourceSpaceUserNotification();
        $admin_notify_message->set_subject($applicationname . ": ");
        $admin_notify_message->append_subject("lang_requestassignedtoyou");
        $admin_notify_message->set_text("lang_requestassignedtoyoumail");
        $admin_notify_message->append_text("<br /><br />");
        $admin_notify_message->append_text("lang_username");
        $admin_notify_message->append_text(": " . $username . "<br />\n");
        $admin_notify_message->append_text_multi($message->get_text(true));
        $admin_notify_message->user_preference = ["user_pref_resource_access_notifications" => ["requiredvalue" => true,"default" => $admin_resource_access_notifications]];
        $admin_notify_message->url = $templatevars['requesturl'];
        $admin_notify_message->eventdata = ["type" => MANAGED_REQUEST,"ref" => $request];
        if ($notify_manage_request_admin) {
            send_user_notification([$admin_notify_user], $admin_notify_message);
            $notification_sent = true;
        }

        $admin_notify_users = array();

        # Legacy: Check if alternative request email notification address is set, only valid if collection contains resources of the same type
        if (isset($resource_type_request_emails) && !$can_use_owner_field) {
            debug('[legacy] Check if alternative request email notification address is set (config: $resource_type_request_emails)');
            // Legacy support for $resource_type_request_emails
            $requestrestypes = ps_array("SELECT r.resource_type AS value FROM collection_resource cr LEFT JOIN resource r ON cr.resource=r.ref WHERE cr.collection=?", array("i",$ref));
            $requestrestypes = array_unique($requestrestypes);
            if (count($requestrestypes) == 1 && isset($resource_type_request_emails[$requestrestypes[0]])) {
                $emailusers = get_user_by_email($resource_type_request_emails[$requestrestypes[0]]);
            }
            if (is_array($emailusers) && count($emailusers) > 0) {
                send_user_notification($emailusers, $admin_notify_message);
            } else {
                send_mail($resource_type_request_emails[$requestrestypes[0]], $applicationname . ": " . $lang["requestcollection"] . " - $ref", "<p>" . $admin_notify_message->get_text() . "</p>" . $admin_notify_message->url, $email_from, $email_from, $admin_mail_template, $templatevars);
            }
        }

        if (!$notification_sent && $can_use_owner_field) {
            debug('Send notification to resource owner');
            $admin_notify_users = array_keys(get_notification_users_by_owner_field(get_notification_users("RESOURCE_ACCESS"), $colresources));
            $admin_notify_message->set_subject($applicationname . ": ");
            $admin_notify_message->append_subject("lang_requestcollection");
            $admin_notify_message->append_subject(" - " . $ref);
            $admin_notify_message->eventdata = ["type" => MANAGED_REQUEST,"ref" => $request];
            send_user_notification($admin_notify_users, $admin_notify_message);
            $notification_sent = true;
        }

        if (!$notification_sent) {
            debug('Send notifications to admins that have no action appearing');
            $default_notify_users = get_notification_users("RESOURCE_ACCESS");
            // Exclude any users who will already have an action appearing
            $action_users = get_config_option_users("actions_resource_requests", true);
            $admin_notify_users = array_diff(array_column($default_notify_users, "ref"), $action_users);

            $admin_notify_message->set_subject($applicationname . ": ");
            $admin_notify_message->append_subject("lang_requestcollection");
            $admin_notify_message->append_subject(" - " . $ref);
            $admin_notify_message->eventdata = ["type" => MANAGED_REQUEST,"ref" => $request];
            $admin_notify_message->user_preference = [
                "user_pref_resource_access_notifications" => [
                    "requiredvalue" => true,
                    "default" => $admin_resource_access_notifications,
                ],
                "actions_resource_requests" => [
                    "requiredvalue" => false,
                    "default" => true,
                ],
            ];
            send_user_notification($admin_notify_users, $admin_notify_message);
        }
    }

    $user_message = new ResourceSpaceUserNotification();
    $user_message->set_subject($applicationname . ": ");
    $user_message->append_subject("lang_requestsent");
    $user_message->append_subject(" - " . $ref);
    $user_message->set_text("lang_requestsenttext");
    $user_message->append_text("<br /><br />");
    $user_message->append_text_multi($message->get_text(true));
    $user_message->append_text("<br /><br />");
    $user_message->append_text("lang_clicktoviewresource");
    $user_message->url = $baseurl . "/?c=" . $ref;
    // Note no user_preference set so that message will always send
    send_user_notification([$userref], $user_message);

    # Increment the request counter for each resource in the requested collection
    ps_query("UPDATE resource SET request_count=request_count+1  
               WHERE ref IN(SELECT cr.resource FROM collection_resource cr WHERE cr.collection=? AND cr.resource = ref)", array("i",$ref));

    return true;
}


/**
 * E-mails a basic resource request for a single resource (posted) to the team (not a managed request)
 *
 * @param  mixed $ref   The resource ID
 * @param  mixed $details   The request details provided by the user
 * @return void|false|string
 */
function email_resource_request($ref, $details)
{
    global $applicationname,$email_from,$baseurl,$email_notify,$username,$useremail,$userref,$lang,$watermark,$filename_field,$view_title_field,$access,$resource_type_request_emails,$resource_request_reason_required, $user_dl_limit, $user_dl_days, $k, $user_is_anon,$resource_type_request_emails_and_email_notify,$admin_resource_access_notifications;

    $message = new ResourceSpaceUserNotification();
    $detailstext = new ResourceSpaceUserNotification();
    $detailstext->set_text($details);

    if (intval($user_dl_limit) > 0) {
        $download_limit_check = get_user_downloads($userref, $user_dl_days);
        if ($download_limit_check >= $user_dl_limit) {
            $detailstext->prepend_text("<br />");
            $detailstext->prepend_text("lang_download_limit_request_text", ["[downloaded]","[limit]"], [$download_limit_check,$user_dl_limit]);
        }
    }

    $resourcedata = get_resource_data($ref);
    $templatevars['thumbnail'] = get_resource_path($ref, true, "thm", false, "jpg", -1, 1, ($watermark) ? (($access == 1) ? true : false) : false);
    if (!file_exists($templatevars['thumbnail'])) {
        $templatevars['thumbnail'] = "../gfx/no_preview/default.png";
    }

    if (isset($filename_field)) {
        $templatevars["filename"] = $lang["fieldtitle-original_filename"] . ": " . get_data_by_field($ref, $filename_field);
    }
    if (isset($resourcedata["field" . $view_title_field])) {
        $templatevars["title"] = $resourcedata["field" . $view_title_field];
    }
    $templatevars['username'] = $username . " (" . $useremail . ")";
    $templatevars['formfullname'] = getval("fullname", "");
    $templatevars['formemail'] = getval("email", "");
    $templatevars['formtelephone'] = getval("contact", "");
    $templatevars['url'] = $baseurl . "/?r=" . $ref;
    $templatevars["requesturl"] = $templatevars['url'];

    // for anon user access use form vars
    if ($k != "" || $user_is_anon) {
        $templatevars["fullname"] = getval("fullname", "");
        $useremail = getval("email", "");
    } else {
        $userdata = get_user($userref);
        $templatevars["fullname"] = isset($userdata["fullname"]) ? $userdata["fullname"] : "";
    }

    $htmlbreak = "<br /><br />";

    $list = "";
    reset($_POST);
    foreach ($_POST as $key => $value) {
        if (strpos($key, "_label") !== false) {
            # Add custom field
            $data = "";
            $data = $_POST[str_replace("_label", "", $key)];
            $list .= $htmlbreak . $value . ": " . $data . "\n";
        }
    }
    $list .= $htmlbreak;
    $templatevars['list'] = $list;
    $templatevars['details'] = stripslashes($detailstext->get_text());
    $adddetails = "";
    if ($templatevars['details'] != "") {
        $adddetails = $lang["requestreason"] . ": " . newlines($templatevars['details']) . "<br />";
    } elseif ($resource_request_reason_required) {
        return false;
    }

    # Add custom fields
    $c = "";
    global $custom_request_fields,$custom_request_required;
    if (isset($custom_request_fields)) {
        $custom = explode(",", $custom_request_fields);
        # Required fields?
        if (isset($custom_request_required)) {
            $required = explode(",", $custom_request_required);
        }
        for ($n = 0; $n < count($custom); $n++) {
            if (isset($required) && in_array($custom[$n], $required) && getval("custom" . $n, "") == "") {
                # Required field was not set.
                return false;
            }
            $c .= i18n_get_translated($custom[$n]) . ": " . getval("custom" . $n, "") . "<br />";
        }
    }
    $templatevars["requestreason"] = $lang["requestreason"] . ": " . $templatevars['details'] . $c . "";
    if (isset($username)) {
        $message->append_text("lang_username");
        $message->append_text(": " . $username . " (" . $useremail . ")<br />");
    }
    if (!empty($templatevars["formfullname"])) {
        $message->append_text("lang_fullname");
        $message->append_text(": " . $templatevars["formfullname"] . "<br />");
    }
    if (!empty($templatevars["formemail"])) {
        $message->append_text("lang_email");
        $message->append_text(": " . $templatevars["formemail"] . "<br />");
    }
    if (!empty($templatevars["formtelephone"])) {
        $message->append_text("lang_contacttelephone");
        $message->append_text(": " . $templatevars["formtelephone"] . "<br />");
    }

    $notification_message = clone $message;
    $notification_message->set_subject($applicationname . ": ");
    $notification_message->append_subject("lang_requestresource");
    $notification_message->append_subject(" - "  . $ref);
    $notification_message->prepend_text($htmlbreak);
    $notification_message->prepend_text("lang_user_made_request");
    $notification_message->append_text($adddetails . $c);
    $notification_message->append_text("<br />");
    $notification_message->append_text("lang_clicktoviewresource");
    $notification_message->user_preference = ["user_pref_resource_access_notifications" => ["requiredvalue" => true,"default" => $admin_resource_access_notifications]];
    $notification_message->url = $templatevars['url'];

    $notify_users = [];
    $notify_emails = [];
    # Legacy: Check if alternative request email notification address is set
    if (isset($resource_type_request_emails) && !can_use_owner_field()) {
        if (isset($resource_type_request_emails[$resourcedata["resource_type"]])) {
            $restype_email = $resource_type_request_emails[$resourcedata["resource_type"]];
        }
        $emailusers = get_user_by_email($restype_email);
        if (is_array($emailusers) && count($emailusers) > 0) {
            $notify_users = array_merge($notify_users, $emailusers);
        } else {
            $notify_emails[] = $restype_email;
        }
    }

    if ((count($notify_users) == 0 && count($notify_emails) == 0) || $resource_type_request_emails_and_email_notify) {
        // Add the default notifications
        $admin_notify_users = get_notification_users("RESOURCE_ACCESS");
        $notify_users = array_merge($notify_users, $admin_notify_users);
    }
    $notify_users = array_keys(get_notification_users_by_owner_field($notify_users, [$ref]));

    send_user_notification($notify_users, $notification_message);
    foreach ($notify_emails as $notify_email) {
        $send_result = send_mail($notify_email, $applicationname . ": " . $lang["requestresource"] . " - $ref", $message->get_text(), $email_from, $email_from, "emailresourcerequest", $templatevars);
        if ($send_result !== true) {
            return $send_result;
        }
    }

    $userconfirmmessage = clone $message;
    $userconfirmmessage->set_subject($applicationname . ": ");
    $userconfirmmessage->append_subject(" - "  . $ref);
    $userconfirmmessage->prepend_text("<br /><br />");
    $userconfirmmessage->prepend_text("lang_requestsenttext");
    $userconfirmmessage->append_text($adddetails . $c);
    $key_str = ($k != "") ? "&k=" . $k : "";

    if (isset($userref)) {
        $userconfirmmessage->url = $baseurl . "/?r=" . $ref . $key_str;
        send_user_notification([$userref], $userconfirmmessage);
    } else {
        $sender =  (!empty($useremail)) ? $useremail : ((!empty($templatevars["formemail"])) ? $templatevars["formemail"] : "");
        if ($sender != "" && filter_var($sender, FILTER_VALIDATE_EMAIL)) {
            $userconfirmmessage->append_text("<br /><a href='" . $baseurl . "/?r=" . $ref . $key_str . "'>" . $baseurl . "/?r=" . $ref . $key_str . "</a>");
            send_mail($sender, $applicationname . ": " . $lang["requestsent"] . " - $ref", $userconfirmmessage->get_text(), $email_from, $email_notify);
        }
    }


    # Increment the request counter
    ps_query("UPDATE resource SET request_count=request_count+1 WHERE ref = ?", array("i",$ref));
}

/**
* Get collection of valid custom fields. A valid fields has at least the expected field properties
*
* IMPORTANT: these fields are not metadata fields - they are configured through config options such as custom_researchrequest_fields
*
* @param  array  $fields  List of custom fields. Often this will simply be the global configuration option (e.g custom_researchrequest_fields)
*
* @return array
*/
function get_valid_custom_fields(array $fields)
{
    return array_filter($fields, function ($field) {
        global $lang, $FIXED_LIST_FIELD_TYPES;

        $expected_field_properties = array("id", "title", "type", "required");
        $available_properties      = array_keys($field);
        $missing_required_fields   = array_diff(
            $expected_field_properties,
            array_intersect($expected_field_properties, $available_properties)
        );

        if (count($missing_required_fields) > 0) {
            debug("get_valid_custom_fields: custom field misconfigured. Missing properties: "
                . implode(", ", array_values($missing_required_fields)));
            return false;
        }

        // options property required for fixed list fields type
        if (in_array($field["type"], $FIXED_LIST_FIELD_TYPES) && !array_key_exists("options", $field)) {
            debug("get_valid_custom_fields: custom fixed list field misconfigured. Missing the 'options' property!");
            return false;
        }

        return true;
    });
}

/**
* Generate HTML properties for custom fields. These properties can then be used by other functions
* like render_custom_fields or process_custom_fields_submission
*
* @param  array  $fields  List of custom fields as returned by get_valid_custom_fields(). Note: At this point code
* assumes fields have been validated
* @param  array  $ctx     Contextual information for {@see process_custom_fields_submission()}
*
* @return array Returns collection items with the extra "html_properties" key
*/
function gen_custom_fields_html_props(array $fields, array $ctx): array
{
    $prefix = $ctx['html_properties_prefix'] ?? 'custom_field_';
    return array_map(function ($field) use ($prefix) {
        $ident = $prefix . $field['id'];
        $field["html_properties"] = array(
            "id"   => $ident,
            "name" => (!empty($field["options"]) ? "{$ident}[]" : $ident),
        );
        return $field;
    }, $fields);
}

/**
* Process posted custom fields
*
* @param  array    $fields     List of custom fields
* @param  boolean  $submitted  Processing submitted fields?
* @param  array    $ctx        Contextual information (e.g. change the prefix for html tags' identifiers)
*
* @return array Returns collection of items with the extra "html_properties" key
*/
function process_custom_fields_submission(array $fields, $submitted, array $ctx): array
{
    return array_map(function ($field) use ($submitted) {
        global $lang, $FIXED_LIST_FIELD_TYPES;

        $field["value"] = trim(getval($field["html_properties"]["name"], ""));

        if (in_array($field["type"], $FIXED_LIST_FIELD_TYPES)) {
            // The HTML id and name are basically identical (@see gen_custom_fields_html_props() ). If field is of fixed
            // list type, then the name prop will be appended with "[]". For this reason, when we call getval() we need
            // to use the elements' ID instead.
            $submitted_data = getval($field["html_properties"]["id"], [], false, 'is_array');

            // Find the selected options
            $field["selected_options"] = array_filter($field["options"], function ($option) use ($field, $submitted_data) {
                return in_array(md5("{$field["html_properties"]["id"]}_{$option}"), $submitted_data);
            });

            $field["value"] = implode(", ", $field["selected_options"]);
        }

        if ($submitted && ($error = hook('process_custom_fields_submission_validator', '', [$field]))) {
            // Allow plugins to validate (their own) custom field types or override existing behaviour
            $field['error'] = $error;
        } elseif ($submitted && $field['required'] && $field['value'] == '') {
            $field['error'] = str_replace(
                '%field',
                i18n_get_translated($field['title']),
                $lang['researchrequest_custom_field_required']
            );
        }

        return $field;
    }, gen_custom_fields_html_props(get_valid_custom_fields($fields), $ctx));
}

/**
 * Initialisation and system check if configuration is correctly enabled to use the owner field and mappings logic.
 *
 * IMPORTANT: during init the globals $owner_field & $owner_field_mappings values will be updated for validation purposes
 *
 * @return boolean Return true if the system is configured with a valid $owner_field and numeric $owner_field_mappings, false otherwise.
 */
function can_use_owner_field()
{
    $GLOBALS['owner_field'] = is_int_loose($GLOBALS['owner_field']) ? (int) $GLOBALS['owner_field'] : 0;

    // Filter out non numeric user group IDs
    $GLOBALS['owner_field_mappings'] = array_filter($GLOBALS['owner_field_mappings'], 'is_int_loose');

    // Filter out non numeric node IDs
    $GLOBALS['owner_field_mappings'] = array_intersect_key(
        $GLOBALS['owner_field_mappings'],
        array_flip(array_filter(array_keys($GLOBALS['owner_field_mappings']), 'is_int_loose'))
    );

    return $GLOBALS['owner_field'] > 0
        && !empty($GLOBALS['owner_field_mappings'])
        && in_array($GLOBALS['owner_field'], array_column(
            get_resource_type_fields('', 'ref', 'asc', '', [FIELD_TYPE_DROP_DOWN_LIST, FIELD_TYPE_RADIO_BUTTONS], false),
            'ref'
        ));
}

/**
 * Get users to notify for requested resources "owned" by particular groups. Configurable using a metadata field
 * ($owner_field) and a defined map ($owner_field_mappings).
 *
 * @param array $users     List of notification users {@see get_notification_users()}. Any array structure where each
 *                         value contains an array with at least a "ref" and "email" keys.
 * @param array $resources List of resource IDs
 *
 * @return array Returns user ID (key) and email (value)
 * */
function get_notification_users_by_owner_field(array $users, array $resources)
{
    $users_map_ref_email = array_column($users, 'email', 'ref');

    if (!can_use_owner_field()) {
        return $users_map_ref_email;
    }

    global $owner_field, $owner_field_mappings;

    $users_to_notify = [];

    // Build a list of owner field nodes belonging to the incoming list of resources; true means get full node details
    $resource_nodes = get_resource_nodes_batch($resources, [$owner_field], true);

    // There are resources, but there is no routing data (ie. no owners to route to), so no users are filtered out
    if (!empty($resources) && empty($resource_nodes)) {
        return $users_map_ref_email;
    }

    // Determine which users should be notified based on the owner field value and its mappings
    foreach ($resource_nodes as $rtf_rns) {
        $owner_field_node_id = $rtf_rns[$owner_field][0]['ref'] ?? 0;
        $mapped_group = $owner_field_mappings[$owner_field_node_id] ?? 0;
        if ($owner_field_node_id > 0 && $mapped_group > 0) {
            // Build a list of userrefs within the mapped group
            $group_users = array_column(get_users($mapped_group, '', 'u.username', false, -1, 1, false, 'u.ref'), 'ref');
            // Each of the userrefs which is a member of the mapped group will be notified
            $users_to_notify += array_intersect_key($users_map_ref_email, array_flip($group_users));
        }
    }

    return $users_to_notify;
}

/**
 * Can the logged in user see the request specified?
 *
 * @param array $request    Array of request details
 *
 * @return bool
 *
 */
function resource_request_visible(array $request): bool
{
    global $userref;
    $show_this_request = false;
    # Show request if the user can accept requests (permission "Rb")
    if (checkperm("Rb") && ($request["assigned_to"] == $userref)) {
        $show_this_request = true;
    }
    # Show request if the user can assign requests (permission "Ra")
    if (checkperm('Ra')) {
        $show_this_request = true;
    }
    return $show_this_request;
}
