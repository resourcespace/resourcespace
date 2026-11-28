<?php
$lang['openai_gpt_process_existing_configure'] = 'உள்ள AI புலங்களை செயலாக்க வேலை அமைக்கவும்';
$lang['openai_gpt_process_existing_field_ref'] = 'AI புலம்';
$lang['openai_gpt_process_existing_overwrite'] = 'மீண்டும் எழுதவும்';
$lang['openai_gpt_process_existing_field_ref_help'] = 'இது புதுப்பிக்கப்பட வேண்டிய இலக்கு புலம்.';
$lang['openai_gpt_process_existing_collection_refs_help'] = 'இந்த விருப்பத்தை அமைத்தால், பட்டியலிடப்பட்ட தொகுப்புகளில் உள்ள வளங்கள் மட்டுமே செயலாக்கப்படும். எந்த தொகுப்புகளும் குறிப்பிடப்படாவிட்டால், அனைத்து பொருத்தமான வளங்களும் செயலாக்கப்படும். தொகுப்புகளை காமா பிரிக்கப்பட்ட பட்டியலாகவும், வரம்புகளாகவும் குறிப்பிடலாம் உதா: 100,105,110-115';
$lang['openai_gpt_process_existing_overwrite_help'] = 'இந்த விருப்பத்தை அமைத்தால், இலக்கு புலத்தில் உள்ள ஏதேனும் உள்ளடக்கம் மீண்டும் எழுதப்படும். மீண்டும் எழுதுதல் இயங்கும் போது உள்ளீட்டு புலத்தில் தரவு இல்லாவிட்டால், இலக்கு புலம் காலியாகும் என்பதை கவனிக்கவும்.';
$lang['openai_gpt_limit_warning'] = 'எச்சரிக்கை - டோக்கன் வரம்பு மீறப்பட்டுள்ளது, மேலும் OpenAI API அழைப்புகள் செயல்படாது. GPT புலங்கள் செயலாக்கப்பட மாட்டாது.';
$lang['openai_gpt_limit_warning_short'] = 'எச்சரிக்கை - டோக்கன் வரம்பு மீறப்பட்டுள்ளது, GPT புலங்கள் செயலாக்கப்பட மாட்டாது.';
$lang['openai_gpt_usage_days'] = 'கடந்த %%DAYS%% நாட்களில் டோக்கன் பயன்பாடு';
$lang['openai_gpt_token_limit'] = 'டோக்கன் வரம்பு';
$lang['openai_gpt_no_token_limit'] = 'டோக்கன் வரம்பு அமைக்கப்படவில்லை';
$lang['openai_gpt_configured_limit'] = '%%DAYS%% நாட்களுக்கு %%TOKEN_LIMIT%%';
$lang['openai_gpt_token_count'] = '%%TOKEN_COUNT%% டோக்கன்கள்';
$lang['openai_gpt_provider'] = 'AI வழங்குநர்';
$lang['openai_gpt_provider_override'] = 'வழங்குநர் உலகளாவிய அமைப்பில் பிணைக்கப்பட்டுள்ளது: [provider]';
$lang['ollama_name'] = 'ஒல்லாமா';
$lang['ollama_model'] = 'பயன்படுத்த வேண்டிய ஒல்லாமா மாதிரி (உதா. \'gemma3:12b\')';
$lang['plugin-openai_gpt-title'] = 'OpenAI/ஒல்லாமா மெட்டாடேட்டா செயலாக்கம்';
$lang['plugin-openai_gpt-desc'] = 'AI பயன்படுத்தி மெட்டாடேட்டா மற்றும் படங்களை தானாக செயலாக்குதல்.';

$lang['openai_gpt_title'] = 'OpenAI/Ollama மெட்டாடேட்டா செயலாக்கம்';
$lang['openai_gpt_intro'] = 'OpenAI API (அல்லது Ollama போன்ற பொருந்தக்கூடியவை)க்கு உள்ளடக்கப்பட்டுள்ள தரவுகள் அல்லது ஒரு வளத்தின் முன்னோட்ட படத்தை அனுப்பி, தனிப்பயன் கேள்வியுடன் உருவாக்கப்பட்ட மெட்டாடேட்டாவை சேர்க்கிறது. விரிவான தகவலுக்கு <a href=\'https://platform.openai.com/docs/introduction\' target=\'_blank\'>OpenAI ஆவணங்களை</a> பார்க்கவும்.';
$lang['property-openai_gpt_prompt'] = 'AI செயலாக்கக் கேள்வி';
$lang['property-openai_gpt_input_field'] = 'AI செயலாக்க உள்ளீடு';
$lang['openai_gpt_model'] = 'பயன்படுத்த வேண்டிய OpenAI மாடல் (உதா: \'gpt-4o\')';
$lang['openai_gpt_temperature'] = '0 முதல் 1 வரை மாதிரிப் பரிமாணம் (உயர் மதிப்புகள் மாதிரி அதிக ஆபத்துகளை ஏற்கும்)';
$lang['openai_gpt_max_tokens'] = 'அதிகபட்ச டோக்கன்கள்';
$lang['openai_gpt_advanced'] = 'எச்சரிக்கை - இந்த பகுதி சோதனை நோக்கங்களுக்காக மட்டுமே உள்ளது மற்றும் நேரடி அமைப்புகளில் மாற்றக்கூடாது. இங்கு உள்ள எந்தவொரு பிளக்கின் விருப்பங்களையும் மாற்றுவது அனைத்து மெட்டாடேட்டா புலங்களின் செயல்பாட்டையும் பாதிக்கும். கவனமாக மாற்றவும்!';
$lang['openai_gpt_system_message'] = 'ஆரம்ப அமைப்பு செய்தி உரை. %%IN_TYPE%% மற்றும் %%OUT_TYPE%% இடமாற்றிகள் மூலம் மூல/இலக்கு புல வகைகளின் அடிப்படையில் \'text\' அல்லது \'json\' ஆக மாற்றப்படும்';
$lang['openai_gpt_model_override'] = 'மாடல் உலகளாவிய அமைப்பில் பூட்டப்பட்டுள்ளது: [model]';
$lang['openai_gpt_processing_multiple_resources'] = 'பல வளங்கள்';
$lang['openai_gpt_processing_resource'] = 'வளம் [resource]';
$lang['openai_gpt_processing_field'] = '\'[field]\' புலத்திற்கான AI மெட்டாடேட்டா உருவாக்கம்';
$lang['property-gpt_source'] = 'GPT மூலம்';
$lang['openai_gpt_language'] = 'வெளியீட்டு மொழி';
$lang['openai_gpt_language_user'] = 'தற்போதைய பயனரின் மொழி';
$lang['openai_gpt_overwrite_data'] = 'விண்ணப்பிக்கப்பட்ட புலங்களில் உள்ள தற்போதைய தரவை மீள எழுத வேண்டுமா?';
$lang['openai_gpt'] = 'OpenAI GPT';
$lang['openai_gpt_process_existing'] = 'இருப்புள்ள AI புலங்களை செயலாக்கவும்';

$lang['openai_gpt_api_key'] = 'OpenAI API விசை. உங்கள் API விசையை பெற <a href=\'https://openai.com/api\' target=\'_blank\' >https://openai.com/api</a>';

$lang['ollama_endpoint'] = 'பயன்படுத்த வேண்டிய Ollama இறுதிப்புள்ளி (உதாரணமாக http://[IP]:11434/v1/chat/completions';
