<?php

include_once __DIR__ . '/../include/emu_functions.php';

function HookEmuCron_copy_hitcountAddplugincronjob()
{
    global $lang, $php_path, $config_windows, $emu_enable_script, $emu_interval_run, $emu_script_mode, $emu_log_directory,
           $emu_script_start_time, $emu_script_failure_notify_days;

    if (!$emu_enable_script) {
        return false;
    }

    // Make sure we run this at an interval specified by Admins, otherwise run this every time cron jobs run
    if ('' != $emu_interval_run) {
        $emu_script_last_ran        = '';
        $check_script_last_ran      = check_script_last_ran('last_emu_import', $emu_script_failure_notify_days, $emu_script_last_ran);
        $emu_script_future_run_date = new DateTime();

        // Use last date the script was run, if available
        if ($check_script_last_ran || (!$check_script_last_ran && $lang['status-never'] != $emu_script_last_ran)) {
            $emu_script_future_run_date = DateTime::createFromFormat('l F jS Y @ H:m:s', $emu_script_last_ran);
        }

        $emu_script_future_run_date->modify($emu_interval_run);

        // Calculate difference between dates and establish whether it should run or not
        $date_diff = $emu_script_future_run_date->diff(new DateTime());

        if (0 < $date_diff->days) {
            return false;
        }
    }

    if (!(isset($php_path) && (file_exists("{$php_path}/php") || ($config_windows && file_exists("{$php_path}/php.exe"))))) {
        echo PHP_EOL . 'EMu script failed - $php_path variable must be set in config.php';
        return false;
    }

    // Deal with log directory now so that scripts can just use it if they need to
    if ('' != trim($emu_log_directory)) {
        if (!is_dir($emu_log_directory)) {
            @mkdir($emu_log_directory, 0755, true);

            if (!is_dir($emu_log_directory)) {
                echo PHP_EOL . 'Unable to create log directory: "' . escape($emu_log_directory) . '"';
                return false;
            }
        } else {
            // Valid log directory

            // Clean up old files
            $iterator        = new DirectoryIterator($emu_log_directory);
            $log_expiry_time = $emu_script_start_time - ((5 * intval($emu_script_failure_notify_days)) * 24 * 60 * 60) ;

            foreach ($iterator as $file_info) {
                if (!$file_info->isFile()) {
                    continue;
                }

                $filename = $file_info->getFilename();

                // Delete log file if it is older than its expiration time
                if ('emu_script_log' == substr($filename, 0, 14) && $file_info->getMTime() < $log_expiry_time) {
                    @unlink($file_info->getPathName());
                }
            }
        }
    }


    if (EMU_SCRIPT_MODE_IMPORT == $emu_script_mode) {
        $script_file = __DIR__ . '/../pages/emu_script.php';

        if (!file_exists($script_file)) {
            echo PHP_EOL . 'EMu script "' . $script_file . '" not found!' . PHP_EOL;
            return false;
        }

        $command = "\"{$php_path}" . ($config_windows ? '/php.exe" ' : '/php" ') . $script_file;

        echo PHP_EOL . 'Running EMu script...' . PHP_EOL . "COMMAND: '{$command}'" . PHP_EOL;

        run_command($command);

        echo PHP_EOL . 'EMu script started, please check setup page to ensure script has completed.' . PHP_EOL;
    }

    if (EMU_SCRIPT_MODE_SYNC == $emu_script_mode) {
        $script_file = __DIR__ . '/../pages/emu_sync_script.php';

        if (!file_exists($script_file)) {
            echo PHP_EOL . 'EMu script "' . $script_file . '" not found!' . PHP_EOL;
            return false;
        }

        $command = "\"{$php_path}" . ($config_windows ? '/php.exe" ' : '/php" ') . $script_file;

        echo PHP_EOL . 'Running EMu script...' . PHP_EOL . "COMMAND: '{$command}'";

        run_command($command);

        echo PHP_EOL . 'EMu script started, please check setup page to ensure script has completed.';
    }

    return true;
}
