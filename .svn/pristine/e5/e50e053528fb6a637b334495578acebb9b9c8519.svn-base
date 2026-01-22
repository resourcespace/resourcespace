<?php

command_line_only();


// --- Set up
$original_state = $GLOBALS;
$orig_plugins = $GLOBALS['plugins'];
$setup_global_env = function () use ($original_state) {
    $GLOBALS['download_filename_format'] = 'RS%resource';
    $GLOBALS['userpermissions'] = $original_state['userpermissions'];

    // Fake (re)loading plugins on the fly so we can add one when a use case requires it
    unset($GLOBALS['hook_cache']);
    $GLOBALS['plugins'] = [];
};

$rtf_text = create_resource_type_field("Test #421 text", 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, "test_421_text", false);
$rtf_text2 = create_resource_type_field("Test #421 text 2", 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, "test_421_text2", false);

$resource_jpg_file = create_resource(1, 0);
$resource_mp4_file = create_resource(3, 0);

$file_jpg = create_random_image([
    'text' => "Resource #$resource_jpg_file",
    'width' => 300,
    'height' => 150,
]);
if (isset($file_jpg['error'])) {
    echo "{$file_jpg['error']} - ";
    return false;
}

$file_mp4 = create_random_video([
    'text' => "Resource #$resource_mp4_file",
]);
if (isset($file_mp4['error'])) {
    // This is considered an environment issue, not a code one!
    echo "INFO: {$file_mp4['error']} - ";
    return true;
}

// Upload files (don't create previews, out of scope)
$enable_thumbnail_creation_on_upload = false;
upload_file($resource_jpg_file, false, false, false, $file_jpg['path'], false, true);
upload_file($resource_mp4_file, false, false, false, $file_mp4['path'], false, true);

$resource_jpg_file_alt_ref = add_alternative_file(
    $resource_jpg_file,
    'test 421 alternative image',
    '',
    'alt_file_test_421',
    'png',
    42100,
    'alttype:test421'
);

function HookTestframeworkAllDownloadfilenamealt()
{
    return 'Hook download filename.jpg';
}
// --- End of Set up



$use_cases = [
    [
        'name' => 'Remove invalid file name characters from end result',
        'setup' => fn() => $GLOBALS['download_filename_format'] = "RS%resource_with:_or\r\n_or\r_or\n.%extension",
        'input' => ['ref' => $resource_jpg_file, 'size' => '', 'alternative' => 0, 'ext' => 'jpg'],
        'expected' => sprintf('RS%s_with__or__or__or_.%s', $resource_jpg_file, pathinfo($file_jpg['path'], PATHINFO_EXTENSION)),
    ],
    [
        'name' => 'Remove HTML tags from end result',
        'setup' => fn() => $GLOBALS['download_filename_format'] = "RS%resource_<b>without_tags</b>.%extension",
        'input' => ['ref' => $resource_jpg_file, 'size' => '', 'alternative' => 0, 'ext' => 'jpg'],
        'expected' => "RS{$resource_jpg_file}_without_tags.jpg",
    ],
    [
        'name' => 'Download filename overriden by a hook',
        'setup' => fn() => $GLOBALS['plugins'][] = 'testframework',
        'input' => ['ref' => $resource_jpg_file, 'size' => '', 'alternative' => 0, 'ext' => 'jpg'],
        'expected' => HookTestframeworkAllDownloadfilenamealt(),
    ],
    [
        'name' => 'Format with %resource placeholder',
        'setup' => fn() => $GLOBALS['download_filename_format'] = 'RS%resource',
        'input' => ['ref' => $resource_jpg_file, 'size' => '', 'alternative' => 0, 'ext' => 'jpg'],
        'expected' => "RS{$resource_jpg_file}.jpg",
    ],
    [
        'name' => 'Format with %resource and %extension placeholders',
        'setup' => fn() => $GLOBALS['download_filename_format'] = 'RS%resource.%extension',
        'input' => ['ref' => $resource_jpg_file, 'size' => '', 'alternative' => 0, 'ext' => 'jpg'],
        'expected' => "RS{$resource_jpg_file}.jpg",
    ],
    [
        'name' => '%size for original file is replaced with empty string',
        'setup' => fn() => $GLOBALS['download_filename_format'] = 'RS%resource%size.%extension',
        'input' => ['ref' => $resource_jpg_file, 'size' => '', 'alternative' => 0, 'ext' => 'jpg'],
        'expected' => "RS{$resource_jpg_file}.jpg",
    ],
    [
        'name' => '%size for screen preview is prefixed with underscore',
        'setup' => fn() => $GLOBALS['download_filename_format'] = 'RS%resource%size.%extension',
        'input' => ['ref' => $resource_jpg_file, 'size' => 'scr', 'alternative' => 0, 'ext' => 'jpg'],
        'expected' => "RS{$resource_jpg_file}_scr.jpg",
    ],
    [
        'name' => '%alternative for original file is replaced with empty string',
        'setup' => fn() => $GLOBALS['download_filename_format'] = 'RS%resource%alternative.%extension',
        'input' => ['ref' => $resource_jpg_file, 'size' => '', 'alternative' => 0, 'ext' => 'jpg'],
        'expected' => "RS{$resource_jpg_file}.jpg",
    ],
    [
        'name' => 'For alternative files, prefix %alternative with an underscore',
        'setup' => fn() => $GLOBALS['download_filename_format'] = 'RS%resource%alternative.%extension',
        'input' => ['ref' => $resource_jpg_file, 'size' => '', 'alternative' => 421, 'ext' => 'jpg'],
        'expected' => "RS{$resource_jpg_file}_421.jpg",
    ],
    [
        'name' => '%filename for original file',
        'setup' => fn() => $GLOBALS['download_filename_format'] = 'RS%resource-%filename.%extension',
        'input' => ['ref' => $resource_jpg_file, 'size' => '', 'alternative' => 0, 'ext' => 'jpg'],
        'expected' => sprintf('RS%s-%s.jpg', $resource_jpg_file, pathinfo($file_jpg['path'], PATHINFO_FILENAME)),
    ],
    [
        'name' => '%filename for alternative file',
        'setup' => fn() => $GLOBALS['download_filename_format'] = 'RS%resource-%filename.%extension',
        'input' => ['ref' => $resource_jpg_file, 'size' => '', 'alternative' => $resource_jpg_file_alt_ref, 'ext' => 'png'],
        'expected' => "RS{$resource_jpg_file}-test 421 alternative image.png",
    ],
    [
        'name' => 'Format with %fieldXX placeholder',
        'setup' => function () use ($resource_mp4_file, $rtf_text) {
            update_field($resource_mp4_file, $rtf_text, 'Lorem ipsum dolor sit amet.');
            $GLOBALS['download_filename_format'] = "RS%resource-%field{$rtf_text}.%extension";
        },
        'input' => ['ref' => $resource_mp4_file, 'size' => '', 'alternative' => 0, 'ext' => 'mp4'],
        'expected' => "RS{$resource_mp4_file}-Lorem ipsum dolor sit amet..mp4",
    ],
    [
        'name' => 'Format with %fieldXX placeholder (i18n value)',
        'setup' => function () use ($resource_mp4_file, $rtf_text) {
            update_field($resource_mp4_file, $rtf_text, '~en:Bird~fr:Oiseau');
            $GLOBALS['download_filename_format'] = "RS%resource-%field{$rtf_text}.%extension";
        },
        'input' => ['ref' => $resource_mp4_file, 'size' => '', 'alternative' => 0, 'ext' => 'mp4'],
        'expected' => "RS{$resource_mp4_file}-Bird.mp4",
    ],
    [
        'name' => 'Format with multiple %fieldXX placeholders',
        'setup' => function () use ($resource_mp4_file, $rtf_text, $rtf_text2) {
            update_field($resource_mp4_file, $rtf_text, 'valueFromText1');
            update_field($resource_mp4_file, $rtf_text2, 'valueFromText2');
            $GLOBALS['download_filename_format'] = "RS%resource-%field{$rtf_text}-%field{$rtf_text2}.%extension";
        },
        'input' => ['ref' => $resource_mp4_file, 'size' => '', 'alternative' => 0, 'ext' => 'mp4'],
        'expected' => "RS{$resource_mp4_file}-valueFromText1-valueFromText2.mp4",
    ],
    [
        'name' => 'Truncate the final filename (if > 255)',
        'setup' => function () use ($resource_mp4_file, $rtf_text) {
            update_field($resource_mp4_file, $rtf_text, str_repeat('X', 256));
            $GLOBALS['download_filename_format'] = "%field{$rtf_text}";
        },
        'input' => ['ref' => $resource_mp4_file, 'size' => '', 'alternative' => 0, 'ext' => 'mp4'],
        'expected' => str_repeat('X', 251) . ".mp4",
    ],
    [
        'name' => 'Enforce access control for %fieldXX placeholder',
        'setup' => function () use ($resource_mp4_file, $rtf_text) {
            update_field($resource_mp4_file, $rtf_text, 'valueFromText2');
            $GLOBALS['userpermissions'][] = "f-{$rtf_text}";
            $GLOBALS['download_filename_format'] = "RS%resource-%field{$rtf_text}.%extension";
        },
        'input' => ['ref' => $resource_mp4_file, 'size' => '', 'alternative' => 0, 'ext' => 'mp4'],
        'expected' => "RS{$resource_mp4_file}-.mp4",
    ],
    [
        'name' => 'Set download filename based on resource type',
        'setup' => function () use ($resource_jpg_file) {
            $rt = get_resource_data($resource_jpg_file)['resource_type'];
            $lockout = $GLOBALS['execution_lockout'];
            $GLOBALS['execution_lockout'] = false;
            save_resource_type($rt, ['config_options' => '$download_filename_format="RT_specific_name.%extension";']);
            resign_all_code(false, false);
            $GLOBALS['execution_lockout'] = $lockout;
        },
        'input' => ['ref' => $resource_jpg_file, 'size' => '', 'alternative' => 0, 'ext' => 'jpg'],
        'expected' => "RT_specific_name.jpg",
    ],
];
foreach ($use_cases as $use_case) {
    $setup_global_env();
    if (isset($use_case['setup'])) {
        $use_case['setup']();
    }

    $result = get_download_filename(
        $use_case['input']['ref'],
        $use_case['input']['size'],
        $use_case['input']['alternative'],
        $use_case['input']['ext']
    );
    if ($use_case['expected'] !== $result) {
        echo "Use case: {$use_case['name']} - ";
        printf(PHP_EOL . '$result: %s = %s' . PHP_EOL, gettype($result), $result);
        return false;
    }
}



// Tear down
$GLOBALS['plugins'] = $orig_plugins;
$GLOBALS['enable_thumbnail_creation_on_upload'] = $original_state['enable_thumbnail_creation_on_upload'];
unset(
    $original_state,
    $setup_global_env,
    $rtf_text,
    $rtf_text2,
    $resource_jpg_file,
    $resource_mp4_file,
    $file_jpg,
    $file_mp4,
    $resource_jpg_file_alt_ref,
    $use_cases,
    $result
);

return true;
