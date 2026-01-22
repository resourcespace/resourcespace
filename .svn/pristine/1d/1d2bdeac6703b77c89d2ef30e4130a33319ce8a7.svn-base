<?php

/**
 * LDAP authentication source.
 *
 * See the ldap-entry in config-templates/authsources.php for information about
 * configuration of this authentication source.
 *
 * This class is based on www/auth/login.php.
 *
 * @package simplesamlphp/simplesamlphp-module-ldap
 */

declare(strict_types=1);

namespace SimpleSAML\Module\ldap\Auth\Source;

use SimpleSAML\Assert\Assert;
use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\Module\core\Auth\UserPassOrgBase;

use function array_key_exists;
use function var_export;

class LdapMulti extends UserPassOrgBase
{
    /**
     * An LDAP configuration object.
     */
    private Configuration $ldapConfig;

    /**
     * An array with mappings for organization => authsource.
     */
    private array $mapping;

    /**
     * An array with descriptions for organizations.
     */
    private array $orgs;

    /**
     * An array of organization IDs to LDAP configuration objects.
     */
    private array $ldapOrgs;

    /**
     * Whether we should include the organization as part of the username.
     */
    private bool $includeOrgInUsername;


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

        $usernameOrgMethod = $this->ldapConfig->getValueValidate(
            'username_organization_method',
            ['none', 'allow', 'force']
        );
        $this->setUsernameOrgMethod($usernameOrgMethod);

        $this->includeOrgInUsername = $this->ldapConfig->getOptionalBoolean(
            'include_organization_in_username',
            false
        );

        $this->mapping = $this->ldapConfig->getArray('mapping');
        Assert::notEmpty($this->mapping);

        $organizations = array_keys($this->mapping);
        $authsources = Configuration::getConfig('authsources.php');

        foreach ($organizations as $organization) {
            Assert::keyExists($this->mapping[$organization], 'authsource');
            $authsource = $this->mapping[$organization]['authsource'];
            Assert::notNull(Auth\Source::getById($authsource, Ldap::class));

            if (array_key_exists('description', $this->mapping[$organization])) {
                $this->orgs[$organization] = $this->mapping[$organization]['description'];
            } else {
                $this->orgs[$organization] = $organization;
            }

            $this->ldapOrgs[$organization] = Configuration::loadFromArray(
                $authsources->getValue($authsource),
                'authsources[' . var_export($this->authId, true) . '][' . var_export($organization, true) . ']'
            );
        }
    }


    /**
     * Attempt to log in using the given username and password.
     *
     * @param string $username  The username the user wrote.
     * @param string $password  The password the user wrote.
     * @return array  Associative array with the users attributes.
     */
    protected function login(string $username, #[\SensitiveParameter]string $password, string $organization): array
    {
        if ($this->includeOrgInUsername) {
            $username = $username . '@' . $organization;
        }

        $authsource = $this->mapping[$organization]['authsource'];

        if (!array_key_exists($organization, $this->ldapOrgs)) {
            // The organization is unknown to us.
            throw new Error\Error('WRONGUSERPASS');
        }

        $sourceConfig = $this->ldapOrgs[$organization];

        $ldap = new class (['AuthId' => $authsource], $sourceConfig->toArray()) extends Ldap
        {
            public function loginOverload(string $username, #[\SensitiveParameter]string $password): array
            {
                return $this->login($username, $password);
            }
        };

        return $ldap->loginOverload($username, $password);
    }


    /**
     * Retrieve list of organizations.
     *
     * @return array  Associative array with the organizations.
     */
    protected function getOrganizations(): array
    {
        return $this->orgs;
    }
}
