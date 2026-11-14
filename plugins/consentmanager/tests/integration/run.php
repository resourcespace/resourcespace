<?php

/** Isolated external-consent integration tests. Never select or modify installation data. */

if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
define('RS_TEST_MODE', 1);
require dirname(__DIR__, 4) . '/include/boot.php';
require_once dirname(__DIR__, 2) . '/hooks/all.php';

$assertions = 0;
$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$reject = static function (callable $operation, string $message) use ($assert): void {
    try {
        $operation();
    } catch (RuntimeException | InvalidArgumentException $e) {
        $assert(true, $message);
        return;
    }
    $assert(false, $message);
};

$test_database = 'rs_test_db';
if (in_array('--cleanup', $argv, true)) {
    $marker = ps_value('SELECT COUNT(*) value FROM rs_test_db.user WHERE username = ?', ['s', 'consent_test'], 0);
    $assert((int) $marker === 1, 'Refusing to remove an unrecognised test database');
    ps_query('DROP DATABASE rs_test_db', [], '', -1, false);
    echo "Removed interrupted consent test database.\n";
    exit;
}

$assert((int) ps_value(
    'SELECT COUNT(*) value FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = ?',
    ['s', $test_database],
    0
) === 0, 'Test database already exists; refusing to overwrite it');
ps_query('CREATE DATABASE rs_test_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci', [], '', -1, false);
$mysql_db = $test_database;
$query_cache_enabled = false;
$plugins = ['consentmanager'];
$hook_cache = [];
$directory = sys_get_temp_dir() . '/rs_consent_' . bin2hex(random_bytes(8));
mkdir($directory, 0700);
$storagedir = $directory;
$tempdir = $directory;
sql_connect();
$success = false;
$cleaned = false;
$cleanup = static function () use (&$cleaned, $directory): void {
    if (!$cleaned) {
        $cleaned = true;
        ps_query('DROP DATABASE rs_test_db', [], '', -1, false);
        rcRmdir($directory);
    }
};
register_shutdown_function($cleanup);
try {
    CheckDBStruct('dbstruct');
    CheckDBStruct('plugins/consentmanager/dbstruct');
    // Prove the plugin schema upgrader restores newly added fields for existing installations.
    ps_query('ALTER TABLE consent_request_response DROP COLUMN signed_presented');
    CheckDBStruct('plugins/consentmanager/dbstruct');
    include dirname(__DIR__, 2) . '/config/config.default.php';
    include dirname(__DIR__, 2) . '/languages/en.php';
    $baseurl = 'https://consent.example.invalid';
    $baseurl_short = '/';
    $consentmanager_external_enabled = true;
    $allow_share = true;
    $manage_collections_share_link = true;
    $k = '';
    $system_read_only = false;
    $user = new_user('consent_test', 3);
    setup_user(get_user($user));
    $userpermissions = ['cm', 'a', 't', 'h', 'g'];
    ps_query('UPDATE usergroup SET permissions = ? WHERE ref = 3', ['s', 'cm,a,t,h,g']);
    unset($udata_cache[$user]);
    ps_query('UPDATE user SET email = ? WHERE ref = ?', ['s', 'owner@example.invalid', 'i', $user]);
    $collection = create_collection($user, 'Consent test collection');
    $r1 = create_resource(1, 0, $user, 'consent_test', 'jpg', false);
    $r2 = create_resource(3, 0, $user, 'consent_test', 'mp4', false);
    $r3 = create_resource(4, 0, $user, 'consent_test', 'wav', false);
    foreach ([$r1, $r2, $r3] as $resource) {
        ps_query('UPDATE resource SET access = 0 WHERE ref = ?', ['i', $resource]);
        unset($get_resource_data_cache[$resource]);
        if (isset($resource_access_cache)) {
            unset($resource_access_cache[$resource]);
        }
    }
    add_resource_to_collection($r1, $collection);
    add_resource_to_collection($r2, $collection);
    add_resource_to_collection($r3, $collection);
    $config = ['title' => 'Test photography consent', 'organisation' => 'Test organisation',
        'wording' => "Terms reviewed by the recipient.\nNo use outside the stated purpose.",
        'declarations' => 'I agree to the permitted use.', 'privacy' => 'Contact the requester for privacy rights.',
        'duration' => 'Until the consent expiry date.', 'options' => "Marketing\nInternal use",
        'fields' => 'Preferred contact name', 'enabled' => 'yes'];
    $template = consentmanager_external_save_template(0, $config);
    $data = ['recipient_name' => 'Recipient', 'recipient_email' => 'recipient@example.invalid',
        'guardian' => '', 'consent_usage' => ['Print', 'Websites'], 'personal_message' => '',
        'expires' => date('Y-m-d', strtotime('+14 days')), 'consent_expires' => date('Y-m-d', strtotime('+60 days')),
        'template' => $template];
    $userpermissions = [];
    $reject(fn() => consentmanager_external_create($collection, $data, [$r1]), 'Unauthorised request creation accepted');
    $userpermissions = ['cm', 'a', 't', 'h', 'g'];
    $reject(fn() => consentmanager_external_create($collection, $data, []), 'Empty request accepted');
    $assert(collection_writeable($collection), 'Fixture collection not writable');
    $assert(collection_readable($collection), 'Fixture collection not readable');
    $assert(allow_collection_share(get_collection($collection)), 'Fixture collection not shareable');
    foreach ([$r1, $r2, $r3] as $resource) {
        $assert(get_resource_access($resource) === 0, 'Fixture resource access: ' . var_export(get_resource_access($resource), true));
        $assert(can_share_resource($resource), 'Fixture resource not shareable');
    }
    $ref = consentmanager_external_create($collection, $data, [$r1, $r2, $r3]);
    $frozen = consentmanager_external_manage_request($ref);
    $requester_snapshot = consentmanager_external_json($frozen['terms'])['requester'];
    $assert(
        $requester_snapshot['include'] === true && $requester_snapshot['initiated'] !== '',
        'Default requester/date option was not snapshotted'
    );
    $request = $frozen;
    $preview = true;
    $token = '';
    $error = '';
    $declined = false;
    ob_start();
    consentmanager_external_render_portal($request, '', '', false, true);
    $rendered = ob_get_clean();
    $assert(str_contains($rendered, 'cm-logo'), 'Recipient logo missing');
    $assert(str_contains($rendered, 'cm-signature'), 'Admin preview lacks shared drawing controls');

    $config['wording'] = 'Changed template wording';
    consentmanager_external_save_template($template, $config);
    $assert(consentmanager_external_manage_request($ref)['terms'] === $frozen['terms'], 'Template snapshot changed');
    remove_resource_from_collection($r2, $collection);
    $assert(count(consentmanager_external_resources($ref)) === 3, 'Collection change altered resource snapshot');
    $token = bin2hex(random_bytes(32));
    ps_query(
        'UPDATE consent_request SET status = ?, token_hash = ? WHERE ref = ?',
        ['s', 'pending', 's', hash('sha256', $token), 'i', $ref]
    );
    $assert(consentmanager_external_resolve($token)['ref'] == $ref, 'Token failed to resolve');
    $reject(fn() => consentmanager_external_resolve(str_repeat('0', 64)), 'Wrong token accepted');
    $outside = create_resource(1, 0, $user, 'consent_test', 'jpg', false);
    $reject(fn() => consentmanager_external_media($frozen, $outside), 'Unrelated asset accepted');
    $reject(fn() => consentmanager_external_media($frozen, $r1, true), 'Original exposed by default');
    // Safe preview path is served through the capability route, not a storage URL.
    $image_path = get_resource_path($r1, true, 'pre', true, 'jpg');
    file_put_contents($image_path, 'preview fixture');
    $assert(consentmanager_external_media($frozen, $r1)[1] === 'image/jpeg', 'Image preview unavailable');
    $ffmpeg_preview_extension = 'mp4';
    $video_path = get_resource_path($r2, true, 'pre', true, 'mp4');
    file_put_contents($video_path, 'video fixture');
    $assert(consentmanager_external_media($frozen, $r2)[1] === 'video/mp4', 'Video preview unavailable');
    $audio_path = get_resource_path($r3, true, '', true, 'mp3');
    file_put_contents($audio_path, 'audio fixture');
    $assert(consentmanager_external_media($frozen, $r3)[1] === 'audio/mpeg', 'Audio preview unavailable');
    $answers = ['name' => 'Recipient', 'guardian' => '', 'declarations' => 'yes',
        'options' => [1 => 'yes'], 'fields' => ['Preferred contact name' => 'unused', 0 => 'Person']];
    consentmanager_external_complete_form($token, $answers);
    $response = consentmanager_external_response($ref);
    $assert((int) ps_value('SELECT COUNT(*) value FROM consent', [], 0) === 0, 'Form created active consent');
    $decoded = consentmanager_external_json($response['responses']);
    $assert($decoded['options'] === [false, true], 'Optional permissions not preserved');
    $signature = ['revision' => $response['revision'], 'hash' => $response['unsigned_hash'],
        'method' => 'typed', 'signatory' => 'Recipient', 'confirm' => 'yes'];
    $reject(
        fn() => consentmanager_external_sign($token, array_replace($signature, ['hash' => str_repeat('0', 64)])),
        'Signature accepted for a different document'
    );
    $unsigned = consentmanager_external_document($token, false, $response['revision']);
    $assert(str_starts_with($unsigned, '%PDF-'), 'Unsigned document is not a PDF');
    consentmanager_external_sign($token, $signature);
    $signed = consentmanager_external_response($ref);
    $assert((int) ps_value('SELECT COUNT(*) value FROM consent', [], 0) === 0, 'Signing created active consent');
    $assert(consentmanager_external_resolve($token)['status'] === 'signed', 'Signing marked request completed');
    $reject(
        fn() => consentmanager_external_submit($token, $signed['revision'], $signed['signed_hash']),
        'Submission without signed PDF presentation accepted'
    );
    $first_signed = consentmanager_external_document($token, true, $signed['revision']);
    $assert($first_signed !== $unsigned, 'Signing did not change document');
    // Corrections invalidate signatures and stale tabs cannot approve earlier revisions.
    consentmanager_external_complete_form($token, $answers + ['unused' => 'ignored']);
    $changed = consentmanager_external_response($ref);
    $assert(!$changed['signed_hash'] && $changed['revision'] > $signed['revision'], 'Correction retained signature');
    $reject(fn() => consentmanager_external_submit($token, $signed['revision'], $signed['signed_hash']), 'Stale submit accepted');
    $reject(fn() => consentmanager_external_sign($token, $signature), 'Stale signing accepted');
    consentmanager_external_document($token, false, $changed['revision']);
    $signature['revision'] = $changed['revision'];
    $signature['hash'] = $changed['unsigned_hash'];
    $signature['method'] = 'drawn';
    $signature['signature'] = '[[[0.1,0.2],[0.5,0.5],[0.8,0.1]]]';
    consentmanager_external_sign($token, $signature);
    $signed = consentmanager_external_response($ref);
    $bytes = consentmanager_external_document($token, true, $signed['revision']);
    // Corrupt stored bytes must fail before creating any consent.
    ps_query('UPDATE consent_request_response SET signed_pdf = ? WHERE request = ?', ['s', 'corrupt', 'i', $ref]);
    $reject(fn() => consentmanager_external_submit($token, $signed['revision'], $signed['signed_hash']), 'Corrupted PDF accepted');
    ps_query('UPDATE consent_request_response SET signed_pdf = ? WHERE request = ?', ['s', $bytes, 'i', $ref]);
    // Simulate a temporary storage failure. A retry must use the same signed bytes.
    $saved_storage = $storagedir;
    $storagedir = '/dev/null/consent-test';
    $reject(fn() => consentmanager_external_submit($token, $signed['revision'], $signed['signed_hash']), 'Storage failure marked completed');
    $storagedir = $saved_storage;
    $assert((int) ps_value('SELECT COUNT(*) value FROM consent', [], 0) === 0, 'Failed storage left active consent');
    $consent = consentmanager_external_submit($token, $signed['revision'], $signed['signed_hash']);
    $assert($consent > 0, 'No standard consent created');
    $assert(file_get_contents(get_consent_file_path($consent)) === $bytes, 'Stored document differs from approved bytes');
    $assert(hash_file('sha256', get_consent_file_path($consent)) === $signed['signed_hash'], 'Stored hash differs');
    $record = consentmanager_get_consent($consent);
    $assert(count($record['resources']) === 3, 'Frozen resources not all linked');
    $assert($record['expires'] === $data['consent_expires'], 'Existing expiry field not populated');
    $assert(
        consentmanager_external_submit($token, $signed['revision'], $signed['signed_hash']) === $consent,
        'Duplicate submit created a different consent'
    );
    $assert((int) ps_value('SELECT COUNT(*) value FROM consent', [], 0) === 1, 'Duplicate record created');
    $assert(!consentmanager_save_file($consent, 'replacement.pdf', 'different'), 'Historical attachment overwritten');
    $assert(!consentmanager_delete_consent($consent), 'Historical consent deleted');
    $reject(fn() => consentmanager_external_complete_form($token, $answers), 'Completed evidence changed');
    consentmanager_external_revoke($ref, true);
    $reject(fn() => consentmanager_external_resolve($token), 'Withdrawal left link active');
    $assert(file_get_contents(get_consent_file_path($consent)) === $bytes, 'Withdrawal changed evidence');
    $expired = consentmanager_get_expired_consent_resources(999);
    $assert(in_array($r1, $expired) && in_array($r2, $expired), 'Existing expiry processing excludes external consent');
    $consent_expired_workflow_state = 2;
    $assert(consentmanager_process_expired_auto_archive(), 'Existing automatic archive process failed');
    $assert((int) get_resource_data($r1, false)['archive'] === 2, 'External expired consent did not archive asset');
    foreach ([$r1, $r2, $r3] as $resource) {
        ps_query('UPDATE resource SET archive = 0 WHERE ref = ?', ['i', $resource]);
        get_resource_data($resource, false);
    }
    $second_data = array_replace($data, ['recipient_name' => 'Second person',
        'recipient_email' => 'second@example.invalid', 'consent_expires' => date('Y-m-d', strtotime('+5 days'))]);
    $second_ref = consentmanager_external_create($collection, $second_data, [$r1]);
    $second_token = bin2hex(random_bytes(32));
    ps_query(
        'UPDATE consent_request SET status = ?, token_hash = ? WHERE ref = ?',
        ['s', 'pending', 's', hash('sha256', $second_token), 'i', $second_ref]
    );
    consentmanager_external_complete_form($second_token, array_replace($answers, ['name' => 'Second person']));
    $second_response = consentmanager_external_response($second_ref);
    consentmanager_external_document($second_token, false, $second_response['revision']);
    consentmanager_external_sign($second_token, ['revision' => $second_response['revision'],
        'hash' => $second_response['unsigned_hash'], 'method' => 'typed', 'signatory' => 'Second person']);
    $second_response = consentmanager_external_response($second_ref);
    consentmanager_external_document($second_token, true, $second_response['revision']);
    $second_consent = consentmanager_external_submit($second_token, $second_response['revision'], $second_response['signed_hash']);
    $assert(
        $second_consent !== $consent && count(consentmanager_get_consents($r1)) === 2,
        'Multiple external subjects did not retain independent consent records'
    );
    $assert(
        in_array($second_consent, consentmanager_get_expiring_consents(14)),
        'Existing expiry notification selection excludes external consent'
    );
    $assert(consentmanager_process_expiry_notifications(), 'Existing consent expiry notification process failed');
    $assert((int) ps_value(
        'SELECT expiration_notice_sent value FROM consent WHERE ref = ?',
        ['i', $second_consent],
        0
    ) === 1, 'Existing expiry process did not mark external consent notified');
    // Second individual and manually created consents remain independent.
    $manual = consentmanager_create_consent(
        'Manual subject',
        null,
        null,
        null,
        'manual@example.invalid',
        '',
        'Print',
        '',
        date('Y-m-d'),
        null,
        (string) $user
    );
    $assert(consentmanager_link_consent($manual, $r1), 'Manual linking regressed');
    $assert(consentmanager_save_file($manual, 'manual.pdf', 'manual fixture'), 'Manual attachment saving regressed');
    $active_manual = consentmanager_get_all_consents('Manual subject', 'active');
    $assert(count($active_manual) === 1, 'Combined consent name/status filter failed');
    $assert(consentmanager_delete_consent($manual), 'Manual consent deletion regressed');
    $pending = consentmanager_external_create($collection, $data, [$r1]);
    $pending_token = bin2hex(random_bytes(32));
    ps_query('UPDATE consent_request SET status = ?, token_hash = ?, expires = DATE_SUB(NOW(), INTERVAL 1 DAY) '
        . 'WHERE ref = ?', ['s', 'pending', 's', hash('sha256', $pending_token), 'i', $pending]);
    $reject(fn() => consentmanager_external_submit($pending_token, 0, ''), 'Expired request submitted');
    $delivery = consentmanager_external_create($collection, $data, [$r1]);
    $assert(!consentmanager_external_send($delivery), 'Test-mode mail unexpectedly delivered');
    $delivery_row = consentmanager_external_manage_request($delivery);
    $assert(
        $delivery_row['status'] === 'failed' && $delivery_row['error'] === 'cm_external_mail_failed',
        'Send remained Draft or concealed delivery failure'
    );
    $assert(strlen($delivery_row['token_hash']) === 64, 'Issued token hash missing');
    $page = consentmanager_external_request_page('Recipient', 'failed', 0, 1);
    $assert($page['total'] >= 1 && count($page['rows']) === 1, 'Request filtering/paging failed');
    $reject(fn() => consentmanager_external_request_page('', 'invalid_status', 0, 10), 'Invalid filter accepted');
    // Request management remains possible after the source collection has gone.
    $temporary_collection = create_collection($user, 'Temporary consent source');
    add_resource_to_collection($r1, $temporary_collection);
    $orphan = consentmanager_external_create($temporary_collection, $data, [$r1]);
    delete_collection($temporary_collection);
    $assert(
        consentmanager_external_manage_request($orphan)['ref'] == $orphan,
        'Deleting collection lost central request management'
    );
    $orphan_token = bin2hex(random_bytes(32));
    ps_query(
        'UPDATE consent_request SET token_hash = ?, status = ? WHERE ref = ?',
        ['s', hash('sha256', $orphan_token), 's', 'pending', 'i', $orphan]
    );
    $reject(fn() => consentmanager_external_sign($orphan_token, []), 'Invalid signature accepted');
    $assert((int) ps_value(
        'SELECT attempts value FROM consent_request_response WHERE request = ?',
        ['i', $orphan],
        0
    ) === 1, 'Failed attempts bypass rate limiting');
    ps_query('UPDATE consent_request_response SET attempts = 30, window_start = NOW() WHERE request = ?', ['i', $orphan]);
    $reject(fn() => consentmanager_external_visit($orphan_token), 'Rate limit bypassed');
    consentmanager_external_revoke($orphan);
    $reject(fn() => consentmanager_external_resolve($orphan_token), 'Revoked link accepted');
    $assert(
        consentmanager_get_consent($consent)['consent_usage'] === 'Print, Websites',
        'External usage did not use existing Consent Manager representation'
    );
    $assert(!consentmanager_link_consent($consent, $outside), 'Historical document extended to unsigned assets');
    $consentmanager_external_enabled = false;
    $reject(fn() => consentmanager_external_resolve($pending_token), 'Disabled external endpoint accepted token');
    $assert(consentmanager_get_consent($consent) !== false, 'Disabling external broke existing consent access');
    $assert(!consentmanager_save_file($consent, 'replacement.pdf', 'different'), 'Disabling external removed immutability');
    if (in_array('--browser', $argv, true)) {
        $consentmanager_external_enabled = true;
        activate_plugin('consentmanager');
        add_resource_to_collection($r2, $collection);
        $browser_ref = consentmanager_external_create($collection, $data, [$r1, $r2, $r3]);
        $browser_token = bin2hex(random_bytes(32));
        $session = bin2hex(random_bytes(20));
        ps_query(
            'UPDATE consent_request SET status = ?, token_hash = ? WHERE ref = ?',
            ['s', 'pending', 's', hash('sha256', $browser_token), 'i', $browser_ref]
        );
        ps_query('UPDATE user SET session = ?, last_active = NOW() WHERE ref = ?', ['s', $session, 'i', $user]);
        // Real, tiny image fixture for browser rendering.
        $image = imagecreatetruecolor(120, 90);
        imagejpeg($image, $image_path);
        imagedestroy($image);
        $thumb_path = get_resource_path($r1, true, 'thm', true, 'jpg');
        copy($image_path, $thumb_path);
        ps_query('UPDATE resource SET has_image = 1, preview_extension = ? WHERE ref = ?', ['s', 'jpg', 'i', $r1]);
        $fixture_path = $argv[array_search('--browser', $argv, true) + 1] ?? '';
        if (!str_starts_with($fixture_path, '/tmp/rs-consent-browser-')) {
            throw new RuntimeException('Invalid browser fixture destination');
        }
        file_put_contents($fixture_path, json_encode(['request' => $browser_ref, 'collection' => $collection,
            'token' => $browser_token, 'session' => $session, 'storage' => $directory,
            'video_path' => $video_path, 'audio_path' => $audio_path], JSON_THROW_ON_ERROR));
        $deadline = time() + 150;
        while (!is_file($fixture_path . '.done') && time() < $deadline) {
            usleep(100000);
        }
    }
    $success = true;
    echo "PASS: $assertions external consent assertions\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'FAIL: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
} finally {
    $storagedir = $directory;
    $cleanup();
}
exit($success ? 0 : 1);
