<?php

declare(strict_types=1);

use Montala\ResourceSpace\CommandPlaceholderArg;

$saved_command_banned_extensions = [];
beforeEach(function () use (&$saved_command_banned_extensions): void {
    $saved_command_banned_extensions = array_intersect_key($GLOBALS, ['banned_extensions' => true]);
    $GLOBALS['banned_extensions'] = ['php'];
});

afterEach(function () use (&$saved_command_banned_extensions): void {
    unset($GLOBALS['banned_extensions']);
    foreach ($saved_command_banned_extensions as $name => $value) {
        $GLOBALS[$name] = $value;
    }
});

it('replaces placeholders with shell-escaped arguments', function (): void {
    $arguments = [
        'dateFormat' => new CommandPlaceholderArg(
            '%Y-%m-%d %H:%M:%S',
            static fn (string $value): bool => preg_match('/[%Y\-mdH:MS[:space:]]/', $value) === 1
        ),
        'output_file' => new CommandPlaceholderArg('/path/to/file.jpg', 'is_safe_basename'),
    ];

    expect(escape_command_args("'/usr/bin/madeupcmd' -d dateFormat -o output_file", $arguments))
        ->toBe("'/usr/bin/madeupcmd' -d '%Y-%m-%d %H:%M:%S' -o '/path/to/file.jpg'");
});

it('rejects shell metacharacters in an argument with the default validator', function (): void {
    expect(fn () => escape_command_args('ls --ignore=%arg /tmp', ['%arg' => "sth' -l"]))
        ->toThrow(Exception::class, 'Invalid placeholder argument value');
});
