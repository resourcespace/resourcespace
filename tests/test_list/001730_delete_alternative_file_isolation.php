<?php

command_line_only();

return (static function () {
    $resource = create_resource(1, 0, -1, '', 'txt', false);
    $other_resource = create_resource(1, 0);
    $first = add_alternative_file($resource, 'Delete this', '', 'first.txt', 'txt');
    $second = add_alternative_file($resource, 'Keep this', '', 'second.txt', 'txt');
    $original = get_resource_path($resource, true, '', true, 'txt');
    $first_path = get_resource_path($resource, true, '', true, 'txt', -1, 1, false, '', $first);
    $second_path = get_resource_path($resource, true, '', true, 'txt', -1, 1, false, '', $second);
    $preview = get_resource_path($resource, true, 'pre', true, 'jpg', -1, 1, false, '', $first);
    foreach ([$original => 'Original', $first_path => 'First', $second_path => 'Second', $preview => 'Preview'] as $path => $content) {
        if (file_put_contents($path, $content) === false) {
            echo 'Alternative deletion setup - ';
            return false;
        }
    }
    if (delete_alternative_file($other_resource, $first) !== false || !file_exists($first_path)
        || get_alternative_file($resource, $first) === false) {
        echo 'Wrong resource reference deleted an alternative - ';
        return false;
    }
    if (delete_alternative_file($resource, $first) !== true
        || file_exists($first_path) || file_exists($preview) || get_alternative_file($resource, $first) !== false
        || file_get_contents($original) !== 'Original' || file_get_contents($second_path) !== 'Second'
        || get_alternative_file($resource, $second) === false) {
        echo 'Alternative deletion left files or affected the original or another alternative - ';
        return false;
    }
    if (delete_alternative_file($resource, $first) !== false) {
        echo 'Repeated alternative deletion did not report a missing alternative - ';
        return false;
    }
    return true;
})();
