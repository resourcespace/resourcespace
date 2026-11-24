<?php

declare(strict_types=1);

it('inserts word breaks in HTML text', function (array $input, string $expected): void {
    expect(html_break_long_words($input['inputString'], $input['length']))->toBe($expected);
})->with([
    'Plain string, no long words' => [
        [
            'inputString' => 'This is a plain string with no long words in it. It would wrap normally.',
            'length'      => 20
        ],
        'This is a plain string with no long words in it. It would wrap normally.'
    ],
    'String with HTML, no long words' => [
        [
            'inputString' => '<p>This is a <strong>HTML</strong> containing string with no long words in it. It would '
                . 'wrap normally.</p>',
            'length'      => 20
        ],
        '<p>This is a <strong>HTML</strong> containing string with no long words in it. It would wrap normally.</p>'
    ],
    'Plain string, already over multiple lines' => [
        [
            'inputString' => 'This is a plain string with no long words in it.' . PHP_EOL
                . 'It would wrap normally across multiple lines.',
            'length'      => 20
        ],
        'This is a plain string with no long words in it.' . PHP_EOL . 'It would wrap normally across multiple lines.'
    ],
    'String with HTML, already over multiple lines' => [
        [
            'inputString' => '<p>This is a <strong>HTML</strong> containing string with no long words in it. <br>It '
                . 'would wrap normally across multiple lines.</p>',
            'length'      => 20
        ],
        '<p>This is a <strong>HTML</strong> containing string with no long words in it. <br>It would wrap normally '
            . 'across multiple lines.</p>'
    ],
    'Plain string, long word, 20 length' => [
        [
            'inputString' => 'This is a plain string with averylongwordtotestbreakingintochunks. It would wrap '
                . 'normally apart from that word.',
            'length'      => 20
        ],
        'This is a plain string with averylongwordtotestb<br>reakingintochunks. It would wrap normally apart from '
            . 'that word.'
    ],
    'String with HTML, long word, 20 length' => [
        [
            'inputString' => '<p>This is a <strong>HTML</strong> containing string with '
                . 'averylongwordtotestbreakingintochunks. It would wrap normally apart from that word.</p>',
            'length'      => 20
        ],
        '<p>This is a <strong>HTML</strong> containing string with averylongwordtotestb<br>reakingintochunks. It '
            . 'would wrap normally apart from that word.</p>'
    ],
    'Plain string, multiple long words, 20 length' => [
        [
            'inputString' => 'This is a plain string with averylongwordtotestbreakingintochunks. It would wrap '
                . 'normally apart from that word and thisotheralsostrangelylongword.',
            'length'      => 20
        ],
        'This is a plain string with averylongwordtotestb<br>reakingintochunks. It would wrap normally apart from '
            . 'that word and thisotheralsostrange<br>lylongword.'
    ],
    'String with HTML, multiple long words, 20 length' => [
        [
            'inputString' => '<p>This is a <strong>HTML</strong> containing string with '
                . 'averylongwordtotestbreakingintochunks. It would wrap normally apart from that word and '
                . 'thisotheralsostrangelylongword.</p>',
            'length'      => 20
        ],
        '<p>This is a <strong>HTML</strong> containing string with averylongwordtotestb<br>reakingintochunks. It '
            . 'would wrap normally apart from that word and thisotheralsostrange<br>lylongword.</p>'
    ],
    'Plain string, long word, 40 length' => [
        [
            'inputString' => 'This is a plain string with averylongwordtotestbreakingintochunks. The long word should '
                . 'not get broken up.',
            'length'      => 40
        ],
        'This is a plain string with averylongwordtotestbreakingintochunks. The long word should not get broken up.'
    ],
    'String with HTML, long word, 40 length' => [
        [
            'inputString' => '<p>This is a <strong>HTML</strong> containing string with '
                . 'averylongwordtotestbreakingintochunks. The long word should not get broken up.</p>',
            'length'      => 40
        ],
        '<p>This is a <strong>HTML</strong> containing string with averylongwordtotestbreakingintochunks. The long '
            . 'word should not get broken up.</p>'
    ],
    'String with HTML, with long URL' => [
        [
            'inputString' => '<p>This is a <strong>HTML</strong> containing string with '
                . 'averylongwordtotestbreakingintochunks. It also has a <a href="http://www.test.com">hyperlink</a> '
                . 'which should get left alone.</p>',
            'length'      => 20
        ],
        '<p>This is a <strong>HTML</strong> containing string with averylongwordtotestb<br>reakingintochunks. It also '
            . 'has a <a href="http://www.test.com">hyperlink</a> which should get left alone.</p>',
    ],
]);
