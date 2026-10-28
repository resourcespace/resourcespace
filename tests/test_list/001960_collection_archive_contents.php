<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    if (!class_exists('ZipArchive')) {
        echo 'SKIP: PHP ZipArchive not installed - ';
        return true;
    }
    $names = ['use_zip_extension', 'download_filename_format', 'userpermissions', 'exiftool_write_option', 'collection_download_original', 'restricted_full_download', 'open_access_for_contributor'];
    $saved = [];
    foreach ($names as $name) {
        $saved[$name] = $GLOBALS[$name] ?? null;
    }
    $archive = null;
    $zip = null;
    try {
        $GLOBALS['use_zip_extension'] = true;
        $GLOBALS['collection_download_original'] = true;
        $GLOBALS['restricted_full_download'] = false;
        $GLOBALS['open_access_for_contributor'] = false;
        $GLOBALS['download_filename_format'] = '%resource.%extension';
        $GLOBALS['userpermissions'] = ['s', 'g'];
        $collection = create_collection($GLOBALS['userref'], 'Archive fixture');
        $resources = [];
        $contents = [];
        foreach ([0, 0, 2] as $index => $access) {
            $resource = create_resource(1, 0);
            ps_query('UPDATE resource SET file_extension = ?, access = ? WHERE ref = ?', ['s', 'txt', 'i', $access, 'i', $resource]);
            $path = get_resource_path($resource, true, '', true, 'txt');
            $contents[$resource] = 'Archive member ' . $index;
            file_put_contents($path, $contents[$resource]);
            $resources[] = get_resource_data($resource, false);
        }
        $GLOBALS['get_resource_data_cache'] = [];
        $GLOBALS['resource_access_cache'] = [];
        $GLOBALS['download_access_cache'] = [];
        $result = process_collection_download(['collection' => $collection, 'collectiondata' => get_collection($collection),
            'collection_resources' => $resources, 'size' => '', 'id' => 'archive_' . test_generate_random_ID(8),
            'usagecomment' => '', 'usage' => -1, 'useoriginal' => true, 'includetext' => true, 'text' => '', 'settings_id' => '',
            'exiftool_write_option' => false, 'include_csv_file' => false, 'collection_download_tar' => false]);
        $archive = $result['path'] ?? null;
        $check(is_string($archive) && is_file($archive), 'Archive not produced');
        $zip = new ZipArchive();
        $check($zip->open($archive) === true, 'Archive cannot be opened');
        foreach (array_slice($resources, 0, 2) as $row) {
            $check($zip->getFromName($row['ref'] . '.txt') === $contents[$row['ref']], 'Archive member bytes incorrect');
        }
        $check($zip->locateName($resources[2]['ref'] . '.txt') === false, 'Confidential resource disclosed in archive');
        $check($zip->numFiles === 3, 'Archive missing summary or contains unexpected members');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        if ($zip instanceof ZipArchive) {
            $zip->close();
        }
        if (is_string($archive) && is_file($archive)) {
            unlink($archive);
        }
        $GLOBALS['get_resource_data_cache'] = [];
        $GLOBALS['resource_access_cache'] = [];
        $GLOBALS['download_access_cache'] = [];
        foreach ($saved as $name => $value) {
            $GLOBALS[$name] = $value;
        }
    }
})();
