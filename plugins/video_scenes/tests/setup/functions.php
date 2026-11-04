<?php

/**
 * @param string $permission Unused permission name; this fixture represents an administrator.
 * @return bool Whether the request uses the denied-user fixture.
 */
function checkperm($permission): bool
{
    return getval('denied', '') === '';
}

/**
 * Capture the real configuration saver without accessing an installation database.
 *
 * @param string $sql        Expected plugin configuration UPDATE.
 * @param array  $parameters Prepared configuration values.
 * @return array Empty result; saves configuration to the isolated fixture directory.
 */
function ps_query($sql, array $parameters = []): array
{
    $file = getenv('VIDEO_SCENES_SETUP_TEST_DIR') . '/scene.json';
    if (str_starts_with($sql, 'SELECT description, start_time FROM resource_scene')) {
        return $parameters === ['i', 7, 'i', 10]
            ? [['description' => is_file($file) ? json_decode(file_get_contents($file), true) : 'Original',
                'start_time' => 1.5]] : [];
    }
    if (str_starts_with($sql, 'UPDATE resource_scene SET description = ?')) {
        if (array_slice($parameters, 2) !== ['i', 7, 'i', 10]) {
            throw new RuntimeException('Incorrect scene/resource update scope');
        }
        file_put_contents($file, json_encode($parameters[1]));
        return [];
    }
    if (!str_starts_with($sql, 'UPDATE plugins SET config = ?')) {
        throw new RuntimeException('Unexpected setup fixture database call');
    }
    file_put_contents(getenv('VIDEO_SCENES_SETUP_TEST_DIR') . '/settings.json', $parameters[3]);
    return [];
}

/**
 * @param int $ref Requested resource.
 * @return array|false Isolated resource fixture.
 */
function get_resource_data($ref)
{
    return $ref === 10 || $ref === 11 ? ['ref' => $ref, 'resource_type' => 3] : false;
}

/**
 * @param array $resource Unused resource fixture.
 * @return int Fixture resource access.
 */
function get_resource_access($resource): int
{
    return getval('inaccessible', '') === '1' ? RESOURCE_ACCESS_CONFIDENTIAL : RESOURCE_ACCESS_FULL;
}

/**
 * @param int    $type Unused resource type.
 * @param string $size Unused preview size.
 * @return bool Fixture preview permission.
 */
function resource_has_access_denied_by_RT_size($type, $size): bool
{
    return getval('preview_denied', '') === '1';
}

/**
 * @param int   $ref      Unused resource reference.
 * @param int   $status   Unused status.
 * @param array $resource Unused resource data.
 * @return bool Fixture resource edit permission, independent of administrator access.
 */
function get_edit_access($ref, $status, array &$resource): bool
{
    return getval('denied', '') === '';
}

/**
 * @param mixed ...$arguments Log arguments, including previous and updated description.
 * @return void Record the audit event in isolated test storage.
 */
function resource_log(...$arguments): void
{
    file_put_contents(getenv('VIDEO_SCENES_SETUP_TEST_DIR') . '/scene-log.json', json_encode($arguments));
}

/**
 * @param mixed ...$arguments Unused activity data; fixture has no installation activity log.
 * @return void
 */
function log_activity(...$arguments): void
{
}

/**
 * @param string $cache Unused cache name; fixture has no database query cache.
 * @return void
 */
function clear_query_cache($cache): void
{
}
