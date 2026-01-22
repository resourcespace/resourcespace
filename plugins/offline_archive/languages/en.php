<?php
# English
# Language File for the offline_archive plugin
# -------
#

$lang['offline_archive_configuration']="Offline Archive Configuration Page";
$lang['offline_archive_archivefield']="Please select the metadata field that will hold the archive reference code";
$lang['offline_archive_archivepath']="Please enter the location that the archives will be created in";
$lang['offline_archive_restorepath']="For restoration of resources in StaticSync folder you can enter a subfolder of the StaticSync folder that restored files will be placed in";
$lang['offline_archive_preservedate']="Preserve file timestamp?";

$lang['offline_archive_administer_archive']="Administer archives";
$lang['offline_archive_view_pending']="View resources pending archive";
$lang['offline_archive_view_pending_restore']="View resources pending restore";
$lang['offline_archive_view_completed']="View archive jobs";

$lang['offline_archive_view_associated']="View associated resources";
$lang['offline_archive_view_as_search']="View resources as search";
$lang['offline_archive_entername']="Please enter archive name";

$lang['offline_archive_input_text']="Please enter either a list of resources or a collection id to mark for restore";
$lang['offline_archive_input_resources']="Please enter resource ids to restore, separated by commas";
$lang['offline_archive_input_collection']="Please enter collection id to restore";

$lang['offline_archive_name']="Archive prefix";
$lang['offline_archive_createnew']="Create new archive job";
$lang['offline_archive_archive_created']="Archive successfully created";
$lang['offline_archive_archive_ref']="Archive reference";
$lang['offline_archive_archive_date']="Archive date";
$lang['offline_archive_archive_status']="Archive date";
$lang['offline_archive_request_restore']="Request restore";
$lang['offline_archive_cancel_restore']="Cancel restore";
$lang['offline_archive_cancel_confirm']="Are you sure you want to cancel restoration of this resource";

$lang['offline_archive_restore_pending']="Pending restore from archive";
$lang['offline_archive_restore_confirm']="Confirm restore";
$lang['offline_archive_restore_resources']="Restore resources from archive";

$lang['offline_archive_restore_resource']="Restore from archive";
$lang['offline_archive_request_restore_text']="Please enter the reason for the request";
$lang['offline_archive_request_restore_reason']="Reason for restore request";
$lang['offline_archive_request_email_subject']="offline archive restore request";
$lang["offline_archive_requestsenttext"]="Your archive restore request has been submitted and will be looked at shortly.";

$lang['offline_archive_resources_restore_confirmed']="Resources successfully set for restore and will be restored when the relevant archives have been inserted or copied to the configured archive location";
$lang['offline_archive_resources_restore_cancel_confirmed']="Resource successfully removed from pending restore";

$lang['offline_archive_resource_log_archived']="- archived by offline archive plugin. Archive code: ";
$lang['offline_archive_resource_log_restored']="- restored by offline archive plugin";
$lang['offline_archive_resource_log_restore_set']="Marked for restoration from archive";
$lang['offline_archive_resource_log_restore_removed']="Cancelled restore from archive";
$lang['offline_archive_resource_log_restore_request']="Requested restore from archive";

$lang['offline_archive_resource_restore']="Restore resources";

$lang["offline_archive_statustype"][0]="Pending";
$lang["offline_archive_statustype"][1]="In progress";
$lang["offline_archive_statustype"][2]="Completed";

// Admin menu tool tip
$lang["offline_archive_administer_archive-tooltip"] = "Relocate archived files to an offline location";

$lang["page-title_offline_archive_administer_archive"]      = "Administer Archives";
$lang["page-title_offline_archive_offline_archive_action"]  = "Offline Archive";
$lang["page-title_offline_archive_pending_restore"]         = "Pending Resource Restore";
$lang["page-title_offline_archive_restore_request"]         = "Request Resource Restore";
$lang["page-title_offline_archive_restore"]                 = "Restore Archive";
$lang["page-title_offline_archive_setup"]                   = "Setup Offline Archive Plugin";
$lang["page-title_offline_archive_view_archives"]           = "View Resource Archives";
$lang["page-title_offline_archive_view_pending"]            = "View Pending Archives";