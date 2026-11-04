<?php

define('RESOURCE_ACCESS_FULL', 0);
define('RESOURCE_ACCESS_RESTRICTED', 1);
define('RESOURCE_ACCESS_CONFIDENTIAL', 2);
define('STATUS_ACTIVE', 1);
define('STATUS_INPROGRESS', 3);

/**
 * @param bool   $condition Expected true result.
 * @param string $message   Failure diagnostic.
 * @return void Throws when the condition is false.
 */
function scene_assert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

/**
 * @param mixed $resource Unused resource fixture.
 * @return int Simulated current-user access.
 */
function get_resource_access($resource): int
{
    return $GLOBALS['test_access'];
}

/**
 * @param int    $type Unused resource type.
 * @param string $size Unused preview size.
 * @return bool Simulated preview denial.
 */
function resource_has_access_denied_by_RT_size($type, $size): bool
{
    return $GLOBALS['test_preview_denied'];
}

/**
 * @param int  $ref   Fixture resource reference.
 * @param bool $cache Unused cache flag.
 * @return array|false Fixture resource, or false if absent.
 */
function get_resource_data($ref, $cache = true)
{
    return $GLOBALS['test_resources'][$ref] ?? false;
}

/**
 * @param int    $ref       Unused resource reference.
 * @param bool   $physical  Unused physical-path flag.
 * @param string $size      Unused preview size.
 * @param bool   $generate  Unused folder creation flag.
 * @param string $extension Unused extension.
 * @return string Isolated fixture path.
 */
function get_resource_path($ref, $physical, $size, $generate, $extension): string
{
    return $GLOBALS['test_original'];
}

/**
 * @param string $sql        Recorded query.
 * @param array  $parameters Recorded prepared parameters.
 * @return array Empty fixture results; records the query in test_queries.
 */
function ps_query($sql, $parameters = []): array
{
    $GLOBALS['test_queries'][] = [$sql, $parameters];
    return [];
}
