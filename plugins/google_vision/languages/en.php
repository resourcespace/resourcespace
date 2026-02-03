<?php

$lang["google_vision_api_key"]="Google API key";
$lang["google_vision_label_field"]="Field for suggested keywords";
$lang["google_vision_landmarks_field"]="Field for landmarks";
$lang["google_vision_text_field"]="Field for extracted text";
$lang["google_vision_logo_field"]="Field for detected popular logos";


$lang["google_vision_api"]="Google Vision API";

$lang["google_vision_restypes"]="Enabled resource types";
$lang["google_vision_features"]="Enabled feature detection";
$lang["google_vision_autotitle"]="Automatically set the title to the highest ranking keyword";
$lang["google_vision_help"]="<strong>IMPORTANT</strong> - It is suggested that new fields are created for the purpose of storing Google Vision data, so it is clearly distinct from user-entered content.";
$lang["google_vision_face_detect"] = "Facial detection";
$lang["google_vision_face_detect_field"]="Field to store face detection data in (optional)";
$lang["google_vision_face_detect_fullface"] = "If storing face data, detect full faces and not just skin area. See <a href='https://cloud.google.com/vision/docs/reference/rest/v1/images/annotate#FaceAnnotation' target='_blank'>this link</a> for more information";
$lang["google_vision_face_detect_verbose"] = "Store verbose face detection data (includes all face data, locations of facial features and emotion detection information)";
$lang["google_vision_face_dependent_field"] = "Select a metadata field that will be hidden when facial recognition data field is empty (optional)";

$lang["google_vision_translation"]="Multilingual translation";
$lang["google_vision_translation_intro"]="Google Vision only returns English keywords. The separate translation API can be used to translate these to other languages. Ensure the Translation API is enabled in the Google console.";
$lang["google_vision_translation_api_key"]="Enable translation of Vision keywords via translation API by entering a valid Google API key";
$lang["google_vision_translation_languages"]='Comma separated list of language codes, e.g. "no,es"';
$lang["google_vision_translation_keep_english"]="Keep the original English keywords?";

$lang["page-title_google_vision_process_existing"]  = "Google Vision Process Existing";
$lang["page-title_google_vision_setup"]             = "Setup Google Vision Plugin";
$lang["page-title_google_vision_test_jpg"]          = "Google Vision Test";
$lang["page-title_google_vision_test"]              = "Google Vision Test";

$lang["google_vision"] = "Google Vision";
$lang["google_vision_process_existing"] = "Process unprocessed resources";
$lang["google_vision_process_existing_configure"] = "Configure job to process unprocessed resources";
$lang["google_vision_process_existing_collection_refs_help"] = "Setting this option will mean only resources in the listed collections will be processed. If no collections are specified then ALL suitable resources will be processed. Collections can be specified using a comma-separated list as well as ranges e.g 100,105,110-115";