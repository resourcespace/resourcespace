<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $tool = get_utility_path('exiftool');
    if ($tool === false) {
        echo 'SKIP: ExifTool not installed - ';
        return true;
    }
    $names = ['exiftool_write', 'exiftool_write_option', 'exiftool_remove_existing'];
    $saved = [];
    foreach ($names as $name) {
        $saved[$name] = $GLOBALS[$name];
    }
    $temporary = false;
    $path = '';
    try {
        $resource = create_resource(1, 0);
        ps_query('UPDATE resource SET file_extension = ? WHERE ref = ?', ['s', 'jpg', 'i', $resource]);
        $path = get_resource_path($resource, true, '', true, 'jpg');
        copy(dirname(__DIR__, 2) . '/gfx/homeanim/1.jpg', $path);
        $title = 'Café 日本語 – metadata';
        $field = create_resource_type_field('Metadata round trip', 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 'metadataroundtrip');
        ps_query('UPDATE resource_type_field SET exiftool_field = ? WHERE ref = ?', ['s', 'XMP:Title', 'i', $field]);
        clear_query_cache('schema');
        update_field($resource, $field, $title);
        foreach ($names as $name) {
            $GLOBALS[$name] = true;
        }
        $original_hash = hash_file('sha256', $path);
        $temporary = write_metadata($path, $resource);
        $check(is_string($temporary) && is_file($temporary), 'Metadata write produced no file');
        $read = static function ($file) use ($tool) {
            return json_decode(run_command($tool . ' -j -XMP:Title -EXIF:Artist -GPS:all ' . escapeshellarg($file)), true);
        };
        $metadata = $read($temporary);
        $check(($metadata[0]['Title'] ?? '') === $title, 'Unicode metadata did not round trip: ' . json_encode($metadata));
        $check(hash_file('sha256', $path) === $original_hash, 'Metadata export changed original');
        run_command($tool . ' -overwrite_original -EXIF:Artist=SensitiveName -GPSLatitude=51.5 -GPSLatitudeRef=N ' . escapeshellarg($temporary));
        $before = $read($temporary);
        $check(($before[0]['Artist'] ?? '') === 'SensitiveName' && isset($before[0]['GPSLatitude']), 'Sensitive metadata fixture missing');
        $check(stripMetadata($temporary), 'Metadata stripping failed');
        $after = $read($temporary);
        $check(!isset($after[0]['Title']) && !isset($after[0]['Artist']) && !isset($after[0]['GPSLatitude']), 'Sensitive embedded metadata retained');
        $check(getimagesize($temporary) !== false, 'Metadata stripping damaged image');
        $check(!file_exists($temporary . '_original'), 'Metadata stripping leaked backup');
        $check(stripMetadata($path . '.missing') === false, 'Missing file stripping succeeded');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        foreach ($saved as $name => $value) {
            $GLOBALS[$name] = $value;
        }
        if (is_string($temporary) && is_file($temporary)) {
            unlink($temporary);
        }
    }
})();
