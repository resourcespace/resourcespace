<?php

$setting = $job_data['setting'] ?? '';
$fields = $job_data['fields'] ?? null;
$version = $job_data['version'] ?? null;
$success = false;

if (
    checkperm('a')
    && !$GLOBALS['system_read_only']
    && in_array($setting, CONFIG_SEARCH_FIELD_LISTS, true)
    && !in_array($setting, $GLOBALS['system_config_hide'], true)
    && is_string($fields)
    && is_string($version)
) {
    try {
        $success = config_save_option_list($setting, $fields, $version, config_search_field_choices(), true) !== null;
    } catch (Throwable $exception) {
        debug("Could not prepare search fields for job {$jobref}: " . $exception->getMessage());
    }
}

job_queue_update($jobref, $job_data, $success ? STATUS_COMPLETE : STATUS_ERROR);
