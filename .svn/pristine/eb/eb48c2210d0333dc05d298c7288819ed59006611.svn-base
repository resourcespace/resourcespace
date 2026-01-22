<?php
namespace RseVersion;

function is_valid_revert_state_request()
    {
    $collection = (int) getval("collection", 0, true);
    $ref        = (int) getval("ref", 0, true);

    // Reverting state is done for collections only
    if($collection > 0 && $ref > 0)
        {
        return true;
        }

    return false;
    }


function render_revert_state_form()
    {
    global $lang, $baseurl_short;

    $collection = (int) getval("collection", 0, true);
    $ref        = (int) getval("ref", 0, true);

    $change_summary = str_replace("%COLLECTION", $collection, $lang['rse_version_rstate_changes']);
    ?>
    <div class="BasicsBox">
        <p>
            <a href="<?php echo $baseurl_short ?>pages/collection_log.php?ref=<?php echo $collection; ?>"
               onclick="CentralSpaceLoad(this, true); return false;"><?php echo LINK_CARET_BACK ?><?php echo escape($lang["back"]); ?></a>
       </p>
        <h1><?php echo escape($lang["rse_version_revert_state"]); ?></h1>
        <p><?php echo $change_summary; ?></p>
        <form method="post"
              name="rse_version_revert_state_form" 
              id="rse_version_revert_state_form"
              action="<?php echo $baseurl_short ?>plugins/rse_version/pages/revert.php" onsubmit="CentralSpacePost(this, true); return false;">
            <input type="hidden" name="collection" value="<?php echo $collection; ?>">
            <input type="hidden" name="ref" value="<?php echo $ref; ?>">
            <input type="hidden" name="action" value="revert_state">
            <?php generateFormToken("rse_version_revert_state_form"); ?>
            <div class="QuestionSubmit">
                <input name="revert" type="submit" value="<?php echo escape($lang["revert"]); ?>">
            </div>
        </form>
    </div>
    <?php
    }


function process_revert_state_form()
    {
    $revert_state = getval("action", "") == "revert_state" ? true : false;
    if(!$revert_state)
        {
        return;
        }

    $collection = (int) getval("collection", 0, true);
    $ref        = (int) getval("ref", 0, true);

    revert_collection_state($collection, $ref);
    }


function revert_collection_state($collection, $ref)
    {
    global $baseurl;

    $parameters=array("i",$collection, 
                      "s",LOG_CODE_COLLECTION_ADDED_RESOURCE, 
                      "s",LOG_CODE_COLLECTION_REMOVED_RESOURCE, 
                      "s",LOG_CODE_COLLECTION_DELETED_ALL_RESOURCES,
                      "i",$ref);

    # The binary operators ensure that only "a", "r" and "D" entries are selected
    # Ignore LOG_CODE_COLLECTION_ACCESS_CHANGED (A) because it is irrelevant
    # Ignore LOG_CODE_COLLECTION_REMOVED_ALL_RESOURCES (R) because individual logs will be available as LOG_CODE_COLLECTION_REMOVED_RESOURCE (r)
    # Ignore LOG_CODE_COLLECTION_DELETED_RESOURCE (d) - Why? 
    $logs = ps_query("SELECT `ref`, `type`, resource
            FROM collection_log
           WHERE collection = ?
             AND (
                   `type` = ? AND BINARY `type` <> BINARY UPPER(`type`)
                OR `type` = ? AND BINARY `type` <> BINARY UPPER(`type`)
                OR `type` = ? AND BINARY `type` =  BINARY UPPER(`type`)
             )
             AND `ref` < ?
        ORDER BY `ref` ASC;",$parameters);

    if(count($logs) == 0)
        {
        return;
        }

    remove_all_resources_from_collection($collection);

    foreach($logs as $log)
        {
        if($log["ref"] == $ref)
            {
            break;
            }

        switch ($log["type"])
            {
            case LOG_CODE_COLLECTION_ADDED_RESOURCE:
                add_resource_to_collection($log['resource'], $collection);
                break;

            case LOG_CODE_COLLECTION_REMOVED_RESOURCE:
                remove_resource_from_collection($log['resource'], $collection);
                break;

            case LOG_CODE_COLLECTION_DELETED_ALL_RESOURCES:
                /*We remove all resources rather than delete all again because the user is just replaying events and 
                if we are deleting all at this point it will have undesired side effects such as deleting permanently
                the resources or moving to Deleted state when the resources were meant to be in a different state*/
                remove_all_resources_from_collection($collection);
                break;

            default:
                // Always move on to the next log if we didn't explictly handled it
                continue 2;
                break;
            }
        }

    redirect("{$baseurl}/pages/collection_log.php?ref={$collection}");
    }