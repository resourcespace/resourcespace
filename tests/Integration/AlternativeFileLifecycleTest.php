<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('updates only the supplied writable fields on the owning resource', function () use (&$fixtures): void {
    $resource = $fixtures->resource(1, 0);
    $other = $fixtures->resource(1, 0);
    $alternative = add_alternative_file(
        $resource,
        'Original name',
        'Original description',
        'original.txt',
        'txt',
        123,
        'original-type',
    );
    $original = get_alternative_file($resource, $alternative);
    expect($original)->toBeArray();

    save_alternative_file($other, $alternative, ['name' => 'Wrong resource']);
    expect(get_alternative_file($resource, $alternative))->toBe($original);

    save_alternative_file($resource, $alternative, ['name' => 'Updated name', 'description' => '', 'file_size' => 0]);
    $updated = get_alternative_file($resource, $alternative);
    expect($updated)->toBeArray()
        ->and($updated['name'])->toBe('Updated name')
        ->and($updated['description'])->toBe('')
        ->and((int) $updated['file_size'])->toBe(0)
        ->and($updated['file_name'])->toBe($original['file_name'])
        ->and($updated['file_extension'])->toBe($original['file_extension'])
        ->and($updated['alt_type'])->toBe($original['alt_type']);

    save_alternative_file($resource, $alternative, [
        'resource' => $other,
        'ref' => $alternative + 1,
        'creation_date' => '2000-01-01',
    ]);
    expect(get_alternative_file($resource, $alternative))->toBe($updated);
    save_alternative_file($resource, $alternative, []);
    expect(get_alternative_file($resource, $alternative))->toBe($updated);
});

it('deletes only the matching alternative and its previews', function () use (&$fixtures): void {
    $resource = $fixtures->resource(1, 0, -1, '', 'txt', false);
    $other = $fixtures->resource(1, 0);
    $first = add_alternative_file($resource, 'Delete this', '', 'first.txt', 'txt');
    $second = add_alternative_file($resource, 'Keep this', '', 'second.txt', 'txt');
    $original = get_resource_path($resource, true, '', true, 'txt');
    $first_path = get_resource_path($resource, true, '', true, 'txt', alternative: $first);
    $second_path = get_resource_path($resource, true, '', true, 'txt', alternative: $second);
    $preview = get_resource_path($resource, true, 'pre', true, 'jpg', alternative: $first);
    $files = [$original => 'Original', $first_path => 'First', $second_path => 'Second', $preview => 'Preview'];
    foreach ($files as $path => $content) {
        expect(file_put_contents($path, $content))->toBe(strlen($content));
    }

    expect(delete_alternative_file($other, $first))->toBeFalse()
        ->and($first_path)->toBeFile()
        ->and(get_alternative_file($resource, $first))->toBeArray();
    expect(delete_alternative_file($resource, $first))->toBeTrue()
        ->and($first_path)->not->toBeFile()
        ->and($preview)->not->toBeFile()
        ->and(get_alternative_file($resource, $first))->toBeFalse()
        ->and(file_get_contents($original))->toBe('Original')
        ->and(file_get_contents($second_path))->toBe('Second')
        ->and(get_alternative_file($resource, $second))->toBeArray();
    expect(delete_alternative_file($resource, $first))->toBeFalse();
});
