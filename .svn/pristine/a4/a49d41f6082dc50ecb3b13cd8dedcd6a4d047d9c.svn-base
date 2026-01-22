<?php

/**
 * Does a reverse membership lookup on the logged in user,
 * looking for groups it is a member of and adds them to
 * a defined attribute, in DN format.
 *
 * @package simplesamlphp/simplesamlphp-module-ldap
 */

declare(strict_types=1);

namespace SimpleSAML\Module\ldap\Auth\Process;

use SimpleSAML\Assert\Assert;
use SimpleSAML\Error;
use SimpleSAML\Logger;
use SimpleSAML\Utils;
use Symfony\Component\Ldap\Adapter\ExtLdap\Query;

class AttributeAddUsersGroups extends BaseFilter
{
    /** @var string|null */
    protected ?string $searchUsername;

    /** @var string|null */
    protected ?string $searchPassword;

    /** @var string|null */
    protected ?string $product;


    /**
     * Initialize this filter.
     *
     * @param array $config Configuration information about this filter.
     * @param mixed $reserved For future use.
     */
    public function __construct(array $config, $reserved)
    {
        parent::__construct($config, $reserved);

        // Get filter specific config options
        $this->searchUsername = $this->config->getOptionalString('search.username', null);
        $this->searchPassword = $this->config->getOptionalString('search.password', null);
        $this->product = $this->config->getOptionalString('ldap.product', null);
    }


    /**
     * LDAP search filters to be added to the base filters for this authproc-filter.
     * It's an array of key => value pairs that will be translated to (key=value) in the ldap query.
     *
     * @var array
     */
    protected array $additional_filters;


    /**
     * This is run when the filter is processed by SimpleSAML.
     * It will attempt to find the current users groups using
     * the best method possible for the LDAP product. The groups
     * are then added to the request attributes.
     *
     * @throws \SimpleSAML\Error\Exception
     * @param array &$state
     */
    public function process(array &$state): void
    {
        Assert::keyExists($state, 'Attributes');

        // Log the process
        Logger::debug(sprintf(
            '%s : Attempting to get the users groups...',
            $this->title
        ));

        $this->additional_filters = $this->config->getOptionalArray('additional_filters', []);

        // Reference the attributes, just to make the names shorter
        $attributes = &$state['Attributes'];
        $map = &$this->attribute_map;

        // Get the users groups from LDAP
        $groups = $this->getGroups($attributes);

        // If there are none, do not proceed
        if (empty($groups)) {
            return;
        }

        // Make the array if it is not set already
        if (!isset($attributes[$map['groups']])) {
            $attributes[$map['groups']] = [];
        }

        // Must be an array, else cannot merge groups
        if (!is_array($attributes[$map['groups']])) {
            throw new Error\Exception(sprintf(
                '%s : The group attribute [%s] is not an array of group DNs. %s',
                $this->title,
                $map['groups'],
                $this->varExport($attributes[$map['groups']])
            ));
        }

        // Add the users group(s)
        $group_attribute = &$attributes[$map['groups']];
        $group_attribute = array_merge($group_attribute, $groups);
        $group_attribute = array_unique($group_attribute);

        // All done
        Logger::debug(sprintf(
            '%s : Added users groups to the group attribute[%s]: %s',
            $this->title,
            $map['groups'],
            implode('; ', $groups)
        ));
    }


    /**
     * Will perform a search using the required attribute values from the user to
     * get their group membership, recursively.
     *
     * @throws \SimpleSAML\Error\Exception
     * @param array $attributes
     * @return array
     */
    protected function getGroups(array $attributes): array
    {
        // Log the request
        Logger::debug(sprintf(
            '%s : Checking for groups based on the best method for the LDAP product.',
            $this->title
        ));

        $this->connector->bind($this->searchUsername, $this->searchPassword);

        $options = [
            'scope' => $this->config->getOptionalString('search.scope', Query::SCOPE_SUB),
            'timeout' => $this->config->getOptionalInteger('timeout', 3),
        ];

        // Reference the map, just to make the name shorter
        $map = &$this->attribute_map;


        // All map-properties are guaranteed to exist and have a default value
        $dn_attribute = $map['dn'];
        $return_attribute = $map['return'];

        // Based on the directory service, search LDAP for groups
        // If any attributes are needed, prepare them before calling search method
        switch ($this->product) {
            case 'ActiveDirectory':
                // Log the AD specific search
                Logger::debug(sprintf(
                    '%s : Searching LDAP using ActiveDirectory specific method.',
                    $this->title
                ));

                // Make sure the defined DN attribute exists
                if (!isset($attributes[$dn_attribute])) {
                    Logger::warning(sprintf(
                        "%s : The DN attribute [%s] is not defined in the user's Attributes: %s",
                        $this->title,
                        $dn_attribute,
                        implode(', ', array_keys($attributes)),
                    ));

                    return [];
                }

                // Make sure the defined DN attribute has a value
                if (!isset($attributes[$dn_attribute][0]) || !$attributes[$dn_attribute][0]) {
                    Logger::warning(sprintf(
                        '%s : The DN attribute [%s] does not have a [0] value defined. %s',
                        $this->title,
                        $dn_attribute,
                        $this->varExport($attributes[$dn_attribute])
                    ));

                    return [];
                }

                // Log the search
                $arrayUtils = new Utils\Arrays();
                Logger::debug(sprintf(
                    '%s : Searching ActiveDirectory group membership.'
                        . ' DN: %s DN Attribute: %s Member Attribute: %s Type Attribute: %s Type Value: %s Base: %s',
                    $this->title,
                    $attributes[$dn_attribute][0],
                    $dn_attribute,
                    $map['member'],
                    $map['type'],
                    $this->type_map['group'],
                    implode('; ', $arrayUtils->arrayize($this->searchBase))
                ));

                $filter = sprintf(
                    "(&(%s=%s)(%s=%s))",
                    $map['type'],
                    $this->type_map['group'],
                    $map['member'] . ':1.2.840.113556.1.4.1941:',
                    $this->connector->escapeFilterValue($attributes[$dn_attribute][0], true),
                );

                $entries = $this->connector->searchForMultiple(
                    $this->searchBase,
                    $filter,
                    $options,
                    true
                );

                break;
            case 'OpenLDAP':
                // Log the OpenLDAP specific search
                Logger::debug(sprintf(
                    '%s : Searching LDAP using OpenLDAP specific method.',
                    $this->title
                ));

                Logger::debug(sprintf(
                    '%s : Searching for groups in base [%s] with filter (%s=%s) and attributes %s',
                    $this->title,
                    implode(', ', $this->searchBase),
                    $map['memberOf'],
                    $attributes[$map['username']][0],
                    $map['member']
                ));

                $filter = sprintf(
                    '(&(%s=%s))',
                    $map['memberOf'],
                    $attributes[$map['username']][0]
                );

                $entries = $this->connector->searchForMultiple(
                    $this->searchBase,
                    $filter,
                    $options,
                    true
                );

                break;
            default:
                // Log the generic search
                Logger::debug(
                    sprintf('%s : Searching LDAP using the generic search method.', $this->title)
                );

                // Make sure the defined memberOf attribute exists
                Assert::keyExists(
                    $attributes,
                    $map['memberOf'],
                    sprintf(
                        "%s : The memberOf attribute [%s] is not defined in the user's attributes: [%s]",
                        $this->title,
                        $map['memberOf'],
                        implode(', ', array_keys($attributes))
                    ),
                    Error\Exception::class,
                );

                // MemberOf must be an array of group DN's
                Assert::isArray(
                    $attributes[$map['memberOf']],
                    sprintf(
                        '%s : The memberOf attribute [%s] is not an array of group DNs;  %s',
                        $this->title,
                        $map['memberOf'],
                        $this->varExport($attributes[$map['memberOf']]),
                    ),
                    Error\Exception::class,
                );

                Logger::debug(sprintf(
                    '%s : Checking DNs for groups. DNs: %s Attributes: %s, %s Group Type: %s',
                    $this->title,
                    implode('; ', $attributes[$map['memberOf']]),
                    $map['memberOf'],
                    $map['type'],
                    $this->type_map['group']
                ));

                // Search for the users group membership, recursively
                $entries = $this->search($attributes[$map['memberOf']], $options);
        }

        $groups = [];
        foreach ($entries as $entry) {
            if ($entry->hasAttribute($return_attribute)) {
                /** @psalm-var array $values */
                $values = $entry->getAttribute($return_attribute);
                $groups[] = array_pop($values);
                continue;
            } elseif ($entry->hasAttribute(strtolower($return_attribute))) {
                // Some backends return lowercase attributes
                /** @psalm-var array $values */
                $values = $entry->getAttribute(strtolower($return_attribute));
                $groups[] = array_pop($values);
                continue;
            }

            // Could not find return attribute, log and continue
            Logger::notice(sprintf(
                '%s : The return attribute [%s] could not be found in entry `%s`.',
                $this->title,
                implode(', ', array_unique([$map['return'], strtolower($map['return'])])),
                $entry->getDn(),
            ));
            Logger::debug(sprintf('%s : Entry was: %s', $this->title, $this->varExport($entry)));
        }

        // All done
        Logger::debug(sprintf(
            '%s : User found to be a member of the following groups: %s',
            $this->title,
            empty($groups) ? 'none' : implode('; ', $groups),
        ));

        return $groups;
    }


    /**
     * Looks for groups from the list of DN's passed. Also
     * recursively searches groups for further membership.
     * Avoids loops by only searching a DN once. Returns
     * the list of groups found.
     *
     * @param array $memberOf
     * @param array $options
     * @return array
     */
    protected function search(array $memberOf, array $options): array
    {
        // Shorten the variable name
        $map = &$this->attribute_map;

        // Used to determine what DN's have already been searched
        static $searched = [];

        // Init the groups variable
        $entries = [];

        // Set scope to 'base'
        $options['scope'] = Query::SCOPE_BASE;

        // Check each DN of the passed memberOf
        foreach ($memberOf as $dn) {
            // Avoid infinite loops, only need to check a DN once
            if (isset($searched[$dn])) {
                continue;
            }

            // Track all DN's that are searched
            // Use DN for key as well, isset() is faster than in_array()
            $searched[$dn] = $dn;

            // Query LDAP for the attribute values for the DN
            $entry = $this->connector->search(
                [$dn],
                sprintf("(%s=%s)", $map['type'], $this->type_map['group']),
                $options,
                true,
            );

            if ($entry === null) {
                // Probably the DN does not exist within the given search base
                continue;
            }

            // Add to found groups array
            $entries[] = $entry;

            // Recursively search "sub" groups
            $subGroups = $entry->getAttribute($map['memberOf']);
            if (!empty($subGroups)) {
                $entries = array_merge($entries, $this->search($subGroups, $options));
            }
        }

        return $entries;
    }
}
