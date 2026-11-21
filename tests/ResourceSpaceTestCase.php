<?php

namespace Montala\ResourceSpace\Tests;

use PHPUnit\Framework\TestCase;

/**
 * ResourceSpace test case (aimed for unit tests) which doesn't boot the application.
 */
abstract class ResourceSpaceTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Minimal globals just to make unit tests work
        $GLOBALS['plugins'] = [];
        $GLOBALS['hook_cache'] = [];
        $GLOBALS['hook_cache_hits'] = 0;
    }
}
