<?php

command_line_only();

// --- Set up

// Metadata field
$rich_text_field = create_resource_type_field('Test #405 rich text field', 1, FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE, 'test_405_rt', true);

// Resources
$resource_a = create_resource(1, 0);
$resource_b = create_resource(1, 0);
$resource_c = create_resource(1, 0);
$resource_d = create_resource(1, 0);
// --- End of Set up



$use_cases = [
    [
        'name' => 'Valid richtext input from TinyMCE',
        'input' => ['ref' => $resource_a, 'autosave_field' => $rich_text_field],
        'post' => ['value' => '<p><span style="text-decoration: underline;">This</span> is a <strong>valid</strong> rich <em>text</em></p>'],
        'expected' => true,
    ],
    [
        'name' => 'Attempt to save invalid content from TinyMCE',
        'input' => ['ref' => $resource_b, 'autosave_field' => $rich_text_field],
        'post' => ['value' => '<p>This is some invalid content!</p><script>alert(\'XSS!\');</script>'],
        'expected' => [$rich_text_field => 'Test #405 rich text field: ' . $lang['save-error-invalid']], // it means it errored - see save_resource_data
    ],
    [
        'name' => 'Attempt to save invalid content from TinyMCE - onclick',
        'input' => ['ref' => $resource_c, 'autosave_field' => $rich_text_field],
        'post' => ['value' => '<p>This is some invalid content!<a href="#" onclick="alert(\'XSS!\')">Click me</a>'],
        'expected' => [$rich_text_field => 'Test #405 rich text field: ' . $lang['save-error-invalid']], // it means it errored - see save_resource_data
    ],
    [
        'name' => 'Attempt to save invalid content from TinyMCE - Javascript URI',
        'input' => ['ref' => $resource_d, 'autosave_field' => $rich_text_field],
        'post' => ['value' => '<p>This is some invalid content!<a href="javascript:alert(\'XSS!\')">Click me</a>'],
        'expected' => [$rich_text_field => 'Test #405 rich text field: ' . $lang['save-error-invalid']], // it means it errored - see save_resource_data
    ],
];

foreach ($use_cases as $uc) {
    $_POST["field_{$rich_text_field}"] = $uc['post']['value'];
    $result = save_resource_data($uc['input']['ref'], false, $uc['input']['autosave_field']);

    if ($uc['expected'] !== $result) {
        echo "Use case: {$uc['name']} - ";
        return false;
    }

    // Check (internal) saving behaviour (e.g if input is invalid it shouldn't be saved)
    $saved_value = get_data_by_field($uc['input']['ref'], $uc['input']['autosave_field'], true);
    if (
        ($result === true && $uc['post']['value'] !== $saved_value)
        || (is_array($result) && $saved_value !== '')
    ) {
        echo "Use case (data save): {$uc['name']} - ";
        return false;
    }
}

// Tear down
$data_joins = $_POST = [];
unset(
    $rich_text_field,
    $resource_a,
    $resource_b,
    $resource_c,
    $resource_d,
    $use_cases
);

return true;
