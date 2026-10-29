<?php

command_line_only();

// --- Set up
$rtf_text = create_resource_type_field('Test #1101 text', 1, FIELD_TYPE_TEXT_BOX_SINGLE_LINE, 'test_1101_text', false);
$text_simple = 'Lorem ipsum dolor sit amet';
$node_simple = set_node(null, $rtf_text, $text_simple, null, '');
$text_diacritics = 'Lorem ipsum dolor sit amet, consectetur, Aldańa, adipiscing elit.';
$node_diacritics = set_node(null, $rtf_text, $text_diacritics, null, '');
// --- End of Set up



$use_cases = [
    [
        'name' => 'Find a simple text node',
        'input' => [
            'value' => $text_simple,
            'resource_type_field' => $rtf_text,
        ],
        'expected' => $node_simple,
    ],
    [
        'name' => 'Find a node with diacritics',
        'input' => [
            'value' => $text_diacritics,
            'resource_type_field' => $rtf_text,
        ],
        'expected' => $node_diacritics,
    ],
    [
        'name' => 'Expect to not find a node ID by name',
        'input' => [
            'value' => 'test 1101 use case which should not find any nodes',
            'resource_type_field' => $rtf_text,
        ],
        'expected' => false,
    ],
    [
        'name' => 'Expect to not find a node ID by name (with diacritics)',
        'input' => [
            // similar word, diacritic put on different character
            'value' => str_replace('Aldańa', 'Aldána', $text_diacritics),
            'resource_type_field' => $rtf_text,
        ],
        'expected' => false,
    ],
];
foreach ($use_cases as $uc) {
    if ($uc['expected'] !== get_node_id($uc['input']['value'], $uc['input']['resource_type_field'])) {
        echo "Use case: {$uc['name']} - ";
        return false;
    }
}



// Tear down
unset($rtf_text, $text_simple, $node_simple, $text_diacritics, $node_diacritics, $use_cases);

return true;
