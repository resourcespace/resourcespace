<?php

command_line_only();

return (static function () {
    global $userref, $resource_deletion_state, $remove_deleted_resources_from_collections;

    $saved_deletion_state = $resource_deletion_state;
    $saved_remove_from_collections = $remove_deleted_resources_from_collections;
    try {
        $resource_deletion_state = 3;
        foreach ([false, true] as $remove_from_collections) {
            $remove_deleted_resources_from_collections = $remove_from_collections;
            $resource = create_resource(1, 0, -1, '', 'txt', false);
            $control = create_resource(1, 0, -1, '', 'txt', false);
            $collection = create_collection($userref, 'File lifecycle ' . test_generate_random_ID(8));
            $path = get_resource_path($resource, true, '', true, 'txt');
            $control_path = get_resource_path($control, true, '', true, 'txt');
            $alternative = add_alternative_file($resource, 'Alternative', '', 'alternative.txt', 'txt');
            $alt_path = get_resource_path($resource, true, '', true, 'txt', true, 1, false, '', $alternative);

            if (file_put_contents($path, 'Original file') === false
                || file_put_contents($alt_path, 'Alternative file') === false
                || file_put_contents($control_path, 'Unrelated file') === false
                || add_resource_to_collection($resource, $collection) !== true) {
                echo 'File lifecycle setup - ';
                return false;
            }

            if (delete_resource($resource) !== true || !file_exists($path) || !file_exists($alt_path)) {
                echo 'Soft deletion failed or removed resource files - ';
                return false;
            }

            $data = get_resource_data($resource, false);
            if ($data === false || (int) $data['archive'] !== $resource_deletion_state) {
                echo 'Soft deletion did not move the resource to the deletion state - ';
                return false;
            }
            $expected_members = $remove_from_collections ? [] : [$resource];
            if (!match_values(get_collection_resources($collection), $expected_members)) {
                echo 'Soft deletion ignored the collection removal setting - ';
                return false;
            }

            if (delete_resource($resource) !== true || get_resource_data($resource, false) !== false
                || file_exists($path) || file_exists($alt_path)
                || get_alternative_files($resource) !== [] || get_collection_resources($collection) !== []
                || !file_exists($control_path) || file_get_contents($control_path) !== 'Unrelated file') {
                echo 'Permanent deletion left files or membership behind, or affected another resource - ';
                return false;
            }
        }

        return true;
    } finally {
        $resource_deletion_state = $saved_deletion_state;
        $remove_deleted_resources_from_collections = $saved_remove_from_collections;
    }
})();
