<?php

declare(strict_types=1);

$saved_signing = [];
$saved_signing_flag = null;
beforeEach(function () use (&$saved_signing, &$saved_signing_flag): void {
    $saved_signing = array_intersect_key($GLOBALS, ['scramble_key' => true]);
    $GLOBALS['scramble_key'] = 'test_scramble_key_evalSIG';
    $saved_signing_flag = get_sysvar('code_sign_required', null);
});
afterEach(function () use (&$saved_signing, &$saved_signing_flag): void {
    set_sysvar('code_sign_required', $saved_signing_flag);
    unset($GLOBALS['scramble_key']);
    foreach ($saved_signing as $key => $value) {
        $GLOBALS[$key] = $value;
    }
});

it('rejects empty or unsigned configuration code', function (string $code): void {
    expect(eval_check_signed($code))->toBe('');
})->with(['empty' => '', 'whitespace' => '  ', 'unsigned' => '$some_var = true;']);

it('returns the body of signed configuration code', function (): void {
    $code = '$some_var = true;';
    $signed = '//SIG' . sign_code($code) . "\n" . $code;
    expect(eval_check_signed($signed))->toBe($code)
        ->and(eval_check_signed('//SIG' . sign_code($signed) . "\n" . $signed))
        ->toBe($signed);
});

$override_fixture = null;
beforeEach(function () use (&$override_fixture): void {
    $override_fixture = new \Montala\ResourceSpace\Tests\Support\IntegrationFixtures([
        'configs_overwritten', 'metadata_report', 'edit_autosave', 'pest_override_only',
        'pest_validation_value', 'use_error_exception',
    ]);
});
afterEach(function () use (&$override_fixture): void {
    $override_fixture?->cleanUp();
});

it('restores changed and newly introduced settings when a signed override is cleared', function (): void {
    $GLOBALS['metadata_report'] = false;
    unset($GLOBALS['pest_override_only']);
    $code = '$metadata_report = true; $pest_override_only = [1];';

    override_rs_variables_by_eval($GLOBALS, '//SIG' . sign_code($code) . "\n" . $code, 'pest');
    expect($GLOBALS['metadata_report'])->toBeTrue()
        ->and($GLOBALS['pest_override_only'])->toBe([1]);

    override_rs_variables_by_eval($GLOBALS, '', 'pest');
    expect($GLOBALS['metadata_report'])->toBeFalse()
        ->and(array_key_exists('pest_override_only', $GLOBALS))->toBeFalse();
});

it('keeps the user-group override while applying and clearing a resource-type override', function (): void {
    $GLOBALS['configs_overwritten'] = [];
    $GLOBALS['edit_autosave'] = true;
    $GLOBALS['metadata_report'] = true;
    $group = '$edit_autosave = false;';
    $resource_type = '$metadata_report = false;';

    override_rs_variables_by_eval($GLOBALS, '//SIG' . sign_code($group) . "\n" . $group, 'usergroup');
    override_rs_variables_by_eval(
        $GLOBALS,
        '//SIG' . sign_code($resource_type) . "\n" . $resource_type,
        'resource_type'
    );
    expect($GLOBALS['edit_autosave'])->toBeFalse()->and($GLOBALS['metadata_report'])->toBeFalse();

    override_rs_variables_by_eval($GLOBALS, '', 'resource_type');
    expect($GLOBALS['edit_autosave'])->toBeFalse()->and($GLOBALS['metadata_report'])->toBeTrue();
});

it('rejects invalid remote configuration without applying it', function (string $code): void {
    expect(validate_remote_code($code))->toBeFalse();
})->with([
    'undefined variable' => '$pest_validation_value = $pest_nonexistent_variable;',
    'syntax error' => '$pest_validation_value = true',
    'undefined function' => 'pest_undefined_configuration_function();',
    'runtime warning' => 'trigger_error("Test warning");',
]);

it('validates ordinary configuration without committing any global assignments', function (): void {
    $GLOBALS['pest_validation_value'] = true;
    expect(validate_remote_code('$pest_validation_value = false;'))->toBeTrue()
        ->and($GLOBALS['pest_validation_value'])->toBeTrue();
});

it('lists only group overrides that need signing and preserves stored code in preview mode', function () use (
    &$override_fixture,
): void {
    $group = $override_fixture->group('Signing audit ' . generateRandomTestID(10), 's');
    $code = '$edit_autosave = false;';
    $signed = '//SIG' . sign_code($code) . "\n" . $code;
    $double_signed = '//SIG' . sign_code($signed) . "\n" . $signed;
    $group_label = "usergroup -> config_options -> {$group}\n";

    foreach ([
        ['', false],
        [$code, true],
        [$signed, false],
        [$signed . "\n\$metadata_report = true;", true],
        [$double_signed, true],
    ] as [$stored, $should_report]) {
        save_usergroup($group, ['config_options' => $stored]);
        ob_start();
        try {
            resign_all_code(confirm: true, output: false, output_changes_only: true);
            $report = ob_get_contents();
        } finally {
            ob_end_clean();
        }
        expect(str_contains($report, $group_label))->toBe($should_report)
            ->and((string) get_usergroup($group)['config_options'])->toBe($stored);
        if ($should_report) {
            expect($report)->toContain($group_label . $stored . "\n");
        }
    }
});
