<?php

command_line_only();
include_once dirname(__DIR__, 2) . '/include/log_functions.php';
global $userref;
$placeholderref = $userref;

$userref = new_user('activity_log_test');

//Clear logs so that we can check to make sure the number of results match the expected number
ps_query('DELETE FROM resource_log');
ps_query('DELETE FROM collection_log');
ps_query('DELETE FROM activity_log');

log_activity('resource uploaded', LOG_CODE_UPLOADED, 'activity log test 1', 'activity');
log_activity('resource edited', LOG_CODE_EDITED, 'activity log test 2', 'activity');
log_activity('resource viewed', LOG_CODE_VIEWED, 'activity log test 3', 'activity');
log_activity('resource updated', LOG_CODE_EDITED, 'activity log test 3', 'activity');

resource_log(10, LOG_CODE_CREATED, null, 'resource created');
resource_log(11, LOG_CODE_DOWNLOADED, null, 'resource downloaded');
resource_log(12, LOG_CODE_EMAILED, null, 'resource emailed');
resource_log(13, LOG_CODE_CREATED, null, 'resource created');

collection_log('1', LOG_CODE_COLLECTION_ADDED_RESOURCE, '1', 'Added resource to collection');
collection_log('1', LOG_CODE_COLLECTION_REMOVED_RESOURCE, '2', 'Removed resource from collection');
collection_log('1', LOG_CODE_COLLECTION_SHARED_COLLECTION, null, 'Shared collection');
collection_log('1', LOG_CODE_COLLECTION_DELETED_COLLECTION, null, 'Deleted collection');

$usecases = [
    [
        'name'     => 'All tables no search',
        'search'   => '',
        'offset'   => '',
        'rows'     => '',
        'wheres'   => ['activity_log' => "TRUE AND ",'resource_log' => "TRUE AND ",'collection_log' => "TRUE AND ",],
        'table'    => '',
        'count'    => '',
        'expected' => ['count' => 12]
    ],
    [
        'name'     => 'Activity log no search',
        'search'   => '',
        'offset'   => '',
        'rows'     => '',
        'wheres'   => ['activity_log' => "TRUE AND ",'resource_log' => "TRUE AND ",'collection_log' => "TRUE AND ",],
        'table'    => 'activity',
        'count'    => '',
        'expected' => ['count' => 4]
    ],
    [
        'name'     => 'Resource/collection log with no search',
        'search'   => '',
        'offset'   => '',
        'rows'     => '',
        'wheres'   => ['activity_log' => "TRUE AND ",'resource_log' => "TRUE AND ",'collection_log' => "TRUE AND ",],
        'table'    => 'resource',
        'count'    => '',
        'expected' => ['count' => 6]
    ],
    [
        'name'     => 'Collection log no search',
        'search'   => '',
        'offset'   => '',
        'rows'     => '',
        'wheres'   => ['activity_log' => "TRUE AND ",'resource_log' => "TRUE AND ",'collection_log' => "TRUE AND ",],
        'table'    => 'collection',
        'count'    => '',
        'expected' => ['count' => 2]
    ],
    [
        'name'     => 'Search "collection"',
        'search'   => 'collection',
        'offset'   => '',
        'rows'     => '',
        'wheres'   => ['activity_log' => "TRUE AND ",'resource_log' => "TRUE AND ",'collection_log' => "TRUE AND ",],
        'table'    => '',
        'count'    => '',
        'expected' => ['count' => 4]
    ],
    [
        'name'     => 'Search "resource"',
        'search'   => 'resource',
        'offset'   => '',
        'rows'     => '',
        'wheres'   => ['activity_log' => "TRUE AND ",'resource_log' => "TRUE AND ",'collection_log' => "TRUE AND ",],
        'table'    => '',
        'count'    => '',
        'expected' => ['count' => 10]
    ],
    [
        'name'     => 'Special characters in language strings',
        'search'   => '',
        'offset'   => '',
        'rows'     => '',
        'wheres'   => ['activity_log' => "TRUE AND ",'resource_log' => "TRUE AND ",'collection_log' => "TRUE AND ",],
        'table'    => '',
        'count'    => '',
        'eval'     => '$lang["collectionlog-a"] = "\'\"&^\"£$";',
        'expected' => ['count' => 12]
    ]
];

foreach ($usecases as $usecase) {
    try {
        if (array_key_exists('eval', $usecase)) {
            eval($usecase['eval']);
        }
        $results = get_activity_log($usecase['search'], $usecase['offset'], $usecase['rows'], $usecase['wheres'], $usecase['table'], null, $usecase['count']);
        if (count($results) != $usecase['expected']['count']) {
            echo 'ERROR - Result count doesn\'t match expected count for test - ' . $usecase['name'] . ' (expected ' . $usecase['expected']['count'] . ', found ' . count($results) . ')';
            return false;
        }
    } catch (Exception $e) {
        echo 'Failed on ' . $usecase['name'] . PHP_EOL;
        echo $e->getCode() . PHP_EOL;
        echo $e->getTrace();
        exit();
    }
}

$userref = $placeholderref;
