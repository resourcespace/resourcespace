<?php

include __DIR__ . "/../include/adobe_link_functions.php";

function HookAdobe_linkAllInitialise()
{
    if (
        isset($_SERVER['HTTP_USER_AGENT'])
        &&
        in_array($_SERVER['HTTP_USER_AGENT'], ["InDesign-DAMConnect","PhotoShop-DAMConnect"])
    ) {
        // In the app users can't navigate to the login page manually and need to be given the option to use a standard account
        $GLOBALS["simplesaml_prefer_standard_login"] = true;
    }
}
