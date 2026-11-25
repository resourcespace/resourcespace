<?php

declare(strict_types=1);

it('provides the default web app icons and search shortcuts', function (): void {
    $manifest_path = RESOURCESPACE_BASE_PATH . '/manifest.json';
    expect($manifest_path)->toBeFile();
    $json = file_get_contents($manifest_path);
    expect($json)->toBeString();
    $manifest = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['start_url'])->toBe('./')
        ->and($manifest['display'])->toBe('standalone');
    $shortcuts = array_column($manifest['shortcuts'], 'url');
    expect($shortcuts)->toContain('./pages/search.php?search=!last1000')
        ->toContain('./pages/search_advanced.php');

    foreach ($manifest['icons'] as $icon) {
        expect(RESOURCESPACE_BASE_PATH . '/' . $icon['src'])->toBeFile();
    }
    foreach ($shortcuts as $url) {
        expect(RESOURCESPACE_BASE_PATH . '/' . parse_url($url, PHP_URL_PATH))->toBeFile();
    }
});
