<?php

declare(strict_types=1);

$saved_request = [];
beforeEach(function () use (&$saved_request): void {
    $saved_request = [$_POST, $_GET, $_COOKIE];
    $_POST = $_GET = $_COOKIE = [];
});

afterEach(function () use (&$saved_request): void {
    [$_POST, $_GET, $_COOKIE] = $saved_request;
});

it('reads request parameters in POST, GET, cookie order', function (
    array $values,
    string $default,
    string $expected
): void {
    if (isset($values['cookie'])) {
        $_COOKIE['test_param'] = $values['cookie'];
    }
    if (isset($values['get'])) {
        $_GET['test_param'] = $values['get'];
    }
    if (isset($values['post'])) {
        $_POST['test_param'] = $values['post'];
    }

    expect(getval('test_param', $default))->toBe($expected);
})->with([
    'default when the parameter is absent' => [[], 'default value', 'default value'],
    'cookie when GET and POST are absent' => [['cookie' => 'fromCOOKIE'], '', 'fromCOOKIE'],
    'GET takes priority over a cookie' => [['cookie' => 'fromCOOKIE', 'get' => 'fromGET'], '', 'fromGET'],
    'POST takes priority over GET and cookies' => [
        ['cookie' => 'fromCOOKIE', 'get' => 'fromGET', 'post' => 'fromPOST'],
        '',
        'fromPOST',
    ],
]);

it('validates a numeric request parameter', function (string $input, int|string $default, string $expected): void {
    $_GET['test_param'] = $input;

    expect(getval('test_param', $default, true))->toBe($expected);
})->with([
    'numeric input is returned' => ['1234', 1000, '1234'],
    'non-numeric input returns the default' => ['badNumber', 'not numeric', 'not numeric'],
]);

it('accepts a request parameter that passes a custom type check', function (array $input, callable $validator): void {
    $_GET['test_param'] = $input;

    expect(getval('test_param', [], false, $validator))->toBe($input);
})->with([
    'built-in array validator' => [[2345], 'is_array'],
    'custom integer list validator' => [
        [1, 2, 3, 4],
        static fn ($value): bool => is_array($value) && array_filter($value, 'is_int') === $value,
    ],
]);
