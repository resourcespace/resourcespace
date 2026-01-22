<?php

/**
 * Filter to add attributes to the identity by executing a query against an LDAP directory
 *
 * @package simplesamlphp/simplesamlphp-module-ldap
 */

declare(strict_types=1);

namespace SimpleSAML\Module\ldap\Auth\Process;

use SimpleSAML\Assert\Assert;
use SimpleSAML\Logger;
use Symfony\Component\Ldap\Adapter\ExtLdap\Query;

use function array_values;

class AttributeAddFromLDAP extends BaseFilter
{
    /**
     * LDAP attributes to add to the request attributes
     *
     * @var array
     */
    protected array $searchAttributes;

    /**
     * LDAP attributes to base64 encode
     *
     * @var array
     */
    protected array $binaryAttributes;

    /**
     * LDAP search filter to use in the LDAP query
     *
     * @var string
     */
    protected string $searchFilter;

    /**
     * What to do with attributes when the target already exists. Either replace, merge or add.
     *
     * @var string
     */
    protected string $attrPolicy;

    /** @var string|null */
    protected ?string $searchUsername;

    /** @var string|null */
    protected ?string $searchPassword;

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
        $this->binaryAttributes = $this->config->getOptionalArray('attributes.binary', []);
        $this->searchAttributes = $this->config->getOptionalArrayize('attributes', []);
        if (empty($this->searchAttributes)) {
            $new_attribute = $this->config->getString('attribute.new');
            $this->searchAttributes[$new_attribute] = $this->config->getString('search.attribute');
        }
        $this->searchFilter = $this->config->getString('search.filter');

        // get the attribute policy
        $this->attrPolicy = $this->config->getOptionalString('attribute.policy', 'merge');
        Assert::oneOf($this->attrPolicy, ['merge', 'replace', 'add']);

        $this->searchUsername = $this->config->getOptionalString('search.username', null);
        $this->searchPassword = $this->config->getOptionalString('search.password', null);
    }


    /**
     * Add attributes from an LDAP server.
     *
     * @param array &$state The current request
     */
    public function process(array &$state): void
    {
        Assert::keyExists($state, 'Attributes');
        $attributes = &$state['Attributes'];

        // perform a merge on the ldap_search_filter
        // loop over the attributes and build the search and replace arrays
        $arrSearch = $arrReplace = [];
        foreach ($attributes as $attr => $val) {
            $arrSearch[] = '%' . $attr . '%';

            if (is_array($val) && count($val) > 0 && strlen($val[0]) > 0) {
                $arrReplace[] = $this->connector->escapeFilterValue($val[0], true);
            } else {
                $arrReplace[] = '';
            }
        }

        // merge the attributes into the ldap_search_filter
        /** @psalm-var string[] $arrReplace */
        $filter = str_replace($arrSearch, $arrReplace, $this->searchFilter);
        if (strpos($filter, '%') !== false) {
            Logger::info(sprintf(
                '%s: There are non-existing attributes in the search filter. (%s)',
                $this->title,
                $filter
            ));
            return;
        }

        $this->connector->bind($this->searchUsername, $this->searchPassword);

        $wantedAttrs = $this->config->getOptionalValue(
            'attributes',
            // If specifically set to NULL return all attributes, if not set at all return nothing (safe default)
            in_array('attributes', $this->config->getOptions(), true) ? ['*'] : [],
        );

        $options = [
            'scope' => $this->config->getOptionalString('search.scope', Query::SCOPE_SUB),
            'timeout' => $this->config->getOptionalInteger('timeout', 3),
            'filter' => array_values($wantedAttrs),
        ];

        $entries = $this->connector->searchForMultiple(
            $this->searchBase,
            $filter,
            $options,
            true
        );

        $results = [];
        foreach ($entries as $entry) {
            $tmp = array_intersect_key(
                $entry->getAttributes(),
                array_fill_keys(array_values($this->searchAttributes), null)
            );

            $binaries = array_intersect(
                array_keys($tmp),
                $this->binaryAttributes,
            );
            foreach ($binaries as $binary) {
                /** @psalm-var array $attr */
                $attr = $entry->getAttribute($binary);
                $tmp[$binary] = array_map('base64_encode', $attr);
            }

            $results[] = $tmp;
        }

        // handle [multiple] values
        foreach ($results as $result) {
            foreach ($this->searchAttributes as $target => $name) {
                // If there is no mapping defined, just use the name of the LDAP-attribute as a target
                if (is_int($target)) {
                    $target = $name;
                }

                if (isset($attributes[$target]) && $this->attrPolicy === 'replace') {
                    unset($attributes[$target]);
                }

                if (isset($result[$name])) {
                    if (isset($attributes[$target])) {
                        foreach (array_values($result[$name]) as $value) {
                            if ($this->attrPolicy === 'merge') {
                                if (!in_array($value, $attributes[$target], true)) {
                                    $attributes[$target][] = $value;
                                }
                            } else {
                                $attributes[$target][] = $value;
                            }
                        }
                    } else {
                        $attributes[$target] = array_values($result[$name]);
                    }
                }
            }
        }
    }
}
