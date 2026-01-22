<?php

# Research functions
# Functions to accomodate research requests

/**
 * Sends a research request by inserting it into the requests table and notifying the relevant users.
 *
 * This function takes an array of custom fields related to the research request,
 * processes the input data, and sends an email notification to the designated research admins.
 * It gathers resource types, deadlines, contact information, and custom fields, and stores them
 * in the database. It also constructs and sends a notification message with the request details.
 *
 * @param array $rr_cfields An array of custom fields associated with the research request.
 * @return void This function does not return any value but performs database operations and sends notifications.
 * @throws Exception If there is an error during JSON encoding of custom fields.
 */
function send_research_request(array $rr_cfields)
{
    # Insert a search request into the requests table.
    global $baseurl,$username,$userfullname,$useremail, $userref;

    # Resolve resource types
    $rt = "";
    $types = get_resource_types();
    for ($n = 0; $n < count($types); $n++) {
        if (getval("resource" . $types[$n]["ref"], "") != "") {
            if ($rt != "") {
                $rt .= ", ";
            }
            $rt .= $types[$n]["ref"];
        }
    }
    $as_user = getval("as_user", $userref, true); # If userref submitted, use that, else use this user
    $rr_name = getval("name", "");
    $rr_description = getval("description", "");
    $parameters = array("i",$as_user, "s",$rr_name, "s",$rr_description);

    $rr_deadline = getval("deadline", "");
    if ($rr_deadline == "") {
        $rr_deadline = null;
    }
    $rr_contact = mb_strcut(getval("contact", ""), 0, 100);
    $rr_email = mb_strcut(getval("email", ""), 0, 200);
    $rr_finaluse = getval("finaluse", "");
    $parameters = array_merge($parameters, array("s",$rr_deadline, "s",$rr_contact, "s",$rr_email, "s",$rr_finaluse));

    # $rt
    $rr_noresources = getval("noresources", "");
    if ($rr_noresources == "") {
        $rr_noresources = null;
    }
    $rr_shape = mb_strcut(getval("shape", ""), 0, 50);

    $parameters = array_merge($parameters, array("s",$rt, "i",$rr_noresources, "s",$rr_shape));

    /**
    * @var string JSON representation of custom research request fields after removing the generated HTML properties we
    *             needed during form processing
    * @see gen_custom_fields_html_props()
    */
    $rr_cfields_json = json_encode(array_map(function ($v) {
        unset($v["html_properties"]);
        return $v;
    }, $rr_cfields), JSON_UNESCAPED_UNICODE);
    if (json_last_error() !== JSON_ERROR_NONE) {
        trigger_error(json_last_error_msg());
    }
    $rr_cfields_json_sql = ($rr_cfields_json == "" ? "" : $rr_cfields_json);
    $parameters = array_merge($parameters, array("s",$rr_cfields_json_sql));

    ps_query("insert into research_request(created,user,name,description,deadline,contact,email,finaluse,resource_types,noresources,shape, custom_fields_json)
                values (now(), ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)", $parameters);

    # Send request
    $templatevars['ref'] = sql_insert_id();
    $templatevars['teamresearchurl'] = $baseurl . "/pages/team/team_research_edit.php?ref=" . $templatevars['ref'];
    $templatevars['username'] = $username;
    $templatevars['userfullname'] = $userfullname;
    $templatevars['useremail'] = getval("email", $useremail); # Use provided e-mail (for anonymous access) or drop back to user email.
    $templatevars['url'] = $baseurl . "/pages/team/team_research_edit.php?ref=" . $templatevars['ref'];

    $research_notify_users = get_notification_users("RESEARCH_ADMIN");
    $userconfirmmessage = new ResourceSpaceUserNotification();
    $userconfirmmessage->set_subject("lang_newresearchrequestwaiting");
    $userconfirmmessage->set_text("'$username' ($userfullname - $useremail) ");
    $userconfirmmessage->append_text("lang_haspostedresearchrequest");
    $userconfirmmessage->append_text(".\n\n");
    $userconfirmmessage->user_preference = "user_pref_resource_access_notifications";
    $userconfirmmessage->template = "emailnewresearchrequestwaiting";
    $userconfirmmessage->templatevars = $templatevars;
    $userconfirmmessage->url = $templatevars["teamresearchurl"];

    // Hook needs to update the ResourceSpaceUserNotification object
    hook("modifyresearchrequestemail", "", array($userconfirmmessage));
    send_user_notification($research_notify_users, $userconfirmmessage);
}

/**
 * Retrieves research requests from the database, optionally filtering by a search term
 * and sorting the results by a specified field.
 *
 * @param string $find Optional search term to filter research requests by name, description, contact, or reference number.
 * @param string $order_by The field to sort the results by. Valid options are 'ref', 'name', 'created', 'status', or 'assigned_to'.
 * @param string $sort The sort direction, either 'ASC' or 'DESC'. Defaults to 'ASC'.
 *
 * @return array An array of research requests that match the search criteria.
 */
function get_research_requests($find = "", $order_by = "name", $sort = "ASC")
{
    $searchsql = "";
    $use_order_by = "";
    $use_sort = validate_sort_value($sort) ? $sort : 'ASC';
    $parameters = array();
    if ($find != "") {
        $searchsql = "WHERE name like ? or description like ? or contact like ? or ref=?";
        $parameters = array("s","%{$find}%", "s","%{$find}%", "s","%{$find}%", "i",(int)$find);
    }
    if (in_array($order_by, array("ref","name","created","status","assigned_to","collection"))) {
        $use_order_by = $order_by;
    }

    return ps_query("select " . columns_in("research_request", "r") . ",(select username from user u where u.ref=r.user) username, 
		(select username from user u where u.ref=r.assigned_to) assigned_username from research_request r 
		$searchsql 
		order by $use_order_by $use_sort", $parameters);
}

/**
 * Retrieves a research request by its reference number, returning its details including name, description, deadline, contact information, user assignment, status, and custom fields.
 *
 * @param int $ref The reference number of the research request to retrieve.
 * @return array|false An associative array with the research request details if found, or false if no request exists.
 */
function get_research_request($ref)
{
    $rr_sql = "SELECT rr.ref,rr.name,rr.description,rr.deadline,rr.email,rr.contact,rr.finaluse,rr.resource_types,rr.noresources,rr.shape,
					rr.created,rr.user,rr.assigned_to,rr.status,rr.collection,rr.custom_fields_json,
					IFNULL((select u.username from user u where u.ref=rr.user), '') username, 
					IFNULL((select u.username from user u where u.ref=rr.assigned_to), '') assigned_username from research_request rr where rr.ref=?";
    $rr_parameters = array("i",$ref);

    $return = ps_query($rr_sql, $rr_parameters);
    if (count($return) == 0) {
        return false;
    }
    return $return[0];
}

/**
 * Saves a research request by updating its status and assigned user, sending notifications to the originator if the status changes, and optionally deleting the request or copying existing collection resources.
 *
 * @param int $ref The reference number of the research request to be saved.
 * @return bool True if the operation was successful, false otherwise.
 */
function save_research_request($ref)
{
    # Save
    global $baseurl,$email_from,$applicationname,$lang;

    $parameters = array("i",$ref);

    if (getval("delete", "") != "") {
        # Delete this request.
        ps_query("delete from research_request where ref=? limit 1", $parameters);
        return true;
    }

    # Check the status, if changed e-mail the originator
    $currentrequest = ps_query("select status, assigned_to, collection from research_request where ref=?", $parameters);

    $oldstatus = (count($currentrequest) > 0) ? $currentrequest[0]["status"] : 0;
    $newstatus = getval("status", 0);
    $collection = (count($currentrequest) > 0) ? $currentrequest[0]["collection"] : 0;
    $oldassigned_to = (count($currentrequest) > 0) ? $currentrequest[0]["assigned_to"] : 0;
    $assigned_to = getval("assigned_to", 0);

    $templatevars['url'] = $baseurl . "/?c=" . $collection;
    $templatevars['teamresearchurl'] = $baseurl . "/pages/team/team_research_edit.php?ref=" . $ref;

    if ($oldstatus != $newstatus) {
        $requesting_user = ps_query("SELECT u.email, u.ref FROM user u,research_request r WHERE u.ref=r.user AND r.ref = ?", $parameters);
        $requesting_user = $requesting_user[0];
        if ($newstatus == 1) {
            $assignedmessage = new ResourceSpaceUserNotification();
            $assignedmessage->set_subject("lang_researchrequestassigned");
            $assignedmessage->set_text("lang_researchrequestassignedmessage");
            $assignedmessage->template = "emailresearchrequestassigned";
            $assignedmessage->templatevars = $templatevars;
            $assignedmessage->url = $templatevars["teamresearchurl"];
            send_user_notification([$requesting_user['ref']], $assignedmessage);
            # Log this
            daily_stat("Assigned research request", 0);
        }
        if ($newstatus == 2) {
            $completemessage = new ResourceSpaceUserNotification();
            $completemessage->set_subject("lang_researchrequestcomplete");
            $completemessage->set_text("lang_researchrequestcompletemessage");
            $completemessage->append_text("\n\n");
            $completemessage->append_text("lang_clicklinkviewcollection");
            $completemessage->template = "emailresearchrequestcomplete";
            $completemessage->templatevars = $templatevars;
            $completemessage->url = $templatevars["teamresearchurl"];
            send_user_notification([$requesting_user['ref']], $completemessage);

            # Log this
            daily_stat("Processed research request", 0);
        }
    }

    if ($oldassigned_to != $assigned_to) {
        $assignedmessage = new ResourceSpaceUserNotification();
        $assignedmessage->set_subject("lang_researchrequestassigned");
        $assignedmessage->set_text("lang_researchrequestassignedmessage");
        $assignedmessage->template = "emailresearchrequestassigned";
        $assignedmessage->templatevars = $templatevars;
        $assignedmessage->url = $templatevars["teamresearchurl"];
        send_user_notification([$assigned_to], $assignedmessage);
    }

    $parameters = array("i",$newstatus, "i",$assigned_to, "i",$ref);
    ps_query("UPDATE research_request SET status = ?, assigned_to = ? WHERE ref= ?", $parameters);

    # Copy existing collection
    $rr_copyexisting = getval("copyexisting", "");
    $rr_copyexistingref = getval("copyexistingref", "");
    if ($rr_copyexisting != "" && is_numeric($collection)) {
        $parameters = array("i",$collection, "i",$rr_copyexistingref, "i",$collection);
        ps_query("INSERT INTO collection_resource(collection,resource) 
                    SELECT ?, resource FROM collection_resource 
                    WHERE collection = ? AND resource NOT IN (SELECT resource FROM collection_resource WHERE collection = ?)", $parameters);
    }
    return true;
}

/**
 * Retrieves the collection reference associated with a given research request.
 *
 * @param int $ref The reference number of the research request.
 * @return int|false The collection reference if found, or false if not.
 */
function get_research_request_collection($ref)
{
    $parameters = array("i",$ref);
    $return = ps_value("select collection value from research_request where ref=?", $parameters, 0);
    if (($return == 0) || (strlen($return) == 0)) {
        return false;
    } else {
        return $return;
    }
}

/**
 * Updates the collection reference associated with a specified research request.
 *
 * @param int $research The reference number of the research request.
 * @param int $collection The reference number of the collection to associate.
 * @return void
 */
function set_research_collection($research, $collection)
{
    $parameters = array("i",$collection, "i",$research);
    ps_query("update research_request set collection=? where ref=?", $parameters);
}
