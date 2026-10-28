<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    $get = $_GET;
    $post = $_POST;
    $saved_override = $GLOBALS['smartsearch_accessoverride'];
    $saved_permissions = $GLOBALS['userpermissions'];
    $collection = create_collection($GLOBALS['userref'], 'Saved search ' . test_generate_random_ID(8));
    $other = create_collection($GLOBALS['userref'], 'Other saved search');
    try {
        $_POST = [];
        $_GET = ['addsearch' => 'title:O’Reilly café', 'restypes' => '1,2', 'archive' => '0,1'];
        add_saved_search($collection);
        $rows = get_saved_searches($collection);
        $check(count($rows) === 1, 'Saved search not created');
        $row = $rows[0];
        $check($row['search'] === $_GET['addsearch'] && $row['restypes'] === '1,2' && $row['archive'] === '0,1', 'Saved query did not round trip');
        remove_saved_search($other, $row['ref']);
        $check(count(get_saved_searches($collection)) === 1, 'Wrong collection deleted saved search');
        remove_saved_search($collection, $row['ref']);
        $check(get_saved_searches($collection) === [], 'Saved search deletion failed');
        $a = create_resource(1, 0);
        $b = create_resource(1, 0);
        $query = '!list' . $a . ':' . $b;
        add_saved_search_items($collection, $query, '', '0');
        add_saved_search_items($collection, $query, '', '0');
        $check(match_values(get_collection_resources($collection), [$a, $b]), 'Saved search membership missing or duplicated');
        $term = 'smartrefresh' . test_generate_random_ID(8);
        $GLOBALS['smartsearch_accessoverride'] = false;
        update_field($a, $GLOBALS['view_title_field'], $term);
        $_GET = ['addsearch' => $term, 'restypes' => '1', 'archive' => '0'];
        add_saved_search($collection);
        $smart = get_saved_searches($collection)[0]['ref'];
        update_smart_collection($smart);
        $check(match_values(get_collection_resources($collection), [$a]), 'Smart collection did not evaluate saved query');
        update_field($a, $GLOBALS['view_title_field'], 'No longer matches');
        update_field($b, $GLOBALS['view_title_field'], $term);
        update_smart_collection($smart);
        update_smart_collection($smart);
        $check(match_values(get_collection_resources($collection), [$b]), 'Smart collection refresh retained stale membership or duplicated results');
        $GLOBALS['userpermissions'] = ['s', 'T1'];
        update_smart_collection($smart);
        $check(get_collection_resources($collection) === [], 'Smart query ignored current resource type permissions');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        $GLOBALS['smartsearch_accessoverride'] = $saved_override;
        $GLOBALS['userpermissions'] = $saved_permissions;
        $_GET = $get;
        $_POST = $post;
        ps_query('DELETE FROM collection_savedsearch WHERE collection IN (?,?)', ['i', $collection, 'i', $other]);
    }
})();
