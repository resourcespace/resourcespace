<?php

command_line_only();

return (static function () {
    global $userref, $userdata, $collection_block_restypes;

    $saved_user = $userdata;
    $saved_blocked_types = $collection_block_restypes;
    $id = test_generate_random_ID(8);
    $collection = create_collection($userref, 'Membership ' . $id, 0);
    $other_collection = create_collection($userref, 'Other membership ' . $id, 0);
    $resource = create_resource(1, 0);
    $control = create_resource(1, 0);

    try {
        $collection_block_restypes = [];
        if (add_resource_to_collection($resource, $collection) !== true
            || add_resource_to_collection($resource, $collection) !== true
            || add_resource_to_collection($control, $collection) !== true
            || add_resource_to_collection($resource, $other_collection) !== true
            || !match_values(get_collection_resources($collection), [$resource, $control])) {
            echo 'Adding the same resource twice duplicated collection membership - ';
            return false;
        }

        $other_user = new_user('collectionreader' . $id, 2);
        setup_user(get_user($other_user));
        // Failures may return a translated error string: check strictly against true.
        if (add_resource_to_collection($control, $other_collection) === true
            || remove_resource_from_collection($resource, $collection) === true
            || !match_values(get_collection_resources($collection), [$resource, $control])
            || !match_values(get_collection_resources($other_collection), [$resource])) {
            echo 'Non-owner modified a private collection - ';
            return false;
        }

        setup_user($saved_user);
        $collection_block_restypes = [1];
        if (add_resource_to_collection($control, $other_collection) === true
            || !match_values(get_collection_resources($other_collection), [$resource])) {
            echo 'Blocked resource type was added to a collection - ';
            return false;
        }

        if (remove_resource_from_collection($resource, $collection) !== true
            || !match_values(get_collection_resources($collection), [$control])
            || !match_values(get_collection_resources($other_collection), [$resource])
            || get_resource_data($resource, false) === false) {
            echo 'Removing membership affected another collection or deleted the resource - ';
            return false;
        }

        return true;
    } finally {
        setup_user($saved_user);
        $collection_block_restypes = $saved_blocked_types;
    }
})();
