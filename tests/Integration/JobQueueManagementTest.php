<?php

declare(strict_types=1);

$jobs = [];
afterEach(function () use (&$jobs): void {
    foreach ($jobs as $ref) {
        job_queue_delete($ref);
    }
    $jobs = [];
});

it('deduplicates jobs and filters them by priority, due date and status', function () use (&$jobs): void {
    $type = 'test_queue_' . generateRandomTestID(12);
    $past = date('Y-m-d H:i:s', time() - 3600);
    $future = date('Y-m-d H:i:s', time() + 86400);
    $first = job_queue_add($type, ['path' => '/fixture/one'], '', $past, '', '', '', 20);
    expect($first)->toBeNumeric();
    $jobs[] = $first;
    expect(job_queue_add($type, ['path' => '/fixture/one']))->not->toBeNumeric()
        ->and(job_queue_get_jobs($type))->toHaveCount(1);

    $second = job_queue_add($type, ['path' => '/fixture/two'], '', $future, '', '', '', 5);
    expect($second)->toBeNumeric();
    $jobs[] = $second;
    expect(array_column(job_queue_get_jobs($type), 'ref'))->toEqual([$second, $first]);
    $due = job_queue_get_jobs($type, -1, '', '', 'priority', 'asc', '', false, 0, true);
    expect(array_column($due, 'ref'))->toEqual([$first]);

    job_queue_update($second, ['updated' => 'café'], STATUS_COMPLETE, $past, 30);
    $row = job_queue_get_job($second);
    expect(json_decode($row['job_data'], true))->toBe(['updated' => 'café'])
        ->and($row['status'])->toEqual(STATUS_COMPLETE)
        ->and($row['start_date'])->toBe($past)
        ->and($row['priority'])->toEqual(30)
        ->and(array_column(job_queue_get_jobs($type, STATUS_ACTIVE), 'ref'))->toEqual([$first]);

    job_queue_delete($first);
    expect(job_queue_get_job($first))->toBe([])
        ->and(job_queue_get_jobs($type))->toHaveCount(1);
});
