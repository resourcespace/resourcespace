<?php

include dirname(__DIR__, 2) . '/include/boot.php';
command_line_only();

$restrict = $argv[1] ?? false;
// Models to use in the order to try them in. The same model can be attempted twice as results often differ between requests.
$models = ["gpt-4o-mini","gpt-4o-mini","gpt-4o","gpt-3.5-turbo"];

// Try the below to clear any that couldn't be translated by the default model
//$model="gpt-3.5-turbo";

if (substr($restrict, 0, 6) == "model:") {
    $model = substr($restrict, 6);
    $restrict = false;
} // Option to set model

function generateChatCompletions($apiKey, $model, $temperature = 0, $max_tokens = 2048, $messages = array(), $uid = "")
{
    // Set the endpoint URL
    $endpoint = "https://api.openai.com/v1/chat/completions";

    // Encode the completion options as JSON
    // $completion_options_json = json_encode($completion_options);

    // Set the headers for the request
    $headers = [
        "Content-Type: application/json",
        "Authorization: Bearer $apiKey",
    ];

    // Set the data to send with the request
    $data = [
        "model" => $model,
        "messages" => $messages,
        "temperature" => $temperature,
        "max_tokens" => $max_tokens,
        "user" => $uid,
    ];

    // Initialize cURL
    $ch = curl_init($endpoint);

    // Set the options for the request
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
    ]);

    // Send the request and get the response
    $response = curl_exec($ch);


    // Decode the response as JSON
    $response_data = json_decode($response, true);

    // Return the completions
    //return print_r($response_data,true);
    if (isset($response_data["choices"][0]["message"]["content"])) {
        return $response_data["choices"][0]["message"]["content"];
    }
    // Response was not expected. Return the raw response.
    return print_r($response_data, true);
}

$plugins = scandir(RESOURCESPACE_BASE_PATH . "/plugins");
array_shift($plugins);
array_shift($plugins); // Discard first two which are "." and ".."
$plugins[] = ""; // Add an extra row to signify the base languages (not in a plugin)
$plugins = array_reverse($plugins);

$bad_params = 0;
$calamity = 0;
$calamities = [];
$bad_params_list = [];

// Strings that do not translate
$ignore = ["map_hydda_group", "_dupe", "minute-abbreviated", "hour-abbreviated", "map_tf_group", "map_esridelorme", "posixldapauth_rdn", "to-page", "emu_upload_emu_field_label", "all__emailcollectionexternal", "upload_share_email_template", "all__emaillogindetails", "all__emailnotifyresourcessubmitted", "all__emailresourcerequest", "all__emailbulk", "all__emailresource", "system_notification_email", "all__emailcollection", "all__emailnotifyresourcesunsubmitted", "all__emailresearchrequestassigned", "all__emailnotifyuploadsharenew", "email_link_expires_date", "map_esri_group", "geodragmodepan", "map_stamen_group", "field-fileextension", "fileextension-inside-brackets", "fileextension", "plugin_field_fmt", "field_ref_and_name", "ref-title", "all__file_integrity_fail_email"];

foreach ($plugins as $plugin) {
    $plugin_path = "";
    if ($plugin != "") {
        $plugin_path = "plugins/" . $plugin . "/";
    }

    // Get a baseline
    $lang = array();
    $basefile = sprintf('%s/%slanguages/en.php', RESOURCESPACE_BASE_PATH, $plugin_path);
    if (!file_exists($basefile)) {
        continue;
    } // This plugin does not have any translations.
    include $basefile;
    $lang_en = $lang;

    // Fetch a list of valid parameters using en.php as the base
    $base = file_get_contents($basefile);
    preg_match_all("/\[([a-zA-Z0-9_]*)\]/", $base, $params_correct);

    foreach ($languages as $language => $lang_name) {
        if (in_array($language, array("en","en-US"))) {
            continue;
        }
        if ($restrict !== false && $restrict != $language) {
            continue;
        }

        // Process a language
        $lang = array();
        $langfile = "../../" . $plugin_path . "languages/" . $language . ".php";
        $langfile = sprintf('%s/%slanguages/%s.php', RESOURCESPACE_BASE_PATH, $plugin_path, $language);

        // Create file if it doesn't exist.
        if (!file_exists($langfile)) {
            file_put_contents($langfile, "<?php\n\n");
        }

        // Include to get the lang array for this language
        include $langfile;

        // Add plugin title and description from plugin YAML so it gets a translation too
        $lang_en_extended = $lang_en;
        if ($plugin != "") {
            $yaml_path = sprintf('%s/%s.yaml', RESOURCESPACE_BASE_PATH, $plugin_path . $plugin);
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

        // Work out what we're missing.
        $missing = array_diff(array_keys($lang_en_extended), array_keys($lang));

        // Filter out things we won't process
        $missing = array_filter($missing, function ($mkey) use ($lang_en_extended, $ignore) {
            if (is_array($lang_en_extended[$mkey])) {
                return true; // keep, will be json_encoded later
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
        });

        $count = 0;
        foreach ($missing as $mkey) {
            $array = false;
            if (is_array($lang_en_extended[$mkey])) {
                // Process arrays as strings and reassemble at the end.
                $lang_en_extended[$mkey] = json_encode($lang_en_extended[$mkey]);
                $array = true;
            }

            $count++;
            $messages = array();
            $messages[] = array("role" => "system","content" => "Your task is to convert language strings used by the digital asset management software ResourceSpace from English to " . $lang_name . ". Ensure that the translation accurately reflects the intended meaning of the string in the context of digital asset management software, including any relevant objects/terminology used in ResourceSpace such as resources, collections, metadata, tags, users, groups, workflows and downloads.
 Where there is not an obvious translation in the DAM context, use a general context or make a best guess. Don't add the period character at the start or end of the translation if one isn't at the start or end of the value being translated.
Text within square brackets indicates a system parameter that MUST NOT itself be translated so for example '[list]' must remain '[list]' even if translating to Swedish. If a phrase does not require translation (e.g. because it is a Proper Noun with no local translation) simply return the phrase untranslated and consider this to be a valid translation.
In the event that you cannot provide a translation (with the exception of the cases listed above) return the word CALAMITY followed by the reason the translation could not be provided. Do not output anything that is not either a valid translation or the word CALAMITY with the reason.");
            $messages[] = array("role" => "user","content" => "Please translate: " . $lang_en_extended[$mkey]);

            foreach ($models as $model) {
                echo $model . ": " . $plugin_path . " " . $count . "/" . count($missing) . ": Processing $mkey for language $language\n\n";
                flush();
                ob_flush();

                echo "English       : " . $lang_en_extended[$mkey] . "\n";
                echo str_pad(substr($lang_name, 0, 13), 13) . " : ";

                $result = generateChatCompletions($openai_key, $model, 0, 2048, $messages);
                print_r($result);
                echo "\n\n";

                // Check there are no bad parameters in the results by comparing with the master list.
                preg_match_all("/\[([a-zA-Z0-9_]*)\]/", $result, $params);
                $wrong = array_diff($params[0], $params_correct[0]);
                if (count($wrong) > 0) {
                    echo "Bad parameters were generated: ";
                    print_r($wrong);
                    $bad_params++;
                    $bad_params_list[] = $mkey;
                    continue;
                }

                if (is_string($result) && strlen($result) > 0 && strpos(strtolower($result), "calamity") === false && strpos(strtolower($result), "[error]") === false) {
                    $f = fopen($langfile, "a");

                    // Convert an array back to array after being parsed as a string.
                    if ($array) {
                        $result = json_decode($result);
                        if (is_null($result)) {
                            continue;
                        }
                    }

                    fwrite($f, "\n\$lang[\"" . $mkey . "\"] = " . var_export($result, true) . ";");
                    fclose($f);
                    continue 2; // Jump out of the models loop
                } else {
                    if ($model == end($models)) {
                        $calamity++;
                        $calamities[] = $mkey;
                    }
                }
            }
        }
    }
}
echo "\n\nTranslations that contained bad parameters and were skipped=$bad_params\nCould not translate=$calamity\n\n";
