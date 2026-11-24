<?php

declare(strict_types=1);

$image_data = fn (int $width, int $height): array => ['thumb_width' => $width, 'thumb_height' => $height];

it('calculates thumbnail dimensions and margin', function (array $input, array $expected): void {
    expect(calculate_image_display($input['imagedata'], $input['img_url'], $input['display']))->toEqual($expected);
})->with([
    'Thumbs for landscape image' => [
        [
            'imagedata' => $image_data(400, 200),
            'img_url' => '',
            'display' => 'thumbs',
        ],
        [202, 101, 50],
    ],
    'No height (divison by zero)' => [
        [
            'imagedata' => $image_data(400, 0),
            'img_url' => '',
            'display' => 'thumbs',
        ],
        [202, 202, 'auto'],
    ],
]);
