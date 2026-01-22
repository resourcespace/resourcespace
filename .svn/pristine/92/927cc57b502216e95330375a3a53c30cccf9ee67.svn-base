<?php
use Montala\ResourceSpace\CommandPlaceholderArg;

function HookImage_textDownloadModifydownloadfile()
    {
    global $ref, $path, $tmpfile, $usergroup,  $ext, $resource_data,
    $image_text_restypes, $image_text_override_groups, $image_text_filetypes,
    $size, $page, $use_watermark, $alternative, $image_text_height_proportion,
    $image_text_max_height, $image_text_min_height, $image_text_font, $image_text_position,
    $image_text_banner_position, $imagemagick_path;

    if (getval('noattach', '')) {
        return;
    }

    # Return if not configured for this resource type or if user has requested no overlay and is permitted this
    if(!is_array($resource_data)
        ||
        !in_array($resource_data['resource_type'], $image_text_restypes)
        ||
        !in_array(strtoupper($ext), $image_text_filetypes)
        ||
        (getval("nooverlay","")!="" && in_array($usergroup, $image_text_override_groups))
        ||
        $use_watermark)
        {
        return false;
        }

    # Get text from field
    global $image_text_field_select, $image_text_default_text;
    $overlaydata = get_data_by_field($ref, $image_text_field_select);
    $overlaytext = mb_convert_encoding($overlaydata, mb_detect_encoding($overlaydata),"UTF-8");
    if($overlaytext=="")
        {
        if($image_text_default_text!="")
            {$overlaytext=$image_text_default_text;}
        else
            {return false;}
        }

    # If this is not a temporary file having metadata written see if we already have a suitable size with the correct text
    $image_text_saved_file = get_resource_path($ref,true,$size . "_image_text_" . md5($overlaytext . $image_text_height_proportion . $image_text_max_height . $image_text_min_height . $image_text_font . $image_text_position . $image_text_banner_position) . "_" ,false,$ext,-1,$page,$use_watermark,'',$alternative);

    if ($path != $tmpfile && file_exists($image_text_saved_file)) {
        $path = $image_text_saved_file;
        return true;
    }
    if ((list($width,$height) = try_getimagesize($path))===false) {
        return false;
    }
    $olheight=floor($height * $image_text_height_proportion);
    if ($olheight<$image_text_min_height && intval($image_text_min_height)!=0){$olheight=$image_text_min_height;}
    if ($olheight>$image_text_max_height && intval($image_text_max_height)!=0){$olheight=$image_text_max_height;}

    # Locate imagemagick.
    $convert_fullpath = get_utility_path("im-convert");
    if (!$convert_fullpath) {
        exit("Could not find ImageMagick 'convert' utility at location '$imagemagick_path'");
    }

    $tmpolfile= get_temp_dir() . "/" . $ref . "_image_text_" . uniqid() . "." . $ext;
    $createolcommand = $convert_fullpath . " -background '#000' -fill white -gravity %%POSITION%% -font %%FONT%% -size %%WIDTHHEIGHT%% caption:%%CAPTION%% %%TMPOLFILE%%";
    $createolparams = [
        "%%POSITION%%" => new CommandPlaceholderArg($image_text_position,fn($val): bool => in_array($val, ["east","west","center"])),
        "%%FONT%%" => new CommandPlaceholderArg($image_text_font, fn($val): bool => preg_match('/^[a-zA-Z0-9\#\s\-]*$/', $val) === 1),
        "%%WIDTHHEIGHT%%" => (int) $width . "x" . (int) $olheight,
        "%%CAPTION%%" => new CommandPlaceholderArg($overlaytext, 'is_string'),
        "%%TMPOLFILE%%" => $tmpolfile,
    ];

    run_command($createolcommand, false, $createolparams);

    $newdlfile = get_temp_dir() . "/" . $ref . "_image_text_result_" . uniqid() . "." . $ext;
    if($image_text_banner_position == "bottom") {
        $convertcommand = $convert_fullpath . " %%PATH%% %%TMPOLFILE%% -append %%NEWDLFILE%%";
    } else {
        $convertcommand = $convert_fullpath . " %%TMPOLFILE%% %%PATH%% -append %%NEWDLFILE%%";
    }

    $convertparams = [
        "%%PATH%%" => new CommandPlaceholderArg($path, 'is_valid_rs_path'),
        "%%TMPOLFILE%%" => new CommandPlaceholderArg($tmpolfile, 'is_safe_basename'),
        "%%NEWDLFILE%%" => new CommandPlaceholderArg($newdlfile, 'is_safe_basename'),
    ];
    run_command($convertcommand, false, $convertparams);

    $oldpath = $path;
    if ($path != $tmpfile) {
        // If this is not a temporary file having metadata written then move it to the filestore for future use
        rename($newdlfile, $image_text_saved_file);
        $path = $image_text_saved_file;
    } else {
        $path = $newdlfile;
    }
    try_unlink($tmpolfile);
    if (strpos(get_temp_dir(), $oldpath) === 0) {
        try_unlink($oldpath);
    }
    return true;
    }
