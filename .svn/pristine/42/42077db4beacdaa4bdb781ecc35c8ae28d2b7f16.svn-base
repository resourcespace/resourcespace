<?php

function HookTms_linkCron_copy_hitcountAddplugincronjob()
    {
    global $php_path,$config_windows, $tms_link_enable_update_script;
    if(!$tms_link_enable_update_script){return false;}
    if (isset($php_path) && (file_exists($php_path . "/php") || ($config_windows && file_exists($php_path . "/php.exe"))))
        {
        echo "\r\nRunning TMS update script....\r\n";
        echo "COMMAND: \"" . $php_path . (($config_windows)?"/php.exe\" ":"/php\" ") . __DIR__ . "/../pages/tms_update_script.php";
        run_command("\"" . $php_path . (($config_windows)?"/php.exe\" ":"/php\" ") . __DIR__ . "/../pages/tms_update_script.php");
        echo "\r\nTMS update script started, please check setup page to ensure script has completed.\r\n";
        }
    else
        {
        echo "TMS script failed - \$php_path variable must be set in config.php\r\n";
        }
    
    }
