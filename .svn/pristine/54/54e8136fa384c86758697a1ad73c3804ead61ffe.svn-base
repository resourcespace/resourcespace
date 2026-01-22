<?php

declare(strict_types=1);

namespace SimpleSAML\Module\ldap\Auth\Source;

use SimpleSAML\Assert\Assert;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\Module\core\Auth\UserPassBase;
use SimpleSAML\Module\ldap\Connector\LdapHelpers;
use SimpleSAML\Module\ldap\ConnectorFactory;
use SimpleSAML\Module\ldap\ConnectorInterface;
use Symfony\Component\Ldap\Adapter\ExtLdap\Query;
use Symfony\Component\Ldap\Entry;

use function array_keys;
use function array_map;
use function in_array;
use function preg_match;
use function str_replace;
use function var_export;

/**
 * LDAP authentication source.
 *
 * See the ldap-entry in config-templates/authsources.php for information about
 * configuration of this authentication source.
 *
 * @package simplesamlphp/simplesamlphp-module-ldap
 */

class Ldap extends UserPassBase
{
    use LdapHelpers;


    /**
     * @var \SimpleSAML\Module\ldap\ConnectorInterface
     */
    protected ConnectorInterface $connector;

    /**
     * An LDAP configuration object.
     */
    protected Configuration $ldapConfig;


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

        $this->ldapConfig = Configuration::loadFromArray(
            $config,
            'authsources[' . var_export($this->authId, true) . ']'
        );

        $this->connector = ConnectorFactory::fromAuthSource($this->authId);
    }


    /**
     * Attempt to log in using the given username and password.
     *
     * @param string $username  The username the user wrote.
     * @param string $password  The password the user wrote.
     * @return array  Associative array with the users attributes.
     */
    protected function login(string $username, #[\SensitiveParameter]string $password): array
    {
        if (preg_match('/^\s*$/', $password)) {
            // The empty string is considered an anonymous bind to Symfony
            throw new Error\Error('WRONGUSERPASS');
        }

        $searchScope = $this->ldapConfig->getOptionalString('search.scope', Query::SCOPE_SUB);
        Assert::oneOf($searchScope, [Query::SCOPE_BASE, Query::SCOPE_ONE, Query::SCOPE_SUB]);

        $timeout = $this->ldapConfig->getOptionalInteger('timeout', 3);
        Assert::natural($timeout);

        $attributes = $this->ldapConfig->getOptionalValue(
            'attributes',
            // If specifically set to NULL return all attributes, if not set at all return nothing (safe default)
            in_array('attributes', $this->ldapConfig->getOptions(), true) ? ['*'] : [],
        );

        $searchBase = $this->ldapConfig->getArray('search.base');

        $options = [
            'scope' => $searchScope,
            'timeout' => $timeout,
            'filter' => $attributes,
        ];

        $searchEnable = $this->ldapConfig->getOptionalBoolean('search.enable', false);
        if ($searchEnable === false) {
            $dnPattern = $this->ldapConfig->getString('dnpattern');
            $dn = str_replace('%username%', $username, $dnPattern);
        } else {
            $searchUsername = $this->ldapConfig->getOptionalString('search.username', null);
            Assert::nullOrNotWhitespaceOnly($searchUsername);

            $searchPassword = $this->ldapConfig->getOptionalString('search.password', null);
            Assert::nullOrnotWhitespaceOnly($searchPassword);

            try {
                $this->connector->bind($searchUsername, $searchPassword);
            } catch (Error\Error $e) {
                throw new Error\Exception("Unable to bind using the configured search.username and search.password.");
            }

            $filter = $this->buildSearchFilter($username);

            try {
                $entry = /** @scrutinizer-ignore-type */$this->connector->search($searchBase, $filter, $options, false);
                $dn = $entry->getDn();
            } catch (Error\Exception $e) {
                throw new Error\Error('WRONGUSERPASS');
            }
        }

        /* Verify the credentials */
        $this->connector->bind($dn, $password);

        /* If the credentials were correct, rebind using a privileged account to read attributes */
        $readUsername = $this->ldapConfig->getOptionalString('priv.username', null);
        $readPassword = $this->ldapConfig->getOptionalString('priv.password', null);
        if ($readUsername !== null) {
            $this->connector->bind($readUsername, $readPassword);
        }

        $options['scope'] = Query::SCOPE_BASE;
        $filter = '(objectClass=*)';

        $entry = $this->connector->search([$dn], $filter, $options, false);

        return $this->processAttributes(/** @scrutinizer-ignore-type */$entry);
    }


    /**
     * Attempt to find a user's attributes given its username.
     *
     * @param string $username  The username who's attributes we want.
     * @return array  Associative array with the users attributes.
     */
    public function getAttributes(string $username): array
    {
        $searchUsername = $this->ldapConfig->getOptionalString('search.username', null);
        Assert::nullOrNotWhitespaceOnly($searchUsername);

        $searchPassword = $this->ldapConfig->getOptionalString('search.password', null);
        Assert::nullOrnotWhitespaceOnly($searchPassword);

        try {
            $this->connector->bind($searchUsername, $searchPassword);
        } catch (Error\Error $e) {
            throw new Error\Exception("Unable to bind using the configured search.username and search.password.");
        }

        $searchEnable = $this->ldapConfig->getOptionalBoolean('search.enable', false);
        if ($searchEnable === false) {
            $dnPattern = $this->ldapConfig->getString('dnpattern');
            $filter = '(' . str_replace('%username%', $this->escapeFilterValue($username), $dnPattern) . ')';
        } else {
            $filter = $this->buildSearchFilter($username);
        }

        $searchScope = $this->ldapConfig->getOptionalString('search.scope', Query::SCOPE_SUB);
        Assert::oneOf($searchScope, [Query::SCOPE_BASE, Query::SCOPE_ONE, Query::SCOPE_SUB]);

        $timeout = $this->ldapConfig->getOptionalInteger('timeout', 3);
        Assert::natural($timeout);

        $attributes = $this->ldapConfig->getOptionalValue(
            'attributes',
            // If specifically set to NULL return all attributes, if not set at all return nothing (safe default)
            in_array('attributes', $this->ldapConfig->getOptions(), true) ? ['*'] : [],
        );

        $searchBase = $this->ldapConfig->getArray('search.base');
        $options = [
            'scope' => $searchScope,
            'timeout' => $timeout,
            'filter' => $attributes,
        ];

        try {
            /** @var \Symfony\Component\Ldap\Entry $entry */
            $entry = $this->connector->search($searchBase, $filter, $options, false);
        } catch (Error\Exception $e) {
            throw new Error\Error('WRONGUSERPASS');
        }

        return $this->processAttributes($entry);
    }


    /**
     * @param \Symfony\Component\Ldap\Entry $entry
     * @return array
     */
    private function processAttributes(Entry $entry): array
    {
        $result = $entry->getAttributes();

        $binaries = array_intersect(
            array_keys($result),
            $this->ldapConfig->getOptionalArray('attributes.binary', []),
        );

        foreach ($binaries as $binary) {
            $result[$binary] = array_map('base64_encode', $result[$binary]);
        }

        return $result;
    }


    /**
     * @param string $username
     * @return string
     */
    private function buildSearchFilter(string $username): string
    {
        $searchAttributes = $this->ldapConfig->getArray('search.attributes');
        /** @psalm-var string|null $searchFilter */
        $searchFilter = $this->ldapConfig->getOptionalString('search.filter', null);

        $filter = '';
        foreach ($searchAttributes as $attr) {
            $filter .= '(' . $attr . '=' . $this->escapeFilterValue($username) . ')';
        }
        $filter = '(|' . $filter . ')';

        // Append LDAP filters if defined
        if ($searchFilter !== null) {
            $filter = "(&" . $filter . $searchFilter . ")";
        }

        return $filter;
    }


    /**
     * @return \SimpleSAML\Module\ldap\ConnectorInterface
     */
    public function getConnector(): ConnectorInterface
    {
        return $this->connector;
    }
}
