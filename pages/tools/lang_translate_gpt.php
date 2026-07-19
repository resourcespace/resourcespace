<?php

include dirname(__DIR__, 2) . '/include/boot.php';
command_line_only();

$restrict = $argv[1] ?? false;

// Models to use in the order to try them in.
$models = ["gpt-4.1-nano", "gpt-4.1-mini", "gpt-4o-mini", "gpt-4o"];

// Parallel request count
$parallel = 16;

// Smaller batches are usually faster overall
$batch_size = 15;

if (substr((string)$restrict, 0, 6) == "model:") {
    $models = [substr($restrict, 6)];
    $restrict = false;
}

function buildChatCompletionHandle($apiKey, $model, $messages = array(), $uid = "")
{
    $endpoint = "https://api.openai.com/v1/chat/completions";

    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer $apiKey",
    ];

    $data = [
        "model" => $model,
        "messages" => $messages,
        "temperature" => 0,
        "max_tokens" => 4096,
        "response_format" => ["type" => "json_object"],
        "user" => $uid,
    ];

    $ch = curl_init($endpoint);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    return $ch;
}

function run_parallel_translations($requests)
{
    $mh = curl_multi_init();

    foreach ($requests as $id => $request) {
        curl_multi_add_handle($mh, $request["handle"]);
    }

    $running = null;

do {

    do {
        $status = curl_multi_exec($mh, $running);
    } while ($status === CURLM_CALL_MULTI_PERFORM);

    echo "\rParallel requests active: {$running}   ";
    flush();

    if ($running > 0) {
        curl_multi_select($mh, 1);
    }

} while ($running > 0);

echo "\n";

    $results = [];

    foreach ($requests as $id => $request) {

        $response = curl_multi_getcontent($request["handle"]);

        if ($response === false) {
            $results[$id] = null;
        } else {
            $response_data = json_decode($response, true);

            $results[$id] =
                $response_data["choices"][0]["message"]["content"]
                ?? null;
        }

        curl_multi_remove_handle($mh, $request["handle"]);
        curl_close($request["handle"]);
    }

    curl_multi_close($mh);

    return $results;
}

function extract_json_object($text)
{
    $text = trim((string)$text);

    if (str_starts_with($text, "```")) {
        $text = preg_replace('/^```(?:json)?\s*/', '', $text);
        $text = preg_replace('/\s*```$/', '', $text);
    }

    $decoded = json_decode($text, true);

    if (is_array($decoded)) {
        return $decoded;
    }

    $start = strpos($text, "{");
    $end = strrpos($text, "}");

    if ($start !== false && $end !== false && $end > $start) {
        $json = substr($text, $start, $end - $start + 1);
        $decoded = json_decode($json, true);

        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return null;
}

function placeholders_match($source, $translation)
{
    preg_match_all("/\[([a-zA-Z0-9_]*)\]/", (string)$source, $source_params);
    preg_match_all("/\[([a-zA-Z0-9_]*)\]/", (string)$translation, $result_params);

    sort($source_params[0]);
    sort($result_params[0]);

    return $source_params[0] === $result_params[0];
}

function build_translation_messages($batch, $lang_name)
{
    return [
        [
            "role" => "system",
            "content" => "Your task is to translate language strings used by the digital asset management software ResourceSpace from English to {$lang_name}.

Ensure translations accurately reflect the intended meaning in the context of digital asset management software.

Return ONLY valid JSON.

The JSON object must have exactly the same keys as the input JSON object.

Each value must be the translated string.

Do not translate the keys.

Preserve placeholders in square brackets exactly.

Do not output markdown or explanations."
        ],
        [
            "role" => "user",
            "content" => json_encode($batch, JSON_UNESCAPED_UNICODE)
        ]
    ];
}

$plugins = scandir(RESOURCESPACE_BASE_PATH . "/plugins");
array_shift($plugins);
array_shift($plugins);

$plugins[] = "";
$plugins = array_reverse($plugins);

$bad_params = 0;
$calamity = 0;
$calamities = [];
$bad_params_list = [];

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

foreach ($plugins as $plugin) {

    $plugin_path = "";

    if ($plugin != "") {
        $plugin_path = "plugins/" . $plugin . "/";
    }

    $lang = [];
    $basefile = sprintf('%s/%slanguages/en.php', RESOURCESPACE_BASE_PATH, $plugin_path);

    if (!file_exists($basefile)) {
        continue;
    }

    include $basefile;
    $lang_en = $lang;

    foreach ($languages as $language => $lang_name) {

        if (in_array($language, ["en", "en-US"])) {
            continue;
        }

        if ($restrict !== false && $restrict != $language) {
            continue;
        }

        $lang = [];

        $langfile = sprintf(
            '%s/%slanguages/%s.php',
            RESOURCESPACE_BASE_PATH,
            $plugin_path,
            $language
        );

        if (!file_exists($langfile)) {
            file_put_contents($langfile, "<?php\n\n");
        }

        include $langfile;

        $lang_en_extended = $lang_en;

        if ($plugin != "") {

            $yaml_path = sprintf(
                '%s/%s.yaml',
                RESOURCESPACE_BASE_PATH,
                $plugin_path . $plugin
            );

            if (file_exists($yaml_path)) {

                $yaml = get_plugin_yaml($yaml_path);

                if (isset($yaml["title"])) {
                    $lang_en_extended["plugin-" . $plugin . "-title"] = $yaml["title"];
                }

                if (isset($yaml["desc"])) {
                    $lang_en_extended["plugin-" . $plugin . "-desc"] = $yaml["desc"];
                }
            }
        }

        $missing = array_diff(
            array_keys($lang_en_extended),
            array_keys($lang)
        );

        $missing = array_filter(
            $missing,
            function ($mkey) use ($lang_en_extended, $ignore) {

                if (is_array($lang_en_extended[$mkey])) {
                    return true;
                }

                if (!is_string($lang_en_extended[$mkey])) {
                    return false;
                }

                if (strlen(trim($lang_en_extended[$mkey])) <= 1) {
                    return false;
                }

                if (in_array($mkey, $ignore)) {
                    return false;
                }

                return true;
            }
        );

        if (count($missing) === 0) {
            continue;
        }

        echo "\nProcessing {$plugin_path} language {$language} ({$lang_name}) - " . count($missing) . " missing strings\n\n";

        $array_keys = [];
        $output_lines = [];
        $processed = 0;

        // Build all batches first
        $all_batches = [];
        $current_batch = [];

        foreach ($missing as $mkey) {

            $value = $lang_en_extended[$mkey];

            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
                $array_keys[$mkey] = true;
            }

            $current_batch[$mkey] = $value;

            if (count($current_batch) >= $batch_size) {
                $all_batches[] = $current_batch;
                $current_batch = [];
            }
        }

        if (count($current_batch) > 0) {
            $all_batches[] = $current_batch;
        }

        // Process batches in parallel groups
        for ($i = 0; $i < count($all_batches); $i += $parallel) {

            $group = array_slice($all_batches, $i, $parallel, true);

            echo "Processing parallel group starting batch " . ($i + 1) . "/" . count($all_batches) . "\n";

            $remaining_batches = $group;

            foreach ($models as $model) {

                if (count($remaining_batches) === 0) {
                    break;
                }

                echo "Using model: {$model}\n";

                $requests = [];

                foreach ($remaining_batches as $batch_id => $batch) {

                    $messages = build_translation_messages($batch, $lang_name);

                    $requests[$batch_id] = [
                        "batch" => $batch,
                        "handle" => buildChatCompletionHandle(
                            $openai_key,
                            $model,
                            $messages
                        )
                    ];
                }

                $responses = run_parallel_translations($requests);

                $failed_batches = [];

                foreach ($remaining_batches as $batch_id => $batch) {

                    $translations = extract_json_object($responses[$batch_id]);

                    if (!is_array($translations)) {

                        echo "Batch failed JSON parsing with model {$model}\n";

                        $failed_batches[$batch_id] = $batch;
                        continue;
                    }

                    $batch_failed = false;

                    foreach ($batch as $key => $source) {

                        if (
                            !isset($translations[$key])
                            || !is_string($translations[$key])
                        ) {
                            $batch_failed = true;
                            break;
                        }

                        $translation = $translations[$key];

                        if (
                            strlen(trim($translation)) === 0
                            || stripos($translation, "calamity") !== false
                            || stripos($translation, "[error]") !== false
                        ) {
                            $batch_failed = true;
                            break;
                        }

                        if (!placeholders_match($source, $translation)) {

                            echo "Placeholder mismatch: {$key}\n";

                            $bad_params++;
                            $bad_params_list[] = $key;

                            $batch_failed = true;
                            break;
                        }
                    }

                    if ($batch_failed) {
                        $failed_batches[$batch_id] = $batch;
                        continue;
                    }

                    // Batch succeeded
                    foreach ($batch as $key => $source) {

                        $translation = $translations[$key];

                        if (isset($array_keys[$key])) {

                            $decoded = json_decode($translation, true);

                            if ($decoded === null) {

                                echo "Failed array decode: {$key}\n";

                                $calamity++;
                                $calamities[] = $key;

                                continue;
                            }

                            $translation = $decoded;
                        }

                        $output_lines[] =
                            "\n\$lang[\"" .
                            addslashes($key) .
                            "\"] = " .
                            var_export($translation, true) .
                            ";";

                        echo "{$model} translated to {$language}: {$key}\n";

                        $processed++;
                    }
                }

                $remaining_batches = $failed_batches;
            }

            // Any batches still remaining failed all models
            foreach ($remaining_batches as $batch) {
                foreach ($batch as $key => $value) {
                    $calamity++;
                    $calamities[] = $key;

                    echo "Failed all models: {$key}\n";
                }
            }

            // Write after each parallel group
            if (count($output_lines) > 0) {

                file_put_contents(
                    $langfile,
                    implode("", $output_lines),
                    FILE_APPEND
                );

                $output_lines = [];
            }
        }

        echo "\nCompleted {$plugin_path} language {$language}; added {$processed} translations\n\n";
    }
}

echo "\n\nTranslations that contained bad parameters and were skipped={$bad_params}\nCould not translate={$calamity}\n\n";

if (count($bad_params_list) > 0) {
    echo "Bad parameter strings:\n";
    print_r($bad_params_list);
    echo "\n";
}

if (count($calamities) > 0) {
    echo "Calamities:\n";
    print_r($calamities);
    echo "\n";
}
