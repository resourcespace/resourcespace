<?php

command_line_only();
$disable_browser_check = true;

// --- Set up
$cache_hide_real_file_path = $hide_real_filepath;
$test_id_str = 'test_' . test_get_file_id(__FILE__);
$setup_banned_fpath = static function (array $uc) use ($test_id_str): bool {
    return (bool) file_put_contents(
        get_temp_dir(false, $test_id_str) . "/{$test_id_str}.php",
        "<?php echo '{$test_id_str} - {$uc['name']}';"
    );
};
$setup_image = static function (array $uc): bool {
    $img = create_random_image(['text' => "Use case: {$uc['name']}", 'width' => 800]);
    if (!isset($img['path'])) {
        return false;
    }
    return rename($img['path'], $uc['input'][0]);
};
$expect_non_empty_string = static fn ($result): bool => is_string($result) && !$result !== '';
$files_to_delete = [];

$generate_dld_endpoint_content = static fn (string $file): string => <<<EOT
<?php
ob_start();
\$nocache = true;
\$disable_browser_check = true;
include dirname(__DIR__, 4) . '/include/boot.php';

ob_end_clean();

\$filename = basename('$file');
header("Content-Disposition: attachment; filename=\"{\$filename}\"");
header('Content-Type: ' . get_mime_type('$file')[0]);
header('Content-Length: ' . filesize_unlimited('$file'));
echo file_get_contents('$file');

EOT;
// --- End of Set up

$use_cases = [
    [
        'name' => 'Downloading a banned remote (URL) file',
        'setup' => $setup_banned_fpath,
        'input' => [get_temp_dir(true, $test_id_str) . "/{$test_id_str}.php"],
        'expected' => false,
    ],
    [
        'name' => 'Downloading (copy) a banned local file',
        'setup' => $setup_banned_fpath,
        'input' => [get_temp_dir(false, $test_id_str) . "/{$test_id_str}.php"],
        'expected' => false,
    ],
    [
        'name' => 'Downloading from a URL',
        'setup' => static fn() => copy(dirname(__DIR__, 2) . '/gfx/homeanim/1.jpg', get_temp_dir(false, $test_id_str) . "/{$test_id_str}.jpg"),
        'input' => [get_temp_dir(true, $test_id_str) . "/{$test_id_str}.jpg"],
        'expected' => $expect_non_empty_string,
    ],
    [
        'name' => 'Downloading (copy) a file under tmp/remote_files/',
        'setup' => $setup_image,
        'input' => [get_temp_dir(false, $test_id_str) . "/{$test_id_str}.jpg"],
        'expected' => $expect_non_empty_string,
    ],
    [
        'name' => 'Repeated temp_local_download_remote_file calls return the same tmp location',
        'setup' => static function (array $uc) use ($test_id_str, $setup_image): bool {
            if (!$setup_image($uc)) {
                return false;
            }

            $localcopy = temp_local_download_remote_file($uc['input'][0]);
            if ($localcopy === false) {
                return false;
            }

            $GLOBALS["{$test_id_str}_assert_" . md5($uc['name'])] = $localcopy;
            $GLOBALS['files_to_delete'][] = $localcopy;
            return true;
        },
        'input' => [get_temp_dir(false, $test_id_str) . "/{$test_id_str}.jpg"],
        'expected' => static fn ($result): bool => (
            $expect_non_empty_string($result)
            && $result === $GLOBALS["{$test_id_str}_assert_" . md5($GLOBALS['uc']['name'])]
        ),
    ],
    [
        'name' => 'Can download from an allowed remote source URL (dynamic, e.g. pages/download.php)',
        'setup' => static function (array &$uc) use ($test_id_str, $generate_dld_endpoint_content): bool {
            $img = create_random_image(['text' => "Use case: {$uc['name']}", 'width' => 900]);
            if (!isset($img['path'])) {
                return false;
            }

            $GLOBALS['files_to_delete'][] = $img['path'];
            $GLOBALS["{$test_id_str}_assert_" . md5($uc['name'])] = md5_file($img['path']);

            // Generate a download endpoint to provide our newly created file
            $dld_endpoint = file_put_contents(
                get_temp_dir(false, $test_id_str) . "/{$test_id_str}_download_endpoint.php",
                $generate_dld_endpoint_content($img['path'])
            );
            if (!$dld_endpoint) {
                return false;
            }

            $dld_endpoint_url = get_temp_dir(true, $test_id_str) . "/{$test_id_str}_download_endpoint.php";
            $GLOBALS['valid_upload_remote_sources'] = [$dld_endpoint_url];

            $uc['input'] = [$dld_endpoint_url];
            return true;
        },
        'input' => [/* see setup */],
        'expected' => static fn ($result): bool => (
            $expect_non_empty_string($result)
            && md5_file($result) === $GLOBALS["{$test_id_str}_assert_" . md5($GLOBALS['uc']['name'])]
        ),
    ],
];
foreach($use_cases as $uc) {
    // Set up the use case environment
    if(isset($uc['setup'])) {
        $setup = $uc['setup']($uc);
        if (is_bool($setup) && !$setup) {
            echo "Use case setup: {$uc['name']} - ";
            return false;
        }
    }

    $result = temp_local_download_remote_file(...$uc['input']);
    $assertion_fails = is_callable($uc['expected']) ? !$uc['expected']($result) : $uc['expected'] !== $result;
    if ($assertion_fails) {
        echo "Use case: {$uc['name']} - ";
        test_log('Input: ' . json_encode($uc['input'], JSON_UNESCAPED_SLASHES));
        test_log('Result: ' . json_encode($result, JSON_UNESCAPED_SLASHES));
        return false;
    }
}

// Tear down
$hide_real_filepath = $cache_hide_real_file_path;
array_map('unlink', array_merge($files_to_delete, glob(get_temp_dir(false, $test_id_str) . "/{$test_id_str}*")));
unset(
    $test_id_str,
    $use_cases,
    $result,
    $setup_banned_fpath,
    $setup_image,
    $expect_non_empty_string,
    $files_to_delete,
    $cache_hide_real_file_path,
    $generate_dld_endpoint_content,
);

return true;
