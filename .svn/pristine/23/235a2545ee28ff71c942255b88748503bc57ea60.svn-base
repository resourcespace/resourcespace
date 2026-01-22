<?php

/**
 * Check if the user should have read access to a license record
 *
 * Determines if the user has read access for a specific resource or general read permissions.
 *
 * @param int|null $resource The ID of the resource to check read access for. If null, checks for general read permissions.
 * @return bool              Returns true if the user has the required permissions; false otherwise.
 */
function licensemanager_check_read(?int $resource = null): bool
{
    // Default to no access
    $has_access = false;

    if (is_numeric($resource)) {
        // Check read access for this specific resource
        $has_access = get_resource_access($resource) == 0 || checkperm("lm");
    } else {
        // Check general read access if no resource specified
        $has_access = checkperm("t") || checkperm("lm");
    }

    return $has_access;
}

/**
 * Check if the user should have write access to a license record
 *
 * Determines if the user has write access for a specific resource or general write permissions.
 *
 * @param int|null $resource The ID of the resource to check write access for. If null, checks for general write permissions.
 * @return bool              Returns true if the user has the required permissions; false otherwise.
 */
function licensemanager_check_write(?int $resource = null): bool
{
    // Default to no access
    $has_access = false;

    if (is_numeric($resource)) {
        // Check write access for this specific resource
        $has_access = get_edit_access($resource) || checkperm("lm");
    } else {
        // Check general write access if no resource specified
        $has_access = checkperm("t") || checkperm("lm");
    }

    return $has_access;
}

/**
 * Get a list of licenses for a given resource
 *
 * This function retrieves a list of license records associated with a specified resource.
 * Each record includes the license details, expiration date, and consent usage.
 *
 * @param int           $resource The ID of the resource for which to retrieve associated licenses.
 * @return array|bool   Returns an array of licenses associated with the resource if the user has read access;
 *                      otherwise, returns false.
 */
function licensemanager_get_licenses(int $resource): array|bool
{
    if (!licensemanager_check_read($resource)) {
        return false;
    }

    return ps_query("select " . columns_in('license', 'license', 'licensemanager') . " from license join resource_license on license.ref=resource_license.license where resource_license.resource= ? order by ref", ['i', $resource]);
}

/**
 * Delete a license record
 *
 * This function deletes a license record and its associations with resources
 * by removing entries from the `license` and `resource_license` tables.
 *
 * @param int           $ref The ID of the license record to be deleted.
 * @return bool         Returns true if the license record was successfully deleted,
 *                      or false if the user does not have write access to the resource.
 */
function licensemanager_delete_license(int $ref): bool
{
    if (!licensemanager_check_write($ref)) {
        return false;
    }

    ps_query("delete from license where ref= ?", ['i', $ref]);
    ps_query("delete from resource_license where license= ?", ['i', $ref]);

    return true;
}

/**
 * Link a license record with a resource
 *
 * This function links a license record to a specified resource by inserting
 * an entry in the `resource_license` table. It also logs this action in the
 * resource's log.
 *
 * @param int $license  The ID of the license record to be linked to the resource.
 * @param int $resource The ID of the resource to which the license is being linked.
 * @return bool         Returns true if the license was successfully linked,
 *                      false if the user does not have write access to the resource.
 */
function licensemanager_link_license(int $license, int $resource): bool
{
    global $lang;

    if (!licensemanager_check_write($resource)) {
        return false;
    }

    // Check if license exists
    $license_check = ps_query("select " . columns_in('license', null, 'licensemanager') . " from license where ref= ?", ['i', $license]);

    if (empty($license_check)) {
        return false;
    }

    ps_query("insert into resource_license(resource, license) values (?, ?)", ['i', $resource, 'i', $license]);
    resource_log($resource, "", "", $lang["new_license"] . " " . $license);

    return true;
}

/**
 * Retrieve a license record
 *
 * This function retrieves the details of a specified license record
 * It also fetches a list of resources associated with the license.
 *
 * @param int $license The ID of the license record to fetch.
 * @return array|bool  Returns an associative array containing license details and
 *                     associated resources if the user has read access; returns false
 *                     if access is denied or the license record does not exist.
 */
function licensemanager_get_license(int $license): array|bool
{
    if (!licensemanager_check_read()) {
        return false;
    }

    $license = ps_query("select " . columns_in('license', null, 'licensemanager') . " from license where ref= ?", ['i', $license]);

    if (empty($license)) {
        return false;
    }

    $license = $license[0];
    $resources = ps_array("select distinct resource value from resource_license where license= ? order by resource", ['i', $license['ref']]);
    $license["resources"] = $resources;

    return $license;
}

/**
 * Fetch all license records, optionally filtered by search text
 *
 * This function retrieves all license records from the database. If a search
 * string is provided, it filters the results based on licensor/licensee/medium/description
 * It can also filter based on license status e.g all, active (non-expired), expiring (expiring within a configured amount of days), 
 * expired. Defaults to returning all. 
 *
 * @param string $findtext          Optional. A search string to filter the results by the
 *                                  licensor/licensee/medium/description of the license. 
 *                                  If empty, returns all records.
 * @param string $license_status    Status of license records to return
 * @return array|bool               Returns an array of license records if the user has
 *                                  read access; otherwise, returns false.
 */
function licensemanager_get_all_licenses(string $findtext = "", string $license_status = "all"): array|bool
{

    global $license_expiry_notification_days;

    if (!licensemanager_check_read()) {
        return false;
    }

    $sql = "select " . columns_in('license', null, 'licensemanager') . " from license";
    
    $where_sql = "";
    $params = [];

    $orderby_sql = "";

    if ($findtext != "") {
        $where_sql    = "where description like CONCAT('%', ?, '%') or holder like CONCAT('%', ?, '%') or license_usage like CONCAT('%', ?, '%')";
        $params = ['s', $findtext, 's', $findtext, 's', $findtext];
    }

    // All - no filter on licenses
    // Active - any that haven't expired yet or have no date set
    // Expiring - any that have an expiry date within $license_expiry_notification_days days
    // Expired - any that the expiry date has been passed
    if ($license_status == 'expired') {
        
        if ($where_sql == "") {
            $where_sql = " where";
        }
        
        $where_sql .= " expires < CURDATE()";
        $orderby_sql = " order by expires desc, ref";        

    } elseif ($license_status == 'expiring') {

        if ($where_sql == "") {
            $where_sql = " where";
        }
        
        $where_sql .= " expires >= CURDATE() AND expires <= CURDATE() + INTERVAL ? DAY";
        array_push($params, 'i', $license_expiry_notification_days);
        $orderby_sql = " order by expires asc, ref";

    } elseif ($license_status == 'active') {
        
        if ($where_sql == "") {
            $where_sql = " where";
        }

        $where_sql .= " expires >= CURDATE() OR expires is NULL";
        $orderby_sql = " order by ref";

    } else {
        $orderby_sql = " order by ref";
    }

    return ps_query($sql . $where_sql . $orderby_sql, $params);
}

/**
 * Fetch all license records grouped by if they are expiring or not
 *
 * @return array|bool     Returns an array of license records if the user has read access;
 *                        otherwise, returns false.
 */
function licensemanager_get_all_licenses_grouped(): array|bool
{
    if (!licensemanager_check_read()) {
        return false;
    }

    $sql = "select *
            from (
                select l.*, 
                case when l.expires < CURDATE() 
                then 'expired' 
                else 'active' end as license_status
                from license l
            ) ls
            order by license_status, ref";

    return ps_query($sql);
}

/**
 * Fetch all expiring license records
 * 
 * This function returns an array of license records that are expiring within so many days.
 * It can optionally be filtered to include records that have not been flagged as having
 * an expiration notification already sent
 * 
 * @param int    $expires_within    Number of days that the records are expiring within
 * @param bool   $unsent_only       Include only records where an expiration notification hasn't been sent
 * 
 * @return array|bool               Returns an array of expiring license records if the user has read access;
 *                                  otherwise, returns false.
 */
function licensemanager_get_expiring_licenses(int $expires_within, bool $unsent_only = true): array|bool
{
    if ($expires_within <= 0) {
        return false;
    }

    $sql = "select ref value 
            from license 
            where expires >= CURDATE() AND expires <= CURDATE() + INTERVAL ? DAY";

    if ($unsent_only) {
        $sql .= " and expiration_notice_sent = 0";
    }

    $sql .= " order by ref;";

    return ps_array($sql, ['i', $expires_within]);
}

/**
 * Sets expiration notice sent flag on license records
 * 
 * This function takes an array of license record references and sets the
 * expiration_notice_sent flag on each one.
 * 
 * @param array $licenses       An array of license references
 * 
 * @return bool                 Returns true if the flags were set; otherwise, returns false
 */
function licensemanager_set_license_expiration_notice(array $licenses): bool
{
    if (empty($licenses)) {
        return false;
    }

    $in_sql = ps_param_insert(count($licenses));
    $params = ps_param_fill($licenses, "i");

    ps_query("UPDATE license set expiration_notice_sent = 1 where ref in (" . $in_sql . ")", $params);

    return true;

}

/**
 * Fetch expired license records
 * 
 * This function returns expired license records that are not deleted and are not in the passed archive_state
 * 
 * @param int $archive_state    An integer of the archive state, to be excluded from the results
 * 
 * @return array|bool           Returns an array of expired license records;
 *                              otherwise, returns false. 
 */
function licensemanager_get_expired_license_resources(int $archive_status): array|bool
{

    global $resource_deletion_state;

    $sql = "select distinct r.ref value
            from license l
            inner join resource_license rl on l.ref = rl.license
            inner join resource r on rl.resource = r.ref
            where l.expires < CURDATE()
            and r.archive <> ?
            and r.archive <> ?
            order by r.ref;";

    return ps_array($sql, ['i', $archive_status, 'i', $resource_deletion_state]);
}

/**
 * Process expiring license records and send a notification/email
 * 
 * @return bool     Returns true if the notification process completes
 */
function licensemanager_process_expiry_notifications(): bool {

    global $applicationname, $baseurl, $license_expiry_notification_days, $lang;

    logScript("License Manager: Expiry notifications job starting:");

    // Determine if there are any licenses that are expiring but notifications have not been sent yet
    $expiring_licenses = licensemanager_get_expiring_licenses($license_expiry_notification_days, true);

    if (empty($expiring_licenses)) {
        logScript("License Manager: No licenses expiring within " . $license_expiry_notification_days . " days. Exiting.");
        return false;
    } else {
        logScript("License Manager: There are " . count($expiring_licenses) . " expiring within " . $license_expiry_notification_days . " days.");
    }

    // Pull a list of users with lm permission
    $users_to_notify = get_users_with_permission('lm');

    foreach ($users_to_notify as $user) {

        get_config_option(['user' => $user['ref'], 'usergroup' => $user['usergroup']], 'user_pref_license_notifications', $send_message);
        
        // Default to enabled, so if null still send message
        if ($send_message == 1 || is_null($send_message)) {
            logScript("License Manager: Send message for " . $user['username']);
            $expiry_notification = new ResourceSpaceUserNotification;

            $expiry_notification->set_subject($applicationname . ": " . $lang['license_notification_expiring_soon']);

            $expiry_notification->set_text($lang['license_notification_message'] . "<br />");
            $expiry_notification->append_text("<a href='" . generateURL($baseurl . "/plugins/licensemanager/pages/list.php", ["license_status" => "expiring"]) . "'>" . $lang['license_notification_link'] . "</a><br /><br />");
            $expiry_notification->append_text(" <a href='" . generateURL($baseurl . "/pages/user/user_preferences.php") . "'>" . $lang['license_notification_user_pref'] . "</a><br />");
            $expiry_notification->append_text(" <a href='" . generateURL($baseurl . "/plugins/licensemanager/pages/setup.php") . "'>" . $lang['license_notification_global_pref'] . "</a>");

            send_user_notification([$user['ref']], $expiry_notification); 

        }

    }

    // Mark licenses as notified
    $marked_as_notified = licensemanager_set_license_expiration_notice($expiring_licenses);

    if ($marked_as_notified) {
        logScript("License Manager: Licenses marked as notification sent");
    } else {
        logScript("License Manager: Error when marking licenses as notification sent");
    }

    return true;

}

/**
 * Process expired license records and archive them
 * 
 * @return bool     Returns true if the auto archiving process completes
 */
function licensemanager_process_expired_auto_archive(): bool {

    global $license_expired_workflow_state;

    logScript("License Manager: automatic archiving job starting:");

    $expired_license_resources = licensemanager_get_expired_license_resources($license_expired_workflow_state);

    if (empty($expired_license_resources)) {
        logScript("License Manager: No expired license records to archive. Exiting.");
        return false;
    }

    foreach ($expired_license_resources as $resource) {
        logScript("License Manager: changing status of resource #" . $resource);
        update_archive_status($resource, $license_expired_workflow_state, [], 0, "- due to expired license record");
    }

    logScript("License Manager: automatic archiving job complete");

    return true;
}