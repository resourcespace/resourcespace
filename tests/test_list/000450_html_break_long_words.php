<?php

command_line_only();

$use_cases = [
    [
        'name'     => 'Plain string, no long words',
        'input'    => [
            'inputString' => 'This is a plain string with no long words in it. It would wrap normally.',
            'length'      => 20
        ],
        'expected' => 'This is a plain string with no long words in it. It would wrap normally.'
    ],
    [
        'name'     => 'String with HTML, no long words',
        'input'    => [
            'inputString' => '<p>This is a <strong>HTML</strong> containing string with no long words in it. It would wrap normally.</p>',
            'length'      => 20
        ],
        'expected' => '<p>This is a <strong>HTML</strong> containing string with no long words in it. It would wrap normally.</p>'
    ],
    [
        'name'     => 'Plain string, already over multiple lines',
        'input'    => [
            'inputString' => 'This is a plain string with no long words in it.' . PHP_EOL . 'It would wrap normally across multiple lines.',
            'length'      => 20
        ],
        'expected' => 'This is a plain string with no long words in it.' . PHP_EOL . 'It would wrap normally across multiple lines.'
    ],
    [
        'name'     => 'String with HTML, already over multiple lines',
        'input'    => [
            'inputString' => '<p>This is a <strong>HTML</strong> containing string with no long words in it. <br>It would wrap normally across multiple lines.</p>',
            'length'      => 20
        ],
        'expected' => '<p>This is a <strong>HTML</strong> containing string with no long words in it. <br>It would wrap normally across multiple lines.</p>'
    ],
    [
        'name'     => 'Plain string, long word, 20 length',
        'input'    => [
            'inputString' => 'This is a plain string with averylongwordtotestbreakingintochunks. It would wrap normally apart from that word.',
            'length'      => 20
        ],
        'expected' => 'This is a plain string with averylongwordtotestb<br>reakingintochunks. It would wrap normally apart from that word.'
    ],
    [
        'name'     => 'String with HTML, long word, 20 length',
        'input'    => [
            'inputString' => '<p>This is a <strong>HTML</strong> containing string with averylongwordtotestbreakingintochunks. It would wrap normally apart from that word.</p>',
            'length'      => 20
        ],
        'expected' => '<p>This is a <strong>HTML</strong> containing string with averylongwordtotestb<br>reakingintochunks. It would wrap normally apart from that word.</p>'
    ],
    [
        'name'     => 'Plain string, multiple long words, 20 length',
        'input'    => [
            'inputString' => 'This is a plain string with averylongwordtotestbreakingintochunks. It would wrap normally apart from that word and thisotheralsostrangelylongword.',
            'length'      => 20
        ],
        'expected' => 'This is a plain string with averylongwordtotestb<br>reakingintochunks. It would wrap normally apart from that word and thisotheralsostrange<br>lylongword.'
    ],
    [
        'name'     => 'String with HTML, multiple long words, 20 length',
        'input'    => [
            'inputString' => '<p>This is a <strong>HTML</strong> containing string with averylongwordtotestbreakingintochunks. It would wrap normally apart from that word and thisotheralsostrangelylongword.</p>',
            'length'      => 20
        ],
        'expected' => '<p>This is a <strong>HTML</strong> containing string with averylongwordtotestb<br>reakingintochunks. It would wrap normally apart from that word and thisotheralsostrange<br>lylongword.</p>'
    ],
    [
        'name'  => 'Plain string, long word, 40 length',
        'input' => [
            'inputString' => 'This is a plain string with averylongwordtotestbreakingintochunks. The long word should not get broken up.',
            'length'      => 40
        ],
        'expected' => 'This is a plain string with averylongwordtotestbreakingintochunks. The long word should not get broken up.'
    ],
    [
        'name'     => 'String with HTML, long word, 40 length',
        'input'    => [
            'inputString' => '<p>This is a <strong>HTML</strong> containing string with averylongwordtotestbreakingintochunks. The long word should not get broken up.</p>',
            'length'      => 40
        ],
        'expected' => '<p>This is a <strong>HTML</strong> containing string with averylongwordtotestbreakingintochunks. The long word should not get broken up.</p>'
    ],
    [
        'name'     => 'String with HTML, with long URL',
        'input'    => [
            'inputString' => '<p>This is a <strong>HTML</strong> containing string with averylongwordtotestbreakingintochunks. It also has a <a href="http://www.test.com">hyperlink</a> which should get left alone.</p>',
            'length'      => 20
        ],
        'expected' => '<p>This is a <strong>HTML</strong> containing string with averylongwordtotestb<br>reakingintochunks. It also has a <a href="http://www.test.com">hyperlink</a> which should get left alone.</p>',
    ],
];

foreach ($use_cases as $use_case) {
    $output = html_break_long_words($use_case['input']['inputString'], $use_case['input']['length']);

    if ($use_case['expected'] !== $output) {
        echo "Use case: {$use_case['name']} - ";
        test_log("Output: " . $output);
        test_log("Expected = " . $use_case['expected']);
        test_log('--- ');
        return false;
    }
}

//Tests all pass so return true
return true;
