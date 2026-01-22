<?php

function HookPropose_changesAllAddtoactions()
    {
    global $actions_propose_changes;
    if($actions_propose_changes)
        {
        $alleditable_query=do_search("","","",0,-1,"",false,0,false,false,"",false,true, true, true,true);
        
        $alleditable_changes_query= new PreparedStatementQuery();  
        
        $alleditable_changes_query->sql="SELECT pc.date,pc.resource AS ref, pc.user as user,group_concat(f.title) AS description, 'proposed_change' AS type 
                        FROM propose_changes_data pc JOIN resource_type_field f ON pc.resource_type_field=f.ref 
                        WHERE pc.resource in (select ref from (" . $alleditable_query->sql . ") editable) GROUP BY pc.resource";
        $alleditable_changes_query->parameters = array_merge($alleditable_query->parameters, $alleditable_changes_query->parameters);
        return $alleditable_changes_query;  
        }
    return false;
    }
   
function HookPropose_changesAllShowfieldedit($field)
    {
    if(checkperm("f*") || checkperm("f" . $field))
        {
        return true;   
        }
    return false;
    }

function HookPropose_changesAllExport_add_tables()
    {
    return array("propose_changes_data"=>array("scramble"=>array("value"=>"mix_text")));
    }

/**
 * Add any recent proposed changes to the $newactions array passed from get_user_actions_recent()
 * and return the updated array
 *
 * @param int $minutes
 * @param array $newactions
 * 
 * @return array
 * 
 */
function HookPropose_changesAlluser_actions_recent(int $minutes,array $newactions) : array
    {
    $recentprops = ps_query("
                    SELECT pc.date,
                        pc.resource AS ref,
                        pc.user AS user,
                        group_concat(f.title) AS description,
                        'proposed_change' AS type 
                    FROM propose_changes_data pc
                    JOIN resource_type_field f ON pc.resource_type_field=f.ref 
                    WHERE TIMESTAMPDIFF(MINUTE,pc.date ,NOW())<?
                GROUP BY pc.resource",
                            ["i",$minutes]
                        );


    foreach($recentprops as &$recentprop)
        {
        $recentprop["access_callback"] = [
                "function"=>"get_edit_access",
                "parameters" => [$recentprop["ref"]],
                "required" => true,
            ];
        }

    $newactions["proposed_change"] = $recentprops;
    return $newactions;
    }


function HookPropose_changesAllUpdateactiontypes($actiontypes)
    {
    global $actions_propose_changes;
    if($actions_propose_changes)
        {
        $addactiontypes=array("proposed_change");
        return array_merge($actiontypes,$addactiontypes);
        }
    return false;
    }
    

function HookPropose_changesAllActioneditlink($action)
    {
    global $baseurl;
    if($action["type"]=="proposed_change")
        {
        return array("editlink" => $baseurl . "/plugins/propose_changes/pages/propose_changes.php","viewlink" => $baseurl . "/pages/view.php");
        }
    return false;
    }
