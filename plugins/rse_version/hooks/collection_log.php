<?php
function HookRse_versionCollection_logLog_extra_columns_header()
    {
    global $lang;
    ?>
    <td width="5%">
        <div class="ListTools"><?php echo escape($lang["tools"]); ?></div>
    </td>
    <?php
    }


function HookRse_versionCollection_logLog_extra_columns_row($log, array $collection_info)
    {
    global $lang, $baseurl;

    if(!$log['revert_state_enabled'])
        {
        ?>
        <td></td>
        <?php
        return;
        }

    $url = generateURL(
        "{$baseurl}/plugins/rse_version/pages/revert.php",
        array(
            "collection" => $collection_info["ref"],
            "ref"       => $log["ref"],
        )
    );
    ?>
    <td>
        <div class="ListTools">
        <a href="<?php echo $url; ?>"
           onclick="CentralSpaceLoad(this, true); return false;"><?php echo LINK_CARET . $lang["rse_version_revert_state"]; ?></a>
        </div>
    </td>
    <?php
    }


function HookRse_versionCollection_logCollection_log_extra_fields()
    {
    return sprintf(",
            IF(
                   (`type` = '%s' AND BINARY `type` <> BINARY UPPER(`type`))
                OR `type` = '%s'
                OR (`type` = '%s' AND BINARY `type` = BINARY UPPER(`type`)), true, false
            ) AS revert_state_enabled",
        LOG_CODE_COLLECTION_ADDED_RESOURCE,
        LOG_CODE_COLLECTION_REMOVED_RESOURCE,
        LOG_CODE_COLLECTION_DELETED_ALL_RESOURCES
    );
    }