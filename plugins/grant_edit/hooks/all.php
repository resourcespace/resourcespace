<?php

function HookGrant_editAllCustomediteaccess($ref)
    {
    global $userref;

    if (!isset($GLOBALS['grant_edit_custom_edit_access'])){
        $GLOBALS['grant_edit_custom_edit_access'] = ps_array(
            "SELECT resource value FROM grant_edit WHERE user = ? AND (expiry IS null OR expiry >= NOW())
            UNION
            SELECT resource value FROM grant_edit ea JOIN user u ON u.usergroup = ea.usergroup WHERE user = ? AND (expiry IS null OR expiry >= NOW())"
            , array('i', $userref, 'i', $userref));
    }

    return in_array($ref, $GLOBALS['grant_edit_custom_edit_access']);
    }

function HookGrant_editAllModifysearcheditable($editable_filter, $user)
    {
    if(!is_numeric($user) || $editable_filter->sql == "")
        {
        // There is no restriction on editing so granting edit access is moot
        return false;
        }
    $editable_filter->sql = " ( " . $editable_filter->sql . " 
                                OR (r.ref IN (SELECT resource FROM grant_edit WHERE user = ? AND (expiry IS null OR expiry>=NOW())))
                                OR (r.ref IN (SELECT resource FROM grant_edit JOIN user WHERE user.usergroup = grant_edit.usergroup AND user.ref = ? AND (expiry IS null OR expiry>=NOW()) ))
                               )";
    $editable_filter->parameters = array_merge($editable_filter->parameters,["i",$user, "i", $user]); 
    return $editable_filter;
    }

function HookGrant_editAllExport_add_tables()
    {
    return array("grant_edit"=>array());
    }

function HookGrant_editAllModifyDefaultStatusMode()
    {
    global $resource;
    
    if(is_array($resource) && array_key_exists('archive', $resource))
        {
        return $resource['archive'];
        }
    return false;
    }
