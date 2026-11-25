<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestScript;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures();
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('marks a job from a missing user as failed and processes the next extraction job', function () use (&$fixtures): void {
    $rejected = $fixtures->resource(2, 0, file_extension: 'txt');
    $resource = $fixtures->resource(2, 0, file_extension: 'txt');
    $content = 'Text extracted by offline job ' . generateRandomTestID(12);
    $path = get_resource_path($resource, true, '', true, 'txt');
    expect(file_put_contents($path, $content))->toBe(strlen($content));

    $missing_user = (int) ps_value('SELECT MAX(ref) + 1 AS value FROM user', [], 1);
    $invalid = job_queue_add('extract_text', ['ref' => $rejected, 'extension' => 'txt'], $missing_user);
    $valid = job_queue_add('extract_text', ['ref' => $resource, 'extension' => 'txt'], (int) $GLOBALS['userref']);
    expect($invalid)->toBeInt()->and($valid)->toBeInt();
    foreach ([$invalid, $valid] as $job) {
        $fixtures->onCleanup(static function () use ($job): void {
            clear_process_lock("job_{$job}");
            job_queue_delete($job);
        });
    }

    LocalTestScript::run('pages/tools/offline_jobs.php', [
        'offline_job_queue' => true,
        'offline_job_delete_completed' => false,
    ], ['--job=' . $invalid, '--job=' . $valid]);

    expect((int) job_queue_get_job($invalid)['status'])->toBe(STATUS_ERROR)
        ->and((int) job_queue_get_job($valid)['status'])->toBe(STATUS_COMPLETE)
        ->and(get_data_by_field($resource, $GLOBALS['extracted_text_field']))->toBe($content);
});
