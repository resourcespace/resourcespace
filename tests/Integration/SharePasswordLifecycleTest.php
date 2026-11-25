<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('opens the resource from its internal share link for a signed-in user', function () use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $owner = $fixtures->user('internalshareowner' . $id, 3);
    $reader = $fixtures->user('internalsharereader' . $id, 2);
    $sessions = [$owner => generateSecureKey(32), $reader => generateSecureKey(32)];
    foreach ($sessions as $user => $session) {
        ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
            's', $session, 'i', $user,
        ]);
    }
    $resource = $fixtures->resource(1, 0, $owner);
    $title = 'Internal share ' . $id;
    $errors = [];
    expect(update_field($resource, (int) $GLOBALS['view_title_field'], $title, $errors))->toBeTrue();

    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure([
        'baseurl' => $server->url, 'session_autologout' => false, 'anonymous_login' => null, 'allow_share' => true,
    ]);
    $form = $server->request('/pages/resource_share.php?ref=' . $resource, headers: $server->sessionHeaders($sessions[$owner]));
    expect($form['status'])->toBe(200);
    $document = new DOMDocument();
    $document->loadHTML($form['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $link = parse_url($xpath->evaluate('string(//form[@id="resourceshareform"]//input[@class="URLDisplay"]/@value)'));
    expect($link['path'])->toBe('/');
    parse_str($link['query'], $query);
    expect($query)->toBe(['r' => (string) $resource]);

    $target = $link['path'] . '?' . $link['query'];
    $guest = $server->request($target, headers: $server->sessionHeaders(''));
    expect($guest['status'])->toBe(302)->and(parse_url($guest['headers']['location'], PHP_URL_PATH))->toBe('/login.php');
    $reader_headers = $server->sessionHeaders($sessions[$reader]);
    $redirect = $server->request($target, headers: $reader_headers);
    expect($redirect['status'])->toBe(302);
    $destination = parse_url($redirect['headers']['location']);
    expect($destination['path'])->toBe('/pages/view.php');
    $view = $server->request($destination['path'] . '?' . $destination['query'], headers: $reader_headers);
    expect($view['status'])->toBe(200)->and($view['body'])->toContain($title);
});

it('shows external sharing controls according to the user group and retains the selected group on a new share', function (
    string $permission,
    bool $can_share_externally,
    bool $can_choose_group,
) use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $group = $fixtures->group('Sharing member ' . $id, 's,g,f*' . $permission);
    $recipient_group = $fixtures->group('Sharing recipient ' . $id, 's,g,f*');
    $user = $fixtures->user('sharingmember' . $id, $group);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', [
        's', $session, 'i', $user,
    ]);
    $resource = $fixtures->resource(1, 0, $user);
    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure([
        'baseurl' => $server->url, 'session_autologout' => false, 'allow_share' => true,
        'allowed_external_share_groups' => [$group, $recipient_group], 'share_password_required' => false,
    ]);
    $headers = $server->sessionHeaders($session);
    $path = '/pages/resource_share.php?ref=' . $resource;
    $form = $server->request($path, headers: $headers);
    expect($form['status'])->toBe(200);
    $document = new DOMDocument();
    $document->loadHTML($form['body'], LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    expect($xpath->evaluate('count(//form[@id="resourceshareform"]//input[@class="URLDisplay"])'))->toBe(1.0);
    expect($xpath->evaluate('count(//input[@name="generateurl" and @type="button"])'))
        ->toBe($can_share_externally ? 1.0 : 0.0);
    expect($xpath->evaluate('count(//select[@name="usergroup"])'))->toBe($can_choose_group ? 1.0 : 0.0);

    $selected_group = $group;
    if ($can_choose_group) {
        $groups = [];
        foreach ($xpath->query('//select[@name="usergroup"]/option') as $option) {
            $groups[] = (int) $option->getAttribute('value');
        }
        expect($groups)->toEqualCanonicalizing([$group, $recipient_group]);
        $selected_group = $recipient_group;
    }
    $fields = LocalTestServer::hiddenFields($form['body'], '//form[@id="resourceshareform"]');
    expect($fields)->toHaveKey($GLOBALS['CSRF_token_identifier']);
    $response = $server->request($path, array_replace($fields, [
        'generateurl' => 'yes', 'access' => RESOURCE_ACCESS_RESTRICTED, 'usergroup' => $selected_group,
    ]), $headers);
    expect($response['status'])->toBe(200);
    $shares = ps_query(
        'SELECT access, usergroup FROM external_access_keys WHERE resource = ? AND user = ?',
        ['i', $resource, 'i', $user]
    );
    expect($shares)->toBe($can_share_externally
        ? [['access' => RESOURCE_ACCESS_RESTRICTED, 'usergroup' => $selected_group]]
        : []);
})->with([
    'standard sharing group' => ['', true, false],
    'allowed to choose an external group' => [',x', true, true],
    'internal sharing only' => [',noex', false, false],
]);

it('enforces isolated share passwords and expires old password cookies', function () use (&$fixtures): void {
    $resource = $fixtures->resource(1, 0);
    $keys = [];
    foreach (['one', 'two'] as $label) {
        $key = generateRandomTestID(10);
        $keys[] = $key;
        ps_query(
            'INSERT INTO external_access_keys (resource,access_key,user,access,password_hash) VALUES (?,?,?,?,?)',
            ['i', $resource, 's', $key, 'i', $GLOBALS['userref'], 'i', 0,
            's',
            hash('sha256', $key . 'SharePassword1' . $GLOBALS['scramble_key'])]
        );
    }
    [$first, $second] = $keys;
    $hash = hash('sha256', $first . 'SharePassword1' . $GLOBALS['scramble_key']);
    $cookie = hash('sha256', date('Ymd') . $first . $hash . $GLOBALS['scramble_key']);
    expect(check_share_password($first, 'SharePassword1', 'unused'))->toBeTrue('Correct share password rejected');
    expect(!check_share_password($first, 'wrong', 'unused'))->toBeTrue('Wrong password accepted');
    expect(!check_share_password($first, '', ''))->toBeTrue('Empty password/cookie accepted');
    expect(check_share_password($first, '', $cookie))->toBeTrue('Valid share cookie rejected');
    expect(!check_share_password($second, '', $cookie))->toBeTrue('Cookie accepted for another share');
    ps_query(
        'UPDATE external_access_keys SET password_hash = ? WHERE access_key = ?',
        ['s', hash('sha256', $first . 'Replacement2' . $GLOBALS['scramble_key']), 's', $first]
    );
    expect(!check_share_password($first, '', $cookie))->toBeTrue('Old cookie survived password change');
    expect(!check_share_password($first, 'SharePassword1', 'unused'))->toBeTrue('Old password survived change');
    expect(check_share_password($first, 'Replacement2', 'unused'))->toBeTrue('Replacement password rejected');
    ps_query('DELETE FROM external_access_keys WHERE access_key = ?', ['s', $first]);
    expect(!check_share_password($first, 'Replacement2', 'unused'))->toBeTrue('Revoked share accepted password');
    expect(!check_share_password($first, '', $cookie))->toBeTrue('Revoked share accepted old cookie');
});

it('opens a password-protected share only after the guest form accepts the password', function (
    bool $collection_share,
) use (&$fixtures): void {
    $id = generateRandomTestID(12);
    $title = 'GuestShare ' . $id;
    $resource = $fixtures->resource(1, 0);
    $user = (int) $GLOBALS['userref'];
    $title_field = (int) $GLOBALS['view_title_field'];
    $errors = [];
    expect(update_field($resource, $title_field, $title, $errors))->toBeTrue();
    $password = generateSecureKey(20);
    $expires = date('Y-m-d H:i:s', strtotime('+2 days'));

    if ($collection_share) {
        $collection = $fixtures->collection($user, 'Guest members ' . $id);
        expect(add_resource_to_collection($resource, $collection))->toBeTrue();
        $key = generate_collection_access_key($collection, 0, '', RESOURCE_ACCESS_RESTRICTED, $expires, '', $password);
        $path = '/pages/search.php?' . http_build_query(['search' => '!collection' . $collection, 'k' => $key]);
        $fixtures->onCleanup(static fn () => delete_collection_access_key($collection, $key));
    } else {
        $key = generate_resource_access_key($resource, $user, RESOURCE_ACCESS_FULL, $expires, '', '', $password);
        $path = '/pages/view.php?' . http_build_query(['ref' => $resource, 'k' => $key]);
        $fixtures->onCleanup(static fn () => delete_resource_access_key($resource, $key));
    }
    expect($key)->toBeString()->not->toBeEmpty();

    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure(['baseurl' => $server->url]);
    $guest = $server->sessionHeaders('');
    $guarded = $server->request($path, headers: $guest);
    expect($guarded['status'])->toBe(302)->and($guarded['body'])->not->toContain($title);
    $challenge = parse_url($guarded['headers']['location']);
    expect($challenge['path'])->toBe('/pages/share_access.php');
    $form = $server->request($challenge['path'] . '?' . $challenge['query'], headers: $guest);
    expect($form['status'])->toBe(200)->and($form['body'])->toContain('name="share_password"');
    $inputs = LocalTestServer::hiddenFields($form['body'], '//form[contains(@action, "share_access.php")]');
    expect($inputs)->toHaveKey('submit');

    $accepted = $server->request('/pages/share_access.php', $inputs + ['share_password' => $password], $guest);
    expect($accepted['status'])->toBe(302);
    $cookie = explode(';', $accepted['headers']['set-cookie'] ?? '', 2)[0];
    expect($cookie)->toStartWith('share_access=');
    $guest = array_map(
        static fn (string $header): string => str_starts_with($header, 'Cookie: ')
            ? $header . '; ' . $cookie
            : $header,
        $guest
    );
    $visible = $server->request($path, headers: $guest);
    expect($visible['status'])->toBe(200)->and($visible['body'])->toContain($title);

    $past = date('Y-m-d H:i:s', strtotime('-2 days'));
    $expired = $collection_share
        ? edit_collection_external_access($key, RESOURCE_ACCESS_RESTRICTED, $past)
        : edit_resource_external_access($key, RESOURCE_ACCESS_FULL, $past);
    expect($expired)->toBeTrue();
    expect($server->request($path, headers: $guest)['body'])->not->toContain($title);
})->with(['resource link' => [false], 'collection link' => [true]]);
