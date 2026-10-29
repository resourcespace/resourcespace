<?php

command_line_only();

// --- Set up
$run_id = test_generate_random_ID(5);
test_log("Run ID - {$run_id}");
$uc_generic_param_name = "test_{$run_id}";
// --- End of Set up



$use_cases = [
    [
        'name' => 'Use the default when not found',
        'input' => ['param' => $uc_generic_param_name, 'default' => 'default value', 'force_numeric' => false],
        'expected' => 'default value',
    ],
    [
        'name' => 'Honour the expected search order (post/get/COOKIE)',
        'setup' => fn() => $_COOKIE[$uc_generic_param_name] = 'fromCOOKIE',
        'input' => ['param' => $uc_generic_param_name, 'default' => '', 'force_numeric' => false],
        'expected' => 'fromCOOKIE',
    ],
    [
        'name' => 'Honour the expected search order (post/GET/cookie)',
        'setup' => fn() => $_GET[$uc_generic_param_name] = 'fromGET',
        'input' => ['param' => $uc_generic_param_name, 'default' => '', 'force_numeric' => false],
        'expected' => 'fromGET',
    ],
    [
        'name' => 'Honour the expected search order (POST/get/cookie)',
        'setup' => fn() => $_POST[$uc_generic_param_name] = 'fromPOST',
        'input' => ['param' => $uc_generic_param_name, 'default' => '', 'force_numeric' => false],
        'expected' => 'fromPOST',
    ],
    [
        'name' => 'Force numeric (when OK)',
        'setup' => fn() => $_GET['test_force_numeric'] = '1234',
        'input' => ['param' => 'test_force_numeric', 'default' => 1000, 'force_numeric' => true],
        'expected' => '1234',
    ],
    [
        'name' => 'Force numeric (when invalid, use default)',
        'setup' => fn() => $_GET['test_force_numeric'] = 'badNumber',
        'input' => ['param' => 'test_force_numeric', 'default' => 'not numeric', 'force_numeric' => true],
        'expected' => 'not numeric',
    ],
    [
        'name' => 'Check type using internal function name as string',
        'setup' => fn() => $_GET['test_is_array'] = [2345],
        'input' => ['param' => 'test_is_array', 'default' => [], 'force_numeric' => false, 'type_check' => 'is_array'],
        'expected' => [2345],
    ],
    [
        'name' => 'Check type using custom validator (e.g. param is a list of integers)',
        'setup' => fn() => $_GET['test_list_of_int'] = [1, 2, 3, 4],
        'input' => [
            'param' => 'test_list_of_int',
            'default' => [],
            'force_numeric' => false,
            'type_check' => fn($V) => is_array($V) && array_filter($V, 'is_int') === $V,
        ],
        'expected' => [1, 2, 3, 4],
    ],
];
foreach ($use_cases as $uc) {
    unset($GLOBALS[$uc_generic_param_name]);

    // Set up the use case environment
    if (isset($uc['setup'])) {
        $uc['setup']();
    }

    $result = !isset($uc['input']['type_check'])
        ? getval($uc['input']['param'], $uc['input']['default'], $uc['input']['force_numeric'])
        : getval($uc['input']['param'], $uc['input']['default'], $uc['input']['force_numeric'], $uc['input']['type_check']);

    if ($uc['expected'] !== $result) {
        printf(
            '%sUse case: %s - ',
            RS_TEST_DEBUG ? PHP_EOL : '',
            $uc['name'],
        );
        return false;
    }
}



// Tear down
unset($run_id, $uc_generic_param_name, $use_cases, $result);

return true;
