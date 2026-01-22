<?php

use ImageBanks\ResourceSpace;

use function ImageBanks\getProviders;
use function ImageBanks\getProviderSelectInstance;
use function ImageBanks\providersCheckedAndActive;
use function ImageBanks\validFileSource;

$rs_root = dirname(__DIR__, 3);
include_once "{$rs_root}/include/boot.php";
include_once "{$rs_root}/include/authenticate.php";
include_once "{$rs_root}/include/image_processing.php";
include_once "{$rs_root}/include/ajax_functions.php";

if(!(checkperm("c") || checkperm("d")))
    {
    ajax_unauthorized();
    }

$original_file_url = getval("original_file_url", "");
$image_bank_provider_id = (int) getval("image_bank_provider_id", 0, true);
$build_unable_to_upload_msg = fn(int $ref): array => ajax_build_message(
    str_replace("%RESOURCE", $ref, $GLOBALS['lang']["image_banks_unable_to_upload_file"])
);

[$providers,] = getProviders($image_banks_loaded_providers);
$providers_select_list = providersCheckedAndActive($providers);
if (!($image_bank_provider_id > 0 && array_key_exists($image_bank_provider_id, $providers_select_list)))
    {
    debug(sprintf('[image_banks][pages/ajax.php] Unable to find Provider #%s in %s', $image_bank_provider_id, json_encode($providers_select_list)));
    ajax_send_response(400, ajax_response_fail(ajax_build_message($GLOBALS['lang']['image_banks_provider_not_found'])));
    }
$provider = getProviderSelectInstance($providers, $image_bank_provider_id);

if(!validFileSource($original_file_url, $provider))
    {
    $log_activity_note = str_replace("%FILE", $original_file_url, $GLOBALS['lang']["image_banks_bad_file_create_attempt"]);
    log_activity($log_activity_note, LOG_CODE_SYSTEM, null, 'user', null, null, null, null, $userref, false);
    $original_file_url = "";
    }

if($original_file_url !== "")
    {
    $file_info = $provider->getDownloadFileInfo($original_file_url);
    if (is_banned_extension($file_info->getExtension()))
        {
        ajax_send_response(
            400,
            ajax_response_fail(
                ajax_build_message(str_replace("[filetype]",$uploaded_extension,$GLOBALS['lang']["error_upload_invalid_file"]))
            )
        );
        }

    $resource_type_from_extension = get_resource_type_from_extension(
        $file_info->getExtension(),
        $resource_type_extension_mapping,
        $resource_type_extension_mapping_default
    );

    // Clear the user template and then copy resource from user template. This should deal with archive state permissions
    // and put the resource in active state if user has access to it
    clear_resource_data(0 - $userref);
    $new_resource_ref = copy_resource(0 - $userref, $resource_type_from_extension);
    if($new_resource_ref === false)
        {
        $new_resource_ref = create_resource(
            $resource_type_from_extension,
            get_default_archive_state(),
            $userref,
            $GLOBALS['lang']["image_banks_createdfromimagebanks"]
        );
        }

    if(!$new_resource_ref)
        {
        ajax_send_response(500, ajax_response_fail(ajax_build_message($GLOBALS['lang']["image_banks_unable_to_create_resource"])));
        }

    if ($provider instanceof ResourceSpace)
        {
        // Download the file locally because if it's proxied via the remote pages/download.php it will end up being banned
        $GLOBALS['use_error_exception'] = true;
        try
            {
            $tmp_file_path = sprintf(
                '%s/%s.%s',
                get_temp_dir(false, generateUserFilenameUID($userref) . $provider->getId()),
                safe_file_name(pathinfo($file_info->getFilename(), PATHINFO_FILENAME)),
                $file_info->getExtension()
            );
            if(!copy($original_file_url, $tmp_file_path))
                {
                ajax_send_response(500, ajax_response_fail($build_unable_to_upload_msg($new_resource_ref)));
                }
            }
        catch(Throwable $t)
            {
            debug(sprintf(
                '[image_banks][pages/ajax.php] Failed to download remote file from "%s" to temp location "%s". Reason: %s',
                $original_file_url,
                $tmp_file_path,
                $t->getMessage()
            ));
            ajax_send_response(500, ajax_response_fail($build_unable_to_upload_msg($new_resource_ref)));
            }
        unset($GLOBALS['use_error_exception']);
        }

    // We intentionally want to extract embedded metadata from external Image Bank Provider
    if(!upload_file_by_url($new_resource_ref, false, false, false, $tmp_file_path ?? $original_file_url))
        {
        delete_resource($new_resource_ref);
        if (isset($tmp_file_path) && file_exists($tmp_file_path))
            {
            unlink($tmp_file_path);
            }
        ajax_send_response(500, ajax_response_fail($build_unable_to_upload_msg($new_resource_ref)));
        }

    ajax_send_response(200, ajax_response_ok(['new_resource_ref' => $new_resource_ref]));
    }


// If by this point we still don't have a response for the request, create one now telling client this is a bad request
ajax_send_response(400, ajax_response_fail(ajax_build_message($GLOBALS['lang']['error_generic'])));
