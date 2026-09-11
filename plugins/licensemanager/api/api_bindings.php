<?php
/**
 * Valencia DAM – erweitere License-Manager API-Bindings
 *
 * INSTALLATION auf dem ResourceSpace-Server:
 *   Diese Datei ersetzen/zusammenführen mit:
 *   plugins/licensemanager/api/api_bindings.php
 *
 * Danach Plugin-Cache leeren bzw. Seite neu laden.
 *
 * Standard-RS liefert nur api_licensemanager_get_licenses (pro Resource).
 * Diese Datei ergänzt Listen, Anlegen, Bearbeiten, Löschen, Link/Unlink.
 */

function api_licensemanager_get_licenses($resource)
{
    return licensemanager_get_licenses((int) $resource);
}

function api_licensemanager_get_all_licenses($findtext = "", $license_status = "all")
{
    $result = licensemanager_get_all_licenses((string) $findtext, (string) $license_status);
    return $result === false ? [] : $result;
}

function api_licensemanager_get_license($license)
{
    $result = licensemanager_get_license((int) $license);
    return $result === false ? null : $result;
}

function api_licensemanager_delete_license($ref)
{
    return licensemanager_delete_license((int) $ref);
}

function api_licensemanager_link_license($license, $resource)
{
    return licensemanager_link_license((int) $license, (int) $resource);
}

function api_licensemanager_unlink_license($license, $resource)
{
    global $lang;

    $license = (int) $license;
    $resource = (int) $resource;

    if (!licensemanager_check_write($resource)) {
        return false;
    }

    ps_query(
        "delete from resource_license where license= ? and resource= ?",
        ['i', $license, 'i', $resource]
    );
    resource_log($resource, "", "", ($lang["unlink_license"] ?? "Unlink license") . " " . $license);
    return true;
}

/**
 * Batch link/unlink a license to all resources in a collection.
 */
function api_licensemanager_batch_link_unlink($license, $collection, $unlink = false)
{
    $license = (int) $license;
    $collection = (int) $collection;
    $unlink = filter_var($unlink, FILTER_VALIDATE_BOOLEAN);

    if ($license <= 0) {
        return false;
    }

    $resources = get_collection_resources($collection);
    foreach ($resources as $resource) {
        if (!licensemanager_check_write($resource)) {
            continue;
        }
        ps_query(
            "delete from resource_license where license= ? and resource= ?",
            ['i', $license, 'i', $resource]
        );
        if (!$unlink) {
            ps_query(
                "insert into resource_license (resource, license) values (?, ?)",
                ['i', $resource, 'i', $license]
            );
        }
        global $lang;
        resource_log(
            $resource,
            "",
            "",
            ($unlink
                ? ($lang["unlink_license"] ?? "Unlink license")
                : ($lang["new_license"] ?? "Link license"))
            . " " . $license
        );
    }
    return true;
}

/**
 * Create a license record. Returns new ref or false.
 *
 * @param int|string $outbound 1 = outbound, 0 = inbound
 * @param string     $holder
 * @param string     $license_usage
 * @param string     $description
 * @param string     $expires      YYYY-MM-DD or empty for none
 */
function api_licensemanager_create_license($outbound, $holder, $license_usage = "", $description = "", $expires = "")
{
    if (!licensemanager_check_write()) {
        return false;
    }

    $outbound = ((string) $outbound === "1" || (int) $outbound === 1) ? "1" : "0";
    $holder = trim((string) $holder);
    $license_usage = trim((string) $license_usage);
    $description = trim((string) $description);
    $expires = trim((string) $expires);
    if ($expires === "") {
        $expires = null;
    }

    ps_query(
        "insert into license (outbound, holder, license_usage, description, expires) values (?, ?, ?, ?, ?)",
        [
            's', $outbound,
            's', $holder,
            's', $license_usage,
            's', $description,
            's', $expires,
        ]
    );

    return sql_insert_id();
}

/**
 * Update an existing license. Returns true/false.
 */
function api_licensemanager_update_license($ref, $outbound, $holder, $license_usage = "", $description = "", $expires = "")
{
    $ref = (int) $ref;
    if ($ref <= 0 || !licensemanager_check_write()) {
        return false;
    }

    $outbound = ((string) $outbound === "1" || (int) $outbound === 1) ? "1" : "0";
    $holder = trim((string) $holder);
    $license_usage = trim((string) $license_usage);
    $description = trim((string) $description);
    $expires = trim((string) $expires);
    if ($expires === "") {
        $expires = null;
    }

    $previous = ps_query(
        "select expires, expiration_notice_sent from license where ref = ?",
        ['i', $ref]
    );
    $expiration_notice_sent = 0;
    if (!empty($previous) && count($previous) === 1) {
        if ($previous[0]['expires'] === $expires) {
            $expiration_notice_sent = (int) $previous[0]['expiration_notice_sent'];
        }
    }

    ps_query(
        "update license set outbound= ?, holder= ?, license_usage= ?, description= ?, expires= ?, expiration_notice_sent= ? where ref= ?",
        [
            's', $outbound,
            's', $holder,
            's', $license_usage,
            's', $description,
            's', $expires,
            'i', $expiration_notice_sent,
            'i', $ref,
        ]
    );

    return true;
}
