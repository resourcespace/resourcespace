<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['resource_deletion_state', 'remove_deleted_resources_from_collections']);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('removes resource files only on permanent deletion', function () use (&$fixtures): void {
    global $userref, $resource_deletion_state, $remove_deleted_resources_from_collections;

    $resource_deletion_state = 3;
    foreach ([false, true] as $remove_from_collections) {
        $remove_deleted_resources_from_collections = $remove_from_collections;
        $resource = $fixtures->resource(1, 0, -1, '', 'txt', false);
        $control = $fixtures->resource(1, 0, -1, '', 'txt', false);
        $collection = $fixtures->collection($userref, 'File lifecycle ' . generateRandomTestID(8));
        $path = get_resource_path($resource, true, '', true, 'txt');
        $control_path = get_resource_path($control, true, '', true, 'txt');
        $alternative = add_alternative_file($resource, 'Alternative', '', 'alternative.txt', 'txt');
        $alt_path = get_resource_path($resource, true, '', true, 'txt', alternative: $alternative);

        expect(file_put_contents($path, 'Original file') === false
            || file_put_contents($alt_path, 'Alternative file') === false
            || file_put_contents($control_path, 'Unrelated file') === false
            || add_resource_to_collection($resource, $collection) !== true)->toBeFalse('File lifecycle setup - ');

        expect(delete_resource($resource) !== true || !file_exists($path) || !file_exists($alt_path))->toBeFalse('Soft deletion failed or removed resource files - ');

        $data = get_resource_data($resource, false);
        expect($data === false || (int) $data['archive'] !== $resource_deletion_state)->toBeFalse('Soft deletion did not move the resource to the deletion state - ');
        $expected_members = $remove_from_collections ? [] : [$resource];
        expect(get_collection_resources($collection))->toEqual($expected_members);

        expect(delete_resource($resource) !== true || get_resource_data($resource, false) !== false
            || file_exists($path) || file_exists($alt_path)
            || get_alternative_files($resource) !== [] || get_collection_resources($collection) !== []
            || !file_exists($control_path) || file_get_contents($control_path) !== 'Unrelated file')->toBeFalse('Permanent deletion left files or membership behind, or affected another resource - ');
    }
});
