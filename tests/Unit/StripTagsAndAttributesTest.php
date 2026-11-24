<?php

declare(strict_types=1);

$saved_html_url_config = [];
$strip_libxml_errors = false;
beforeEach(function () use (&$saved_html_url_config, &$strip_libxml_errors): void {
    $saved_html_url_config = array_intersect_key($GLOBALS, ['baseurl' => true, 'baseurl_short' => true]);
    $GLOBALS['baseurl'] = 'https://example.test';
    $GLOBALS['baseurl_short'] = '/';
    $strip_libxml_errors = libxml_use_internal_errors();
});

afterEach(function () use (&$saved_html_url_config, &$strip_libxml_errors): void {
    unset($GLOBALS['baseurl'], $GLOBALS['baseurl_short']);
    foreach ($saved_html_url_config as $name => $value) {
        $GLOBALS[$name] = $value;
    }
    libxml_clear_errors();
    libxml_use_internal_errors($strip_libxml_errors);
});

it('keeps permitted HTML and strips disallowed tags and attributes', function (
    array $input,
    string $expected,
    ?callable $normalise = null
): void {
    $html = strip_tags_and_attributes($input['html'], $input['tags'] ?? [], $input['attributes'] ?? []);

    expect($normalise === null ? $html : $normalise($html))->toBe($expected);
})->with([
    'Empty string' => [
        [
            'html' => '',
        ],
        '',
    ],
    'Text (ie. no HTML present)' => [
        [
            'html' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
        ],
        'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
    ],
    'Text (ie. no HTML present) with new line feeds at the end' => [
        [
            'html' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.' . PHP_EOL,
        ],
        'Lorem ipsum dolor sit amet, consectetur adipiscing elit.' . PHP_EOL,
    ],
    'Plain text with multiple trailing newlines' => [
        [
            'html' => "Text\n\n",
        ],
        "Text\n\n",
    ],
    'Plain text with an entity and a trailing newline' => [
        [
            'html' => "Fish &amp; chips\n",
        ],
        "Fish & chips\n",
    ],
    'Text with a tag allowed by default requires post processing' => [
        [
            'html' => 'Lorem ipsum <strong>dolor</strong> sit amet, consectetur adipiscing elit.',
        ],
        'Lorem ipsum <strong>dolor</strong> sit amet, consectetur adipiscing elit.',
        'strip_paragraph_tags',
    ],
    'Text with a <script> (not allowed by default) tag in' => [
        [
            'html' => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit. <script>alert("XSS test")</script>',
        ],
        // libxml < 2.14 adds a paragraph around this fragment; newer versions do not.
        'Lorem ipsum dolor sit amet, consectetur adipiscing elit. ',
        'strip_paragraph_tags',
    ],
    'Tag (header) not allowed by default' => [
        [
            'html' => '<header>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</header>',
        ],
        '',
    ],
    'Default configuration - allowed tag (p)' => [
        [
            'html' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>'
        ],
        '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
    ],
    'Default configuration - allowed attribute (id)' => [
        [
            'html' => '<p id="testID">Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
        ],
        '<p id="testID">Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
    ],
    'Stripping code based on default configuration' => [
        [
            'html' => '<p id="testID" data-test="testData" onmousedown="this.style.color=\'#FF0000\';" '
                . 'onmouseup="this.style.color=\'#000000\';">Lorem ipsum dolor sit amet, consectetur adipiscing '
                . 'elit.</p>',
        ],
        '<p id="testID">Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
    ],
    'Default configuration - remove <script> tags' => [
        [
            'html' => '<p id="testID" onmousedown="this.style.color=\'#FF0000\';" '
                . 'onmouseup="this.style.color=\'#000000\';">Lorem ipsum dolor sit amet, consectetur adipiscing '
                . 'elit.<script>console.log(true);</script></p>',
        ],
        '<p id="testID">Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
    ],
    'Default configuration - remove onmouse* attributes' => [
        [
            'html' => '<p onmousedown="this.style.color=\'#FF0000\';" onmouseup="this.style.color=\'#000000\';">Lorem '
                . 'ipsum dolor sit amet, consectetur adipiscing elit.</p>',
        ],
        '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
    ],
    'Allow extra attributes' => [
        [
            'html' => '<p id="testID" data-test="testData" onmousedown="this.style.color=\'#FF0000\';" '
                . 'onmouseup="this.style.color=\'#000000\';">Lorem ipsum dolor sit amet, consectetur adipiscing '
                . 'elit.</p>',
            'attributes' => ['data-test'],
        ],
        '<p id="testID" data-test="testData">Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
    ],
    'Allow extra tags' => [
        [
            'html' => '<p id="testID" onmousedown="this.style.color=\'#FF0000\';">Lorem ipsum dolor sit amet, '
                . 'consectetur adipiscing elit.<script>console.log(true);</script></p>',
            'tags' => ['script'],
        ],
        '<p id="testID">Lorem ipsum dolor sit amet, consectetur adipiscing '
            . 'elit.<script>console.log(true);</script></p>',
    ],
    'Poorly formated tags (e.g. missing closing tags)' => [
        [
            'html' => '<p onmousedown="this.style.color=\'#FF0000\';">Lorem ipsum dolor sit amet, consectetur '
                . 'adipiscing elit.',
        ],
        '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
    ],
    'Non standard tags' => [
        [
            'html' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit,<invalid tag>sed do eiusmod tempor '
                . 'incididunt ut labore et dolore magna aliqua.</p>'
        ],
        '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit,</p>'
    ],
    'HTML with new line feeds at the end' => [
        [
            'html' => '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>' . PHP_EOL,
        ],
        '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit.</p>',
    ],
    'HTML which has been through htmlspecialchars()' => [
        [
            'html' => escape('<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. <script>alert("XSS '
                . 'test")</script></p>'),
        ],
        '<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. </p>',
    ],
    'Multi byte characters' => [
        [
            'html' => 'Multi byte characters: © è å 泉',
        ],
        'Multi byte characters: © è å 泉',
    ],
    'Dangerous URI scheme in attribute value should be removed' => [
        [
            'html' => '<a onmouseover="javascript:alert(\'XSS\')">Test</a>',
            'tags' => ['a'],
            'attributes' => ['onmouseover'],
        ],
        '<a>Test</a>',
    ],
    'Dangerous URI scheme in the "style" attribute value should be removed' => [
        [
            'html' => '<div style="background-image: url(javascript:alert(\'XSS\'));">Test</div>',
        ],
        '<div>Test</div>',
    ],
    'Dangerous URI scheme amongst multiple "style" attribute values drops the style' => [
        [
            'html' => '<div style="color: red; background-image: url(javascript:alert(\'XSS\'));">Test</div>',
        ],
        '<div>Test</div>',
    ],
    'Allowed URI scheme in the "style" attribute value will be left alone (1)' => [
        [
            'html' => '<div style="color: red;background-image: url(http://test.localhost/pages);">Test</div>',
        ],
        '<div style="color: red;background-image: url(http://test.localhost/pages);">Test</div>',
    ],
    'Allowed URI scheme in the "style" attribute value will be left alone (2)' => [
        [
            'html' => '<div style="color: red;background-image: url(\'http://test.localhost/pages\');">Test</div>',
        ],
        '<div style="color: red;background-image: url(\'http://test.localhost/pages\');">Test</div>',
    ],
    'Allowed URI scheme in the "style" attribute value will be left alone (3)' => [
        [
            'html' => '<div style=\'color: red;background-image: url("http://test.localhost/pages");\'>Test</div>',
        ],
        '<html><body><div style="color: red;background-image: '
            . 'url(&quot;http://test.localhost/pages&quot;);">Test</div></body></html>',
        // Compare the complete DOM independently of libxml's HTML attribute quoting.
        static function (string $html): string {
            $document = new DOMDocument();
            $document->loadHTML($html);
            return $document->C14N();
        },
    ],
    'Allowed URI scheme, i.e http(s), should be left alone' => [
        [
            'html' => '<a href="https://example.test/?foo=bar#fragment">Test</a>',
            'tags' => ['a'],
            'attributes' => ['href'],
        ],
        '<a href="https://example.test/?foo=bar#fragment">Test</a>',
    ],
    'Dangerous URI scheme (embedded encoded tab) in attribute value should be removed' => [
        [
            'html' => '<a onmouseover="jav&#x09;ascript:alert(\'XSS\')">Test</a>',
            'tags' => ['a'],
            'attributes' => ['onmouseover'],
        ],
        '<a>Test</a>',
    ],
]);
