<?php

command_line_only();

return (static function () {
    $resource = create_resource(1, 0);
    $other = create_resource(1, 0);
    $alternative = add_alternative_file($resource, 'Original name', 'Original description', 'original.txt', 'txt', 123, 'original-type');
    $original = get_alternative_file($resource, $alternative);

    save_alternative_file($other, $alternative, ['name' => 'Wrong resource']);
    if (get_alternative_file($resource, $alternative) !== $original) {
        echo 'Alternative metadata update was not scoped to its resource - ';
        return false;
    }
    save_alternative_file($resource, $alternative, ['name' => 'Updated name', 'description' => '', 'file_size' => 0]);
    $updated = get_alternative_file($resource, $alternative);
    if ($updated['name'] !== 'Updated name' || $updated['description'] !== '' || (int) $updated['file_size'] !== 0
        || $updated['file_name'] !== $original['file_name'] || $updated['file_extension'] !== $original['file_extension']
        || $updated['alt_type'] !== $original['alt_type']) {
        echo 'Partial alternative update lost unspecified values or ignored empty/zero values - ';
        return false;
    }
    save_alternative_file($resource, $alternative, ['resource' => $other, 'ref' => $alternative + 1, 'creation_date' => '2000-01-01']);
    save_alternative_file($resource, $alternative, []);
    if (get_alternative_file($resource, $alternative) !== $updated) {
        echo 'Alternative update changed protected columns or an empty update changed data - ';
        return false;
    }
    return true;
})();
