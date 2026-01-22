<?php

include '../../../include/boot.php';
include_once __DIR__ . "/../include/utility.php";

$k=getval("k","");
$ref = getval('ref', 0, true);
$size = getval('size', '');
$page = getval('page', 1, true);
$alternative = getval('alt', -1, true);
$usage = getval('usage', "-1");
$usagecomment=getval('usagecomment',"");

$resource = get_resource_data($ref);
$width = getval('width', 0, true);
$height = getval('height', 0, true);

if('' == $k || !check_access_key($ref, $k)) //Check if available through external share
    {
    include __DIR__ . '/../../../include/authenticate.php';
    }

// Permissions check
$allowed = resource_download_allowed($ref, $size, $resource['resource_type'], $alternative);
debug("PLUGINS/FORMAT_CHOOSER/PAGES/CONVERT.PHP: \$allowed = " . ($allowed ? 'TRUE' : 'FALSE'));

if(!$allowed || $ref <= 0)
    {
    debug("PLUGINS/FORMAT_CHOOSER/PAGES/CONVERT.PHP: Permission denied!");
    # This download is not allowed. How did the user get here?
    exit('Permission denied');
    }

if ($width == 0 && $height == 0)
    {
    $format = getImageFormat($size);
    $width = (int)$format['width'];
    $height = (int)$format['height'];
    }

$ext = getval('ext', getDefaultOutputFormat());
if(is_banned_extension($ext))
    {
    $error_extension = str_replace('[filetype]',$ext,$lang['error_upload_invalid_file']);
    error_alert($error_extension, true);
    exit();
    }
$profile = getProfileFileName(getval('profile', null));

$target = sprintf('%s/%s_%s.%s',
    get_temp_dir(false, 'format_chooser' . $scramble_key),
    $ref,
    md5($username . date('Ymd', time()) . $scramble_key),
    $ext
);

set_time_limit(0);

convertImage($resource, $page, $alternative, $target, $width, $height, $profile);

daily_stat('Resource download', $ref);
resource_log($ref, LOG_CODE_DOWNLOADED, 0, $lang['format_chooser'] . ' ' . $usagecomment, '', $size, $usage);

if(file_exists($target))
    {
    sendFile($target, get_download_filename($ref, $size, $alternative, $ext), $usage, $usagecomment);
    unlink($target);
    }
