<?php

function HookGrant_editViewBeforepermissionscheck()
    {
    global $ref,$userref, $access;
    $grant_edit=ps_value("SELECT resource value FROM grant_edit WHERE resource = ? AND user = ? AND (expiry IS null OR expiry >= NOW())
                        UNION 
                        SELECT resource value FROM grant_edit ea JOIN user u ON u.usergroup = ea.usergroup WHERE resource = ? AND user = ? AND (expiry IS null OR expiry>=NOW())", array("i",$ref,"i",$userref, 'i', $ref, 'i', $userref), "");
    if($grant_edit!=""){$access=0;}
    return true;        
    }