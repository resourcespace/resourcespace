<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\ResourceSpaceTestEnvironment;

if (getenv('RS_TEST_MODE') !== '1') {
    throw new RuntimeException('ResourceSpace integration tests require `RS_TEST_MODE=1` environment variable');
}

// Boot the normal ResourceSpace environment (will load config.test.php)
$suppress_headers = true;
include_once dirname(__DIR__, 2) . '/include/boot.php';

ResourceSpaceTestEnvironment::stop();
