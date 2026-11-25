<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

require_once dirname(__DIR__, 2) . '/include/csv_functions.php';

$fixtures = null;
$import_file = null;
beforeEach(function () use (&$fixtures, &$import_file): void {
    $fixtures = new IntegrationFixtures();
    $import_file = tmpfile();
    expect($import_file)->not->toBeFalse();
});
afterEach(function () use (&$fixtures, &$import_file): void {
    if (is_resource($import_file)) {
        fclose($import_file);
    }
    $fixtures?->cleanUp();
});

it('validates and imports users with required, optional and extended CSV columns', function (string $columns) use (&$fixtures, &$import_file): void {
    $id = generateRandomTestID(10);
    $group = $fixtures->group('Imported accounts ' . $id, 's,g');
    $headers = ['username', 'email'];
    $names = ['csv_a_' . $id, 'csv_b_' . $id];
    $emails = [$names[0] . '@example.invalid', $names[1] . '@example.invalid'];
    $password = 'CsvTest-' . generateSecureKey(18) . '!7';
    $rows = [[$names[0], $emails[0]], [$names[1], $emails[1]]];

    if ($columns !== 'minimum') {
        $headers = [...$headers, 'password', 'fullname'];
        $rows[0] = [...$rows[0], $password, 'Shared example name'];
        $rows[1] = [...$rows[1], $password, 'Shared example name'];
        $rows[] = ['csv_c_' . $id, 'csv_c_' . $id . '@example.invalid', '', ''];
    }
    if ($columns === 'extended') {
        $headers = [...$headers, 'account_expires', 'comments', 'ip_restrict', 'lang'];
        $rows[0] = [...$rows[0], '2099-12-31', 'Imported IPv4 account', '192.0.2.*', 'fr'];
        $rows[1] = [...$rows[1], '2099-12-31', 'Imported IPv6 account', '2001:db8::*', ''];
        $rows[2] = [...$rows[2], '', '', '', ''];
    }
    foreach ([$headers, ...$rows] as $row) {
        fputcsv($import_file, $row, escape: '');
    }
    fflush($import_file);
    $path = stream_get_meta_data($import_file)['uri'];
    $messages = [];
    expect(csv_user_import_process($path, $group, $messages))->toBeTrue();
    $usernames = array_column($rows, 0);
    foreach ($usernames as $username) {
        expect(get_user_by_username($username))->toBeFalse();
    }

    $imported = csv_user_import_process($path, $group, $messages, true);
    $refs = array_map('get_user_by_username', $usernames);
    foreach (array_filter($refs) as $ref) {
        $fixtures->trackUser($ref);
    }
    expect($imported)->toBeTrue();
    foreach ($refs as $index => $ref) {
        expect($ref)->toBeInt();
        $user = get_user($ref);
        expect($user)->toMatchArray(['username' => $rows[$index][0], 'email' => $rows[$index][1], 'usergroup' => $group]);
        $secret = ps_value('SELECT password AS value FROM user WHERE ref = ?', ['i', $ref], '');
        expect($secret !== '')->toBeTrue();
        if (!empty($rows[$index][2])) {
            expect(rs_password_verify($password, $secret, ['username' => $user['username']]))->toBeTrue();
            expect($user['fullname'])->toBe('Shared example name');
        }
        if ($columns === 'extended' && $index < 2) {
            expect($user['account_expires'])->toStartWith('2099-12-31');
            expect($user)->toMatchArray([
                'comments' => $rows[$index][5], 'ip_restrict_user' => $rows[$index][6],
                'lang' => $rows[$index][7] ?: $GLOBALS['defaultlanguage'],
            ]);
        }
    }
    if ($columns !== 'minimum') {
        expect(trim((string) get_user($refs[2])['fullname']))->toBe('');
    }
})->with(['minimum', 'selected', 'extended']);

it('reports missing, duplicate and malformed user CSV values before writing any accounts', function () use (&$fixtures, &$import_file): void {
    $id = generateRandomTestID(10);
    $existing_name = 'csv_existing_' . $id;
    $existing_email = $existing_name . '@example.invalid';
    $existing = $fixtures->user($existing_name);
    ps_query('UPDATE user SET email = ? WHERE ref = ?', ['s', $existing_email, 'i', $existing]);
    $unused = 'csv_unused_' . $id;
    $rows = [
        ['username', 'email', 'account_expires', 'ip_restrict', 'lang'],
        ['', $unused . '@example.invalid', '', '', ''],
        [$unused, '', '', '', ''],
        [$existing_name, $unused . '@example.invalid', '', '', ''],
        [$unused, $existing_email, '', '', ''],
        [$unused, $unused . '@example.invalid', '31/12/2099', '192.0.2.999', 'invalid-language'],
        [$unused, $unused . '@example.invalid', '', '2001:db8::gg', ''],
    ];
    foreach ($rows as $row) {
        fputcsv($import_file, $row, escape: '');
    }
    fflush($import_file);
    $messages = [];
    expect(csv_user_import_process(stream_get_meta_data($import_file)['uri'], 2, $messages))->toBeFalse();
    expect(implode("\n", $messages))->toContain(
        'Mandatory column "username" cannot be empty', 'Mandatory column "email" cannot be empty',
        'Username "' . $existing_name . '" exists', 'Email "' . $existing_email . '" exists',
        '"31/12/2099" is not a valid date', '"192.0.2.999" is not a valid IP',
        '"2001:db8::gg" is not a valid IP', '"invalid-language" is not a valid language ID',
    );
    expect(get_user_by_username($unused))->toBeFalse();
});
