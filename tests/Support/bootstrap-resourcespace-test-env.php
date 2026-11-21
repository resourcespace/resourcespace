<?php

declare(strict_types=1);

if (getenv('RS_TEST_MODE') !== '1') {
    throw new RuntimeException('ResourceSpace test bootstrap requires the `RS_TEST_MODE=1` environment variable');
}

// Must happen before ResourceSpace loads vendor/autoload.php or Pest won't output the report
$_SERVER['COLLISION_PRINTER'] = 'DefaultPrinter';

// Boot the normal ResourceSpace environment (will load config.test.php)
$suppress_headers = true;
include_once dirname(__DIR__, 2) . '/include/boot.php';
