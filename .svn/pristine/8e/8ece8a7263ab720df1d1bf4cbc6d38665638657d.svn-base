<?php
include_once __DIR__ . "/../include/consent_functions.php";
include_once __DIR__ . "/../include/file_functions.php";

function HookConsentmanagerAllExport_add_tables()
{
    return array("consent" => array("scramble" => array("name" => "mix_text","email" => "mix_email","telephone" => "mix_text","consent_usage" => "mix_text","expires" => "mix_date")));
}

function HookConsentmanagerAllRender_actions_add_collection_option($top_actions, array $options, array $collection_data)
{
    // Add the options to link/unlink consent
    global $search,$lang,$k,$baseurl_short;

    // Make sure this check takes place before $GLOBALS["hook_return_value"] can be unset by subsequent calls to hook()
    if (isset($GLOBALS["hook_return_value"]) && is_array($GLOBALS["hook_return_value"])) {
    // @see hook() for an explanation about the hook_return_value global
        $options = $GLOBALS["hook_return_value"];
    }

    if ($k != '' || !(checkperm("a") || checkperm("cm"))) {
        return $options;
    }

    $collection = (isset($collection_data["ref"]) ? $collection_data["ref"] : null);

    $data_attr_url = generateURL(
        $baseurl_short . "plugins/consentmanager/pages/batch.php",
        array(
            'collection' => $collection,
            'unlink'     => 'true',
            'search'     => getval('search', $search),
            'order_by'   => getval('order_by', ''),
            'offset'     => getval('offset', 0),
            'restypes'   => getval('restypes', ''),
            'archive'    => getval('archive', '')
        )
    );

    $option = array(
        'value'     => 'unlink_consent_batch',
        'label'     => $lang['unlinkconsent'],
        'data_attr' => array(
            'url' => $data_attr_url,
        ),
        'category' => ACTIONGROUP_ADVANCED
    );

    array_push($options, $option);

    $data_attr_url = generateURL(
        $baseurl_short . "plugins/consentmanager/pages/batch.php",
        array(
            'collection' => $collection,
            'search'     => getval('search', $search),
            'order_by'   => getval('order_by', ''),
            'offset'     => getval('offset', 0),
            'restypes'   => getval('restypes', ''),
            'archive'    => getval('archive', '')
        )
    );

    $option = array(
        'value'     => 'link_consent_batch',
        'label'     => $lang['linkconsent'],
        'data_attr' => array(
            'url' => $data_attr_url,
        ),
        'category' => ACTIONGROUP_ADVANCED
    );

    array_push($options, $option);

    return $options;
}

function HookConsentmanagerAllTopnavlinksafterhome()
{
    global $baseurl, $lang;
    if (!checkperm("t") && checkperm("cm")) {
        ?>
        <li class="HeaderLink">
            <a href="<?php echo $baseurl ?>/plugins/consentmanager/pages/list.php" onClick="CentralSpaceLoad(this,true);return false;">
                <?php echo '<i aria-hidden="true" class="icon-user-check"></i>&nbsp;' . escape($lang["manageconsent"]); ?>
            </a>
        </li>
        <?php
    }
}

function HookConsentmanagerAllCron()
{
    global $consent_expiry_notification, $consent_expired_workflow_state;

    // Check if expiry notifications are enabled globally
    if ($consent_expiry_notification) {
        consentmanager_process_expiry_notifications();
    } else {
        logScript("Consent Manager: notifications disabled globally");
    }

    // Check if expired workflow state has been set
    if ($consent_expired_workflow_state !== '') {
        consentmanager_process_expired_auto_archive();
    } else {
        logScript("Consent Manager: automatic archiving disabled");
    }
}

function HookConsentmanagerAllAddspecialsearch($search, $select, $sql_join, $sql_filter)
{
    if (substr($search, 0, 8) == '!consent') {
        $consent_ref = substr($search, 8);
    } else {
        return null;
    }

    $consent = consentmanager_get_consent($consent_ref);

    $ids = isset($consent['resources']) ? $consent['resources'] : [];

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