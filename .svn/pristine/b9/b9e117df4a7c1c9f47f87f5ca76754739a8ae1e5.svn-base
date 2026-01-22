<?php

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, 'Start download_filename_format configuration upgrade...');

/*
Set the legacy vars to the defaults if not set.
*/
$prefix_resource_id_to_filename = $prefix_resource_id_to_filename ?? true;
$prefix_filename_string = $prefix_filename_string ?? "RS";
$original_filenames_when_downloading = $original_filenames_when_downloading ?? true;
$download_filename_id_only = $download_filename_id_only ?? false;
$download_id_only_with_size = $download_id_only_with_size ?? false;
$download_filenames_without_size = $download_filenames_without_size ?? false;

$build_download_filename_format = function (): string {
    $format_parts = [
        $GLOBALS['prefix_filename_string'],
    ];

    $filename_parts = [];
    $add_separator = $resource_added = false;
    if ($GLOBALS['original_filenames_when_downloading']) {
        $add_separator = true;
        $filename_parts[] = '%filename';
        if (!$GLOBALS['download_filenames_without_size']) {
            $filename_parts[] = '%size';
        }
        $filename_parts[] = '.%extension';
    } elseif ($GLOBALS['download_filename_id_only']) {
        $resource_added = true;
        $filename_parts[] = '%resource';
        if ($GLOBALS['download_id_only_with_size']) {
            $filename_parts[] = '%size';
        }
        $filename_parts[] = '.%extension';
    } elseif (isset($GLOBALS['download_filename_field'])) {
        $add_separator = true;
        $filename_parts[] = "%field{$GLOBALS['download_filename_field']}";
        if (!$GLOBALS['download_filenames_without_size']) {
            $filename_parts[] = '%size';
        }
        $filename_parts[] = '.%extension';
    } else {
        if (!$GLOBALS['download_filenames_without_size']) {
            $filename_parts[] = '%size';
        }
        $filename_parts[] = '%alternative';
        $filename_parts[] = '.%extension';
    }

    if (!$resource_added && $GLOBALS['prefix_resource_id_to_filename']) {
        $format_parts[] = '%resource' . ($add_separator ? '_' : '');
    }

    return implode('', array_merge($format_parts, $filename_parts));
};

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, 'Processing system wide config...');
$system_wide_cfg_msg = [];
$system_wide_dld_filename_format = $build_download_filename_format();
if (set_config_option(null, 'download_filename_format', $system_wide_dld_filename_format)) {
    set_sysvar(
        SYSVAR_UPGRADE_PROGRESS_SCRIPT,
        "Set download_filename_format configuration: {$system_wide_dld_filename_format}"
    );
} else {
    $system_wide_cfg_msg[] = str_replace(
        '%format%',
        $system_wide_dld_filename_format,
        $lang['upgrade_026_error_unable_to_set_config_system_wide']
    );
}

// Override only the options that have been deprecated
$get_global_config_options = function (bool $include_optional): array {
    $opts = [
        'prefix_resource_id_to_filename' => $GLOBALS['prefix_resource_id_to_filename'],
        'prefix_filename_string' => $GLOBALS['prefix_filename_string'],
        'original_filenames_when_downloading' => $GLOBALS['original_filenames_when_downloading'],
        'download_filename_id_only' => $GLOBALS['download_filename_id_only'],
        'download_id_only_with_size' => $GLOBALS['download_id_only_with_size'],
        'download_filenames_without_size' => $GLOBALS['download_filenames_without_size'],
    ];

    if (isset($GLOBALS['download_filename_field'])) {
        $opts['download_filename_field'] = $GLOBALS['download_filename_field'];
    } elseif ($include_optional) {
        $opts['download_filename_field'] = 'unset';

        // Required so override_rs_variables_by_eval() can override undefined globals
        $GLOBALS['download_filename_field'] = 'unset';
    }

    return $opts;
};
$deprecated_options = $get_global_config_options(true);
$reset_global_values_to = function (array $vars) {
    foreach ($vars as $name => $value) {
        $GLOBALS[$name] = $value;
    }
};
$get_var_value_from_code = function (array $find, string $code) {
    if (!eval_check_signed($code)) {
        return [];
    }

    $tokens = token_get_all("<?php {$code}");
    $matches = [];
    $current = [
        'token' => null,
        'value' => [],
        'assign' => false,
    ];

    foreach ($tokens as $token) {
        $is_token = is_array($token);
        $token_id = $is_token ? $token[0] : $token;
        $assigning = $current['assign'] && $current['token'] !== null;

        // Debug
        /* if ($is_token)
            {
            printf('%sLine %s: %s (%s)', PHP_EOL, $token[2], token_name($token[0]), $token[1]);
            }
        else
            {
            printf('%sCharacter (%s)', PHP_EOL, $token);
            } */

        if (T_VARIABLE == $token_id && isset($find[mb_strcut($token[1], 1)])) {
            $current['token'] = $token;
        } elseif ($current['token'] !== null && '=' === $token_id) {
            $current['assign'] = true;
            $current['value'][] = $token;
        } elseif ($assigning && ';' === $token_id) {
            $current['value'][] = $token;
            $matches[] = $current['token'][1] . implode('', $current['value']);
            $current = [
                'token' => null,
                'value' => [],
                'assign' => false,
            ];
        } elseif ($assigning) {
            $current['value'][] = $is_token ? $token[1] : $token;
        }
    }

    return $matches;
};
$sign_code = fn(string $code) => "//SIG" . sign_code($code) . "\n" . $code;

$process_config_overrides = function (
    array $rows,
    string $what
) use (
    $lang,
    $build_download_filename_format,
    $deprecated_options,
    $get_global_config_options,
    $get_var_value_from_code,
    $sign_code,
    $reset_global_values_to
): array {
    $messages = [];
    $init_deprecated_options = $deprecated_options;
    foreach ($rows as $row) {
        set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, sprintf("%s: %s", ucfirst($what), $row['name']));
        $config_options = trim((string) $row['config_options']);
        if ($config_options === '') {
            continue;
        }

        $reset_global_values_to($init_deprecated_options);
        $deprecated_options = $init_deprecated_options;
        $matches = $get_var_value_from_code($deprecated_options, $config_options);

        // Try overriding global variables based on the matched deprecated config options
        $GLOBALS['use_error_exception'] = true;
        try {
            // Auto signing $matches because a variable assignment from an already signed code is considered safe
            override_rs_variables_by_eval($GLOBALS, $sign_code(implode(PHP_EOL, $matches)), 'test');
        } catch (Throwable $th) {
            unset($GLOBALS['use_error_exception']);
            $err_msg = str_replace(
                ['%entity%', '%error%'],
                [
                    sprintf('%s (%s)', $row['name'], mb_strtolower($what)),
                    $th->getMessage()
                ],
                $lang['upgrade_026_error_unable_to_process_deprecated_config_options']
            );
            debug(sprintf('[upgrade][%s] %s', __FILE__, $err_msg));
            logScript($err_msg);
            $messages[] = $err_msg;
            continue;
        }
        unset($GLOBALS['use_error_exception']);

        $current_global_vars = $get_global_config_options(false);

        if ($deprecated_options === $current_global_vars) {
            continue;
        }

        // Remove made up value - @see $get_global_config_options definition
        if (
            isset($current_global_vars['download_filename_field'])
            && $current_global_vars['download_filename_field'] === 'unset'
        ) {
            unset($GLOBALS['download_filename_field'], $deprecated_options['download_filename_field']);
        }

        $messages[] = str_replace(
            ['%entity%', '%format%'],
            [
                sprintf("%s (%s)", $row['name'], mb_strtolower($what)),
                $build_download_filename_format()
            ],
            $lang['upgrade_026_notification']
        );
    }
    return $messages;
};

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, 'Processing user group config overrides...');
$ug_msg = $process_config_overrides(get_usergroups(), $lang['user_group']);

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, 'Processing resource type config overrides...');
$rt_msg = $process_config_overrides(get_resource_types('', true, true, false), $lang['property-resource_type']);

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, 'Notify admins');
$upgrade_26_admin_messages = array_filter(array_merge($system_wide_cfg_msg, $ug_msg, $rt_msg));
$notification_users = array_column(get_notification_users('SYSTEM_ADMIN'), 'ref');
foreach ($upgrade_26_admin_messages as $msg) {
    message_add(
        $notification_users,
        "{$lang['upgrade_script']} #026: {$msg}",
        '',
        null,
        MESSAGE_ENUM_NOTIFICATION_TYPE_SCREEN,
        MESSAGE_DEFAULT_TTL_SECONDS
    );
}

set_sysvar(SYSVAR_UPGRADE_PROGRESS_SCRIPT, 'Finished download_filename_format configuration upgrade!');
