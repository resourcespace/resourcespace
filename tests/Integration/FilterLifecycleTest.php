<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;
use Montala\ResourceSpace\Tests\Support\LocalTestServer;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'usereditfilter', 'usersearchfilter', 'userderestrictfilter', 'userpermissions', 'resource_access_cache',
        'get_resource_data_cache', 'userdata',
    ]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('edits a filter and its rule, warns about inactive fields and protects filters in use', function () use (&$fixtures): void {
    $suffix = generateRandomTestID(10);
    $admin = $fixtures->user('filteradmin' . $suffix, 3);
    $session = generateSecureKey(32);
    ps_query('UPDATE user SET approved = 1, session = ?, last_active = NOW() WHERE ref = ?', ['s', $session, 'i', $admin]);
    $field_name = 'Access category ' . $suffix;
    $field = $fixtures->field($field_name, 0, FIELD_TYPE_CHECK_BOX_LIST);
    $former = set_node(null, $field, 'Former option', null, 10);
    $replacement = set_node(null, $field, 'Replacement option', null, 20);
    $filter = save_filter(0, 'Editable filter ' . $suffix, (string) RS_FILTER_ALL);
    $rule = save_filter_rule(0, $filter, [[RS_FILTER_NODE_IN, [$former]]]);
    $group = $fixtures->group('Filtered group ' . $suffix, 's,g,f*');
    expect(save_usergroup($group, ['search_filter_id' => $filter]))->toBeTrue();
    $fixtures->onCleanup(static function () use ($filter, $group): void {
        save_usergroup($group, ['search_filter_id' => 0]);
        delete_filter($filter);
    });

    $server = new LocalTestServer();
    $fixtures->onCleanup($server->close(...));
    $server->configure(['baseurl' => $server->url, 'session_autologout' => false]);
    $headers = $server->sessionHeaders($session);
    $edit = '/pages/admin/admin_filter_edit.php?filter=' . $filter;
    $form = $server->request($edit, headers: $headers);
    expect($form['status'])->toBe(200)->and($form['body'])->toContain('Former option');
    $save = LocalTestServer::hiddenFields($form['body'], '//form[@id="filter_edit_form"]');
    expect($save)->toHaveKey($GLOBALS['CSRF_token_identifier']);
    $new_name = 'Renamed filter ' . $suffix;
    $saved = $server->request($edit, $save + ['filter_name' => $new_name, 'filter_condition' => RS_FILTER_ANY], $headers);
    expect($saved['status'])->toBe(302);
    expect(get_filter($filter))->toMatchArray(['name' => $new_name, 'filter_condition' => RS_FILTER_ANY]);

    $rule_form = '/pages/admin/ajax/admin_filter_rule_edit.php?' . http_build_query(['filter' => $filter, 'ref' => $rule]);
    $rendered = $server->request($rule_form, headers: $headers);
    expect($rendered['status'])->toBe(200);
    $rule_fields = LocalTestServer::hiddenFields($rendered['body'], '//form[@id="filter_edit_form"]');
    expect($rule_fields)->toHaveKeys([$GLOBALS['CSRF_token_identifier'], 'filter_rule']);
    $rule_fields['filter_rule_data'] = json_encode([[RS_FILTER_NODE_NOT_IN, [$replacement]]], JSON_THROW_ON_ERROR);
    $edited = $server->request($edit, $rule_fields, $headers);
    expect($edited['status'])->toBe(200)->and($edited['body'])->toContain('Replacement option');
    expect(get_filter_rules($filter)[$rule])->toMatchArray(['nodes_on' => [], 'nodes_off' => [$replacement]]);

    $delete = $save + ['delete_filter' => $filter, 'filter_manage_page' => 'true'];
    $result = $server->request($edit, $delete, $headers);
    expect($result['status'])->toBe(200);
    $warning = json_decode($result['body'], true, flags: JSON_THROW_ON_ERROR);
    expect($warning['deleted'])->toBeFalse();
    expect(implode(' ', $warning['errors']))->toContain($GLOBALS['lang']['filter_delete_error'], '?ref=' . $group);
    expect(get_filter($filter)['name'])->toBe($new_name);

    $columns = array_intersect_key(get_resource_type_field_columns(), ['active' => true]);
    expect(save_resource_type_field($field, $columns, ['active' => 0]))->toBeTrue();
    $disabled_field = $server->request($edit, headers: $headers);
    $inactive = str_replace('%NAME', get_resource_type_field($field)['name'], $GLOBALS['lang']['filter_err_field_disabled']);
    expect($disabled_field['status'])->toBe(200)
        ->and(html_entity_decode($disabled_field['body'], ENT_QUOTES))->toContain($inactive);
});

it('copies filter rules independently and removes their dependent rows', function () use (&$fixtures): void {
    $field = $fixtures->field('Lifecycle filter', 0, FIELD_TYPE_CHECK_BOX_LIST, 'lifecyclefilter');
    $node = set_node(null, $field, 'Unique option', '', 10);
    $original = save_filter(0, 'Lifecycle filter', (string) RS_FILTER_ALL);
    $fixtures->onCleanup(static fn () => get_filter($original) && delete_filter($original));
    save_filter_rule(0, $original, [[RS_FILTER_NODE_IN, [$node]]]);
    $copy = copy_filter($original);
    $fixtures->onCleanup(static fn () => get_filter($copy) && delete_filter($copy));
    $original_rules = get_filter_rules($original);
    $copy_rules = get_filter_rules($copy);
    expect($copy != $original && count($original_rules) === 1 && count($copy_rules) === 1)->toBeTrue('Filter copy missing rules');
    expect(filter_check($original, [$node]) && filter_check($copy, [$node]))->toBeTrue('Copied filter does not match original');
    expect(!filter_check($copy, []))->toBeTrue('Copied filter accepts missing required node');
    $rule = ps_value('SELECT ref value FROM filter_rule WHERE filter = ?', ['i', $original], 0);
    expect(delete_filter_rule($rule))->toBeTrue('Rule deletion failed');
    expect(ps_value('SELECT COUNT(*) value FROM filter_rule_node WHERE filter_rule = ?', ['i', $rule], -1) == 0)->toBeTrue('Rule deletion left node mappings');
    expect(filter_check($copy, [$node]) && count(get_filter_rules($copy)) === 1)->toBeTrue('Deleting original rule changed copy');
    $copy_rule = ps_value('SELECT ref value FROM filter_rule WHERE filter = ?', ['i', $copy], 0);
    expect(delete_filter($copy))->toBeTrue('Filter deletion failed');
    expect(ps_value('SELECT COUNT(*) value FROM filter_rule WHERE filter = ?', ['i', $copy], -1) == 0
        && ps_value('SELECT COUNT(*) value FROM filter_rule_node WHERE filter_rule = ?', ['i', $copy_rule], -1) == 0)->toBeTrue('Filter deletion left dependent records');
});

it('matches all, any or none of the configured search filter rules', function (
    int $condition,
    array $visible_indexes,
    bool $exclude_sensitive = false,
) use (&$fixtures): void {
    $id = generateRandomTestID(10);
    $classification = $fixtures->field('Filter classification', 0, FIELD_TYPE_CHECK_BOX_LIST);
    $department = $fixtures->field('Filter department', 0, FIELD_TYPE_CHECK_BOX_LIST);
    $public = set_node(null, $classification, 'Public', null, 10);
    $sensitive = set_node(null, $classification, 'Sensitive', null, 20);
    $first_dept = set_node(null, $department, 'Communications', null, 10);
    $second_dept = set_node(null, $department, 'Outreach', null, 20);
    $filter = save_filter(0, 'Search rules ' . $id, (string) $condition);
    $group = $fixtures->group('Search filter group ' . $id, 's,g,f*');
    $fixtures->onCleanup(static function () use ($filter, $group): void {
        save_usergroup($group, ['search_filter_id' => 0]);
        delete_filter($filter);
    });
    expect(save_filter_rule(0, $filter, [[RS_FILTER_NODE_IN, [$first_dept, $second_dept]]]))->toBeInt();
    $rule = $exclude_sensitive ? [RS_FILTER_NODE_NOT_IN, [$sensitive]] : [RS_FILTER_NODE_IN, [$public]];
    expect(save_filter_rule(0, $filter, [$rule]))->toBeInt();
    expect(save_usergroup($group, ['search_filter_id' => $filter]))->not->toBeFalse();
    $user = $fixtures->user('filtered_search_' . $id, $group);
    $term = 'searchrule' . $id;
    $resources = [];
    $errors = [];
    foreach ([
        [$first_dept, $public], [$second_dept], [$public], [$public, $sensitive], [], [$first_dept, $sensitive],
    ] as $nodes) {
        $resource = $fixtures->resource(1, 0);
        expect(update_field($resource, $GLOBALS['view_title_field'], $term, $errors))->toBeTrue();
        add_resource_nodes($resource, $nodes);
        $resources[] = $resource;
    }

    setup_user(get_user($user));
    $visible = array_map(static fn (int $index): int => $resources[$index], $visible_indexes);
    expect(array_column((array) do_search($term), 'ref'))->toEqualCanonicalizing($visible);
})->with([
    'all rules' => [RS_FILTER_ALL, [0]],
    'any rule' => [RS_FILTER_ANY, [0, 1, 2, 3, 5]],
    'no rules' => [RS_FILTER_NONE, [4]],
    'all rules with a negative criterion' => [RS_FILTER_ALL, [0, 1], true],
]);

it('migrates legacy edit filters and enforces the criteria and resource type permissions', function () use (&$fixtures): void {
    $suffix = generateRandomTestID(10);
    $group = $fixtures->group('Legacy edit filter ' . $suffix, 's,g,v,e0,f*');
    $editor = $fixtures->user('filtereditor_' . $suffix, $group);
    $filters = [];
    $fixtures->onCleanup(static function () use ($group, &$filters): void {
        save_usergroup($group, ['edit_filter' => '', 'edit_filter_id' => 0]);
        foreach ($filters as $filter) {
            delete_filter($filter);
        }
    });
    $dept_name = 'editdepartment' . $suffix;
    $colour_name = 'editcolour' . $suffix;
    $department = $fixtures->field('Edit department', 0, FIELD_TYPE_CHECK_BOX_LIST, $dept_name);
    $colour = $fixtures->field('Edit colour', 0, FIELD_TYPE_DROP_DOWN_LIST, $colour_name);
    $sales = set_node(null, $department, 'Sales', null, 10);
    $it = set_node(null, $department, 'IT', null, 20);
    $red = set_node(null, $colour, 'Red', null, 10);
    $blue = set_node(null, $colour, 'Blue', null, 20);
    $references = [];
    $term = 'editquery' . $suffix;
    $errors = [];
    foreach ([[1, [$sales, $red]], [1, [$sales, $it, $red]], [2, [$it, $red]], [2, [$it, $blue]]] as [$type, $nodes]) {
        $ref = $fixtures->resource($type, 0);
        expect(update_field($ref, $GLOBALS['view_title_field'], $term, $errors))->toBeTrue();
        add_resource_nodes($ref, $nodes);
        $references[] = $ref;
    }
    [$first, $second] = $references;

    $apply = static function (string $legacy) use ($group, $editor, &$filters): void {
        expect(save_usergroup($group, ['edit_filter' => $legacy, 'edit_filter_id' => 0]))->not->toBeFalse();
        unset($GLOBALS['udata_cache'][$editor]);
        setup_user(get_user($editor));
        $criteria = edit_filter_to_restype_permission($legacy, $group, $GLOBALS['userpermissions']);
        $filter = migrate_filter($criteria);
        expect($filter)->toBeInt()->toBeGreaterThan(0);
        $filters[] = $filter;
        expect(save_usergroup($group, ['edit_filter_id' => $filter]))->not->toBeFalse();
        unset($GLOBALS['udata_cache'][$editor]);
        setup_user(get_user($editor));
    };
    foreach ([
        "{$dept_name}=Sales;{$colour_name}=Red" => [$first, $second],
        "{$dept_name}=IT;resource_type=1" => [$second],
    ] as $legacy => $editable) {
        $apply($legacy);
        expect(array_column((array) do_search($term, editable_only: true), 'ref'))->toEqualCanonicalizing($editable)
            ->and(array_values(array_filter($references, static fn (int $ref): bool => get_edit_access($ref))))
            ->toBe($editable);
    }
});

it('migrates a legacy derestrict filter and grants open access only to matching resources', function () use (&$fixtures): void {
    $suffix = generateRandomTestID(10);
    $group = $fixtures->group('Legacy derestrict filter ' . $suffix, 's,e0,f*');
    $region_name = 'region' . $suffix;
    $class_name = 'classification' . $suffix;
    $region = $fixtures->field('Filter region', 0, FIELD_TYPE_CHECK_BOX_LIST, $region_name);
    $classification = $fixtures->field('Filter classification', 0, FIELD_TYPE_DROP_DOWN_LIST, $class_name);
    $emea = set_node(null, $region, 'EMEA', null, 10);
    $apac = set_node(null, $region, 'APAC', null, 20);
    $open = set_node(null, $classification, 'Open', null, 10);
    $closed = set_node(null, $classification, 'Sensitive', null, 20);
    $matching = $fixtures->resource(1, 0);
    $wrong_region = $fixtures->resource(1, 0);
    $wrong_classification = $fixtures->resource(2, 0);
    add_resource_nodes($matching, [$emea, $open]);
    add_resource_nodes($wrong_region, [$apac, $open]);
    add_resource_nodes($wrong_classification, [$emea, $closed]);

    $legacy = "{$class_name}=Open;{$region_name}=EMEA";
    $filter = migrate_filter($legacy);
    expect($filter)->toBeInt()->toBeGreaterThan(0);
    $fixtures->onCleanup(static function () use ($filter, $group): void {
        save_usergroup($group, ['derestrict_filter' => '', 'derestrict_filter_id' => 0]);
        delete_filter($filter);
    });
    expect(save_usergroup($group, ['derestrict_filter' => $legacy, 'derestrict_filter_id' => $filter]))->not->toBeFalse();
    $reader = $fixtures->user('derestrict_reader_' . $suffix, $group);
    setup_user(get_user($reader));
    $GLOBALS['resource_access_cache'] = [];

    expect(get_resource_access($matching))->toBe(RESOURCE_ACCESS_FULL)
        ->and(get_resource_access($wrong_region))->toBe(RESOURCE_ACCESS_RESTRICTED)
        ->and(get_resource_access($wrong_classification))->toBe(RESOURCE_ACCESS_RESTRICTED);
});
