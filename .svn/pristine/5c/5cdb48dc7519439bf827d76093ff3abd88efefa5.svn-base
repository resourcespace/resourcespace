<?php

command_line_only();

include_once dirname(__FILE__, 3) . '/include/annotation_functions.php';

// --- Set up
$run_id = test_generate_random_ID(5);
test_log("Run ID - {$run_id}");

$cache_globals = [
    'userpermissions' => $userpermissions,
    'annotate_text_adds_comment' => $annotate_text_adds_comment,
    'annotate_public_view' => $annotate_public_view,
    'annotate_exclude_restypes' => $annotate_exclude_restypes,
];
$reset_globals = static function (array $cache) {
    foreach ($cache as $name => $value) {
        $GLOBALS[$name] = $value;
    }
    unset($_POST['k']);
};

$fake_annotation = static function (array $with): array {
    return array_merge(
        [
            'resource' => create_resource(1, 0),
            'resource_type_field' => 0,
            'page' => 0,
            'tags' => [],
            'shapes' => [
                ['type' => 'rect', 'geometry' => ['x' => 0.10, 'y' => 0.20, 'width' => 0.50, 'height' => 0.60]]
            ],
        ],
        $with
    );
};
$without_permission = static fn (array $codes): array => array_values(array_diff($userpermissions, $codes));
$external_share_uc_setup = static function (array $uc_input) use ($userref) {
    $_POST['k'] = generate_resource_access_key(
        $uc_input['resource'],
        $userref,
        RESOURCE_ACCESS_FULL,
        '',
        'test'
    ) ?: '';
    $GLOBALS['annotate_public_view'] = true;
};

$rtf_dkl = create_resource_type_field(
    sprintf('Test #%s-%s DKL', test_get_file_id(__FILE__), $run_id),
    0,
    FIELD_TYPE_DYNAMIC_KEYWORDS_LIST
);
// --- End of Set up

$use_cases = [
    // Create
    [
        'name' => 'Admin can create annotation (field bound)',
        'input' => $fake_annotation(['resource_type_field' => $rtf_dkl]),
        'expected' => true,
    ],
    [
        'name' => 'Non-admin can create annotation (field bound)',
        'setup' => static fn() => $GLOBALS['userpermissions'] = $without_permission(['a']),
        'input' => $fake_annotation(['resource_type_field' => $rtf_dkl]),
        'expected' => true,
    ],
    [
        'name' => "Users (w/o resource edit access) shouldn't create annotation (field bound)",
        'setup' => static fn() => $GLOBALS['userpermissions'] = $without_permission(['e0']),
        'input' => $fake_annotation(['resource_type_field' => $rtf_dkl]),
        'expected' => false,
    ],
    [
        'name' => 'Admin can create text annotation',
        'setup' => static fn() => $GLOBALS['annotate_text_adds_comment'] = true,
        'input' => $fake_annotation(['resource_type_field' => 0]),
        'expected' => true,
    ],
    [
        'name' => 'Non-admin can create text annotation',
        'setup' => static function () use ($without_permission) {
            $GLOBALS['userpermissions'] = $without_permission(['a']);
            $GLOBALS['annotate_text_adds_comment'] = true;
        },
        'input' => $fake_annotation(['resource_type_field' => 0]),
        'expected' => true,
    ],
    [
        'name' => 'Users (w/o resource edit access) can create text annotation',
        'setup' => static function () use ($without_permission) {
            $GLOBALS['userpermissions'] = $without_permission(['e0']);
            $GLOBALS['annotate_text_adds_comment'] = true;
        },
        'input' => $fake_annotation(['resource_type_field' => 0]),
        'expected' => true,
    ],
    [
        'name' => 'Text annotation not allowed if confid disabled (any user)',
        'setup' => static fn() => $GLOBALS['annotate_text_adds_comment'] = false,
        'input' => $fake_annotation(['resource_type_field' => 0]),
        'expected' => false,
    ],
    [
        'name' => 'Users should not create text annotation in an external share context',
        'setup' => $external_share_uc_setup,
        'input' => $fake_annotation(['resource_type_field' => 0]),
        'expected' => false,
    ],
    [
        'name' => 'Users should not create an annotation in an external share context',
        'setup' => $external_share_uc_setup,
        'input' => $fake_annotation(['resource_type_field' => $rtf_dkl]),
        'expected' => false,
    ],
    [
        'name' => 'Users should not create an annotation for inapplicable resources (by type)',
        'setup' => static fn() => $GLOBALS['annotate_exclude_restypes'] = [1], # exclude Photos
        'input' => $fake_annotation(['resource_type_field' => $rtf_dkl]),
        'expected' => false,
    ],
    // Edit - Own records
    [
        'name' => 'Admin can edit their own annotation (field bound; w/ access)',
        'input' => $fake_annotation(['resource_type_field' => $rtf_dkl, 'user' => $userref]),
        'expected' => true,
    ],
    [
        'name' => 'Non-admin can edit their own annotation (field bound; w/ access)',
        'setup' => static fn() => $GLOBALS['userpermissions'] = $without_permission(['a']),
        'input' => $fake_annotation(['resource_type_field' => $rtf_dkl, 'user' => $userref]),
        'expected' => true,
    ],
    [
        'name' => "Admin shouldn't edit their own annotation (field bound; w/o access)",
        'setup' => static fn() => $GLOBALS['userpermissions'][] = "F{$rtf_dkl}", # can't edit the field
        'input' => $fake_annotation(['resource_type_field' => $rtf_dkl, 'user' => $userref]),
        'expected' => false,
    ],
    [
        'name' => "Non-admin shouldn't edit their own annotation (field bound; w/o access)",
        'setup' => static function () use ($rtf_dkl, $without_permission) {
            $GLOBALS['userpermissions'] = $without_permission(['a']);
            $GLOBALS['userpermissions'][] = "F{$rtf_dkl}"; # can't edit the field
        },
        'input' => $fake_annotation(['resource_type_field' => $rtf_dkl, 'user' => $userref]),
        'expected' => false,
    ],
    [
        'name' => "Users shouldn't edit their annotations (field bound; w/o resource access)",
        'setup' => static fn() => $GLOBALS['userpermissions'] = $without_permission(['e0']),
        'input' => $fake_annotation(['resource_type_field' => $rtf_dkl, 'user' => $userref]),
        'expected' => false,
    ],
    [
        'name' => 'Admin can edit their own text annotation (w/ access)',
        'setup' => static fn() => $GLOBALS['annotate_text_adds_comment'] = true,
        'input' => $fake_annotation(['resource_type_field' => 0, 'user' => $userref]),
        'expected' => true,
    ],
    [
        'name' => "Non-admin shouldn't edit their own text annotation",
        'setup' => static function () use ($without_permission) {
            $GLOBALS['annotate_text_adds_comment'] = true;
            $GLOBALS['userpermissions'] = $without_permission(['a', 'o']);
        },
        'input' => $fake_annotation(['resource_type_field' => 0, 'user' => $userref]),
        'expected' => false,
    ],
    [
        'name' => "Users shouldn't edit their annotations (field bound; inapplicable resource type)",
        'setup' => static fn() => $GLOBALS['annotate_exclude_restypes'] = [1], # exclude Photos
        'input' => $fake_annotation(['resource_type_field' => $rtf_dkl, 'user' => $userref]),
        'expected' => false,
    ],
    [
        'name' => "Users shouldn't edit their text annotation (inapplicable resource type)",
        'setup' => static fn() => $GLOBALS['annotate_exclude_restypes'] = [1], # exclude Photos
        'input' => $fake_annotation(['resource_type_field' => 0, 'user' => $userref]),
        'expected' => false,
    ],
    // Edit - Other user records
    [
        'name' => 'Admin can edit another user annotation (field bound)',
        'input' => $fake_annotation(['resource_type_field' => $rtf_dkl, 'user' => 300]),
        'expected' => true,
    ],
    [
        'name' => "Users shouldn't edit other annotations (field bound; w/o resource access)",
        'setup' => static fn() => $GLOBALS['userpermissions'] = $without_permission(['e0']),
        'input' => $fake_annotation(['resource_type_field' => $rtf_dkl, 'user' => 300]),
        'expected' => false,
    ],
    [
        'name' => "Users shouldn't edit other annotations (external share context)",
        'setup' => $external_share_uc_setup,
        'input' => $fake_annotation(['resource_type_field' => $rtf_dkl, 'user' => 300]),
        'expected' => false,
    ],
    [
        'name' => "Non-admin shouldn't edit another user annotation (field bound)",
        'setup' => static fn() => $GLOBALS['userpermissions'] = $without_permission(['a']),
        'input' => $fake_annotation(['resource_type_field' => $rtf_dkl, 'user' => 300]),
        'expected' => false,
    ],
    [
        'name' => "Non-admin shouldn't edit another user text annotation",
        'setup' => static function () use ($without_permission) {
            $GLOBALS['userpermissions'] = $without_permission(['a', 'o']);
            $GLOBALS['annotate_text_adds_comment'] = true;
        },
        'input' => $fake_annotation(['resource_type_field' => 0, 'user' => 300]),
        'expected' => false,
    ],
    [
        'name' => "Users shouldn't edit another user text annotation (external share context)",
        'setup' => $external_share_uc_setup,
        'input' => $fake_annotation(['resource_type_field' => 0, 'user' => 300]),
        'expected' => false,
    ],
    [
        'name' => "Admin can edit another user text annotation (w/ content manage access)",
        'setup' => static function () {
            $GLOBALS['userpermissions'][] = 'o';
            $GLOBALS['annotate_text_adds_comment'] = true;
        },
        'input' => $fake_annotation(['resource_type_field' => 0, 'user' => 300]),
        'expected' => true,
    ],
    [
        'name' => "Admin shouldn't edit another user text annotation (w/o content manage access)",
        'setup' => static function () use ($without_permission) {
            $GLOBALS['userpermissions'] = $without_permission(['o']);
            $GLOBALS['annotate_text_adds_comment'] = true;
        },
        'input' => $fake_annotation(['resource_type_field' => 0, 'user' => 300]),
        'expected' => false,
    ],
];
foreach ($use_cases as $uc) {
    // Set up the use case environment
    if (isset($uc['setup'])) {
        $uc['setup']($uc['input']);
    }

    $result = annotationEditable($uc['input'], array_intersect_key($_POST, ['k' => '']));
    if ($uc['expected'] !== $result) {
        echo "Use case: {$uc['name']} - ";
        return false;
    }

    $reset_globals($cache_globals);
}

// Tear down
$reset_globals($cache_globals);
unset($run_id, $use_cases, $result, $cache_globals, $reset_globals, $fake_annotation, $without_permission, $rtf_dkl);

return true;
