<?php

declare(strict_types=1);

$html_find_libxml_errors = false;
beforeEach(function () use (&$html_find_libxml_errors): void {
    $html_find_libxml_errors = libxml_use_internal_errors();
});

afterEach(function () use (&$html_find_libxml_errors): void {
    libxml_clear_errors();
    libxml_use_internal_errors($html_find_libxml_errors);
});

it('replaces matching text in HTML', function (array $input, string $expected): void {
    expect(html_find_and_replace($input['find'], $input['replace'], $input['html']))->toBe($expected);
})->with([
    'Empty string' => [
        [
            'find'      => '',
            'replace'   => '',
            'html'      => '',
        ],
        '',
    ],
    'Text (ie. no HTML present)' => [
        [
            'find'      => '',
            'replace'   => '',
            'html'      => 'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
        ],
        'Lorem ipsum dolor sit amet, consectetur adipiscing elit.',
    ],
    'HTML - Simple find/replace' => [
        [
            'find'      => 'text',
            'replace'   => 'HTML',
            'html'      => '<p>This is some text to test finding and replacing.</p>',
        ],
        '<p>This is some HTML to test finding and replacing.</p>',
    ],
    'HTML - Simple find/replace - malformed HTML' => [
        [
            'find'      => 'text',
            'replace'   => 'HTML',
            'html'      => '<p>This is some text to test finding and replacing.',
        ],
        '<p>This is some HTML to test finding and replacing.</p>',
    ],
    'HTML - Multiple find/replace' => [
        [
            'find'      => 'text',
            'replace'   => 'HTML',
            'html'      => '<p>This is some text to test finding and replacing. It has multiple instances of the word '
                . 'text to be replaced.</p>',
        ],
        '<p>This is some HTML to test finding and replacing. It has multiple instances of the word HTML to be '
            . 'replaced.</p>',
    ],
    'HTML - Simple replace' => [
        [
            'find'      => 'sample ',
            'replace'   => '',
            'html'      => '<p>This is some sample text to test replacing.</p>',
        ],
        '<p>This is some text to test replacing.</p>',
    ],
    'HTML - Multiple replace' => [
        [
            'find'      => 'sample ',
            'replace'   => '',
            'html'      => '<p>This is some sample text to test replacing. The word sample in sample text should be '
                . 'replaced.</p>',
        ],
        '<p>This is some text to test replacing. The word in text should be replaced.</p>',
    ],
    'HTML - Multiple find/replace with nested tags' => [
        [
            'find'      => 'text',
            'replace'   => 'HTML',
            'html'      => '<p>This is <strong>some text</strong> to test finding and replacing. <em>It has multiple '
                . 'instances of the word text to be replaced.</em></p>',
        ],
        '<p>This is <strong>some HTML</strong> to test finding and replacing. <em>It has multiple instances of the '
            . 'word HTML to be replaced.</em></p>',
    ],
    'Replaces text matching a tag name while preserving the tag' => [
        [
            'find' => 'strong',
            'replace' => 'weak',
            'html' => '<p>This is <strong>some strong text</strong> to test finding and replacing. '
                . '<em>It has multiple instances of the word strong to be replaced.</em></p>',
        ],
        '<p>This is <strong>some weak text</strong> to test finding and replacing. '
            . '<em>It has multiple instances of the word weak to be replaced.</em></p>',
    ],
    'Multi byte characters' => [
        [
            'find'      => '©',
            'replace'   => 'è',
            'html'      => '<p>泉 This is <strong>some multi byte charåcters ©</strong> to test finding and replacing. '
                . '<em>© It has multiple instances of the word text to be replaced.</em></p>',
        ],
        '<p>泉 This is <strong>some multi byte charåcters è</strong> to test finding and replacing. <em>è It has '
            . 'multiple instances of the word text to be replaced.</em></p>',
    ],
]);
