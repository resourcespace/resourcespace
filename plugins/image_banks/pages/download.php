<?php

use function ImageBanks\getProviders;
use function ImageBanks\getProviderSelectInstance;
use function ImageBanks\providersCheckedAndActive;
use function ImageBanks\validFileSource;

ob_start(); $nocache=true;
$rs_root = dirname(__DIR__, 3);
include_once "{$rs_root}/include/boot.php";
include_once "{$rs_root}/include/authenticate.php";
ob_end_clean();

$id = getval("id", ""); # ID can be a string or integer based on how each provider implemented it
$file_path = getval("file", "");
$image_bank_provider_id = (int) getval("image_bank_provider_id", 0, true);

[$providers,] = getProviders($image_banks_loaded_providers);
$providers_select_list = providersCheckedAndActive($providers);
if (!($image_bank_provider_id > 0 && array_key_exists($image_bank_provider_id, $providers_select_list)))
    {
    debug(sprintf('[image_banks] Unable to find Provider #%s in %s', $image_bank_provider_id, json_encode($providers_select_list)));
    exit();
    }
$provider = getProviderSelectInstance($providers, $image_bank_provider_id);

if(!validFileSource($file_path, $provider))
    {
    $log_activity_note = str_replace("%FILE", $file_path, $lang["image_banks_local_download_attempt"]);
    log_activity($log_activity_note, LOG_CODE_SYSTEM, null, 'user', null, null, null, null, $userref, false);
    exit();
    }

debug(sprintf('[image_banks][Provider: %s] Downloading file %s', $providers_select_list[$image_bank_provider_id], $file_path));
$file = file_get_contents($file_path);
$file_size = strlen($file);
$file_info = $provider->getDownloadFileInfo($file_path);
$file_ext = $file_info->getExtension();
$filename = $file_info->getFilename();

header("Content-Type: application/octet-stream");
header("Content-Disposition: attachment; filename=\"{$filename}\"");
header("Content-Length: {$file_size}");
echo $file;
exit();
