<?php

# English
# Language File for the csv_upload Plugin
# -------
# Note: when translating to a new language, preserve the original case if possible.

$lang["csv_upload_nav_link"] = "CSV upload";
$lang["csv_upload_intro"] = "This plugin allows you to create or update resources by uploading a CSV file. The format of the CSV is important";
$lang["csv_upload_condition1"] = "Make sure the CSV file is encoded using <b>UTF-8</b>.";
$lang["csv_upload_condition2"] = "The CSV must have a header row";
$lang["csv_upload_condition3"] = "To be able to upload resource files later using batch replace functionality there should be a column named 'Original filename' and each file should have a unique filename";
$lang["csv_upload_condition4"] = "All mandatory fields for any newly created resources must be present in the CSV";
$lang["csv_upload_condition5"] = "For column(s) that have values containing <b>commas( , )</b>, make sure you format it as type \"text\" so you don't have to add quotes (\"\"). When saving as a csv file, make sure to check the option of quoting text type cells";
$lang["csv_upload_condition6"] = "You can download a CSV file example by clicking on <a href=\"../downloads/csv_upload_example.csv\">csv-upload-example.csv</a>";
$lang["csv_upload_condition7"] = "To update existing resource data you can download a CSV with the existing metadata by clicking on the 'CSV export - metadata' option from the collection or search results actions menu";
$lang["csv_upload_condition8"] = "You can re-use a previously configured CSV mapping file by clicking on 'Upload CSV configuration file'";
$lang["csv_upload_error_no_permission"] = "You do not have the correct permissions to upload a CSV file";
$lang["check_line_count"] = "At least two rows found in CSV file";
$lang["csv_upload_file"] = "Select CSV file";
$lang["csv_upload_default"] = "Default";
$lang["csv_upload_error_no_header"]             = "No header row found in file";
$lang["csv_upload_update_existing"]             = "Update existing resources? If this is unchecked then new resources will be created based on the CSV data";
$lang["csv_upload_update_existing_collection"]  = "Only update resources in a specific collection?";
$lang["csv_upload_process"]                     = "Process";
$lang["csv_upload_add_to_collection"]           = "Add newly created resources to current collection?";
$lang["csv_upload_step1"]                       = "Step 1 - Select file";
$lang["csv_upload_step2"]                       = "Step 2 - Default resource options";
$lang["csv_upload_step3"]                       = "Step 3 - Map columns to metadata fields";
$lang["csv_upload_step4"]                       = "Step 4 - Checking CSV data";
$lang["csv_upload_step5"]                       = "Step 5 - Processing CSV";

$lang["csv_upload_update_existing_title"]       = "Update existing resources";
$lang["csv_upload_update_existing_notes"]       = "Select the options required to update existing resources";
$lang["csv_upload_create_new_title"]            = "Create new resources";
$lang["csv_upload_create_new_notes"]            = "Select the options required to create new resources";

$lang["csv_upload_map_fields_notes"]            = "Match the columns in the CSV to the required metadata fields. Clicking 'Next' will check the CSV without actually changing data";
$lang["csv_upload_map_fields_auto_notes"]       = "Metadata fields have been pre-selected based on names or titles but please check that these are correct";
$lang["csv_upload_workflow_column"]             = "Select the column that contains the workflow state ID";
$lang["csv_upload_workflow_default"]            = "Default workflow state if no column selected or if no valid state found in column";
$lang["csv_upload_access_column"]               = "Select the column that contains the access level (0=Open, 1=Restricted, 2=Confidential)";
$lang["csv_upload_access_default"]              = "Default access level if no column is selected or if no valid access found in column";
$lang["csv_upload_resource_type_column"]        = "Select the column that contains the resource type identifier";
$lang["csv_upload_resource_type_default"]       = "Default resource type if no column selected or if no valid type is found in column";
$lang["csv_upload_resource_match_column"]       = "Select the column that contains the resource identifier";
$lang["csv_upload_match_type"]                  = "Match resource based on resource ID or metadata field value?";
$lang["csv_upload_multiple_match_action"]       = "Action to take if multiple matching resources are found";
$lang["csv_upload_validation_notes"]            = "Check the validation messages below before proceeding. Click Process to commit the changes";
$lang["csv_upload_upload_another"]              = "Upload another CSV";
$lang["csv_upload_mapping config"]              = "CSV column mapping settings";
$lang["csv_upload_download_config"]             = "Download CSV mapping settings as file";
$lang["csv_upload_upload_config"]               = "Upload CSV mapping file";
$lang["csv_upload_upload_config_question"]      = "Upload CSV mapping file? Use this if you have uploaded a similar CSV before and have saved the configuration";
$lang["csv_upload_upload_config_set"]           = "CSV configuration set";
$lang["csv_upload_upload_config_clear"]         = "Clear CSV mapping configuration";
$lang["csv_upload_mapping_ignore"]              = "DO NOT USE";
$lang["csv_upload_mapping_header"]              = "Column Header";
$lang["csv_upload_mapping_csv_data"]            = "Sample data from CSV";
$lang["csv_upload_using_config"]                = "Using existing CSV configuration";
$lang["csv_upload_process_offline"]             = "Process CSV file offline? This should be used for large CSV files. You will be notified via a ResourceSpace message once the processing is complete";
$lang["csv_upload_oj_created"]                  = "CSV upload job created with job ID # [jobref]. <br/>You will receive a ResourceSpace system message once the job has completed";
$lang["csv_upload_oj_complete"]                 = "CSV upload job complete. Click the link to view the full log file";
$lang["csv_upload_oj_failed"]                   = "CSV upload job failed. Click the link to view the full log file and check for errors";
$lang["csv_upload_processing_x_meta_columns"]   = "Processing %count metadata columns";
$lang["csv_upload_processing_complete"]         = "Processing completed at [time] ([hours] hours, [minutes] minutes, [seconds] seconds";
$lang["csv_upload_error_in_progress"]           = "Processing aborted - this CSV file is already being processed";
$lang["csv_upload_error_file_missing"]          = "Error - CSV file missing: [file]";
$lang["csv_upload_full_messages_link"]          = "Showing only the first 1000 lines, to download the full log file please click <a href='[log_url]' target='_blank'>here</a>";
$lang["csv_upload_ignore_errors"]               = "Ignore errors and process file anyway";
$lang["csv_upload_process_offline_quick"]       = "Skip validation and process CSV file offline? This should only be used for large CSV files when testing on smaller files has been completed. You will be notified via a ResourceSpace message once the upload is complete";
$lang["csv_upload_force_offline"]               = "This large CSV may take a long time to process so will be run offline. You will be notified via a ResourceSpace message once the processing is complete";
$lang["csv_upload_recommend_offline"]           = "This large CSV may take a very long time to process. It is recommended that offline jobs are enabled if you need to process large CSVs";

$lang["csv_upload_createdfromcsvupload"]        = "Created from CSV Upload plugin";

$lang["csv_upload_check_file_error"]            = "CSV file can't be opened or read";
$lang["csv_upload_check_utf_error"]             = "CSV file is not valid UTF-8. Invalid character on line ";
$lang["csv_upload_check_removebom"]             = "CSV file has BOM which was not able to be removed";

$lang['csv_upload_validating'] = 'Validating';
$lang['csv_upload_processing'] = 'Processing';
$lang['processing_batch_edit_save'] = 'row [row_number] of [rows_count]';

$lang["page-title_csv_upload_csv_upload"] = "Upload CSV";