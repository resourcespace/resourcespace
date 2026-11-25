<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

require_once dirname(__DIR__, 2) . '/include/file_functions.php';

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures();
    if (!is_plugin_activated('licensemanager')) {
        expect(activate_plugin('licensemanager'))->toBeTrue();
        $fixtures->onCleanup(static fn () => deactivate_plugin('licensemanager'));
    }
    CheckDBStruct(dirname(__DIR__, 2) . '/dbstruct');
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('manages a licence and delivers its attachment through the plugin pages', function () use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $user = $fixtures->user('licenceoperator' . $id, 3);
    $session = generateSecureKey(32);
    ps_query(
        'UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?',
        ['s', $session, 'i', $user]
    );

    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure(['baseurl' => $server->url, 'session_autologout' => false]);
    $headers = $server->sessionHeaders($session);
    $base = '/plugins/licensemanager/pages/';
    $form_submission = static function (string $name, array $params) use ($server, $headers, $base): array {
        $page = $server->request($base . $name . '.php?' . http_build_query($params), headers: $headers);
        expect($page['status'])->toBe(200);
        $fields = LocalTestServer::hiddenFields(
            $page['body'],
            '//form[contains(@action, "plugins/licensemanager/pages/' . $name . '.php")]'
        );
        expect($fields)->toHaveKey($GLOBALS['CSRF_token_identifier']);
        return [$page['body'], $fields];
    };

    $resource = $fixtures->resource(1, 0);
    $other = $fixtures->resource(1, 0);
    $outside = $fixtures->resource(1, 0);
    $collection = $fixtures->collection($user, 'Licence assets ' . $id);
    expect(add_resource_to_collection($resource, $collection))->toBeTrue()
        ->and(add_resource_to_collection($other, $collection))->toBeTrue();
    [$create_page, $fields] = $form_submission('edit', ['ref' => 'new', 'resource' => $outside]);
    expect($fields['resource'])->toBe((string) $outside);
    expect($create_page)->toContain('id="resources">' . $outside . '</textarea>');

    $description = 'Release reference ' . $id;
    $payload = [
        'holder' => 'Example rights holder',
        'outbound' => '1',
        'description' => $description,
        'license_usage[0]' => 'Publication',
        'expires' => '2001-01-01',
        'resources' => (string) $outside,
    ];
    $source = testFixture('Documents/basic.pdf');
    $uploaded = $server->request($base . 'edit.php', $payload + $fields + [
        'file' => new CURLFile($source, 'application/pdf', 'fixture-licence.pdf'),
    ], $headers);
    expect($uploaded['status'])->toBeIn([302, 303]);
    $ref = (int) ps_value('SELECT ref AS value FROM license WHERE description = ?', ['s', $description], 0);
    expect($ref)->toBeGreaterThan(0);
    $fixtures->onCleanup(static function () use ($ref): void {
        ps_query('DELETE FROM resource_license WHERE license = ?', ['i', $ref]);
        ps_query('DELETE FROM license WHERE ref = ?', ['i', $ref]);
        $attachment = get_license_file_path($ref);
        if (is_file($attachment)) {
            unlink($attachment);
        }
    });
    $record = ps_query('SELECT holder, description, expires, file FROM license WHERE ref = ?', ['i', $ref]);
    expect($record)->toHaveCount(1)->and($record[0])->toMatchArray([
        'holder' => $payload['holder'], 'description' => $description, 'expires' => '2001-01-01',
        'file' => 'fixture-licence.pdf',
    ]);
    $linked = static fn (): array =>
        ps_array('SELECT resource AS value FROM resource_license WHERE license = ?', ['i', $ref]);
    expect($linked())->toBe([$outside]);

    $download = $server->request($base . 'download.php?ref=' . $ref, headers: $headers);
    expect($download['status'])->toBe(200)
        ->and($download['headers']['content-disposition'])->toContain('fixture-licence.pdf')
        ->and(hash('sha256', $download['body']))->toBe(hash_file('sha256', $source));

    foreach ([false, true] as $unlink) {
        [, $batch] = $form_submission('batch', ['collection' => $collection] + ($unlink ? ['unlink' => 'true'] : []));
        $result = $server->request($base . 'batch.php', ['ref' => $ref] + $batch, $headers);
        expect($result['status'])->toBeIn([302, 303]);
        expect($linked())->toEqualCanonicalizing($unlink ? [$outside] : [$resource, $other, $outside]);
    }

    [, $edit] = $form_submission('edit', ['ref' => $ref]);
    $edited = $server->request($base . 'edit.php', [
        'description' => $description . ' updated',
        'resources' => (string) $outside,
        'no_expiry_date' => 'yes',
    ] + $payload + $edit, $headers);
    expect($edited['status'])->toBeIn([302, 303])
        ->and(ps_query('SELECT description, expires FROM license WHERE ref = ?', ['i', $ref]))
        ->toBe([['description' => $description . ' updated', 'expires' => null]]);
    [, $delete] = $form_submission('delete', ['ref' => $ref]);
    $deleted = $server->request($base . 'delete.php', $delete, $headers);
    expect($deleted['status'])->toBeIn([302, 303])
        ->and(ps_query('SELECT ref FROM license WHERE ref = ?', ['i', $ref]))->toBeEmpty()
        ->and($linked())->toBeEmpty();
});
