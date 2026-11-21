<?php

declare(strict_types=1);

test('default metadata value filters have valid signatures after provisioning', function (): void {
    $filters = ps_query(
        'SELECT ref, value_filter FROM resource_type_field WHERE value_filter IS NOT NULL AND TRIM(value_filter) <> ?',
        ['s', ''],
    );
    expect($filters)->not->toBeEmpty();

    $previous_signing_flag = get_sysvar('code_sign_required', '');
    try {
        foreach ($filters as $field) {
            expect(eval_check_signed($field['value_filter']))
                ->not
                ->toBe('', "Default metadata field {$field['ref']} has an invalid code signature.");
        }
    } finally {
        set_sysvar('code_sign_required', $previous_signing_flag);
    }
});
