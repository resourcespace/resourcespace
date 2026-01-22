<?php

declare(strict_types=1);

command_line_only();

// --- Set up
$image_data = fn(int $w, int $h): array => ['thumb_width'  => $w, 'thumb_height' => $h];

$use_cases = [
    [
        'name' => 'Thumbs for landscape image',
        'input' => [
            'imagedata' => $image_data(400, 200),
            'img_url' => '',
            'display' => 'thumbs',
        ],
        'expected' => [200, 100, 50],
    ],
    [
        'name' => 'No height (divison by zero)',
        'input' => [
            'imagedata' => $image_data(400, 0),
            'img_url' => '',
            'display' => 'thumbs',
        ],
        'expected' => [200, 200, 'auto'],
    ],
];
foreach ($use_cases as $uc) {
    $result = calculate_image_display($uc['input']['imagedata'], $uc['input']['img_url'], $uc['input']['display']);
    if ($uc['expected'] != $result) {
        echo "Use case: {$uc['name']} - ";
        return false;
    }
}

// Tear down
unset($use_cases, $result, $image_data);

return true;
