<?php

/**
 * Send the new field value or image to the OpenAI API in order to update the linked field 
 *
 * @param int|array $resources          Resource ID or array of resource IDS
 * @param array     $target_field       Target metadata field array from get_resource_type_field()
 * @param array     $values             Array of strings from the field currently being processed
 * @param string    $file               Path to image file. If provided will use this file instead of metadata values
 * 
 * @return bool|array                    Array indicating success/failure 
 *                                      True if update successful, false if invalid field or no data returned
 * 
 */
function openai_gpt_update_field($resources,array $target_field,array $values, string $file="")
    {
    global $valid_ai_field_types, $FIXED_LIST_FIELD_TYPES,$language, $defaultlanguage, $openai_gpt_message_input_JSON, 
    $openai_gpt_message_output_json, $openai_gpt_message_text, $openai_gpt_processed, $openai_gpt_api_key,$openai_gpt_model,
    $openai_gpt_temperature,$openai_gpt_example_json_user,$openai_gpt_example_json_assistant,$openai_gpt_example_text_user,
    $openai_gpt_example_text_assistant,$openai_gpt_max_tokens, $openai_gpt_max_data_length, $openai_gpt_system_message,
    $openai_gpt_fallback_model, $openai_gpt_message_output_text, $open_gpt_model_override, $lang, $language, $languages, $openai_gpt_language;

    // Don't update if not a valid field type
    if(!in_array($target_field["type"],$valid_ai_field_types))
        {
        return false;
        }

    if(!is_array($resources))
        {
        $resources = [$resources];
        }

    set_processing_message(((count($resources)>1)?$lang["openai_gpt_processing_multiple_resources"]:str_replace("[resource]",$resources[0],$lang["openai_gpt_processing_resource"])) . ": " . str_replace("[field]",$target_field["name"],$lang["openai_gpt_processing_field"]));

    // Define a language instruction based on the language of the current user.
    $output_language=$openai_gpt_language;
    if ($output_language=="") {$output_language=$language;} // Empty string = use the language of the current user.
    $language_instruction=" The response should be in language: " . $languages[$output_language];

    $resources = array_filter($resources,"is_int_loose");

    $results = [];
    // Only get data for resources in resource types which have access to the target field.
    // No need to get openai_gpt data for resources that update_field() can't update.
    if ($target_field['global'] === 0 && isset($target_field['resource_types']))
        {
        $valid_resource_types = explode(',', $target_field['resource_types']);

        if (count($valid_resource_types) > 0)
            {
            $filtered_resources = array();
            foreach ($resources as $resource_ref)
                {
                $resource_ref_resource_type = get_resource_data($resource_ref)['resource_type'];
                if (in_array($resource_ref_resource_type, $valid_resource_types))
                    {
                    $filtered_resources[] = $resource_ref;
                    }
                else
                    {
                    debug("openai_gpt - skipping resource $resource_ref - target field unavailable for resource type $resource_ref_resource_type");
                    $results[$resource_ref] = false;
                    }
                }
            $resources = $filtered_resources;
            if (count($resources) === 0)
                {
                // All resources filtered out, nothing to process.
                return $results;
                }
            }
        }

    $valid_response = false;
    if(trim($file) != "")
        {
        $file_data = file_get_contents($file);
        $file_data_base64 = base64_encode($file_data);
                               
        
        $return_json = in_array($target_field["type"],$FIXED_LIST_FIELD_TYPES);
        $outtype = $return_json ? $openai_gpt_message_output_json : $openai_gpt_message_output_text;
        $system_message = str_replace(["%%IN_TYPE%%","%%OUT_TYPE%%"],["image",$outtype],$openai_gpt_system_message) . $language_instruction;
       
        $messages = [];
        $messages[] = ["role"=>"system","content"=>$system_message];

        $messages[] = [
            "role" => "user",
            "content" => [
                ["type" => "text", "text" => $target_field["openai_gpt_prompt"]],
                ["type" => "image_url",
                    "image_url" => [
                    "url" => "data:image/jpeg;base64, " . $file_data_base64,
                    "detail" => "low"
                    ]
                ]
            ]
        ];

        debug("openai_gpt - sending request prompt for image");
        }
    else
        {
        // Get data to use
        // Remove any i18n variants and use default system language
        $prompt_values  = [];
        $saved_language = $language;
        $language       = $defaultlanguage;
        
        foreach($values as $value)
            {
            if(substr($value,0,1) == "~")
                {
                $prompt_values[] = mb_strcut(i18n_get_translated($value),0,$openai_gpt_max_data_length);
                }
            elseif(trim($value) != "")
                {
                $prompt_values[] = mb_strcut($value,0,$openai_gpt_max_data_length);
                }
            }
        $language = $saved_language;
    
        // Generate prompt (only if there are any strings)
        if(count($prompt_values)==0)
            {
            // No nodes present, fake a valid response to clear target field
            $newvalue = '';
            $valid_response = true;
            $messages = [];
            }
        else
            {
            $send_as_json = count($prompt_values) > 1;
            $return_json = in_array($target_field["type"],$FIXED_LIST_FIELD_TYPES);

            $intype = $send_as_json ? $openai_gpt_message_input_JSON : $openai_gpt_message_text; 
            $outtype = $return_json ? $openai_gpt_message_output_json : $openai_gpt_message_output_text;

            $system_message = str_replace(["%%IN_TYPE%%","%%OUT_TYPE%%"],[$intype,$outtype],$openai_gpt_system_message) . $language_instruction;

            $messages = [];
            $messages[] = ["role"=>"system","content"=>$system_message];
            // Give a sample 
            if(in_array($target_field["type"],$FIXED_LIST_FIELD_TYPES))
                {
                $messages[] = ["role"=>"user","content"=> $openai_gpt_example_json_user];
                $messages[] = ["role"=>"assistant","content"=>$openai_gpt_example_json_assistant];
                }
            else
                {
                $messages[] = ["role"=>"user","content"=> $openai_gpt_example_text_user];
                $messages[] = ["role"=>"assistant","content"=>$openai_gpt_example_text_assistant];
                }
            $messages[] = ["role"=>"user","content"=> $target_field["openai_gpt_prompt"] . ": " . ($send_as_json ? json_encode($prompt_values) : $prompt_values[0])];
            }
        }
   
    debug("openai_gpt - sending request prompt " . json_encode($messages));

    // Can't use old model since move to chat API
    $use_model =trim($openai_gpt_model) == "text-davinci-003" ? $openai_gpt_fallback_model : $openai_gpt_model; 
    if (isset($open_gpt_model_override)) {$use_model=$open_gpt_model_override;}
    
    $openai_response = openai_gpt_generate_completions($openai_gpt_api_key,$use_model,$messages,$openai_gpt_temperature,$openai_gpt_max_tokens);

    if(trim($openai_response) != "")
        {
        debug("response from openai_gpt_generate_completions() : " . $openai_response);
        if(in_array($target_field["type"],$FIXED_LIST_FIELD_TYPES))
            {
            // Clean up response
            if(substr($openai_response,0,7) == "```json")
                {
                debug("openai_gpt - extracting JSON text");
                $openai_response = substr(trim($openai_response," `\""),4);
                }
            else
                {
                $openai_response = trim($openai_response," \"");
                }
            $apivalues = json_decode(trim($openai_response),true);
            if(json_last_error() !== JSON_ERROR_NONE || !is_array($apivalues))
                {
                debug("openai_gpt error - invalid JSON text response received from API: " . json_last_error_msg() . " " . trim($openai_response));
                if (strpos($openai_response, ",") !== false)
                    {
                    // Try and split on comma
                    $apivalues = explode(",",$openai_response);
                    }
                else
                    {
                    $apivalues = [$openai_response];
                    }
                }
            // The returned array elements may be associative or contain sub arrays - convert to list of strings
            $newstrings = [];
            foreach($apivalues as $attribute=>&$value)
                {
                if(is_array($value))
                    {
                    $value = json_encode($value);
                    }                
                $newstrings[] = is_int_loose($attribute) ? $value : $attribute . " : " . $value;
                }            
            // update_field() will separate on NODE_NAME_STRING_SEPARATOR
            $newvalue = implode(NODE_NAME_STRING_SEPARATOR,$newstrings);
            }
        else
            {
            $newvalue = trim($openai_response," \"");
            }
        $valid_response = true;
        }
    else
        {
        debug("openai_gpt error - empty response received from API: '" . trim($openai_response) . "'");
        }

    foreach ($resources as $resource) {
        $valuepresent = false;
        if (!$GLOBALS["openai_gpt_overwrite_data"]) {
            $current_data = get_data_by_field($resource, $target_field["ref"]);
            if (trim((string) $current_data) !== '') {
                $valuepresent = true;
            }
        }
        if (isset($openai_gpt_processed[$resource . "_" . $target_field["ref"]]) || $valuepresent) {
            // This resource/field has already been processed, or already has data present
            continue;
        }
        if ($valid_response) {
            debug("openai_gpt_update_field() - resource # " . $resource . ", target field #" . $target_field["ref"]);
            // Set a flag to prevent any possibility of infinite recursion within update_field()
            $openai_gpt_processed[$resource . "_" . $target_field["ref"]] = true;
            $result = update_field($resource,$target_field["ref"],$newvalue);
            $results[$resource] = $result;
        } else {
            $results[$resource] = false;
        }
    }
    return $results;
    }

/**
 * Call the OpenAI API
 *
 * Refer to https://beta.openai.com/docs/api-reference for detailed explanation
 * 
 * @param string    $apiKey             API key 
 * @param string    $model              Model name e.g. "text-davinci-003"
 * @param array     $messages           Array of prompt messages to generate response from API.
 *                                      See https://platform.openai.com/docs/guides/chat/introduction for more information
 * @param float     $temperature        Value between 0 and 1 - higher values means model will take more risks. Default 0.
 * @param int       $max_tokens         The maximum number of completions to generate, default 2048
 * 
 * @return string   The first API response text output
 * 
 */
function openai_gpt_generate_completions($apiKey, $model, $messages, $temperature = 0, $max_tokens = 2048)
    {
    debug("openai_gpt_generate_completions() \$model = '" . $model . "', \$prompt = '" . json_encode($messages) . "' \$temperature = '" . $temperature . "', \$max_tokens = " . $max_tokens);

    // Set the endpoint URL
    global $openai_gpt_endpoint,  $openai_response_cache, $userref;
    
    $messagestring = json_encode($messages);
    if(isset($openai_response_cache[md5($openai_gpt_endpoint . $messagestring)]))
        {
        return $openai_response_cache[md5($openai_gpt_endpoint . $messagestring)];
        }
    // $temperature must be between 0 and 1
    $temperature = floatval($temperature);
    if($temperature>1 || $temperature<0)
        {
        debug("openai_gpt invalid temperature value set : '" . $temperature . "'");
        $temperature = 0;
        }
    if(trim($apiKey) == "")
        {
        debug("openai_gpt error - missing API key");
        }
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
        "max_tokens" => (int)$max_tokens,
    ];

    // Initialize cURL
    $ch = curl_init($openai_gpt_endpoint);
    
    // Set the options for the request
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    // Send the request and get the response
    $response = curl_exec($ch);

    // Decode the response as JSON
    debug("openai_gpt_generate_completions original response : " . print_r($response,true));
    $response_data = json_decode($response, true);

    if(json_last_error() !== JSON_ERROR_NONE)
        {
        debug("openai_gpt error - invalid JSON response received from API: " . json_last_error_msg() . " " . trim($response));
        $openai_response_cache[md5($openai_gpt_endpoint . $messagestring)] = false;
        return false;
        }

    $error = $response_data["error"] ?? ($response_data["error"][0] ?? []); 
    if(!empty($error))
        {
        debug("openai_gpt_generate_completions API error - type:" . $error["type"] . ", message: " . $error["message"]);
        $openai_response_cache[md5($openai_gpt_endpoint . $messagestring)] = false;
        return false;
        }

    // Log the usage
    if (isset($response_data["usage"]["total_tokens"]) && is_numeric($response_data["usage"]["total_tokens"])) {daily_stat("OpenAI Token Usage",$userref,$response_data["usage"]["total_tokens"]);}

    // Return the text from the completions
    if (isset($response_data["choices"][0]["message"]["content"]))
        {
        $return = $response_data["choices"][0]["message"]["content"];
        $openai_response_cache[md5($openai_gpt_endpoint . $messagestring)] = $return;
        return $return;
        }
    return false;
    }

/**
 * Return array of resource type field refs for a given openai_gpt_input_field value.
 * 
 * @param  int   $field   ID of GPT input field.
 */
function openai_gpt_get_dependent_fields(int $field): array
    {
    return ps_array("SELECT ref AS `value` FROM resource_type_field WHERE openai_gpt_input_field = ?", ["i", $field]);
    }

/**
 * Return an array of resource type field refs for all AI configured fields
 * 
 * @return array    Array field references for all AI configured fields 
 */
function openai_gpt_get_configured_fields(): array
{
    global $valid_ai_field_types;

    $results = [];

    $ai_fields = ps_array("SELECT ref AS `value` FROM resource_type_field WHERE openai_gpt_input_field IS NOT NULL AND openai_gpt_prompt IS NOT NULL ORDER BY ref ASC");

    foreach($ai_fields as $ai_field) {

        $ai_field_data = get_resource_type_field($ai_field);

        if(!in_array($ai_field_data["type"], $valid_ai_field_types)) {
            continue;
        } else {
            $results[] = $ai_field_data;
        }
    }

    return $results;

}