<?php

function HookRss2AllInitialise()
    {
    global $rss_fieldvars;
    config_register_core_fieldvars("RSS2 plugin",$rss_fieldvars);
    }

function HookRss2AllPreheaderoutput()
    {
    if(!function_exists("get_api_key"))
        {
        include_once __DIR__ . "/../../../include/api_functions.php";
        }
    }

function HookRss2AllSearchbarbeforebottomlinks()
    {
    include_once __DIR__ . "/../../../include/api_functions.php";   
    global $baseurl,$lang,$userpassword,$username,$userref,$api_scramble_key;
    
    $query="user=" . base64_encode($username) . "&search=!last50";
    $private_key = get_api_key($userref);
    // Sign the query using the private key
    $sign=hash("sha256",$private_key . $query);
    ?>
    <p><a href="<?php echo $baseurl?>/plugins/rss2/pages/rssfilter.php?<?php echo $query; ?>&sign=<?php echo urlencode($sign); ?>"><i aria-hidden="true" class="icon-rss"></i>&nbsp;<?php echo escape($lang["new_content_rss_feed"]); ?></a></p>
    <?php
    }

