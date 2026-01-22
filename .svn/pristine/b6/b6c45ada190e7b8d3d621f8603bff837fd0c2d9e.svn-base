<?php

declare(strict_types=1);

namespace SimpleSAML\Module\ldap\Connector;

use SimpleSAML\Assert\Assert;
use SimpleSAML\Error;
use SimpleSAML\Logger;
use SimpleSAML\Module\ldap\ConnectorInterface;
use Symfony\Component\Ldap\Adapter\AdapterInterface;
use Symfony\Component\Ldap\Adapter\ExtLdap\Adapter;
use Symfony\Component\Ldap\Entry;
use Symfony\Component\Ldap\Exception\InvalidCredentialsException;
use Symfony\Component\Ldap\Exception\LdapException;
use Symfony\Component\Ldap\Ldap as LdapObject;

use function array_merge;
use function array_pop;
use function explode;
use function implode;
use function ini_get;
use function sprintf;
use function var_export;

class Ldap implements ConnectorInterface
{
    use LdapHelpers;

    /**
     * @var \Symfony\Component\Ldap\Adapter\AdapterInterface
     */
    protected AdapterInterface $adapter;

    /**
     * @var \Symfony\Component\Ldap\Ldap
     */
    protected LdapObject $connection;


    /**
     * @param string $connection_strings
     * @param string $encryption
     * @param int $version
     * @param string $extension
     * @param bool $debug
     * @param array $options
     */
    public function __construct(
        string $connection_strings,
        string $encryption = 'ssl',
        int $version = 3,
        string $extension = 'ext_ldap',
        bool $debug = false,
        array $options = ['referrals' => false, 'network_timeout' => 3]
    ) {
        foreach (explode(' ', $connection_strings) as $connection_string) {
            Assert::regex($connection_string, '#^ldap[s]?:\/\/#');
        }

        Logger::debug(sprintf(
            "Setting up LDAP connection: host='%s', encryption=%s, version=%d, debug=%s, timeout=%d, referrals=%s.",
            $connection_strings,
            $encryption,
            $version,
            var_export($debug, true),
            $options['timeout'] ?? ini_get('default_socket_timeout'),
            var_export($options['referrals'] ?? false, true),
        ));

        $this->adapter = new Adapter(
            [
                'connection_string' => $connection_strings,
                'encryption'        => $encryption,
                'version'           => $version,
                'debug'             => $debug,
                'options'           => $options,
            ]
        );

        $this->connection = new LdapObject($this->adapter);
    }


    /**
     * @return \Symfony\Component\Ldap\Adapter\AdapterInterface
     */
    public function getAdapter(): AdapterInterface
    {
        return $this->adapter;
    }


    /**
     * @inheritDoc
     */
    public function bind(?string $username, #[\SensitiveParameter]?string $password): void
    {
        try {
            $this->connection->bind($username, strval($password));
        } catch (InvalidCredentialsException $e) {
            throw new Error\Error($this->resolveBindError($e));
        }

        if ($username === null) {
            Logger::debug("LDAP bind(): Anonymous bind succesful.");
        } else {
            Logger::debug(sprintf("LDAP bind(): Bind successful for DN '%s'.", $username));
        }
    }


    /**
     * @inheritDoc
     */
    public function search(
        array $searchBase,
        string $filter,
        array $options,
        bool $allowMissing
    ): ?Entry {
        $entry = null;

        foreach ($searchBase as $base) {
            $query  = $this->connection->query($base, $filter, $options);
            $result = $query->execute()->toArray();

            if (count($result) > 1) {
                throw new Error\Exception(
                    sprintf(
                        "LDAP search(): Found %d entries searching base '%s' for '%s'",
                        count($result),
                        $base,
                        $filter,
                    )
                );
            } elseif (count($result) === 1) {
                $entry = array_pop($result);
                break;
            } else {
                Logger::debug(
                    sprintf(
                        "LDAP search(): Found no entries searching base '%s' for '%s'",
                        $base,
                        $filter,
                    )
                );
            }
        }

        if ($entry === null && $allowMissing === false) {
            throw new Error\Exception(
                sprintf(
                    "Object not found using search base [%s] and filter '%s'",
                    implode(', ', $searchBase),
                    $filter
                )
            );
        }

        return $entry;
    }


    /**
     * @inheritDoc
     */
    public function searchForMultiple(
        array $searchBase,
        string $filter,
        array $options,
        bool $allowMissing
    ): array {
        $results = [];

        foreach ($searchBase as $base) {
            $query   = $this->connection->query($base, $filter, $options);
            $result  = $query->execute()->toArray();
            $results = array_merge($results, $result);

            Logger::debug(sprintf(
                "Library - LDAP search(): Found %d entries searching base '%s' for '%s'",
                count($result),
                $base,
                $filter,
            ));
        }

        if (empty($results) && ($allowMissing === false)) {
            throw new Error\Exception(
                sprintf(
                    "No Objects found using search base [%s] and filter '%s'",
                    implode(', ', $searchBase),
                    $filter
                )
            );
        }

        return $results;
    }


    /**
     * Resolve the message to a UI exception
     *
     * @param InvalidCredentialsException $e
     * @return string
     */
    protected function resolveBindError(InvalidCredentialsException $e): string
    {
        return self::ERR_WRONG_PASS;
    }


    /**
     * @param \Symfony\Component\Ldap\Entry $entry
     * @return bool
     */
    public function updateEntry(Entry $entry): bool
    {
        try {
            $this->adapter->getEntryManager()->update($entry);
            return true;
        } catch (LdapException $e) {
            Logger::warning($e->getMessage());
            return false;
        }
    }
}
