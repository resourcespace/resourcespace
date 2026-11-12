<?php

command_line_only();

return (static function (): bool {
    $check = static function (bool $condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };
    $permissions = $GLOBALS['userpermissions'];
    $read_only = $GLOBALS['system_read_only'];
    $resource = create_resource(1, 0);
    $path = get_resource_path($resource, true, 'scr', true, 'jpg');
    $write_image = static function (int $width, int $height) use (&$path): void {
        $image = imagecreatetruecolor($width, $height);
        imagejpeg($image, $path);
    };
    $rows = static function () use ($resource): array {
        return array_column(ps_query(
            'SELECT * FROM resource_dimensions WHERE resource = ? AND preview_size IS NOT NULL',
            ['i', $resource]
        ), null, 'preview_size');
    };

    try {
        // Existing original rows must coexist with previews without being read or overwritten as previews.
        ps_query(
            'INSERT INTO resource_dimensions (resource, width, height, file_size, page_count)
             VALUES (?, 1000, 2000, 12345, 7)',
            ['i', $resource]
        );
        $original = ps_query('SELECT * FROM resource_dimensions WHERE resource = ?', ['i', $resource]);
        // Exercise the normal schema updater against the layout used by existing installations.
        ps_query(
            'ALTER TABLE resource_dimensions DROP INDEX resource_preview,
             DROP COLUMN preview_size, DROP COLUMN file_modified, DROP COLUMN path_hash'
        );
        check_db_structs();
        $check((int) ps_value(
            "SELECT NON_UNIQUE AS value FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'resource_dimensions'
             AND INDEX_NAME = 'resource_preview' LIMIT 1",
            [],
            -1
        ) === 0, 'Preview cache index is not unique after upgrade');
        $write_image(40, 20);
        $sizes = array_column(get_image_sizes($resource), null, 'id');
        $check($sizes['scr']['width'] === 40 && $sizes['scr']['height'] === 20, 'Cold dimensions incorrect');
        $check(count($rows()) === 1, 'Existing preview was not cached');

        // A cached result must be reused, rather than recalculated from the JPEG on every view.
        ps_query(
            'UPDATE resource_dimensions SET width = 41 WHERE resource = ? AND preview_size = ?',
            ['i', $resource, 's', 'scr']
        );
        $sizes = array_column(get_image_sizes($resource), null, 'id');
        $check($sizes['scr']['width'] === 41, 'Warm view did not use cached dimensions');

        // Replaced files must refresh a single cache row, even without an explicit invalidation.
        $write_image(60, 30);
        touch($path, time() + 5);
        $sizes = array_column(get_image_sizes($resource), null, 'id');
        $check($sizes['scr']['width'] === 60 && $sizes['scr']['height'] === 30, 'Replacement was not detected');
        $check(count($rows()) === 1, 'Replacement created duplicate preview rows');
        $check((int) get_page_count(get_resource_data($resource)) === 7, 'Preview changed original page count');
        $check(!in_array($resource, array_column(do_search('!propertiesorientation:landscape'), 'ref')),
            'Landscape preview changed the original orientation search');
        $check(ps_query(
            'SELECT * FROM resource_dimensions WHERE resource = ? AND preview_size IS NULL',
            ['i', $resource]
        ) === $original, 'Preview caching modified the original dimensions');

        // Permission checks must still apply even when dimensions are already cached.
        $GLOBALS['userpermissions'][] = 'T1_scr';
        $sizes = array_column(get_image_sizes($resource), null, 'id');
        $check(!isset($sizes['scr']), 'Denied preview exposed by the cache');
        $GLOBALS['userpermissions'] = $permissions;

        clear_preview_size_cache($resource);
        $check($rows() === [], 'Invalidation retained preview rows');
        $GLOBALS['system_read_only'] = true;
        $info = get_preview_size_info($resource, 'scr', $path, []);
        $check($info['width'] === 60 && $rows() === [], 'Read-only request populated the cache');
        $GLOBALS['system_read_only'] = $read_only;

        file_put_contents($path, 'not an image');
        $info = get_preview_size_info($resource, 'scr', $path, []);
        $check($info['width'] === 0 && $rows() === [], 'Invalid image was cached');
        unlink($path);
        $sizes = array_column(get_image_sizes($resource, false, 'jpg', false), null, 'id');
        $check($sizes['scr']['width'] === 0 && $sizes['scr']['filesize'] === formatfilesize(0), 'Missing size changed');
        $check($rows() === [], 'Missing file was cached');

        // Rotation must invalidate even if dimensions/file stats could otherwise look unchanged.
        $path = get_resource_path($resource, true, 'pre', true, 'jpg');
        $write_image(40, 20);
        get_preview_size_info($resource, 'pre', $path, []);
        $check($rows() !== [], 'Rotation fixture not cached');
        tweak_preview_images($resource, 90, 1);
        $check($rows() === [], 'Preview tweak did not invalidate the cache');
        get_image_sizes($resource, true);
        $check($rows() !== [], 'Tweaked preview did not repopulate');
        delete_previews($resource);
        $check($rows() === [], 'Preview deletion did not invalidate the cache');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        $GLOBALS['userpermissions'] = $permissions;
        $GLOBALS['system_read_only'] = $read_only;
        delete_resource($resource);
    }
})();
