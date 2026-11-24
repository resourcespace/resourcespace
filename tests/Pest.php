<?php

declare(strict_types=1);

/**
 *  Configuration file
 *
 * {@see https://pestphp.com/docs/configuring-tests}
 */

use Montala\ResourceSpace\Tests\ResourceSpaceBootTestCase;
use Montala\ResourceSpace\Tests\ResourceSpaceBrowserTestCase;
use Montala\ResourceSpace\Tests\ResourceSpaceTestCase;

/*
 |--------------------------------------------------------------------------
 | Test Case
 |--------------------------------------------------------------------------
 |
 | The closure you provide to your test functions is always bound to a specific PHPUnit test
 | case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
 | need to change it using the "pest()" function to bind different classes or traits.
 |
 */

pest()
    ->extend(ResourceSpaceTestCase::class)
    ->group('unit')
    ->in('Unit', '../plugins/*/tests/Unit');

pest()
    ->extend(ResourceSpaceBootTestCase::class)
    ->group('integration')
    ->in('Integration', '../plugins/*/tests/Integration');

pest()
    ->extend(ResourceSpaceBrowserTestCase::class)
    ->group('browser')
    ->beforeEach(function (): void {
        /** @var ResourceSpaceBrowserTestCase $this */
        $name = method_exists($this, 'getPrintableTestCaseMethodName')
            ? $this->getPrintableTestCaseMethodName()
            : $this->nameWithDataSet();
        testLog('Running browser test: ' . $name);
    })
    ->in('Browser', '../plugins/*/tests/Browser');

/*
 |--------------------------------------------------------------------------
 | Browser (E2E) testing
 |--------------------------------------------------------------------------
 |
 | {@see https://pestphp.com/docs/browser-testing}
 |
 */
pest()
    ->browser()
    ->withHost('127.0.0.1');

/*
 |--------------------------------------------------------------------------
 | Expectations
 |--------------------------------------------------------------------------
 |
 | When you're writing tests, you often need to check that values meet certain conditions. The
 | "expect()" function gives you access to a set of "expectations" methods that you can use
 | to assert different things. Of course, you may extend the Expectation API at any time.
 |
 */

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
 |--------------------------------------------------------------------------
 | Functions
 |--------------------------------------------------------------------------
 |
 | While Pest is very powerful out-of-the-box, you may have some testing code specific to your
 | project that you don't want to repeat in every file. Here you can also expose helpers as
 | global functions to help you to reduce the number of lines of code in your test files.
 |
 */

include_once dirname(__DIR__) . '/include/test_functions.php';

function testLog(string $message): void
{
    fwrite(STDERR, sprintf("\n[%s] %s", date('H:i:s'), $message));
}

/**
 * Generate a random hexadecimal UID of the specified length for use in tests.
 *
 * Useful for avoiding collisions with existing test data when running tests without setup and teardown.
 *
 * @param int $length Length of the generated UID
 */
function generateRandomTestID(int $length): string
{
    if ($length % 2 !== 0) {
        throw new InvalidArgumentException('$length must be an even number.');
    }

    return bin2hex(random_bytes($length / 2));
}

/**
 * Get the full path of a test fixture.
 *
 * @param string $path Relative file path
 */
function testFixture(string $path, string $type = 'Files'): string
{
    $path = sprintf('%s/tests/Fixtures/%s/%s', RESOURCESPACE_BASE_PATH, $type, ltrim($path, '/'));

    expect($path)->toBeFile();

    return $path;
}
