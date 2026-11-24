<?php

declare(strict_types=1);

$saved_date_lang = [];
beforeEach(function () use (&$saved_date_lang): void {
    $saved_date_lang = array_intersect_key($GLOBALS, ['lang' => true]);
    $GLOBALS['lang'] = [];
    $lang =& $GLOBALS['lang'];
    require dirname(__DIR__, 2) . '/languages/en.php';
});

afterEach(function () use (&$saved_date_lang): void {
    unset($GLOBALS['lang']);
    foreach ($saved_date_lang as $name => $value) {
        $GLOBALS[$name] = $value;
    }
});

it('validates the date format', function (mixed $input, bool $valid): void {
    expect(check_date_format($input) === '')->toBe($valid);
})->with([
    'If passed empty string should return NOT empty' => ["", false],
    'Legitimate date should return empty' => ["1990-04-05", true],
    'Illegitimate DAY should return NOT empty' => ["1990-04-50", false],
    'Illegitimate MONTH should return NOT empty' => ["1990-40-05", false],
    'Check all 0s should return NOT empty' => ["0000-00-00", false],
    'Legitimate LEAP YEAR should return empty' => ["2020-02-29", true],
    'Illegitimate LEAP YEAR should return NOT empty' => ["2019-02-29", false],
    'Junk in DAY should return NOT empty' => ["1990-04-oe", false],
    'Junk in MONTH should return NOT empty' => ["1990-ad-05", false],
    'Junk in YEAR should return NOT empty' => ["ioaj-04-05", false],
    'valid year and month without a day' => ["1990-04", true],
    'invalid month in a partial date' => ["1990-40", false],
    'valid year without a month or day' => ["1990", true],
    'Legitimate DATE TIME including SECONDS should return empty' => ["1990-04-05 12:00:00", true],
    'Legitimate DATE TIME without SECONDS should return empty' => ["1990-04-05 12:00", true],
    'Illegitimate MINUTES should return NOT empty' => ["1990-04-05 12:60", false],
    'Illegitimate HOURS should return NOT empty' => ["1990-04-05 25:00", false],
    'If passed NULL should return NOT empty' => [null, false],
    'If passed string should return NOT empty' => ["Hello", false],
    'If passed TRUE should return NOT empty' => [true, false],
    'If passed FALSE should return NOT empty' => [false, false],
    'If passed INT should return NOT empty' => [1234567890, false],
    'HOWEVER if passed 4 digit INT it will return empty (assumes its a YEAR)' => [1234, true],
]);
