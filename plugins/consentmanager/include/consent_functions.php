<?php

/**
 * Check if the user should have read access to a consent record
 *
 * Determines if the user has read access for a specific resource or general read permissions.
 *
 * @param int|null $resource The ID of the resource to check read access for. If null, checks for general read permissions.
 * @return bool              Returns true if the user has the required permissions; false otherwise.
 */
function consentmanager_check_read(?int $resource = null): bool
{
    // Default to no access
    $has_access = false;

    if (is_numeric($resource)) {
        // Check read access for this specific resource
        $has_access = get_resource_access($resource) == 0 || checkperm("cm");
    } else {
        // Check general read access if no resource specified
        $has_access = checkperm("t") || checkperm("cm");
    }

    return $has_access;
}

/**
 * Check if the user should have write access to a consent record
 *
 * Determines if the user has write access for a specific resource or general write permissions.
 *
 * @param int|null $resource The ID of the resource to check write access for. If null, checks for general write permissions.
 * @return bool              Returns true if the user has the required permissions; false otherwise.
 */
function consentmanager_check_write(?int $resource = null): bool
{
    // Default to no access
    $has_access = false;

    if (is_numeric($resource)) {
        // Check write access for this specific resource
        $has_access = get_edit_access($resource) || checkperm("cm");
    } else {
        // Check general write access if no resource specified
        $has_access = checkperm("t") || checkperm("cm");
    }

    return $has_access;
}

/**
 * Get a list of consents for a given resource
 *
 * This function retrieves a list of consent records associated with a specified resource.
 * Each record includes the consent ID, name, expiration date, and consent usage.
 *
 * @param int $resource The ID of the resource for which to retrieve associated consents.
 * @return array|bool   Returns an array of consents associated with the resource if the user has read access;
 *                      otherwise, returns false.
 */
function consentmanager_get_consents(int $resource): array|bool
{
    if (!consentmanager_check_read($resource)) {
        return false;
    }

    return ps_query("select " . columns_in('consent', 'consent', 'consentmanager') . " from consent join resource_consent on consent.ref=resource_consent.consent where resource_consent.resource= ? order by ref", ['i', $resource]);
}

/**
 * Delete a consent record
 *
 * This function deletes a consent record and its associations with resources
 * by removing entries from the `consent` and `resource_consent` tables.
 *
 * @param int $ref The ID of the consent record to be deleted.
 * @return bool         Returns true if the consent record was successfully deleted,
 *                      or false if the user does not have write access to the resource.
 */
function consentmanager_delete_consent(int $ref): bool
{
    $consent = consentmanager_get_consent($ref);
    foreach ($consent['resources'] as $resource) {
        if (get_resource_data($resource) !== false) {
            if (!consentmanager_check_write($resource)) {
                return false;
            }
        }
    }

    ps_query("delete from consent where ref= ?", ['i', $ref]);
    ps_query("delete from resource_consent where consent= ?", ['i', $ref]);

    return true;
}

/**
 * Create a new consent record
 *
 * This function creates a new consent record by inserting the provided details
 * into the `consent` table. It returns the ID of the newly created consent
 * record if successful.
 *
 * @param string      $name              The name of the individual giving consent.
 * @param string|null $date_of_birth     The DOB of the individual, formatted as a string. Optional.
 * @param string|null $address           The address of the individual. Optional.
 * @param string|null $parent_guardian   The parent or guardian of the individual. Optional.
 * @param string      $email             The email address of the individual.
 * @param string      $telephone         The telephone number of the individual.
 * @param string      $consent_usage     Description of the intended usage for which consent is given.
 * @param string      $notes             Any additional notes related to the consent record.
 * @param string|null $date_of_consent   The date the consent applies from.
 * @param string|null $expires           The expiry date of the consent, formatted as a string.
 * @param string      $created_by        The ref of the user who created the consent record.
 * 
 * @return int|bool              Returns the ID of the new consent record on success,
 *                               or false if the user does not have write access.
 */
function consentmanager_create_consent(string $name, 
                                       ?string $date_of_birth, 
                                       ?string $address, 
                                       ?string $parent_guardian, 
                                       string $email, 
                                       string $telephone, 
                                       string $consent_usage, 
                                       string $notes, 
                                       ?string $date_of_consent, 
                                       ?string $expires, 
                                       string $created_by): int|bool
{
    if (!consentmanager_check_write()) {
        return false;
    }

    # New record
    ps_query(
        "insert into consent (name,
                              date_of_birth,
                              address,
                              parent_guardian_name,
                              email,
                              telephone,
                              consent_usage,
                              notes,
                              date_of_consent,
                              expires,
                              created_by) values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        [
            's', $name,
            's', $date_of_birth,
            's', $address,  
            's', $parent_guardian, 
            's', $email,
            's', $telephone,
            's', $consent_usage,
            's', $notes,
            's', $date_of_consent,
            's', $expires,
            'i', $created_by
        ]
    );

    return sql_insert_id();
}

/**
 * Link a consent record with a resource
 *
 * This function links a consent record to a specified resource by inserting
 * an entry in the `resource_consent` table. It also logs this action in the
 * resource's log.
 *
 * @param int $consent  The ID of the consent record to be linked to the resource.
 * @param int $resource The ID of the resource to which the consent is being linked.
 * @return bool         Returns true if the consent was successfully linked,
 *                      false if the user does not have write access to the resource.
 */
function consentmanager_link_consent(int $consent, int $resource): bool
{
    global $lang;

    if (!consentmanager_check_write($resource)) {
        return false;
    }

    // Check if consent exists
    $consent_check = ps_query("select " . columns_in('consent', null, 'consentmanager') . " from consent where ref= ?", ['i', $consent]);

    if (empty($consent_check)) {
        return false;
    }

    ps_query("insert into resource_consent(resource,consent) values (?, ?)", ['i', $resource, 'i', $consent]);
    resource_log($resource, "", "", $lang["new_consent"] . " " . $consent);

    return true;
}

/**
 * Unlink a consent record from a resource
 *
 * This function removes the association between a specified consent record and a resource.
 * The action is logged for the resource.
 *
 * @param int $consent  The ID of the consent record to unlink.
 * @param int $resource The ID of the resource from which to unlink the consent.
 * @return bool         Returns true if the consent record is successfully unlinked;
 *                      returns false if the user does not have write access to the resource.
 */
function consentmanager_unlink_consent(int $consent, int $resource): bool
{
    global $lang;

    if (!consentmanager_check_write($resource)) {
        return false;
    }

    ps_query("delete from resource_consent where consent= ? and resource= ?", ['i', $consent, 'i', $resource]);
    resource_log($resource, "", "", $lang["unlink_consent"] . " " . $consent);

    return true;
}

/**
 * Link/unlink all resources in a collection with a consent record
 *
 * This function links or unlinks all resources in a specified collection to/from
 * a given consent record. If unlinking, it removes existing relationships between
 * the consent record and the resources. If linking, it creates new relationships.
 * Each action is logged.
 *
 * @param int  $consent     The ID of the consent record to link or unlink.
 * @param int  $collection  The ID of the collection containing the resources to process.
 * @param bool $unlink      Set to true to unlink resources from the consent; set to
 *                          false to link resources to the consent.
 * @return bool             Returns true if the process completes successfully;
 *                          returns false if an invalid consent ID is provided.
 */
function consentmanager_batch_link_unlink(int $consent, int $collection, bool $unlink): bool
{
    $resources = get_collection_resources($collection);

    if ($consent <= 0) {
        return false;
    }

    foreach ($resources as $resource) {
        if (consentmanager_check_write($resource)) {
            // Always remove any existing relationship
            ps_query("delete from resource_consent where consent= ? and resource= ?", ['i', $consent, 'i', $resource]);

            // Add link?
            if (!$unlink) {
                ps_query("insert into resource_consent (resource,consent) values (?, ?)", ['i', $resource, 'i', $consent]);
            }

            // Log
            global $lang;
            resource_log($resource, "", "", $lang[($unlink ? "un" : "") . "linkconsent"] . " " . $consent);
        }
    }
    return true;
}

/**
 * Retrieve a consent record
 *
 * This function retrieves the details of a specified consent record, including
 * the subject's name, email, telephone number, consent usage types, notes, expiry
 * date, and file. It also fetches a list of resources associated with the consent.
 *
 * @param int $consent The ID of the consent record to fetch.
 * @return array|bool  Returns an associative array containing consent details and
 *                     associated resources if the user has read access; returns false
 *                     if access is denied or the consent record does not exist.
 */
function consentmanager_get_consent(int $consent): array|bool
{
    if (!consentmanager_check_read()) {
        return false;
    }

    $consent = ps_query("select " . columns_in('consent', null, 'consentmanager') . " from consent where ref= ?", ['i', $consent]);

    if (empty($consent)) {
        return false;
    }

    $consent = $consent[0];
    $resources = ps_array("select distinct resource value from resource_consent where consent= ? order by resource", ['i', $consent['ref']]);
    $consent["resources"] = $resources;

    return $consent;
}

/**
 * Update a consent record
 *
 * This function updates the details of an existing consent record with the provided
 * information. It allows modification of the subject's name, DOB, address, parent/guardian name,
 * email, telephone number, consent usage types, date of consent, notes, and expiry date.
 * It also resets the expiration_notice_sent flag if the expiry date is modified.
 *
 * @param int         $consent           The ID of the consent record to update.
 * @param string      $name              The name of the individual giving consent.
 * @param string|null $date_of_birth     The DOB of the individual, formatted as a string. Optional.
 * @param string|null $address           The address of the individual. Optional.
 * @param string|null $parent_guardian   The parent or guardian of the individual. Optional.
 * @param string      $email             The email address of the individual.
 * @param string      $telephone         The telephone number of the individual.
 * @param string      $consent_usage     A description of the permitted usage types for the consent.
 * @param string      $notes             Additional notes related to the consent record.
 * @param string|null $date_of_consent   The date the consent applies from.
 * @param string|null $expires           The expiry date of the consent record, formatted as a string.
 * @return bool                     Returns true if the consent record was successfully updated,
 *                                  or false if the user does not have write access.
 */
function consentmanager_update_consent(int $consent, 
                                       string $name, 
                                       ?string $date_of_birth, 
                                       ?string $address, 
                                       ?string $parent_guardian, 
                                       string $email, 
                                       string $telephone, 
                                       string $consent_usage, 
                                       string $notes, 
                                       ?string $date_of_consent, 
                                       ?string $expires): bool
{
    if (!consentmanager_check_write()) {
        return false;
    }

    // Determine the previous expiry date and expiration_notice_sent flag
    $previous_data = ps_query("select expires, expiration_notice_sent from consent where ref = ?", ['i', $consent]);

    if (!empty($previous_data) && count($previous_data) === 1) {

        //If expiry date has changed
        if ($previous_data[0]['expires'] !== $expires) {
            $expiration_notice_sent = 0;
        } else {
            $expiration_notice_sent = (int) $previous_data[0]['expiration_notice_sent'];
        }

    } else {
        return false;
    }

    ps_query(
        "update consent set name= ?, date_of_birth= ?, address= ?, parent_guardian_name= ?, email= ?, telephone= ?,consent_usage= ?,notes= ?, date_of_consent= ?, expires= ?, expiration_notice_sent = ? where ref= ?",
        [
            's', $name,
            's', $date_of_birth,
            's', $address,
            's', $parent_guardian,
            's', $email,
            's', $telephone,
            's', $consent_usage,
            's', $notes,
            's', $date_of_consent,
            's', $expires,
            'i', $expiration_notice_sent,
            'i', $consent
        ]
    );

    return true;
}

/**
 * Fetch all consent records linked to resources in a collection
 *
 * This function retrieves all consent records that are linked to resources within
 * a specified collection. It returns an array of consents associated with the resources
 * in the collection.
 *
 * @param int $collection The ID of the collection containing the resources for which
 *                        to retrieve consent records.
 * @return array|bool     Returns an array of consent records if the user has read access;
 *                        otherwise, returns false.
 */
function consentmanager_get_all_consents_by_collection(int $collection): array|bool
{
    if (!consentmanager_check_read()) {
        return false;
    }

    return ps_query("select ref,name from consent where ref in (select consent from resource_consent where resource in (select resource from collection_resource where collection=?)) order by ref", ["i",$collection]);
}

/**
 * Fetch all consent records, optionally filtered by search text
 *
 * This function retrieves all consent records from the database. If a search
 * string is provided, it filters the results based on the name of the person
 * associated with each consent record. It can also filter based on consent 
 * status e.g all, active (non-expired), expiring (expiring within a configured amount of days), 
 * expired. Defaults to returning all.
 *
 * @param string $findtext          Optional. A search string to filter the results by the
 *                                  name of the person giving consent. If empty, returns
 *                                  all records.
 * @param string $consent_status    Status of consent records to return
 * @return array|bool               Returns an array of consent records if the user has
 *                                  read access; otherwise, returns false.
 */
function consentmanager_get_all_consents(string $findtext = "", string $consent_status = "all"): array|bool
{
    global $consent_expiry_notification_days;

    if (!consentmanager_check_read()) {
        return false;
    }

    $sql = "select " . columns_in('consent', null, 'consentmanager') . " from consent";
    
    $where_sql = "";
    $params = [];

    $orderby_sql = "";

    if ($findtext != "") {
        $where_sql = " where name like ?";
        $params = ['s', "%$findtext%"];
    }

    // All - no filter on consents
    // Active - any that haven't expired yet or have no date set
    // Expiring - any that have an expiry date within $consent_expiry_notification_days days
    // Expired - any that the expiry date has been passed
    if ($consent_status == 'expired') {
        
        if ($where_sql == "") {
            $where_sql = " where";
        }
        
        $where_sql .= " expires < CURDATE()";
        $orderby_sql = " order by expires desc, ref";        

    } elseif ($consent_status == 'expiring') {

        if ($where_sql == "") {
            $where_sql = " where";
        }
        
        $where_sql .= " expires >= CURDATE() AND expires <= CURDATE() + INTERVAL ? DAY";
        array_push($params, 'i', $consent_expiry_notification_days);
        $orderby_sql = " order by expires asc, ref";

    } elseif ($consent_status == 'active') {
        
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
 * Fetch all consent records grouped by if they are expiring or not
 *
 * @return array|bool     Returns an array of consent records if the user has read access;
 *                        otherwise, returns false.
 */
function consentmanager_get_all_consents_grouped(): array|bool
{
    if (!consentmanager_check_read()) {
        return false;
    }

    $sql = "select *
            from (
                select c.*, 
                case when c.expires < CURDATE() 
                then 'expired' 
                else 'active' end as consent_status
                from consent c
            ) cs
            order by consent_status, ref";

    return ps_query($sql);
}

/**
 * Save a file associated with a consent record
 *
 * This function saves a file (typically a consent form or related document) to the file system
 * and updates the associated consent record in the database with the filename. The function
 * checks user permissions and blocks the upload if the file extension is banned.
 *
 * @param int    $consent   The ID of the consent record to associate the file with.
 * @param string $filename  The name of the file to be saved (including extension).
 * @param string $filedata  The raw file data (contents) to be written to disk.
 *
 * @return bool  Returns true if the file was saved and the database updated successfully;
 *               returns false if the user lacks permission or the file extension is not allowed.
 */
function consentmanager_save_file(int $consent, string $filename, string $filedata): bool
{
    if (!(checkperm("t") || checkperm("cm"))) {
        return false;
    }
    $file_path = get_consent_file_path($consent);
    file_put_contents($file_path, $filedata);
    if (is_banned_extension(parse_filename_extension($filename))) {
        return false;
    }
    ps_query("UPDATE consent set file= ? where ref= ?", ['s', $filename, 'i', $consent]);
    return true;
}

/**
 * Fetch all expiring consent records
 * 
 * This function returns an array of consent records that are expiring within so many days.
 * It can optionally be filtered to include records that have not been flagged as having
 * an expiration notification already sent
 * 
 * @param int    $expires_within    Number of days that the records are expiring within
 * @param bool   $unsent_only       Include only records where an expiration notification hasn't been sent
 * 
 * @return array|bool               Returns an array of expiring consent records if the user has read access;
 *                                  otherwise, returns false.
 */
function consentmanager_get_expiring_consents(int $expires_within, bool $unsent_only = true): array|bool
{
    if ($expires_within <= 0) {
        return false;
    }

    $sql = "select ref value 
            from consent 
            where expires >= CURDATE() AND expires <= CURDATE() + INTERVAL ? DAY";

    if ($unsent_only) {
        $sql .= " and expiration_notice_sent = 0";
    }

    $sql .= " order by ref;";

    return ps_array($sql, ['i', $expires_within]);
}

/**
 * Sets expiration notice sent flag on consent records
 * 
 * This function takes an array of consent record references and sets the
 * expiration_notice_sent flag on each one.
 * 
 * @param array $consents       An array of consent references
 * 
 * @return bool                 Returns true if the flags were set; otherwise, returns false
 */
function consentmanager_set_consent_expiration_notice(array $consents): bool
{
    if (empty($consents)) {
        return false;
    }

    $in_sql = ps_param_insert(count($consents));
    $params = ps_param_fill($consents, "i");

    ps_query("UPDATE consent set expiration_notice_sent = 1 where ref in (" . $in_sql . ")", $params);

    return true;

}

/**
 * Fetch expired consent records
 * 
 * This function returns expired consent records that are not deleted and are not in the passed archive_state
 * 
 * @param int $archive_state    An integer of the archive state, to be excluded from the results
 * 
 * @return array|bool           Returns an array of expired consent records;
 *                              otherwise, returns false. 
 */
function consentmanager_get_expired_consent_resources(int $archive_status): array|bool
{
    global $resource_deletion_state;

    $sql = "select distinct r.ref value
            from consent c
            inner join resource_consent rc on c.ref = rc.consent
            inner join resource r on rc.resource = r.ref
            where c.expires < CURDATE()
            and r.archive <> ?
            and r.archive <> ?
            order by r.ref;";

    return ps_array($sql, ['i', $archive_status, 'i', $resource_deletion_state]);
}

/**
 * Process expiring consent records and send a notification/email
 * 
 * @return bool     Returns true if the notification process completes
 */
function consentmanager_process_expiry_notifications(): bool {

    global $applicationname, $baseurl, $consent_expiry_notification_days, $lang;

    logScript("Consent Manager: Expiry notifications job starting:");

    // Determine if there are any consents that are expiring but notifications have not been sent yet
    $expiring_consents = consentmanager_get_expiring_consents($consent_expiry_notification_days, true);

    if (empty($expiring_consents)) {
        logScript("Consent Manager: No consents expiring within " . $consent_expiry_notification_days . " days. Exiting.");
        return false;
    } else {
        logScript("Consent Manager: There are " . count($expiring_consents) . " expiring within " . $consent_expiry_notification_days . " days.");
    }

    // Pull a list of users with cm permission
    $users_to_notify = get_users_with_permission('cm');

    foreach ($users_to_notify as $user) {

        get_config_option(['user' => $user['ref'], 'usergroup' => $user['usergroup']], 'user_pref_consent_notifications', $send_message);
        
        // Default to enabled, so if null still send message
        if ($send_message == 1 || is_null($send_message)) {
            logScript("Consent Manager: Send message for " . $user['username']);
            $expiry_notification = new ResourceSpaceUserNotification;

            $expiry_notification->set_subject($applicationname . ": " . $lang['consent_notification_expiring_soon']);

            $expiry_notification->set_text($lang['consent_notification_message'] . "<br />");
            $expiry_notification->append_text("<a href='" . generateURL($baseurl . "/plugins/consentmanager/pages/list.php", ["consent_status" => "expiring"]) . "'>" . $lang['consent_notification_link'] . "</a><br /><br />");
            $expiry_notification->append_text(" <a href='" . generateURL($baseurl . "/pages/user/user_preferences.php") . "'>" . $lang['consent_notification_user_pref'] . "</a><br />");
            $expiry_notification->append_text(" <a href='" . generateURL($baseurl . "/plugins/consentmanager/pages/setup.php") . "'>" . $lang['consent_notification_global_pref'] . "</a>");

            send_user_notification([$user['ref']], $expiry_notification); 

        }

    }

    // Mark consents as notified
    $marked_as_notified = consentmanager_set_consent_expiration_notice($expiring_consents);

    if ($marked_as_notified) {
        logScript("Consent Manager: Consents marked as notification sent");
    } else {
        logScript("Consent Manager: Error when marking consents as notification sent");
    }

    return true;

}

/**
 * Process expired consent records and archive them
 * 
 * @return bool     Returns true if the auto archiving process completes
 */
function consentmanager_process_expired_auto_archive(): bool {

    global $consent_expired_workflow_state;

    logScript("Consent Manager: Automatic archiving job starting:");

    $expired_consent_resources = consentmanager_get_expired_consent_resources($consent_expired_workflow_state);

    if (empty($expired_consent_resources)) {
        logScript("Consent Manager: No expired consent records to archive. Exiting.");
        return false;
    }

    foreach ($expired_consent_resources as $resource) {
        logScript("Consent Manager: Changing status of resource #" . $resource);
        update_archive_status($resource, $consent_expired_workflow_state, [], 0, "- due to expired consent record");
    }

    logScript("Consent Manager: Automatic archiving job complete");

    return true;
}
