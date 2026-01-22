<?php

declare(strict_types=1);

namespace SimpleSAML\Module\ldap;

use SimpleSAML\Assert\Assert;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\Module\ldap\Connector;

use function current;
use function sprintf;

class ConnectorFactory
{
    /**
     * @param string $authSource
     * @return \SimpleSAML\Module\ldap\ConnectorInterface
     */
    public static function fromAuthSource(string $authSource): ConnectorInterface
    {
        // Get the authsources file, which should contain the config
        $authSources = Configuration::getConfig('authsources.php');

        // Verify that the authsource config exists
        if (!$authSources->hasValue($authSource)) {
            throw new Error\Exception(sprintf(
                'Authsource [%s] not found in authsources.php',
                $authSource
            ));
        }

        // Get just the specified authsource config values
        $ldapConfig = $authSources->getConfigItem($authSource);
        $type = $ldapConfig->toArray();
        Assert::oneOf(current($type), ['ldap:Ldap']);

        $encryption = $ldapConfig->getOptionalString('encryption', 'ssl');
        Assert::oneOf($encryption, ['none', 'ssl', 'tls']);

        $version = $ldapConfig->getOptionalInteger('version', 3);
        Assert::positiveInteger($version);

        $class = $ldapConfig->getOptionalString('connector', Connector\Ldap::class);
        Assert::classExists($class);
        Assert::implementsInterface($class, ConnectorInterface::class);

        return /** @psalm-var \SimpleSAML\Module\ldap\ConnectionInterface */ new $class(
            $ldapConfig->getString('connection_string'),
            $encryption,
            $version,
            $ldapConfig->getOptionalString('extension', 'ext_ldap'),
            $ldapConfig->getOptionalBoolean('debug', false),
            $ldapConfig->getOptionalArray('options', [
                'network_timeout' => 3,
                'referrals' => false,
            ]),
        );
    }
}
