<?php

define('RESOURCE_ACCESS_FULL', 0);
define('RESOURCE_ACCESS_RESTRICTED', 1);

/**
 * @param int   $ref      Unused resource reference.
 * @param int   $status   Unused workflow status.
 * @param array $resource Unused resource data.
 * @return bool Fixture resource edit permission, independent of administrative permissions.
 */
function get_edit_access($ref, $status, array &$resource): bool
{
    return ($GLOBALS['argv'][5] ?? '') === 'editable';
}

/**
 * @param string $form_id Unused form identifier.
 * @return void Render a token that browser tests require in save requests.
 */
function generateFormToken($form_id): void
{
    echo '<input type="hidden" name="csrf_token" value="test-scene-token">';
}

/**
 * @param mixed $resource Unused resource fixture.
 * @return int Fixture access level.
 */
function get_resource_access($resource): int
{
    return (int) ($GLOBALS['argv'][2] ?? 0);
}

/**
 * @param int    $type Unused resource type.
 * @param string $size Unused preview size.
 * @return bool Fixture preview restriction.
 */
function resource_has_access_denied_by_RT_size($type, $size): bool
{
    return ($GLOBALS['argv'][3] ?? '') === 'denied';
}

/**
 * @param string $key Unused share key.
 * @param int    $ref Unused resource reference.
 * @return bool Fixture watermark policy.
 */
function check_use_watermark($key, $ref): bool
{
    return ($GLOBALS['argv'][4] ?? '') === 'watermarked';
}

/**
 * @param mixed $value Value to render.
 * @return string HTML-escaped value.
 */
function escape($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * @param string $url    Base fixture URL.
 * @param array  $params Query parameters.
 * @return string Encoded fixture URL.
 */
function generateURL($url, $params): string
{
    return $url . '?' . http_build_query($params);
}

/**
 * @param string $sql    Unused scene query.
 * @param array  $params Unused prepared parameters.
 * @return array Scene fixtures including hostile text and fractional timestamps.
 */
function ps_query($sql, $params): array
{
    return [
        ['ref' => 1, 'start_time' => 0.5, 'end_time' => 1,
            'description' => '<img src=x onerror="window.sceneXss=true">'],
        ['ref' => 2, 'start_time' => 90000, 'end_time' => 90001, 'description' => 'Later scene'],
    ];
}
