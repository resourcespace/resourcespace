<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

require_once dirname(__DIR__, 2) . '/hooks/all.php';

beforeAll(function (): void {
    CheckDBStruct(dirname(__DIR__, 2) . '/dbstruct');
});

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures(['lang', 'userpermissions', 'consent_expiry_notification_days']);
    register_plugin_language('consentmanager');
    $GLOBALS['consent_expiry_notification_days'] = 30;
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

$create = static function (IntegrationFixtures $fixtures, string $name, ?string $expiry): int {
    $ref = consentmanager_create_consent(
        $name,
        '1980-02-03',
        'Example address',
        'Example guardian',
        'consent@example.invalid',
        '01234 567890',
        'Internal communications',
        'Example notes',
        '2020-05-20',
        $expiry,
        (string) $GLOBALS['userref']
    );
    expect($ref)->toBeInt()->toBeGreaterThan(0);
    $fixtures->onCleanup(static function () use ($ref): void {
        if (consentmanager_get_consent($ref)) {
            consentmanager_delete_consent($ref);
        }
        $path = get_consent_file_path($ref);
        if (is_file($path)) {
            unlink($path);
        }
    });
    return $ref;
};

it('saves consent details and attachment bytes and filters by expiry', function () use (&$fixtures, $create): void {
    $name = 'Consent ' . generateRandomTestID(14);
    $active = $create($fixtures, $name, '2099-01-01');
    $expired = $create($fixtures, $name . ' expired', '2001-01-01');
    $stored = consentmanager_get_consent($active);
    expect($stored)->toMatchArray([
        'name' => $name, 'date_of_birth' => '1980-02-03', 'address' => 'Example address',
        'parent_guardian_name' => 'Example guardian', 'email' => 'consent@example.invalid',
        'telephone' => '01234 567890', 'consent_usage' => 'Internal communications',
        'notes' => 'Example notes', 'date_of_consent' => '2020-05-20', 'expires' => '2099-01-01',
    ]);
    expect(array_column(consentmanager_get_all_consents($name, 'active'), 'ref'))->toBe([$active])
        ->and(array_column(consentmanager_get_all_consents($name, 'expired'), 'ref'))->toBe([$expired]);

    $attachment = file_get_contents(testFixture('Documents/basic.pdf'));
    expect(consentmanager_save_file($active, 'Consent evidence.pdf', $attachment))->toBeTrue()
        ->and(consentmanager_get_consent($active)['file'])->toBe('Consent evidence.pdf')
        ->and(file_get_contents(get_consent_file_path($active)))->toBe($attachment);

    expect(consentmanager_update_consent(
        $active, $name, null, null, null, 'consent@example.invalid', '', 'Publication', 'Updated scope', null, null
    ))->toBeTrue();
    expect(consentmanager_get_consent($active))->toMatchArray([
        'consent_usage' => 'Publication', 'notes' => 'Updated scope', 'expires' => null,
    ]);
    expect(consentmanager_delete_consent($expired))->toBeTrue()
        ->and(consentmanager_get_consent($expired))->toBeFalse()
        ->and(array_column(consentmanager_get_all_consents($name), 'ref'))->toBe([$active]);
});

it('links and unlinks consent on collection members and respects edit permissions', function () use (&$fixtures, $create): void {
    $consent = $create($fixtures, 'Consent ' . generateRandomTestID(14), null);
    $collection = $fixtures->collection((int) $GLOBALS['userref'], 'Consent scope ' . generateRandomTestID(14));
    $first = $fixtures->resource(1, 0);
    $second = $fixtures->resource(1, 0);
    $unrelated = $fixtures->resource(1, 0);
    expect(add_resource_to_collection($first, $collection))->toBeTrue()
        ->and(add_resource_to_collection($second, $collection))->toBeTrue()
        ->and(consentmanager_link_consent($consent, $unrelated))->toBeTrue();

    expect(consentmanager_batch_link_unlink($consent, $collection, false))->toBeTrue()
        ->and(consentmanager_get_consent($consent)['resources'])->toEqualCanonicalizing([$first, $second, $unrelated]);
    $admin = $GLOBALS['userpermissions'];
    $GLOBALS['userpermissions'] = ['s', 'f*'];
    expect(consentmanager_unlink_consent($consent, $first))->toBeFalse();
    $GLOBALS['userpermissions'] = $admin;
    expect(consentmanager_batch_link_unlink($consent, $collection, true))->toBeTrue()
        ->and(consentmanager_get_consent($consent)['resources'])->toBe([$unrelated]);

    expect(consentmanager_delete_consent($consent))->toBeTrue()
        ->and(consentmanager_get_consents($unrelated))->toBeEmpty();
});
