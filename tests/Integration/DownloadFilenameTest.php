<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\IntegrationFixtures;

$fixtures = null;
beforeEach(function () use (&$fixtures): void {
    $fixtures = new IntegrationFixtures([
        'download_filename_format', 'userpermissions', 'plugins', 'hook_cache',
        'resource_type_config_override_last', 'resource_type_config_override_snapshot', 'execution_lockout',
    ]);
});
afterEach(function () use (&$fixtures): void {
    $fixtures?->cleanUp();
});

it('formats filenames from the configured download template', function (
    string $format,
    string $size,
    bool $is_alternative,
    string $expected,
) use (&$fixtures): void {
    $resource = $fixtures->resource(1, 0);
    $GLOBALS['download_filename_format'] = $format;
    $errors = [];
    expect(update_field($resource, $GLOBALS['filename_field'], 'original.JPG', $errors))->toBeTrue();
    $alternative = $is_alternative ? add_alternative_file($resource, 'Alternate name') : 0;
    expect($alternative)->toBeInt();
    $extension = $is_alternative ? 'png' : 'jpg';

    expect(get_download_filename($resource, $size, $alternative, $extension))->toBe(strtr($expected, [
        '{ref}' => (string) $resource,
        '{alt}' => (string) $alternative,
    ]));
})->with([
    'literal text' => ['ABC%resource-approved.%extension', '', false, 'ABC{ref}-approved.JPG'],
    'resource moved' => ['%filename-%resource.%extension', '', false, 'original-{ref}.JPG'],
    'resource omitted' => ['%filename.%extension', '', false, 'original.JPG'],
    'extension appended when omitted' => ['RS%resource', '', false, 'RS{ref}.jpg'],
    'extension moved' => ['%extension-%resource', '', false, 'JPG-{ref}.jpg'],
    'size omitted' => ['RS%resource.%extension', 'scr', false, 'RS{ref}.JPG'],
    'original has no size or alternative' => ['RS%resource%size%alternative.%extension', '', false, 'RS{ref}.JPG'],
    'screen size moved' => ['A%size-RS%resource.%extension', 'scr', false, 'A_scr-RS{ref}.JPG'],
    'alternative id and display name' => ['%filename%alternative.%extension', '', true, 'Alternate name_{alt}.png'],
    'invalid filename characters' => ["RS%resource:_x\r\ny\rz\nf.%extension", '', false, 'RS{ref}__x_y_z_f.JPG'],
    'HTML tags removed' => ['RS%resource_<b>plain</b>.%extension', '', false, 'RS{ref}_plain.JPG'],
]);

it('expands complete field placeholders and omits denied metadata', function () use (&$fixtures): void {
    $resource = $fixtures->resource(1, 0);
    $first = $fixtures->field('Filename first value');
    $second = $fixtures->field('Filename second value');
    $errors = [];
    expect(update_field($resource, $first, '~en:Bird~fr:Oiseau', $errors))->toBeTrue()
        ->and(update_field($resource, $second, 'nest', $errors))->toBeTrue();
    $GLOBALS['download_filename_format'] = "%field{$first}-%field{$second}.%extension";
    expect(get_download_filename($resource, '', 0, 'jpg'))->toBe('Bird-nest.jpg');

    $GLOBALS['userpermissions'][] = "f-{$first}";
    expect(get_download_filename($resource, '', 0, 'jpg'))->toBe('-nest.jpg');

    $GLOBALS['download_filename_format'] = "%field{$second}";
    expect(update_field($resource, $second, str_repeat('X', 256), $errors))->toBeTrue();
    expect(get_download_filename($resource, '', 0, 'jpg'))->toBe(str_repeat('X', 251) . '.jpg');
});

it('applies a signed resource-type override to the filename', function () use (&$fixtures): void {
    $resource = $fixtures->resource(1, 0);
    $original = ps_value('SELECT config_options AS value FROM resource_type WHERE ref = ?', ['i', 1], '');
    $fixtures->onCleanup(static fn () => save_resource_type(1, ['config_options' => $original]));
    $GLOBALS['execution_lockout'] = false;
    $code = '$download_filename_format = "Type override.%extension";';
    expect(save_resource_type(1, ['config_options' => '//SIG' . sign_code($code) . "\n" . $code]))->toBeTrue();
    unset($GLOBALS['resource_type_config_override_last']);

    expect(get_download_filename($resource, '', 0, 'jpg'))->toBe('Type override.jpg');
});

/**
 * Return the filename provided by the synthetic test plugin hook.
 *
 * @return string Filename supplied by the hook.
 */
function HookPestDownloadFilenameAllDownloadfilenamealt(): string
{
    return 'Hook filename.jpg';
}

it('honours the download filename hook', function () use (&$fixtures): void {
    $resource = $fixtures->resource(1, 0);
    $GLOBALS['plugins'] = ['pestdownloadfilename'];
    unset($GLOBALS['hook_cache']);

    expect(get_download_filename($resource, '', 0, 'jpg'))->toBe('Hook filename.jpg');
});
