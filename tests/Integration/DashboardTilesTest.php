<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

require_once RESOURCESPACE_BASE_PATH . '/include/dash_functions.php';

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['userpermissions']);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('isolates personal tiles and persists ordering and deletion of shared tiles', function () use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $a = $fixtures->user("dashtile_a_{$id}");
    $b = $fixtures->user("dashtile_b_{$id}");
    $personal = create_dash_tile('pages/dash_tile.php?search=test', '', "Personal {$id}", 0, false, 90, false);
    expect($personal)->toBeInt();
    $fixtures->onCleanup(static fn () => delete_dash_tile($personal, true, true));
    add_user_dash_tile($a, $personal, 90, false);
    $GLOBALS['userpermissions'] = [];
    expect(can_edit_tile($personal, 0, $a))->toBeTrue()
        ->and(can_edit_tile($personal, 0, $b))->toBeFalse()
        ->and(can_edit_tile($personal, 1, $a))->toBeFalse();

    $shared = create_dash_tile('pages/dash_tile.php?search=all', '', "Shared {$id}", 0, true, 20, false);
    expect($shared)->toBeInt();
    $fixtures->onCleanup(static fn () => delete_dash_tile($shared, true, true));
    $membership = static fn (int $user): array => ps_array(
        'SELECT dash_tile AS value FROM user_dash_tile WHERE user = ? ORDER BY order_by',
        ['i', $user]
    );
    expect($membership($a))->toContain($shared, $personal)
        ->and($membership($b))->toContain($shared)->not->toContain($personal);

    reorder_user_dash($a);
    $order = $membership($a);
    expect(array_search($shared, $order, true))->toBeLessThan(array_search($personal, $order, true));
    $positions = ps_array(
        'SELECT order_by AS value FROM user_dash_tile WHERE user = ? ORDER BY order_by',
        ['i', $a]
    );
    expect(array_map('intval', $positions))->toBe(range(10, count($positions) * 10, 10));

    delete_dash_tile($shared);
    expect(ps_value('SELECT ref AS value FROM dash_tile WHERE ref = ?', ['i', $shared], null))->toBeNull()
        ->and($membership($a))->toContain($personal)->not->toContain($shared)
        ->and($membership($b))->not->toContain($shared)
        ->and(can_edit_tile($personal, 0, $a))->toBeTrue();
});
