<?php
# English
# Language File for the Flickr Theme Publish Plugin
# -------
#
#
$lang["flickr_title"]="Flickr Publishing";
$lang["publish_to_flickr"]="Publish to Flickr";

$lang["publish_all"]="Publish $ and update ? resources"; # e.g. Publish 1 and update 3 resources
$lang["publish_new-1"]="Publish 1 new resource only"; # Publish 1 new resource only
$lang["publish_new-2"]="Publish ? new resources only"; # e.g. Publish 17 new resources only

$lang["publish_new_help"]="Click the button below to publish only the resources that have not previously been published to Flickr.";
$lang["publish_all_help"]="Click the button below to publish new resources, and resubmit metadata for previously published resources.";

$lang["unpublished-1"]="1 unpublished"; # 1 unpublished
$lang["unpublished-2"]="%number unpublished"; # e.g. 17 unpublished

$lang["flickrloggedinas"]="You will be publishing to the Flickr account";

$lang["flickrnotloggedin"]="Log in to the target Flickr account";
$lang["flickronceloggedinreload"]="Once you have logged in and authenticated the application, click the reload button below.";

$lang["flickr_publish_as"]="Publish as:";
$lang["flickr_public"]="public";
$lang["flickr_private"]="private";
$lang["flickr-publish-public"]="Public";
$lang["flickr-publish-private"]="Private";

$lang["flickr_clear_photoid_help"]="You can clear the stored Flickr photo IDs for all photos in this set. This will cause them to be republished to Flickr even if they have been previously published. This may be useful if you have deleted the photos from Flickr and wish to add them again.";
$lang["clear-flickr-photoids"]="Clear Flickr photo IDs";
$lang["action-clear-flickr-photoids"]="Clear photo IDs";

$lang["processing"]="Processing";
$lang["updating_metadata_for_existing_photoid"]="Updating metadata for existing %photoid..."; # %photoid will be replaced, e.g. Updating metadata for existing 0123456789...
$lang["flickr_new_upload"]="Adding new photo: %photoid..."; # %photoid will be replaced
$lang["photo-uploaded"]="Photo uploaded: id=%photoid"; # %photoid will be replaced, e.g. Photo uploaded: id=0123456789
$lang["created-new-photoset"]="Created new photoset: '%photoset_name' with ID %photoset"; # %photoset_name and %photoset will be replaced, e.g. Created new photoset: 'Cars' with ID 01234567890123456
$lang["added-photo-to-photoset"]="Added photo %photoid to photoset %photoset."; # %photoid and %photoset will be replaced, e.g. Added photo 0123456789 to photoset 01234567890123456.
$lang["setting-permissions"]="Setting permissions to %permission"; # %permission will be replaced, e.g. Setting permissions to private
$lang["problem-with-url"]="Problem with %url, %php_errormsg"; # %url and %php_errormsg will be replaced
$lang["problem-reading-data"]="Problem reading data from %url, %php_errormsg"; # %url and %php_errormsg will be replaced
$lang["flickr-problem-finding-upload"]="A suitable upload for this resource cannot be found!";

$lang["flickr_processing"]="Processing";
$lang['photoprocessed']="photo processed";
$lang['photosprocessed']="photos processed";
$lang['flickr_published']="published";
$lang['flickr_updated']="metadata updated";
$lang['flickr_no_published']="without suitable sized upload";
$lang["flickr_publishing_in_progress"]='Please wait while we publish. This might take a while, depending on the total size of your resources.<br /><br />To continue working, you may use the previous window.<br /><br />';

$lang['flickr_theme_publish']="Flickr Theme Publish";
$lang['flickr_title_field'] = 'Title field';
$lang["flickr_caption_field"]="Caption field";
$lang["flickr_keywords_field"]="Keyword field";
$lang['flickr_prefix_id_title']="Prefix resource id to title";
$lang['flickr_default_size']="Default preview size to publish";
$lang['flickr_scale_up']="Publish alternative size if the one selected above is not available";
$lang["flickr_alt_image_sizes"]="List of alternative sizes to use, in order of preference (comma separated). Use 'original' for the original file";

$lang['flickr_nice_progress']="Use a popout with nicer output when publishing";
$lang['flickr_nice_progress_previews']="Show previews on popout";
$lang['flickr_nice_progress_metadata']="Show metadata to be published on popout";
$lang['flickr_nice_progress_min_timeout']="Time between progress pings (ms)";

$lang["flickr_api_key"] = "Flickr API key";
$lang["flickr_api_secret"] = "Flickr API secret";

$lang['flickr_warn_no_title_access'] = "Publishing not allowed without access to the title field (ID #%id). Please contact an administrator!";

$lang["page-title_flickr_theme_publish_flickr_preview"]             = "Flickr Preview";
$lang["page-title_flickr_theme_publish_flickr_publish_progress"]    = "Flickr Publish - Progress";
$lang["page-title_flickr_theme_publish_flickr_publish"]             = "Flickr Publish";
$lang["page-title_flickr_theme_publish_setup"]                      = "Setup Flickr Plugin";
$lang["page-title_flickr_theme_publish_sync_progress"]              = "Flickr Sync - Progress";
$lang["page-title_flickr_theme_publish_sync"]                       = "Flickr Sync";