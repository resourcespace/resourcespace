<?php

namespace Montala\ResourceSpace\Tests\Support;

use RuntimeException;

final class ResourceSpaceTestEnvironment
{
    public const DATABASE_NAME = 'rs_test_db';

    private static bool $started = false;

    /**
     * Start the ResourceSpace test environment and ensure it's booting the normal ResourceSpace environment which is
     * carried out by the resourcespace-bootstrap.php as it needs to load the global scope variables so it can't be done
     * here!
     */
    public static function start(): void
    {
        if (self::$started) {
            return;
        }

        self::$started = true;
        self::guardTestEnvironment();
        self::setupDefaultTestUser();
    }

    /**
     * Clean up the test environment after testing 
     */
    public static function stop(): void
    {
        self::guardDatabaseName($GLOBALS['mysql_db']);
        self::guardFilestore($GLOBALS['storagedir']);

        ps_query(sprintf('DROP DATABASE `%s`', $GLOBALS['mysql_db']));
        rcRmdir($GLOBALS['storagedir']);
    }

    public static function resetRuntimeState(): void
    {
        $_GET = [];
        $_POST = [];
        $_COOKIE = [];
        $_REQUEST = [];
        $_FILES = [];
        $_SESSION = [];
    }

    public static function create_new_db(string $db_name): void
    {
        self::guardDatabaseName($db_name);

        global $db;

        echo "\nCreating database {$db_name}";

        mysqli_query($db['read_write'], "DROP DATABASE IF EXISTS `$db_name`");
        mysqli_query($db['read_write'], "CREATE DATABASE `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        mysqli_query($db['read_write'], "CREATE TABLE `{$db_name}`.`sysvars`(`name` VARCHAR(50) NOT NULL, `value` TEXT NULL, PRIMARY KEY (`name`))");
        mysqli_query($db['read_write'], "INSERT INTO `{$db_name}`.`sysvars`(`name`,`value`) VALUE ('upgrade_system_level', 999)");
    }

    public static function filestoreSetup(string $path, bool $guard_filestore): void
    {
        if ($guard_filestore) {
            self::guardFilestore($path);
        }

        if (file_exists($path)) {
            rcRmdir($path);
        }

        mkdir($path, recursive: true);
    }

    public static function setUpDefaultTestUser(): void
    {
        self::guardTestEnvironment();

        $userdata = get_user(1);
        setup_user($userdata);
    }
    
    private static function guardTestEnvironment(): void
    {
        if (!defined('RS_TEST_MODE')) {
            trigger_error(
                'ResourceSpace has not bootstrapped for test purposes! Run `composer test:integration` or `composer test:browser`.'
            );
        }
    }

    /**
     * Require test mode and reject a database name other than the dedicated test database.
     *
     * @param string $name Database name to validate
     *
     * @throws RuntimeException If the name is not the test database name.
     */
    public static function guardDatabaseName(string $name): void
    {
        self::guardTestEnvironment();

        if (self::DATABASE_NAME !== $name) {
            throw new RuntimeException(sprintf("Refusing to use a non-test database name: {$name}"));
        }
    }

    private static function guardFilestore(string $path): void
    {
        self::guardTestEnvironment();

        if (!str_ends_with($path, 'rs_test')) {
            throw new RuntimeException(sprintf("Refusing to use a non-test filestore location: {$path}"));
        }
    }
}
