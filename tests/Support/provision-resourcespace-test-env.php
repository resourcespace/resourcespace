<?php

declare(strict_types=1);

use Montala\ResourceSpace\Tests\Support\ResourceSpaceTestEnvironment;

define('RS_TEST_MODE', 1);

// Clear overrides before boot can load them up
include_once dirname(__DIR__, 2) . '/include/test_functions.php';
setTestConfiguration('');

// Boot the normal ResourceSpace environment (will load config.test.php)
$suppress_headers = true;
include_once dirname(__DIR__, 2) . '/include/boot.php';

ResourceSpaceTestEnvironment::create_new_db(ResourceSpaceTestEnvironment::DATABASE_NAME);
include_once dirname(__DIR__, 2) . '/include/config.test.php';

echo PHP_EOL . 'Creating default database tables...';
ob_flush();
check_db_structs(true);
echo "...done\n";

// Match installation setup: sign the defaults just imported into the fresh test database.
ResourceSpaceTestEnvironment::guardDatabaseName(ps_value('SELECT DATABASE() AS value', [], ''));
resign_all_code(false, false);

// Create and configure the test administrator
$u = new_user('admin');
ps_query('UPDATE `user` SET `password`=?', ['s', 'admin123']);

user_set_usergroup($u, 3);

ResourceSpaceTestEnvironment::setUpDefaultTestUser();

ResourceSpaceTestEnvironment::filestoreSetup($GLOBALS['storagedir'], true);
ResourceSpaceTestEnvironment::filestoreSetup(RESOURCESPACE_BASE_PATH . "/{$GLOBALS['homeanim_folder']}", false);
echo "Filestore is now at {$GLOBALS['storagedir']}\n";
ob_flush();
