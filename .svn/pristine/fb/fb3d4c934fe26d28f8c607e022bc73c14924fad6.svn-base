<?php
include_once __DIR__ . "/../include/license_functions.php";

function HookLicensemanagerAllExport_add_tables()
{
    return array("license"=>array("scramble"=>array("holder"=>"mix_text","license_usage"=>"mix_text","description"=>"mix_text")));
}

function HookLicensemanagerAllRender_actions_add_collection_option($top_actions,array $options, array $collection_data)
{
    // Add the options to link a license and unlink the license
    global $search,$lang,$k,$baseurl_short;
    
    // Make sure this check takes place before $GLOBALS["hook_return_value"] can be unset by subsequent calls to hook()
    if (isset($GLOBALS["hook_return_value"]) && is_array($GLOBALS["hook_return_value"])) {
        // @see hook() for an explanation about the hook_return_value global
        $options = $GLOBALS["hook_return_value"];
    }

    if ($k != '' || !(checkperm("a") || checkperm("lm"))) {
        return $options;
    }

    $collection = (isset($collection_data["ref"]) ? $collection_data["ref"] : null);

    $data_attr_url = generateURL(
        $baseurl_short . "plugins/licensemanager/pages/batch.php",
        array(
            'collection' => $collection,
            'unlink'     => 'true',
            'search'     => getval('search', $search),
            'order_by'   => getval('order_by',''),
            'offset'     => getval('offset',0),
            'restypes'   => getval('restypes',''),
            'archive'    => getval('archive','')
        )
    );

    $option = array(
        'value'     => 'license_batch',
        'label'     => $lang['unlinklicense'],
        'data_attr' => array(
            'url' => $data_attr_url,
        ),
        'category' => ACTIONGROUP_ADVANCED
    );


    array_push($options, $option);

    $data_attr_url = generateURL(
        $baseurl_short . "plugins/licensemanager/pages/batch.php",
        array(
            'collection' => $collection,
            'search'     => getval('search', $search),
            'order_by'   => getval('order_by',''),
            'offset'     => getval('offset',0),
            'restypes'   => getval('restypes',''),
            'archive'    => getval('archive','')
        )
    );

    $option = array(
        'value'     => 'license_batch',
        'label'     => $lang['linklicense'],
        'data_attr' => array(
            'url' => $data_attr_url,
        ),
        'category' => ACTIONGROUP_ADVANCED
    );

    array_push($options, $option);



    return $options;
}

function HookLicensemanagerAllTopnavlinksafterhome()
{
    global $baseurl, $lang;
    if (!checkperm("a") && checkperm("lm")) {
        ?><li class="HeaderLink"><a href="<?php echo $baseurl ?>/plugins/licensemanager/pages/list.php" onClick="CentralSpaceLoad(this,true);return false;"><?php echo '<i aria-hidden="true" class="icon-scroll"></i>&nbsp;' . escape($lang["managelicenses"]); ?></a></li>
        <?php
    }
}

function HookLicensemanagerAllCron()
{
    global $license_expiry_notification, $license_expired_workflow_state;

    // Check if expiry notifications are enabled globally
    if ($license_expiry_notification) {
        licensemanager_process_expiry_notifications();
    } else {
        logScript("License Manager: notifications disabled globally");
    }

    // Check if expired workflow state has been set
    if ($license_expired_workflow_state !== '') {
        licensemanager_process_expired_auto_archive();
    } else {
        logScript("License Manager: automatic archiving disabled");
    }
}

function HookLicensemanagerAllAddspecialsearch($search, $select, $sql_join, $sql_filter)
{
    if (substr($search, 0, 8) == '!license') {
        $license_ref = substr($search, 8);
    } else {
        return null;
    }

    $license = licensemanager_get_license($license_ref);

    $ids = isset($license['resources']) ? $license['resources'] : [];

    // No results - we must still run a query but one that returns no results.
    if (count($ids) == 0) {
        $ids = [-1];
    }

    $in_sql = ps_param_insert(count($ids));
    $params = ps_param_fill($ids, "i");
    $sql = new PreparedStatementQuery();
    $sql->sql = "SELECT DISTINCT r.hit_count score, $select->sql FROM resource r " . $sql_join->sql . " WHERE r.ref > 0 AND r.ref in ($in_sql) ORDER BY FIELD(r.ref, $in_sql)";
    $sql->parameters = array_merge($select->parameters, $sql_join->parameters, $params, $params);
    
    return $sql;
}