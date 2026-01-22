<?php

/**
 * This base LDAP filter class can be extended to enable real filter classes direct access
 * access to the authsource ldap config and connects to the ldap server.
 *
 * @package simplesamlphp/simplesamlphp-module-ldap
 */

declare(strict_types=1);

namespace SimpleSAML\Module\ldap\Auth\Process;

use SimpleSAML\{Auth, Configuration, Error, Logger};
use SimpleSAML\Module\ldap\ConnectorFactory;
use SimpleSAML\Module\ldap\ConnectorInterface;

abstract class BaseFilter extends Auth\ProcessingFilter
{
    // TODO: Support ldap:LDAPMulti, if possible
    protected static array $ldapsources = ['ldap:Ldap', 'authX509:X509userCert'];

    /**
     * List of attribute "alias's" linked to the real attribute
     * name. Used for abstraction / configuration of the LDAP
     * attribute names, which may change between dir service.
     *
     * @var array
     */
    protected array $attribute_map;

    /**
     * The base DN of the LDAP connection. Used when searching the LDAP server.
     *
     * @var array
     */
    protected array $searchBase;

    /**
     * The construct method will change the filter config into
     * a \SimpleSAML\Configuration object and store it here for
     * later use, if needed.
     *
     * @var \SimpleSAML\Configuration
     */
    protected Configuration $config;

    /**
     * Array of LDAP connection objects. Stored here to be accessed later during processing.
     *
     * @var \SimpleSAML\Module\ldap\ConnectorInterface
     */
    protected ConnectorInterface $connector;

    /**
     * The class "title" used in logging and exception messages.
     * This should be prepended to the beginning of the message.
     *
     * @var string
     */
    protected string $title = 'ldap:BaseFilter';

    /**
     * List of LDAP object types, used to determine the type of
     * object that a DN references.
     *
     * @var array
     */
    protected array $type_map;


    /**
     * Checks the authsource, if defined, for configuration values
     * to the LDAP server. Then sets up the LDAP connection for the
     * instance/object and stores everything in class members.
     *
     * @throws \SimpleSAML\Error\Exception
     * @param array &$config
     * @param mixed $reserved
     */
    public function __construct(array &$config, $reserved)
    {
        parent::__construct($config, $reserved);

        // Change the class $title to match it's true name
        // This way if the class is extended the proper name is used
        $classname = explode('_', get_class($this));
        $classname = end($classname);
        $this->title = 'ldap:' . $classname;

        // Log the construction
        Logger::debug(sprintf('%s : Creating and configuring the filter.', $this->title));

        // If an authsource was defined (an not empty string)...
        if (isset($config['authsource']) && $config['authsource'] !== '') {
            $authconfig = $this->parseAuthSourceConfig($config['authsource']);

            // Merge the authsource config with the filter config,
            // but have the filter config override the authsource config
            $config = array_merge($authconfig, $config);

            // Authsource complete
            Logger::debug(sprintf(
                '%s : Retrieved authsource [%s] configuration values: %s',
                $this->title,
                $config['authsource'],
                $this->varExport($authconfig)
            ));
        }

        // Convert the config array to a config class,
        // that way we can verify type and define defaults.
        // Store in the instance in-case needed later, by a child class.
        $this->config = Configuration::loadFromArray($config, 'ldap:AuthProcess');

        // Initialize the Ldap-object
        $this->connector = ConnectorFactory::fromAuthSource($config['authsource']);

        // Set all the filter values, setting defaults if needed
        $this->searchBase = $this->config->getOptionalArray('search.base', []);

        // Log the member values retrieved above
        Logger::debug(sprintf(
            '%s : Configuration values retrieved; BaseDN: %s',
            $this->title,
            $this->varExport($this->searchBase)
        ));

        // Setup the attribute map which will be used to search LDAP
        $this->attribute_map = [
            'dn'       => $this->config->getOptionalString('attribute.dn', 'distinguishedName'),
            'groups'   => $this->config->getOptionalString('attribute.groups', 'groups'),
            'member'   => $this->config->getOptionalString('attribute.member', 'member'),
            'memberOf' => $this->config->getOptionalString('attribute.memberOf', 'memberOf'),
            'name'     => $this->config->getOptionalString('attribute.groupname', 'name'),
            'return'   => $this->config->getOptionalString('attribute.return', 'distinguishedName'),
            'type'     => $this->config->getOptionalString('attribute.type', 'objectClass'),
            'username' => $this->config->getOptionalString('attribute.username', 'sAMAccountName'),
        ];

        // Log the attribute map
        Logger::debug(sprintf(
            '%s : Attribute map created: %s',
            $this->title,
            $this->varExport($this->attribute_map)
        ));

        // Setup the object type map which is used to determine a DNs' type
        $this->type_map = [
            'group' => $this->config->getOptionalString('type.group', 'group'),
            'user'  => $this->config->getOptionalString('type.user', 'user'),
        ];

        // Log the type map
        Logger::debug(sprintf(
            '%s : Type map created: %s',
            $this->title,
            $this->varExport($this->type_map)
        ));
    }


    /**
     * Parse authsource config
     *
     * @param string $as The name of the authsource
     */
    private function parseAuthSourceConfig(string $as): array
    {
        // Log the authsource request
        Logger::debug(sprintf(
            '%s : Attempting to get configuration values from authsource [%s]',
            $this->title,
            $as
        ));

        // Get the authsources file, which should contain the config
        $authsources = Configuration::getConfig('authsources.php');

        // Verify that the authsource config exists
        if (!$authsources->hasValue($as)) {
            throw new Error\Exception(sprintf(
                '%s : Authsource [%s] defined in filter parameters not found in authsources.php',
                $this->title,
                $as
            ));
        }

        // Get just the specified authsource config values
        $authsource = $authsources->getArray($as);

        // Make sure it is an ldap source
        if (isset($authsource[0]) && !in_array($authsource[0], self::$ldapsources)) {
            throw new Error\Exception(sprintf(
                '%s : Authsource [%s] specified in filter parameters is not an ldap:LDAP type',
                $this->title,
                $as
            ));
        }

        // Build the authsource config
        $authconfig = [];
        if (isset($authsource['connection_string'])) {
            $authconfig['connection_string'] = $authsource['connection_string'];
        }
        if (isset($authsource['encryption'])) {
            $authconfig['encryption'] = $authsource['encryption'];
        }
        if (isset($authsource['version'])) {
            $authconfig['version'] = $authsource['version'];
        }
        if (isset($authsource['timeout'])) {
            $authconfig['timeout'] = $authsource['timeout'];
        }
        if (isset($authsource['debug'])) {
            $authconfig['debug']      = $authsource['debug'];
        }
        if (isset($authsource['referrals'])) {
            $authconfig['referrals']  = $authsource['referrals'];
        }

        // only set when search.enabled = true
        if (isset($authsource['search.enable']) && ($authsource['search.enable'] === true)) {
            if (isset($authsource['search.base'])) {
                $authconfig['search.base'] = $authsource['search.base'];
            }
            if (isset($authsource['search.scope'])) {
                $authconfig['search.scope'] = $authsource['search.scope'];
            }

            if (isset($authsource['search.username'])) {
                $authconfig['search.username']   = $authsource['search.username'];
            }
            if (isset($authsource['search.password'])) {
                $authconfig['search.password']   = $authsource['search.password'];
            }

            // Only set the username attribute if the authsource specifies one attribute
            if (
                isset($authsource['search.attributes'])
                && is_array($authsource['search.attributes'])
                && count($authsource['search.attributes']) == 1
            ) {
                $authconfig['attribute.username'] = reset($authsource['search.attributes']);
            }
        }

        // only set when priv.read = true
        if (isset($authsource['priv.read']) && $authsource['priv.read']) {
            if (isset($authsource['priv.username'])) {
                $authconfig['priv.username'] = $authsource['priv.username'];
            }
            if (isset($authsource['priv.password'])) {
                $authconfig['priv.password'] = $authsource['priv.password'];
            }
        }

        return $authconfig;
    }


    /**
     * Local utility function to get details about a variable,
     * basically converting it to a string to be used in a log
     * message. The var_export() function returns several lines
     * so this will remove the new lines and trim each line.
     *
     * @param mixed $value
     * @return string
     */
    protected function varExport($value): string
    {
        if (is_array($value)) {
            // remove sensitive data
            foreach ($value as $key => &$val) {
                if ($key === 'search.password' || $key === 'priv.password') {
                    $val = empty($val) ? '' : '********';
                }
            }
            unset($val);
        }

        $export = var_export($value, true);
        $lines = explode("\n", $export);
        foreach ($lines as &$line) {
            $line = trim($line);
        }
        return implode(' ', $lines);
    }
}
