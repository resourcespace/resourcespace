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
            'html' => '',
        ],
        'expected' => '',
    ],
    [
        'name' => 'Text (ie. no HTML present)',
        'input' => [
            'html' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
        ],
        'expected' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
    ],
    [
        'name' => 'Text (ie. no HTML present) with new line feeds at the end',
        'input' => [
            'html' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.' . PHP_EOL,
        ],
        'expected' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.' . PHP_EOL,
    ],
    [
        'name' => 'Text with a tag allowed by default requires post processing',
        'input' => [
            'html' => 'Lorem ipsum <strong>dolor</strong> sit amet, consectetur adipiscing elit.',
        ],
        'post-process' => 'strip_paragraph_tags',
        'expected' => 'Lorem ipsum <strong>dolor</strong> sit amet, consectetur adipiscing elit.',
    ],
    [
        'name' => 'Text with a <script> (not allowed by default) tag in',
        'input' => [
            'html' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. <script>alert("XSS test")</script>',
        ],
        'expected' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. </p>',
    ],
    [
        'name' => 'Tag (header) not allowed by default',
        'input' => [
            'html' => '<header>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</header>',
        ],
        'expected' => '',
    ],
    [
        'name' => 'Default configuration - allowed tag (p)',
        'input' => [
            'html' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>'
        ],
        'expected' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
    ],
    [
        'name' => 'Default configuration - allowed attribute (id)',
        'input' => [
            'html' => '<p id="testID">Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
        ],
        'expected' => '<p id="testID">Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
    ],
    [
        'name' => 'Stripping code based on default configuration',
        'input' => [
            'html' => '<p id="testID" data-test="testData" onmousedown="this.style.color=\'#FF0000\';" onmouseup="this.style.color=\'#000000\';">Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
        ],
        'expected' => '<p id="testID">Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
    ],
    [
        'name' => 'Default configuration - remove <script> tags',
        'input' => [
            'html' => '<p id="testID" onmousedown="this.style.color=\'#FF0000\';" onmouseup="this.style.color=\'#000000\';">Lorem ipsum dolor sit amet, consectetur adipiscing elit.<script>console.log(true);</script></p>',
        ],
        'expected' => '<p id="testID">Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
    ],
    [
        'name' => 'Default configuration - remove onmouse* attributes',
        'input' => [
            'html' => '<p onmousedown="this.style.color=\'#FF0000\';" onmouseup="this.style.color=\'#000000\';">Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
        ],
        'expected' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
    ],
    [
        'name' => 'Allow extra attributes',
        'input' => [
            'html' => '<p id="testID" data-test="testData" onmousedown="this.style.color=\'#FF0000\';" onmouseup="this.style.color=\'#000000\';">Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
            'attributes' => ['data-test'],
        ],
        'expected' => '<p id="testID" data-test="testData">Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
    ],
    [
        'name' => 'Allow extra tags',
        'input' => [
            'html' => '<p id="testID" onmousedown="this.style.color=\'#FF0000\';">Lorem ipsum dolor sit amet, consectetur adipiscing elit.<script>console.log(true);</script></p>',
            'tags' => ['script'],
        ],
        'expected' => '<p id="testID">Lorem ipsum dolor sit amet, consectetur adipiscing elit.<script>console.log(true);</script></p>',
    ],
    [
        'name' => 'Poorly formated tags (e.g. missing closing tags)',
        'input' => [
            'html' => '<p onmousedown="this.style.color=\'#FF0000\';">Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
        ],
        'expected' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
    ],
    [
        'name' => 'Non standard tags',
        'input' => [
            'html' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit,<invalid tag>sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.</p>'
        ],
        'expected' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit,</p>'
    ],
    [
        'name' => 'HTML with new line feeds at the end',
        'input' => [
            'html' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>' . PHP_EOL,
        ],
        'expected' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
    ],
    [
        'name' => 'HTML which has been through htmlspecialchars()',
        'input' => [
            'html' => escape('<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. <script>alert("XSS test")</script></p>'),
        ],
        'expected' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. </p>',
    ],
    [
        'name' => 'Multi byte characters',
        'input' => [
            'html' => 'Multi byte characters: © è å 泉',
        ],
        'expected' => 'Multi byte characters: © è å 泉',
    ],
    [
        'name' => 'Dangerous URI scheme in attribute value should be removed',
        'input' => [
            'html' => '<a onmouseover="javascript:alert(\'XSS\')">Test</a>',
            'tags' => ['a'],
            'attributes' => ['onmouseover'],
        ],
        'expected' => '<a>Test</a>',
    ],
    [
        'name' => 'Dangerous URI scheme in the "style" attribute value should be removed',
        'input' => [
            'html' => '<div style="background-image: url(javascript:alert(\'XSS\'));">Test</div>',
        ],
        'expected' => '<div>Test</div>',
    ],
    [
        'name' => 'Dangerous URI scheme amongst multiple "style" attribute values drops the style',
        'input' => [
            'html' => '<div style="color: red; background-image: url(javascript:alert(\'XSS\'));">Test</div>',
        ],
        'expected' => '<div>Test</div>',
    ],
    [
        'name' => 'Allowed URI scheme in the "style" attribute value will be left alone (1)',
        'input' => [
            'html' => '<div style="color: red;background-image: url(http://test.localhost/pages);">Test</div>',
        ],
        'expected' => '<div style="color: red;background-image: url(http://test.localhost/pages);">Test</div>',
    ],
    [
        'name' => 'Allowed URI scheme in the "style" attribute value will be left alone (2)',
        'input' => [
            'html' => '<div style="color: red;background-image: url(\'http://test.localhost/pages\');">Test</div>',
        ],
        'expected' => '<div style="color: red;background-image: url(\'http://test.localhost/pages\');">Test</div>',
    ],
    [
        'name' => 'Allowed URI scheme in the "style" attribute value will be left alone (3)',
        'input' => [
            'html' => '<div style=\'color: red;background-image: url("http://test.localhost/pages");\'>Test</div>',
        ],
        'expected' => '<div style=\'color: red;background-image: url("http://test.localhost/pages");\'>Test</div>',
    ],
    [
        'name' => 'Allowed URI scheme, i.e http(s), should be left alone',
        'input' => [
            'html' => sprintf('<a href="%s">Test</a>', generateURL($baseurl, ['foo' => 'bar']) . '#fragment'),
            'tags' => ['a'],
            'attributes' => ['href'],
        ],
        'expected' => sprintf('<a href="%s">Test</a>', generateURL($baseurl, ['foo' => 'bar']) . '#fragment'),
    ],
    [
        'name' => 'Dangerous URI scheme (embedded encoded tab) in attribute value should be removed',
        'input' => [
            'html' => '<a onmouseover="jav&#x09;ascript:alert(\'XSS\')">Test</a>',
            'tags' => ['a'],
            'attributes' => ['onmouseover'],
        ],
        'expected' => '<a>Test</a>',
    ],
];

foreach ($use_cases as $use_case) {
    $html = $use_case['input']['html'];
    $tags = $use_case['input']['tags'] ?? [];
    $attributes = $use_case['input']['attributes'] ?? [];

    $processed = strip_tags_and_attributes($html, $tags, $attributes);
    if (isset($use_case['post-process']) && is_callable($use_case['post-process'])) {
        $processed = $use_case['post-process']($processed);
    }

    if ($processed !== $use_case['expected']) {
        echo "Use case: {$use_case['name']} - ";
        test_log("- expected >>>{$use_case['expected']}<<<");
        test_log("- result   >>>{$processed}<<<");
        return false;
    }
}

// Tear down
unset($use_cases, $html, $tags, $attributes, $processed);

return true;
