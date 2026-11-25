<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\ResourceSpaceBootTestCase;
use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestScript;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

require_once RESOURCESPACE_BASE_PATH . '/include/image_processing.php';

$fixtures = null;
$user = 0;
$session = '';
beforeEach(function () use (&$fixtures, &$user, &$session): void {
    $fixtures = new IntegrationFixtures([
        'valid_upload_paths', 'enable_thumbnail_creation_on_upload', 'image_alternatives', 'offline_job_queue',
        'minimal_preview_creation_exclude_extensions', 'minimal_previews_sizes', 'upload_then_process',
        'previews_allow_enlarge',
    ]);
    $user = $fixtures->user('offlineuser' . generateRandomTestID(14), 3);
    $session = generateSecureKey(32);
    ps_query(
        'UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?',
        ['s', $session, 'i', $user]
    );
    unset($GLOBALS['udata_cache'][$user]);
    $fixtures->onCleanup(static function () use ($user): void {
        foreach (job_queue_get_jobs(user: $user) as $job) {
            if ($job['type'] === 'delete_file') {
                $data = json_decode($job['job_data'], true, flags: JSON_THROW_ON_ERROR);
                $path = $data['file'] ?? '';
                if (is_file($path)) {
                    unlink($path);
                }
            }
            clear_process_lock('job_' . $job['ref']);
            job_queue_delete($job['ref']);
        }
        foreach (ps_array('SELECT message AS value FROM user_message WHERE user = ?', ['i', $user]) as $message) {
            message_remove($message);
        }
    });
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

$run_job = static function (int $ref, array $overrides = []): void {
    LocalTestScript::run('pages/tools/offline_jobs.php', $overrides + [
        'offline_job_queue' => true,
        'offline_job_delete_completed' => false,
    ], ['--job=' . $ref]);
    expect((int) job_queue_get_job($ref)['status'])->toBe(STATUS_COMPLETE);
};

it('creates only eligible core previews on upload and completes them in the worker', function (
    bool $excluded,
) use (&$fixtures, &$user, $run_job): void {
    /** @var ResourceSpaceBootTestCase $this */
    if (!get_utility_path('im-convert')) {
        $this->markTestSkipped('Image previews require ImageMagick.');
    }
    setup_user(get_user($user));
    $GLOBALS['offline_job_queue'] = true;
    $GLOBALS['enable_thumbnail_creation_on_upload'] = true;
    $GLOBALS['upload_then_process'] = false;
    $GLOBALS['previews_allow_enlarge'] = true;
    $GLOBALS['image_alternatives'] = [];
    $GLOBALS['minimal_previews_sizes'] = ['col', 'thm', 'pre'];
    $GLOBALS['minimal_preview_creation_exclude_extensions'] = $excluded ? ['jpg'] : [];
    $GLOBALS['valid_upload_paths'][] = RESOURCESPACE_BASE_PATH . '/tests/Fixtures/Files';

    $resource = $fixtures->resource(1, 0, $user);
    expect(upload_file($resource, no_exif: true, file_path: testFixture('Images/image_1.jpg'), deletesource: false))
        ->toBeTrue();
    $sizes = ['col', 'thm', 'pre'];
    $exists = static fn (string $size): bool => is_file(get_resource_path($resource, true, $size, false, 'jpg'));
    foreach ($sizes as $size) {
        expect($exists($size))->toBe(!$excluded, $size);
    }
    expect($exists('lpr'))->toBeFalse();
    $jobs = job_queue_get_jobs('create_previews', STATUS_ACTIVE, $user);
    expect($jobs)->toHaveCount(1)
        ->and(json_decode($jobs[0]['job_data'], true, flags: JSON_THROW_ON_ERROR)['resource'])->toBe($resource);
    $run_job($jobs[0]['ref'], ['previews_allow_enlarge' => true, 'image_alternatives' => []]);
    foreach ([...$sizes, 'lpr'] as $size) {
        expect($exists($size))->toBeTrue($size);
    }
    expect(get_job_queue_log_path_by_ref($jobs[0]['ref']))->toBeFalse();
})->with(['core previews on upload' => [false], 'excluded extension' => [true]]);

it('runs a requested preview job and makes successful and skipped resources visible in its downloadable log', function () use (
    &$fixtures,
    &$user,
    &$session,
    $run_job,
): void {
    /** @var ResourceSpaceBootTestCase $this */
    if (!get_utility_path('im-convert')) {
        $this->markTestSkipped('Image previews require ImageMagick.');
    }
    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure([
        'baseurl' => $server->url, 'offline_job_queue' => true, 'session_autologout' => false,
    ]);
    $headers = $server->sessionHeaders($session);
    $collection = $fixtures->collection($user, 'Preview job ' . generateRandomTestID(12));
    $image = $fixtures->resource(1, 0, $user);
    $text = $fixtures->resource(1, 0, $user);
    expect(add_resource_to_collection($image, $collection))->toBeTrue()
        ->and(add_resource_to_collection($text, $collection))->toBeTrue();
    $GLOBALS['valid_upload_paths'][] = RESOURCESPACE_BASE_PATH . '/tests/Fixtures/Files';
    $GLOBALS['offline_job_queue'] = false;
    $GLOBALS['upload_then_process'] = false;
    $GLOBALS['enable_thumbnail_creation_on_upload'] = true;
    $GLOBALS['previews_allow_enlarge'] = true;
    expect(upload_file($image, no_exif: true, file_path: testFixture('Images/image_1.jpg'), deletesource: false))
        ->toBeTrue();
    $preview = get_resource_path($image, true, 'pre', false, 'jpg');
    expect($preview)->toBeFile();
    expect(unlink($preview))->toBeTrue();
    expect(is_file($preview))->toBeFalse();

    $path = '/pages/offline_jobs/recreate_previews.php?job_user=' . $user;
    $form = $server->request($path, headers: $headers);
    expect($form['status'])->toBe(200);
    $fields = LocalTestServer::hiddenFields($form['body'], '//form[@id="recreate_previews_form"]');
    expect($fields)->toHaveKey($GLOBALS['CSRF_token_identifier']);
    $saved = $server->request($path, $fields + [
        'save' => 'yes', 'process_type' => 'collections', 'refs' => (string) $collection,
        'delete_existing' => 'yes', 'resource_types' => [1],
    ], $headers);
    expect($saved['status'])->toBe(302);
    $queued = job_queue_get_jobs('recreate_previews', STATUS_ACTIVE, $user);
    expect($queued)->toHaveCount(1);
    $job = (int) $queued[0]['ref'];
    $log_notes = [
        "Added recreate_previews job {$job}",
        "Completed recreate_previews job {$job}",
        "Deleted recreate_previews job {$job}",
    ];
    $fixtures->onCleanup(static fn () => ps_query(
        'DELETE FROM activity_log WHERE remote_table = ? AND note IN (?, ?, ?)',
        array_merge(['s', 'job_queue'], ps_param_fill($log_notes, 's'))
    ));
    $run_job($job, ['previews_allow_enlarge' => true]);
    expect(get_resource_path($image, true, 'pre', false, 'jpg'))->toBeFile();
    $details = $server->request('/pages/job_details.php?job=' . $job, headers: $headers);
    $download = '/pages/job_log_download.php?job=' . $job;
    expect($details['status'])->toBe(200)->and($details['body'])->toContain($download);
    $log = $server->request($download, headers: $headers);
    expect($log['status'])->toBe(200)->and($log['headers']['content-disposition'])->toContain('.log');
    expect($log['body'])->toContain("resource #{$image}", '....completed', "resource #{$text}", '[ERROR] Failed');

    $filter = '/pages/manage_jobs.php?' . http_build_query([
        'job_user' => $user, 'job_type' => 'recreate_previews', 'job_status' => STATUS_COMPLETE,
    ]);
    $list = $server->request($filter, headers: $headers);
    expect($list['status'])->toBe(200)
        ->and($list['body'])->toContain('/pages/job_details.php?job=' . $job, 'delete_job');
    $log_path = get_job_queue_log_path_by_ref($job);
    expect($log_path)->toBeFile();
    $deleted = $server->request($filter, [
        'delete_job' => $job,
        $GLOBALS['CSRF_token_identifier'] => generateCSRFToken($session, 'jobform'),
    ], $headers);
    expect($deleted['status'])->toBe(200)
        ->and(job_queue_get_job($job))->toBeEmpty()
        ->and(file_exists($log_path))->toBeFalse();
    expect(ps_array(
        'SELECT log_code AS value FROM activity_log WHERE remote_table = ? AND note IN (?, ?, ?)',
        array_merge(['s', 'job_queue'], ps_param_fill($log_notes, 's'))
    ))->toEqualCanonicalizing([LOG_CODE_JOB_ADDED, LOG_CODE_JOB_COMPLETED, LOG_CODE_JOB_DELETED]);
});

it('previews and then applies a collection-scoped duplicate purge without touching unrelated resources', function () use (
    &$fixtures,
    &$user,
    &$session,
    $run_job,
): void {
    $collection = $fixtures->collection($user, 'Duplicates ' . generateRandomTestID(12));
    [$older, $newer, $unrelated_a, $unrelated_b] = array_map(
        static fn (): int => $fixtures->resource(1, 0, $user),
        range(1, 4)
    );
    foreach ([[$older, $newer], [$unrelated_a, $unrelated_b]] as $pair) {
        $checksum = md5(implode(':', $pair) . generateRandomTestID(10));
        ps_query('UPDATE resource SET file_checksum = ? WHERE ref IN (?, ?)', [
            's', $checksum, 'i', $pair[0], 'i', $pair[1],
        ]);
    }
    expect(add_resource_to_collection($older, $collection))->toBeTrue();

    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure([
        'baseurl' => $server->url, 'offline_job_queue' => true, 'resource_deletion_state' => 3,
        'session_autologout' => false,
    ]);
    $headers = $server->sessionHeaders($session);
    $path = '/pages/offline_jobs/purge_duplicates.php?job_user=' . $user;
    $form = $server->request($path, headers: $headers);
    expect($form['status'])->toBe(200);
    $hidden = LocalTestServer::hiddenFields($form['body'], '//form[@id="purge_duplicates_form"]');
    expect($hidden)->toHaveKey($GLOBALS['CSRF_token_identifier']);

    foreach (['yes', ''] as $dry_run) {
        $saved = $server->request($path, $hidden + [
            'save' => 'yes', 'collection_refs' => (string) $collection, 'manage_method' => 'lifo', 'dry_run' => $dry_run,
        ], $headers);
        expect($saved['status'])->toBe(302);
        $jobs = job_queue_get_jobs('purge_duplicates', STATUS_ACTIVE, $user);
        expect($jobs)->toHaveCount(1);
        $job = (int) $jobs[0]['ref'];
        $notes = ["Added purge_duplicates job {$job}", "Completed purge_duplicates job {$job}", "Deleted purge_duplicates job {$job}"];
        $fixtures->onCleanup(static function () use ($job, $notes): void {
            job_queue_delete($job);
            ps_query('DELETE FROM activity_log WHERE remote_table = ? AND note IN (?, ?, ?)', [
                's', 'job_queue', ...ps_param_fill($notes, 's'),
            ]);
        });
        $run_job($job, ['resource_deletion_state' => 3]);
        $log = $server->request('/pages/job_log_download.php?job=' . $job, headers: $headers);
        expect($log['status'])->toBe(200)
            ->and($log['body'])->toContain("keep resource #{$older}", "deleting resource #{$newer}")
            ->not->toContain("resource #{$unrelated_a}", "resource #{$unrelated_b}");
        expect(str_contains($log['body'], '[DRY-RUN]'))->toBe($dry_run === 'yes');
        $states = array_map(static fn (int $ref): int => (int) get_resource_data($ref, false)['archive'], [
            $older, $newer, $unrelated_a, $unrelated_b,
        ]);
        expect($states)->toBe([0, $dry_run === 'yes' ? 0 : 3, 0, 0]);
    }
});

it('queues a download and delivers the requested content through the completion message', function (
    string $type,
) use (&$fixtures, &$user, &$session, $run_job): void {
    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure([
        'baseurl' => $server->url,
        'offline_job_queue' => true,
        'collection_download' => true,
        'use_zip_extension' => true,
        'exiftool_write' => false,
        'terms_download' => false,
        'download_usage' => false,
        'download_filename_format' => '%resource.%extension',
        'session_autologout' => false,
    ]);
    $headers = $server->sessionHeaders($session);
    $resource = $fixtures->resource(1, 0, $user, file_extension: 'txt');
    $outsider = $fixtures->resource(1, 0, $user, file_extension: 'txt');
    $title = 'Offline delivery ' . generateRandomTestID(12);
    $content = 'Offline asset bytes for ' . $resource;
    $errors = [];
    expect(update_field($resource, $GLOBALS['view_title_field'], $title, $errors))->toBeTrue();
    expect(file_put_contents(get_resource_path($resource, true, '', true, 'txt'), $content))->toBe(strlen($content));
    expect(file_put_contents(get_resource_path($outsider, true, '', true, 'txt'), 'Other asset'))->toBeInt();

    if ($type === 'collection_download') {
        $collection = $fixtures->collection($user, $title);
        expect(add_resource_to_collection($resource, $collection))->toBeTrue();
        $form_url = '/pages/collection_download.php?collection=' . $collection;
        $form_selector = '//form[@id="collection_download_form"]';
        $submission = ['collection' => $collection, 'size' => 'original', 'submitted' => 'true'];
    } else {
        $search = '!list' . $resource;
        $form_url = '/pages/csv_export_results_metadata.php?' . http_build_query(['search' => $search, 'count' => 1]);
        $form_selector = '//form[contains(@action, "csv_export_results_metadata.php")]';
        $submission = ['search' => $search, 'count' => 1, 'process_offline' => '1', 'submit' => 'true'];
    }
    $response = $server->request($form_url, headers: $headers);
    expect($response['status'])->toBe(200);
    $form = LocalTestServer::hiddenFields($response['body'], $form_selector);
    $token_name = $GLOBALS['CSRF_token_identifier'];
    expect($form)->toHaveKey($token_name);
    $submitted = $server->request($form_url, $submission + [$token_name => $form[$token_name]], $headers);
    expect($submitted['status'])->toBe(200);
    $jobs = job_queue_get_jobs($type, STATUS_ACTIVE, $user);
    expect($jobs)->toHaveCount(1);
    $job_data = json_decode($jobs[0]['job_data'], true, flags: JSON_THROW_ON_ERROR);
    if ($type === 'collection_download') {
        expect((int) $job_data['collection'])->toBe($collection)->and(array_values($job_data['result']))->toBe([$resource]);
        expect($submitted['body'])->toContain($GLOBALS['lang']['jq_notify_user_preparing_archive']);
    } else {
        expect($job_data['search'])->toBe($search);
    }

    $run_job($jobs[0]['ref'], [
        'download_filename_format' => '%resource.%extension',
        'use_zip_extension' => true,
        'collection_download_original' => true,
    ]);
    $inbox = [];
    expect(message_get($inbox, $user))->toBeTrue();
    expect($inbox)->toHaveCount(1);
    $url = $inbox[0]['url'];
    expect($url)->toContain('/pages/download.php?userfile=');
    $downloaded = $server->request($url, headers: $headers);
    expect($downloaded['status'])->toBe(200);

    if ($type === 'collection_download') {
        $archive = tempnam(get_temp_dir(), 'offline_zip_');
        expect($archive)->toBeString();
        $fixtures->onCleanup(static fn () => unlink($archive));
        expect(file_put_contents($archive, $downloaded['body']))->toBeGreaterThan(0);
        $zip = new ZipArchive();
        expect($zip->open($archive))->toBeTrue();
        $fixtures->onCleanup(static fn () => $zip->close());
        expect($zip->numFiles)->toBe(1)
            ->and($zip->getFromName($resource . '.txt'))->toBe($content);
    } else {
        $records = preg_split('/\R/', trim($downloaded['body']));
        expect($records)->toHaveCount(2);
        $row = str_getcsv($records[1], escape: '');
        expect($row)->toContain((string) $resource, $title);
    }
})->with(['collection ZIP' => ['collection_download'], 'metadata CSV' => ['csv_metadata_export']]);

it('extracts a ZIP file listing without generating a recurring preview job', function () use (&$fixtures, &$user, $run_job): void {
    /** @var ResourceSpaceBootTestCase $this */
    if (!(new Symfony\Component\Process\ExecutableFinder())->find('unzip')) {
        $this->markTestSkipped('Archive listings require the unzip utility.');
    }
    setup_user(get_user($user));
    $GLOBALS['offline_job_queue'] = true;
    $GLOBALS['upload_then_process'] = false;
    $directory = get_temp_dir(false, 'zip-fixture-' . generateRandomTestID(12));
    $fixtures->onCleanup(static fn () => rcRmdir($directory));
    $file = $directory . '/archive.zip';
    $zip = new ZipArchive();
    expect($zip->open($file, ZipArchive::CREATE))->toBeTrue();
    expect($zip->addFromString('first.txt', 'First example'))->toBeTrue()
        ->and($zip->addFromString('nested/second.txt', 'Second example'))->toBeTrue()
        ->and($zip->close())->toBeTrue();

    $resource = $fixtures->resource(2, 0, $user);
    expect(upload_file($resource, no_exif: true, file_path: $file, deletesource: false))->toBeTrue();
    $jobs = job_queue_get_jobs(user: $user, status: STATUS_ACTIVE);
    foreach ($jobs as $job) {
        $run_job($job['ref']);
    }
    expect(job_queue_get_jobs(user: $user, status: STATUS_ACTIVE))->toBeEmpty()
        ->and(job_queue_get_jobs(user: $user, status: STATUS_ERROR))->toBeEmpty();
    expect(get_data_by_field($resource, $GLOBALS['extracted_text_field']))->toContain('first.txt', 'nested/second.txt');
});
