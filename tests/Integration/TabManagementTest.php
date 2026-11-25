<?php

declare(strict_types=1);

$tabs = [];
$permissions = [];
$exception_mode = null;
beforeEach(function () use (&$tabs, &$permissions, &$exception_mode): void {
    $tabs = [];
    $permissions = $GLOBALS['userpermissions'];
    $exception_mode = $GLOBALS['use_error_exception'] ?? null;
});
afterEach(function () use (&$tabs, &$permissions, &$exception_mode): void {
    $GLOBALS['userpermissions'] = $permissions;
    if ($exception_mode === null) {
        unset($GLOBALS['use_error_exception']);
    } else {
        $GLOBALS['use_error_exception'] = $exception_mode;
    }
    if ($tabs !== []) {
        delete_tabs($tabs);
    }
});

it('creates a named tab for an authorised user', function () use (&$tabs): void {
    $name = 'Tab ' . generateRandomTestID(8);
    $ref = create_tab(['name' => $name]);
    expect($ref)->toBeInt()->toBeGreaterThan(1);
    $tabs[] = $ref;

    expect(get_tabs_by_refs([$ref])[0]['name'])->toBe($name);
});

it('rejects creation without permission or a name', function (): void {
    expect(create_tab([]))->toBeFalse();
    $GLOBALS['userpermissions'] = array_diff($GLOBALS['userpermissions'], ['a']);
    expect(create_tab(['name' => 'Denied ' . generateRandomTestID(8)]))->toBeFalse();
});

it('preserves tabs on denied deletion and protects the default tab', function () use (&$tabs, &$permissions): void {
    $tabs[] = create_tab(['name' => 'First ' . generateRandomTestID(8)]);
    $tabs[] = create_tab(['name' => 'Second ' . generateRandomTestID(8)]);
    expect($tabs)->each->toBeInt();
    $GLOBALS['userpermissions'] = array_diff($permissions, ['a']);
    expect(delete_tabs([$tabs[0]]))->toBeFalse()
        ->and(get_tabs_by_refs([$tabs[0]]))->toHaveCount(1);

    $GLOBALS['userpermissions'] = $permissions;
    delete_tabs([1]);
    expect(get_tabs_by_refs([1]))->toHaveCount(1);
    delete_tabs($tabs);
    expect(get_tabs_by_refs($tabs))->toBeEmpty();
});

it('only saves tab changes for an authorised user', function () use (&$tabs, &$permissions): void {
    $name = 'Tab ' . generateRandomTestID(8);
    $ref = create_tab(['name' => $name]);
    expect($ref)->toBeInt();
    $tabs[] = $ref;
    $GLOBALS['userpermissions'] = array_diff($permissions, ['a']);
    expect(save_tab(['ref' => $ref, 'name' => 'Denied edit']))->toBeFalse()
        ->and(get_tabs_by_refs([$ref])[0]['name'])->toBe($name);

    $GLOBALS['userpermissions'] = $permissions;
    save_tab(['ref' => $ref, 'name' => 'Updated ' . $name]);
    expect(get_tabs_by_refs([$ref])[0]['name'])->toBe('Updated ' . $name);
});

it('reports missing tab references when saving', function (): void {
    $GLOBALS['use_error_exception'] = true;
    expect(fn () => save_tab(['name' => 'Missing ref']))->toThrow(ErrorException::class);
});
