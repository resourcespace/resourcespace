<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(401);
    exit('Access denied - Command line only!');
}

$root = dirname(__DIR__, 2);
require_once $root . '/include/cli_functions.php';
require_once $root . '/include/lang_translation_functions.php';

$models = ['gpt-4.1-nano', 'gpt-4.1-mini', 'gpt-4o-mini', 'gpt-4o', 'gpt-6-luna', 'gpt-6.1-terra'];
$restrict = null;
$audit = false;
foreach (array_slice($argv, 1) as $argument) {
    if ($argument === '--help') {
        echo "Usage: php lang_translate_gpt.php [locale] [model:model-id] [--audit]\n";
        echo "--audit checks existing entries for defects without using the API or writing language packs.\n";
        exit(0);
    }
    if ($argument === '--audit') {
        $audit = true;
    } elseif (str_starts_with($argument, 'model:') && strlen($argument) > 6) {
        $models = [substr($argument, 6)];
    } elseif ($restrict === null && preg_match('/^[a-z]{2,3}(?:-[a-zA-Z0-9]+)?$/D', $argument) === 1) {
        $restrict = $argument;
    } else {
        fwrite(STDERR, "Unknown argument: {$argument}\n");
        exit(2);
    }
}

if ($audit) {
    cli_log('ResourceSpace language pack audit', 'heading');
    // The audit reads stock language files and plugin descriptions without booting the installation.
    require_once $root . '/include/definitions.php';
    require_once $root . '/include/file_functions.php';
    require_once $root . '/include/plugin_functions.php';
} else {
    cli_log('ResourceSpace translation', 'heading');
    cli_log('  Loading configuration.', 'muted');
    require_once $root . '/include/boot.php';
    if (!is_string($openai_key ?? null) || trim($openai_key) === '') {
        fwrite(STDERR, "Configure an OpenAI API key before translating.\n");
        exit(2);
    }
    $lock = fopen(sys_get_temp_dir() . '/rs-language-translation-' . hash('sha256', $root) . '.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        fwrite(STDERR, "Could not acquire the translation lock for this checkout.\n");
        exit(2);
    }
}

if ($restrict !== null && !isset($languages[$restrict])) {
    fwrite(STDERR, "Unknown locale: {$restrict}\n");
    exit(2);
}

$ignore = [
    "map_hydda_group",
    "_dupe",
    "minute-abbreviated",
    "hour-abbreviated",
    "map_tf_group",
    "map_esridelorme",
    "posixldapauth_rdn",
    "to-page",
    "emu_upload_emu_field_label",
    "all__emailcollectionexternal",
    "upload_share_email_template",
    "all__emaillogindetails",
    "all__emailnotifyresourcessubmitted",
    "all__emailresourcerequest",
    "all__emailbulk",
    "all__emailresource",
    "system_notification_email",
    "all__emailcollection",
    "all__emailnotifyresourcesunsubmitted",
    "all__emailresearchrequestassigned",
    "all__emailnotifyuploadsharenew",
    "email_link_expires_date",
    "map_esri_group",
    "geodragmodepan",
    "map_stamen_group",
    "field-fileextension",
    "fileextension-inside-brackets",
    "fileextension",
    "plugin_field_fmt",
    "field_ref_and_name",
    "ref-title",
    "all__file_integrity_fail_email"
];

$components = ['' => $root];
foreach (glob($root . '/plugins/*/languages/en.php') ?: [] as $source_file) {
    $component_path = dirname($source_file, 2);
    $components[basename($component_path)] = $component_path;
}
$english_core = lang_translation_load_pack($root . '/languages/en.php');
cli_log('  Checking ' . count($components) . ' language pack directories'
    . ($restrict === null ? ' across all supported locales.' : " for {$restrict}."), 'muted');
$glossary_keys = array_fill_keys([
    'resource', 'resources', 'resourceid', 'resourcetype', 'collection', 'collections', 'metadata', 'archive',
    'archived', 'searchbutton', 'upload', 'download', 'status', 'featuredcollections', 'save', 'cancel',
], true);
$usage = null;
$locale_context = [];
$failed = 0;
$reviews = 0;
$processed = 0;
$groups = [];
$contexts = [];
$sources = [];

try {
    foreach ($components as $component => $directory) {
        $component_name = $component === '' ? 'ResourceSpace core' : $component;
        $english = $component === '' ? $english_core : lang_translation_load_pack($directory . '/languages/en.php');
        if ($component !== '' && ($yaml = get_plugin_yaml($component, true, false)) !== false) {
            $english['plugin-' . $component . '-title'] = $yaml['title'];
            $english['plugin-' . $component . '-desc'] = $yaml['desc'];
        }

        $pending = [];
        $targets = [];
        foreach ($languages as $locale => $language_name) {
            if (in_array($locale, ['en', 'en-US'], true) || ($restrict !== null && $locale !== $restrict)) {
                continue;
            }

            $file = $directory . '/languages/' . $locale . '.php';
            $existing = lang_translation_load_pack($file);
            $label = ($component === '' ? 'core' : $component) . '/' . $locale;
            if ($audit) {
                foreach (array_intersect_key($english, $existing) as $key => $source) {
                    $issue = lang_translation_issue($source, $existing[$key]);
                    if ($issue !== null) {
                        ++$failed;
                        echo "{$label} {$key}: {$issue}\n";
                    } elseif (is_string($source) && $source === $existing[$key] && str_word_count($source) >= 4) {
                        ++$reviews;
                        echo "{$label} {$key}: review possible untranslated English\n";
                    }
                }
                continue;
            }

            $missing = array_filter(
                array_diff_key($english, $existing, array_fill_keys($ignore, true)),
                static fn(mixed $value): bool => is_array($value) || (is_string($value) && strlen(trim($value)) > 1)
            );
            if ($missing === []) {
                continue;
            }

            if (!isset($locale_context[$locale])) {
                $locale_context[$locale] = [];
                $core_existing = $component === '' ? $existing : lang_translation_load_pack(
                    $root . '/languages/' . $locale . '.php'
                );
                foreach (array_intersect_key($core_existing, $glossary_keys, $english_core) as $key => $value) {
                    if (is_string($value) && lang_translation_issue($english_core[$key], $value) === null) {
                        $locale_context[$locale][$english_core[$key]] = $value;
                    }
                }
            }
            $pending[$locale] = $missing;
            $targets[$locale] = [
                'language' => $language_name,
                'existing_terminology' => $locale_context[$locale],
            ];
        }
        if ($pending === []) {
            continue;
        }

        $groups[$component] = lang_translation_batches($pending);
        $contexts[$component] = ['component' => $component_name, 'locales' => $targets];
        $sources[$component] = $english;
    }
    if ($groups !== []) {
        if ($usage === null) {
            cli_log('  Indexing source paths for translation context.', 'muted');
            $usage = lang_translation_usage_index($root);
            cli_log('  Indexed source paths for ' . count($usage) . ' language keys.', 'muted');
        }
        $batch_count = array_sum(array_map('count', $groups));
        $batch_label = $batch_count === 1 ? 'batch' : 'batches';
        cli_log("\nTranslation queue", 'heading');
        cli_log(
            "  {$batch_count} {$batch_label} across " . count($groups) . " components. First model: {$models[0]}.",
            'muted'
        );
        $untranslated = lang_translation_run(
            $openai_key,
            $models,
            $groups,
            ['components' => $contexts, 'usage' => $usage],
            static function (
                string $component,
                array $translations,
                string $model
            ) use (
                $components,
                $contexts,
                $sources,
                &$processed
            ): void {
                $directory = $components[$component];
                $component_name = $contexts[$component]['component'];
                foreach ($translations as $locale => $values) {
                    lang_translation_append($directory . '/languages/' . $locale . '.php', $values);
                    $processed += count($values);
                    $label = "  {$component_name}/{$locale} · Saved ";
                    cli_log($label . count($values) . " translations from {$model}.", 'success');
                    foreach ($values as $key => $value) {
                        $source = $sources[$component][$key];
                        $left = is_string($source) ? $source : json_encode($source, JSON_UNESCAPED_UNICODE);
                        $right = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
                        cli_log(
                            '    ' . cli_preview_column((string) $left) . ' => ' . cli_preview_column((string) $right)
                        );
                    }
                }
            }
        );
        foreach ($untranslated as $component => $locales) {
            $component_name = $contexts[$component]['component'];
            foreach ($locales as $locale => $entries) {
                foreach ($entries as $key => $reason) {
                    ++$failed;
                    cli_log("  {$component_name}/{$locale} {$key}: {$reason}", 'warning');
                }
            }
        }
    }
} catch (RuntimeException | JsonException $exception) {
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}

if ($audit) {
    cli_log("\nAudit summary", 'heading');
    cli_log("  Structural flags: {$failed}.", $failed > 0 ? 'warning' : 'success');
    cli_log("  Possible untranslated English: {$reviews}.", 'muted');
} else {
    cli_log("\nTranslation summary", 'heading');
    cli_log("  Saved: {$processed}.", 'success');
    cli_log("  Untranslated: {$failed}.", $failed > 0 ? 'warning' : 'muted');
}
exit($failed > 0 ? 1 : 0);
