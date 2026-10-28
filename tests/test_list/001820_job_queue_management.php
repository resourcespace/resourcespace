<?php

command_line_only();

return (static function () {
    $check = static function ($condition, string $message): void {
        if (!$condition) {
            throw new RuntimeException($message);
        }
    };

    include_once dirname(__DIR__, 2) . '/include/job_functions.php';
    $type = 'test_queue_' . test_generate_random_ID(6);
    try {
        $future = date('Y-m-d H:i:s', time() + 86400);
        $past = date('Y-m-d H:i:s', time() - 3600);
        $first = job_queue_add($type, ['path' => '/fixture/one'], '', $past, '', '', '', 20);
        $check(is_numeric($first), 'Job creation failed');
        $duplicate = job_queue_add($type, ['path' => '/fixture/one']);
        $check(!is_numeric($duplicate) && count(job_queue_get_jobs($type)) === 1, 'Duplicate job inserted');
        $second = job_queue_add($type, ['path' => '/fixture/two'], '', $future, '', '', '', 5);
        $check(is_numeric($second), 'Second job creation failed');
        $check(array_column(job_queue_get_jobs($type), 'ref') == [$second, $first], 'Queue priority order incorrect');
        $due = job_queue_get_jobs($type, -1, '', '', 'priority', 'asc', '', false, 0, true);
        $check(array_column($due, 'ref') == [$first], 'Future job selected as due');
        job_queue_update($second, ['updated' => 'café'], STATUS_COMPLETE, $past, 30);
        $row = job_queue_get_job($second);
        $check(json_decode($row['job_data'], true) === ['updated' => 'café'] && $row['status'] == STATUS_COMPLETE
            && $row['start_date'] === $past && $row['priority'] == 30, 'Job update did not persist');
        $check(array_column(job_queue_get_jobs($type, STATUS_ACTIVE), 'ref') == [$first], 'Status filtering incorrect');
        job_queue_delete($first);
        $check(job_queue_get_job($first) === [] && count(job_queue_get_jobs($type)) === 1, 'Job deletion affected wrong queue entries');
        return true;
    } catch (Throwable $e) {
        echo $e->getMessage() . ' - ';
        return false;
    } finally {
        ps_query('DELETE FROM job_queue WHERE type = ?', ['s', $type]);
    }
})();
