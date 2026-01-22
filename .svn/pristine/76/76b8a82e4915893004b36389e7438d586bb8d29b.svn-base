<?php

function HookSimpleldapTeam_user_editAdditionaluserfields()
    {
    global $user, $lang;
    if (isset($user["telephone"]))
        {
        ?>      
        <div class="Question"><label><?php echo escape($lang["simpleldap_telephone"]); ?></label><div class="fixed"><?php echo $user["telephone"]; ?></div><div class="clearerleft"> </div></div>
        <?php
        }
    }

function HookSimpleldapTeam_user_editPassword($user)
    {
    if (isset($user["origin"]) && $user["origin"] == "simpleldap")
        {
        return true;
        }
    return false;
    }

function HookSimpleldapTeam_user_editTicktoemailpassword()
    {
    global $user;
    if (isset($user["origin"]) && $user["origin"] == "simpleldap")
        {
        return true;
        }
    return false;
    }


    