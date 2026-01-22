<?php

$lang['csv_user_import_batch_user_import'] = 'Batch user import';
$lang['csv_user_import_import'] = 'Import';
$lang['csv_user_import'] = 'CSV user import';
$lang['csv_user_import_intro'] = 'Use this feature to feature to import a batch of users to ResourceSpace. Please pay close attention to the format of your CSV file and follow the below standards:';
$lang['csv_user_import_upload_file'] = 'Select file';
$lang['csv_user_import_processing_file'] = 'PROCESSING FILE...';
$lang['csv_user_import_error_found'] = 'Error(s) found - aborting';
$lang['csv_user_import_move_upload_file_failure'] = 'There was an error moving the uploaded file. Please try again or contact administrators.';

// CSV conditions:
$lang['csv_user_import_condition1'] = 'Make sure the CSV file is encoded using UTF-8';
$lang['csv_user_import_condition2'] = 'The CSV file must have a header row';
$lang['csv_user_import_condition3'] = 'Column(s) that will have values containing commas( , ), make sure you format it as type text so you don\'t have to add quotes (""). When saving as a .csv file, make sure to check the option of quoting text type cells';
$lang['csv_user_import_condition4'] = 'Allowed columns: *username, *email, password, fullname, account_expires, comments, ip_restrict, lang. Note: mandatory fields are marked with *';
$lang['csv_user_import_condition5'] = 'The language of the user will default back to the one set using "$defaultlanguage" config option if lang column is not found or doesn\'t have a value';

$lang["page-title_csv_user_import_csv_user_import"] = "CSV User Import";
