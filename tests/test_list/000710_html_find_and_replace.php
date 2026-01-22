<?php

command_line_only();

$use_cases = [
    /*
    Example of a full use case structure. The tags & attributes keys are optional
    [
        'name' => '',
        'input' => [
            'html' => '',
            'tags' => [],
            'attributes' => [],
        ],
        'expected' => '',
    ],
    */
    [
        'name' => 'Empty string',
        'input' => [
            'find'      => '',
            'replace'   => '',
            'html'      => '',
        ],
        'expected' => '',
    ],
    [
        'name' => 'Text (ie. no HTML present)',
        'input' => [
            'find'      => '',
            'replace'   => '',
            'html'      => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
        ],
        'expected' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
    ],
    [
        'name' => 'HTML - Simple find/replace',
        'input' => [
            'find'      => 'text',
            'replace'   => 'HTML',
            'html'      => '<p>This is some text to test finding and replacing.</p>',
        ],
        'expected' => '<p>This is some HTML to test finding and replacing.</p>',
    ],
    [
        'name' => 'HTML - Simple find/replace - malformed HTML',
        'input' => [
            'find'      => 'text',
            'replace'   => 'HTML',
            'html'      => '<p>This is some text to test finding and replacing.',
        ],
        'expected' => '<p>This is some HTML to test finding and replacing.</p>',
    ],
    [
        'name' => 'HTML - Multiple find/replace',
        'input' => [
            'find'      => 'text',
            'replace'   => 'HTML',
            'html'      => '<p>This is some text to test finding and replacing. It has multiple instances of the word text to be replaced.</p>',
        ],
        'expected' => '<p>This is some HTML to test finding and replacing. It has multiple instances of the word HTML to be replaced.</p>',
    ],
    [
        'name' => 'HTML - Simple replace',
        'input' => [
            'find'      => 'sample ',
            'replace'   => '',
            'html'      => '<p>This is some sample text to test replacing.</p>',
        ],
        'expected' => '<p>This is some text to test replacing.</p>',
    ],
    [
        'name' => 'HTML - Multiple replace',
        'input' => [
            'find'      => 'sample ',
            'replace'   => '',
            'html'      => '<p>This is some sample text to test replacing. The word sample in sample text should be replaced.</p>',
        ],
        'expected' => '<p>This is some text to test replacing. The word in text should be replaced.</p>',
    ],
    [
        'name' => 'HTML - Multiple find/replace with nested tags',
        'input' => [
            'find'      => 'text',
            'replace'   => 'HTML',
            'html'      => '<p>This is <strong>some text</strong> to test finding and replacing. <em>It has multiple instances of the word text to be replaced.</em></p>',
        ],
        'expected' => '<p>This is <strong>some HTML</strong> to test finding and replacing. <em>It has multiple instances of the word HTML to be replaced.</em></p>',
    ],
    [
         'name' => 'HTML - Multiple find/replace with nested tags and string matches a tag name',
         'input' => [
             'find'      => 'strong',
             'replace'   => 'weak',
             'html'      => '<p>This is <strong>some strong text</strong> to test finding and replacing. <em>It has multiple instances of the word strong to be replaced.</em></p>',
         ],
         'expected' => '<p>This is <strong>some weak text</strong> to test finding and replacing. <em>It has multiple instances of the word weak to be replaced.</em></p>',
    ],
    [
        'name' => 'Multi byte characters',
        'input' => [
            'find'      => '©',
            'replace'   => 'è',
            'html'      => '<p>泉 This is <strong>some multi byte charåcters ©</strong> to test finding and replacing. <em>© It has multiple instances of the word text to be replaced.</em></p>',
        ],
        'expected' => '<p>泉 This is <strong>some multi byte charåcters è</strong> to test finding and replacing. <em>è It has multiple instances of the word text to be replaced.</em></p>',
    ],
];

foreach ($use_cases as $use_case) {

    $find       = $use_case['input']['find'];
    $replace    = $use_case['input']['replace'];
    $html       = $use_case['input']['html'];

    $processed = html_find_and_replace($find, $replace, $html);

    if ($processed !== $use_case['expected']) {
        echo "Use case: {$use_case['name']} - ";
        return false;
    }
}

// Tear down
unset($use_cases, $find, $replace, $html);

return true;
