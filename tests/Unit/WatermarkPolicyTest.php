<?php

declare(strict_types=1);

require_once RESOURCESPACE_BASE_PATH . '/include/resource_functions.php';
require_once RESOURCESPACE_BASE_PATH . '/include/user_functions.php';
require_once RESOURCESPACE_BASE_PATH . '/include/general_functions.php';

$saved_watermark_config = [];
$watermark_keys = array_fill_keys([
    'access', 'watermark', 'watermark_open', 'watermark_open_search', 'pagename', 'userpermissions',
], true);

beforeEach(function () use (&$saved_watermark_config, $watermark_keys): void {
    $saved_watermark_config = array_intersect_key($GLOBALS, $watermark_keys);
});
afterEach(function () use (&$saved_watermark_config, $watermark_keys): void {
    foreach ($watermark_keys as $name => $unused) {
        unset($GLOBALS[$name]);
    }
    foreach ($saved_watermark_config as $name => $value) {
        $GLOBALS[$name] = $value;
    }
});

it('applies a configured watermark according to permission, access and page', function (
    string $page,
    bool $requires_search_setting,
): void {
    $GLOBALS['pagename'] = $page;
    foreach (['', 'watermark.png'] as $watermark) {
        foreach ([[], ['w']] as $permissions) {
            $GLOBALS['watermark'] = $watermark;
            $GLOBALS['userpermissions'] = $permissions;
            $can_watermark = $watermark !== '' && $permissions !== [];
            foreach ([false, true] as $open) {
                foreach ([false, true] as $open_search) {
                    $GLOBALS['watermark_open'] = $open;
                    $GLOBALS['watermark_open_search'] = $open_search;
                    $GLOBALS['access'] = RESOURCE_ACCESS_RESTRICTED;
                    expect(check_use_watermark())->toBe($can_watermark);
                    $GLOBALS['access'] = RESOURCE_ACCESS_FULL;
                    expect(check_use_watermark())->toBe($can_watermark && $open && (!$requires_search_setting || $open_search));
                }
            }
        }
    }
})->with([
    'resource View' => ['view', false],
    'resource Preview' => ['preview', false],
    'search results' => ['search', true],
    'collection results' => ['collections', false],
]);
