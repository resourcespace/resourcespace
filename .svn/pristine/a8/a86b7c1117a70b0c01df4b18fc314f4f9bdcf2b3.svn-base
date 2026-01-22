<?php

declare(strict_types=1);

namespace SimpleSAML\Module\sqlauth\Auth\Source;

use Exception;
use PDO;
use PDOException;
use SimpleSAML\Error;
use SimpleSAML\Logger;
use SimpleSAML\Module\core\Auth\UserPassBase;

use function array_key_exists;
use function array_keys;
use function explode;
use function implode;
use function in_array;
use function is_string;
use function preg_replace;
use function strtolower;
use function var_export;

/**
 * Simple SQL authentication source
 *
 * This class is an example authentication source which authenticates an user
 * against a SQL database.
 *
 * @package SimpleSAMLphp
 */

class SQL extends UserPassBase
{
    /**
     * The DSN we should connect to.
     * @var string
     */
    private string $dsn;

    /**
     * The username we should connect to the database with.
     * @var string
     */
    private string $username;

    /**
     * The password we should connect to the database with.
     * @var string
     */
    private string $password;

    /**
     * An optional regex that the username should match.
     * @var string
     */
    protected ?string $username_regex;

    /**
     * The options that we should connect to the database with.
     * @var array
     */
    private array $options = [];

    /**
     * The query or queries we should use to retrieve the attributes for the user.
     *
     * The username and password will be available as :username and :password.
     * @var array
     */
    protected array $query;

    /**
     * Constructor for this authentication source.
     *
     * @param array $info  Information about this authentication source.
     * @param array $config  Configuration.
     */
    public function __construct(array $info, array $config)
    {
        // Call the parent constructor first, as required by the interface
        parent::__construct($info, $config);

        // Make sure that all required parameters are present.
        foreach (['dsn', 'username', 'password'] as $param) {
            if (!array_key_exists($param, $config)) {
                throw new Exception('Missing required attribute \'' . $param .
                    '\' for authentication source ' . $this->authId);
            }

            if (!is_string($config[$param])) {
                throw new Exception('Expected parameter \'' . $param .
                    '\' for authentication source ' . $this->authId .
                    ' to be a string. Instead it was: ' .
                    var_export($config[$param], true));
            }
        }

        // Query can be a single query or an array of queries.
        if (!array_key_exists('query', $config)) {
            throw new Exception('Missing required attribute \'query\' ' .
                'for authentication source ' . $this->authId);
        } elseif (is_array($config['query']) && (count($config['query']) < 1)) {
            throw new Exception('Required attribute \'query\' is an empty ' .
                'list of queries for authentication source ' . $this->authId);
        }

        $this->dsn = $config['dsn'];
        $this->username = $config['username'];
        $this->password = $config['password'];
        $this->query = is_string($config['query']) ? [$config['query']] : $config['query'];
        if (isset($config['options'])) {
            $this->options = $config['options'];
        }

        // Optional "username_regex" parameter
        $this->username_regex = array_key_exists('username_regex', $config) ? $config['username_regex'] : null;
    }


    /**
     * Create a database connection.
     *
     * @return \PDO  The database connection.
     */
    protected function connect(): PDO
    {
        try {
            $db = new PDO($this->dsn, $this->username, $this->password, $this->options);
        } catch (PDOException $e) {
            // Obfuscate the password if it's part of the dsn
            $obfuscated_dsn =  preg_replace('/(user|password)=(.*?([;]|$))/', '${1}=***', $this->dsn);

            throw new Exception('sqlauth:' . $this->authId . ': - Failed to connect to \'' .
                $obfuscated_dsn . '\': ' . $e->getMessage());
        }

        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $driver = explode(':', $this->dsn, 2);
        $driver = strtolower($driver[0]);

        // Driver specific initialization
        switch ($driver) {
            case 'mysql':
                // Use UTF-8
                $db->exec("SET NAMES 'utf8mb4'");
                break;
            case 'pgsql':
                // Use UTF-8
                $db->exec("SET NAMES 'UTF8'");
                break;
        }

        return $db;
    }

    /**
     * Extract SQL columns into SAML attribute array
     *
     * @param $attributes output place to store extracted attributes
     * @param array  $data  Associative array from database in the format of PDO fetchAll
     * @param array  $forbiddenAttributes An array of attributes to never return
     * @return $attributes
     */
    protected function extractAttributes(&$attributes, $data, $forbiddenAttributes = [])
    {
        foreach ($data as $row) {
            foreach ($row as $name => $value) {
                if ($value === null) {
                    continue;
                }
                if (in_array($name, $forbiddenAttributes)) {
                    continue;
                }

                $value = (string) $value;

                if (!array_key_exists($name, $attributes)) {
                    $attributes[$name] = [];
                }

                if (in_array($value, $attributes[$name], true)) {
                    // Value already exists in attribute
                    continue;
                }

                $attributes[$name][] = $value;
            }
        }
        return $attributes;
    }

    /**
     * Execute the query with given parameters and return the tuples that result.
     *
     * @param string $query  SQL to execute
     * @param array  $params parameters to the SQL query
     * @return tuples that result
     */
    protected function executeQuery(PDO $db, string $query, array $params): array
    {
        try {
            $sth = $db->prepare($query);
        } catch (PDOException $e) {
            throw new Exception('sqlauth:' . $this->authId .
                                ': - Failed to prepare query: ' . $e->getMessage());
        }

        try {
            $sth->execute($params);
        } catch (PDOException $e) {
            throw new Exception('sqlauth:' . $this->authId .
                                ': - Failed to execute query: ' . $e->getMessage());
        }

        try {
            $data = $sth->fetchAll(PDO::FETCH_ASSOC);
            return $data;
        } catch (PDOException $e) {
            throw new Exception('sqlauth:' . $this->authId .
                                ': - Failed to fetch result set: ' . $e->getMessage());
        }
    }

    /**
     * If there is a username_regex then verify the passed username against it and
     * throw an exception if it fails.
     *
     * @param string $username  The username the user wrote.
     */
    protected function verifyUserNameWithRegex(string $username): void
    {
        if ($this->username_regex !== null) {
            if (!preg_match($this->username_regex, $username)) {
                Logger::error('sqlauth:' . $this->authId .
                    ": Username doesn't match username_regex.");
                throw new Error\Error('WRONGUSERPASS');
            }
        }
    }

    /**
     * Attempt to log in using the given username and password.
     *
     * On a successful login, this function should return the users attributes. On failure,
     * it should throw an exception. If the error was caused by the user entering the wrong
     * username or password, a \SimpleSAML\Error\Error('WRONGUSERPASS') should be thrown.
     *
     * Note that both the username and the password are UTF-8 encoded.
     *
     * @param string $username  The username the user wrote.
     * @param string $password  The password the user wrote.
     * @return array  Associative array with the users attributes.
     */
    protected function login(
        string $username,
        #[\SensitiveParameter]
        string $password,
    ): array {
        $this->verifyUserNameWithRegex($username);

        $db = $this->connect();
        $params = ['username' => $username, 'password' => $password];
        $attributes = [];

        $numQueries = count($this->query);
        for ($x = 0; $x < $numQueries; $x++) {
            $data = $this->executeQuery($db, $this->query[$x], $params);

            Logger::info('sqlauth:' . $this->authId . ': Got ' . count($data) .
                ' rows from database');

            if ($x === 0) {
                if (count($data) === 0) {
                    // No rows returned from first query - invalid username/password
                    Logger::error('sqlauth:' . $this->authId .
                        ': No rows in result set. Probably wrong username/password.');
                    throw new Error\Error('WRONGUSERPASS');
                }
                /* Only the first query should be passed the password, as that is the only
                 * one used for authentication. Subsequent queries are only used for
                 * getting attribute lists, so only need the username. */
                unset($params['password']);
            }

            /* Extract attributes. We allow the resultset to consist of multiple rows. Attributes
            * which are present in more than one row will become multivalued. null values and
            * duplicate values will be skipped. All values will be converted to strings.
             */
            $this->extractAttributes($attributes, $data, []);
        }

        Logger::info('sqlauth:' . $this->authId . ': Attributes: ' . implode(',', array_keys($attributes)));

        return $attributes;
    }
}
