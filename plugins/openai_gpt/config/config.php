<?php
// OpenAI key from https://openai.com/api/
global $openai_gpt_api_key, $openai_gpt_model, $openai_gpt_token_limit, $openai_gpt_token_limit_days;
$openai_gpt_api_key = $openai_gpt_api_key ?? "";
$openai_gpt_model = $openai_gpt_model ?? "gpt-4o";

// The number of tokens that can be used in the specified time period, 0 is no limit
$openai_gpt_token_limit = $openai_gpt_token_limit ?? 0;

// The number of days the token limit applies to
$openai_gpt_token_limit_days = $openai_gpt_token_limit_days ?? 0;

$openai_gpt_fallback_model = "gpt-3.5-turbo";
$openai_gpt_system_message = "You are a formal API required to extract or convert information from the data provided. For the provided %%IN_TYPE%% input, you will respond with %%OUT_TYPE%%";

$openai_gpt_example_json_user = "List the largest five cities in: Scotland";
$openai_gpt_example_json_assistant = json_encode(["Glasgow","Edinburgh","Aberdeen","Dundee","Inverness"]);

$openai_gpt_example_text_user = "Summarise this text in a single sentence with a maximum of 30 words: Jupiter is the fifth planet from the Sun 
and the largest in the Solar System. It is a gas giant with a mass one-thousandth that of the Sun, but two-and-a-half times that of 
all the other planets in the Solar System combined. Jupiter is one of the brightest objects visible to the naked eye in the night sky, 
and has been known to ancient civilizations since before recorded history. It is named after the Roman god Jupiter. When viewed from Earth,
 Jupiter can be bright enough for its reflected light to cast visible shadows, and is on average the third-brightest natural object 
 in the night sky after the Moon and Venus.";
$openai_gpt_example_text_assistant = "Jupiter, the largest planet in the Solar System, is a gas giant positioned fifth from the Sun, 
known for its brightness and ability to cast visible shadows, and is named after the Roman god Jupiter.";

$openai_gpt_message_text = "text inputs";
$openai_gpt_message_output_text = "text";
$openai_gpt_message_input_JSON = "JSON encoded values.";
$openai_gpt_message_output_json = "a JSON encoded list of values as if provided by a traditional API.";

$openai_gpt_temperature = 0;

// Language to use for translations. If set to the empty string then the user's own language setting is used.
$openai_gpt_language = "";

// The following can't be changed from the plugin setup page
$openai_gpt_endpoint = "https://api.openai.com/v1/chat/completions";
$openai_gpt_max_tokens = 1000;
$openai_gpt_max_data_length = 10000;

$valid_ai_field_types = [
    FIELD_TYPE_RADIO_BUTTONS,
    FIELD_TYPE_CHECK_BOX_LIST,
    FIELD_TYPE_DROP_DOWN_LIST,
    FIELD_TYPE_DYNAMIC_KEYWORDS_LIST,
    FIELD_TYPE_TEXT_BOX_SINGLE_LINE,
    FIELD_TYPE_TEXT_BOX_MULTI_LINE,
    FIELD_TYPE_TEXT_BOX_LARGE_MULTI_LINE,
    FIELD_TYPE_WARNING_MESSAGE,
    FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE,
    ];

// Allow plugin to overwrite existing data (e.g. exif data)
$openai_gpt_overwrite_data = false;
