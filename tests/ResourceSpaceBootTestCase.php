<?php

namespace Montala\ResourceSpace\Tests;

use Montala\ResourceSpace\Tests\Support\ResourceSpaceTestEnvironment;
use PHPUnit\Framework\TestCase;

abstract class ResourceSpaceBootTestCase extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        ResourceSpaceTestEnvironment::start();
    }

    protected function setUp(): void
    {
        parent::setUp();

        ResourceSpaceTestEnvironment::resetRuntimeState();
    }
}
