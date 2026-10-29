<?php

command_line_only();

return (static function () {
    $id = test_generate_random_ID(8);
    $name = 'lifecycle' . $id;
    $field = create_resource_type_field('Metadata lifecycle ' . $id, 0, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, $name, true);
    $resource = create_resource(1, 0);
    $control = create_resource(1, 0);
    $old = 'old' . $id;
    $new = 'new' . $id;
    $errors = [];

    $matches = static function ($value, $expected) use ($name) {
        $results = do_search($name . ':' . $value);
        // Field-specific searches with no matches can return an empty string.
        if ($results === '') {
            return $expected === [];
        }
        return is_array($results) && match_values(array_column($results, 'ref'), $expected);
    };

    if (!update_field($resource, $field, $old, $errors)
        || !update_field($control, $field, $old, $errors)
        || !$matches($old, [$resource, $control])) {
        echo 'Initial metadata indexing - ';
        return false;
    }

    // Replacement must remove stale search terms without damaging another resource's index.
    if (!update_field($resource, $name, $new, $errors)
        || get_data_by_field($resource, $field) !== $new
        || !$matches($old, [$control]) || !$matches($new, [$resource])) {
        echo 'Metadata replacement left stale or missing search terms - ';
        return false;
    }

    if (!update_field($resource, $field, '', $errors) || $errors !== []) {
        echo 'Clearing optional metadata failed or returned validation errors - ';
        return false;
    }
    if (get_data_by_field($resource, $field) !== '') {
        echo 'Clearing optional metadata reported success but retained its previous value - ';
        return false;
    }
    if (!$matches($new, [])) {
        echo 'Clearing metadata retained its previous search term - ';
        return false;
    }
    if (!$matches($old, [$control])) {
        echo 'Clearing metadata changed another resource\'s search index - ';
        return false;
    }

    // Clearing a shared node must detach only the edited resource.
    if (!update_field($resource, $field, $old, $errors)
        || !update_field($resource, $field, '', $errors)
        || get_resource_nodes($resource, $field) !== []
        || get_data_by_field($control, $field) !== $old
        || !$matches($old, [$control])) {
        echo 'Clearing shared metadata changed another resource or retained a node association - ';
        return false;
    }

    // Repeated clears are successful no-ops and must not add edit logs.
    $logs = count(get_resource_log($resource)['data']);
    if (!update_field($resource, $field, '', $errors) || $errors !== []
        || count(get_resource_log($resource)['data']) !== $logs) {
        echo 'Repeated metadata clearing failed or created an unnecessary edit log - ';
        return false;
    }

    return true;
})();
