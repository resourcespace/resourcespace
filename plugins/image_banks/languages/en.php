<?php
$lang["image_banks_configuration"] = "Image Banks";
$lang["image_banks_search_image_banks_label"] = "Search external image banks";
$lang["image_banks_pixabay_api_key"] = "API key";
$lang["image_banks_image_bank"] = "Image Bank";
$lang["image_banks_image_bank_source"] = "Image Bank source";
$lang["image_banks_create_new_resource"] = "Create new resource";
$lang["image_banks_shutterstock_token"]="Shutterstock token (<a href='https://www.shutterstock.com/account/developers/apps' target='_blank'>generate</a>)";
$lang["image_banks_shutterstock_result_limit"]="Result limit (max. 1000 for free accounts)";
$lang["image_banks_shutterstock_id"]="Shutterstock image ID";
$lang["image_banks_label_resourcespace_instances_cfg"] = "Instances access (format: i18n name|baseURL|username|key|config)";
$lang["image_banks_resourcespace_file_information_description"] = "ResourceSpace %SIZE_CODE size";
$lang["image_banks_label_select_providers"] = "Select active providers";
$lang["image_banks_view_on_provider_system"] = "View on %PROVIDER system";

// Errors
$lang["image_banks_system_unmet_dependencies"] = "ImageBanks plugin has unmet system dependencies!";
$lang["image_banks_provider_unmet_dependencies"] = "'%PROVIDER' provider has unmet dependencies!";
$lang["image_banks_provider_id_required"] = "Provider ID required to complete search";
$lang["image_banks_provider_not_found"] = "Provider could not be identified using ID";
$lang["image_banks_bad_request_title"] = "Bad Request";
$lang["image_banks_bad_request_detail"] = "Request could not be handled by '%FILE'";
$lang["image_banks_unable_to_create_resource"] = "Unable to create a new resource!";
$lang["image_banks_unable_to_upload_file"] = "Unable to upload file from external Image Bank for resource #%RESOURCE";
$lang["image_banks_try_again_later"] = "Please try again later!";
$lang["image_banks_warning"] = "WARNING: ";
$lang["image_banks_warning_rate_limit_almost_reached"] = "Provider '%PROVIDER' will only allow %RATE-LIMIT-REMAINING more searches. This limit will reset in %TIME";
$lang["image_banks_try_something_else"] = "Try something else.";
$lang["image_banks_error_detail_curl"] = "php-curl package is not installed";
$lang["image_banks_error_generic_parse"] = "Unable to parse providers' configuration (for multi-instance)";
$lang["image_banks_error_resourcespace_invalid_instance_cfg"] = "Invalid configuration format for '%PROVIDER' (provider) instance";
$lang["image_banks_error_bad_url_scheme"] = "Invalid URL scheme found for '%PROVIDER' (provider) instance";
$lang["image_banks_error_unexpected_response"] = "Sorry, received an unexpected response from the provider. Please contact your system administrator for further investigation (see debug log).";

// Logs
$lang["image_banks_local_download_attempt"] = "User tried to download '%FILE' using the ImageBank plugin by pointing to a system which is not part of allowed providers";
$lang["image_banks_bad_file_create_attempt"] = "User tried to create a resource with '%FILE' file using the ImageBank plugin by pointing to a system which is not part of allowed providers";

$lang["image_banks_createdfromimagebanks"] = "Created from Image Banks plugin";

$lang["page-title_image_banks_download"]                = "Download Resource";
$lang["page-title_image_banks_search"]                  = "Search";
$lang["page-title_image_banks_setup"]                   = "Setup Image Banks Plugin";
$lang["page-title_image_banks_shutterstock_license"]    = "View Shutterstock Image";
$lang["page-title_image_banks_view"]                    = "View Resource";