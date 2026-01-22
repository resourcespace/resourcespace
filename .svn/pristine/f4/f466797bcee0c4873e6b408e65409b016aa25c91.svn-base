<?php

/**
 * Consent Authentication Processing filter
 *
 * Filter for requesting the user to give consent before attributes are
 * released to the SP.
 *
 * @package SimpleSAMLphp
 */

declare(strict_types=1);

namespace SimpleSAML\Module\consent\Auth\Process;

use Exception;
use SAML2\Constants;
use SimpleSAML\Assert\Assert;
use SimpleSAML\Auth;
use SimpleSAML\Error;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Module\consent\Store;
use SimpleSAML\Stats;
use SimpleSAML\Utils;

class Consent extends Auth\ProcessingFilter
{
    /**
     * Button to receive focus
     *
     * @var string|null
     */
    private ?string $focus = null;

    /**
     * Include attribute values
     *
     * @var bool
     */
    private bool $includeValues = false;

    /**
     * Check remember consent
     *
     * @var bool
     */
    private bool $checked = false;

    /**
     * Consent backend storage configuration
     *
     * @var \SimpleSAML\Module\consent\Store|null
     */
    private ?Store $store = null;

    /**
     * Attributes where the value should be hidden
     *
     * @var array
     */
    private array $hiddenAttributes = [];

    /**
     * Attributes which should not require consent
     *
     * @var array
     */
    private array $noconsentattributes = [];

    /**
     * Whether we should show the "about service"-link on the no consent page.
     *
     * @var bool
     */
    private bool $showNoConsentAboutService = true;

    /**
     * The name of the attribute that holds a unique identifier for the user
     *
     * @var string
     */
    private string $identifyingAttribute;


    /**
     * Initialize consent filter.
     *
     * Validates and parses the configuration.
     *
     * @param array $config Configuration information.
     * @param mixed $reserved For future use.
     *
     * @throws \SimpleSAML\Error\Exception if the configuration is not valid.
     */
    public function __construct(array $config, $reserved)
    {
        parent::__construct($config, $reserved);

        if (array_key_exists('includeValues', $config)) {
            if (!is_bool($config['includeValues'])) {
                throw new Error\Exception(
                    'Consent: includeValues must be boolean. ' .
                    var_export($config['includeValues'], true) . ' given.',
                );
            }
            $this->includeValues = $config['includeValues'];
        }

        if (array_key_exists('checked', $config)) {
            if (!is_bool($config['checked'])) {
                throw new Error\Exception(
                    'Consent: checked must be boolean. ' .
                    var_export($config['checked'], true) . ' given.',
                );
            }
            $this->checked = $config['checked'];
        }

        if (array_key_exists('focus', $config)) {
            if (!in_array($config['focus'], ['yes', 'no'], true)) {
                throw new Error\Exception(
                    'Consent: focus must be a string with values `yes` or `no`. ' .
                    var_export($config['focus'], true) . ' given.',
                );
            }
            $this->focus = $config['focus'];
        }

        if (array_key_exists('hiddenAttributes', $config)) {
            if (!is_array($config['hiddenAttributes'])) {
                throw new Error\Exception(
                    'Consent: hiddenAttributes must be an array. ' .
                    var_export($config['hiddenAttributes'], true) . ' given.',
                );
            }
            $this->hiddenAttributes = $config['hiddenAttributes'];
        }

        if (array_key_exists('attributes.exclude', $config)) {
            if (!is_array($config['attributes.exclude'])) {
                throw new Error\Exception(
                    'Consent: attributes.exclude must be an array. ' .
                    var_export($config['attributes.exclude'], true) . ' given.',
                );
            }
            $this->noconsentattributes = $config['attributes.exclude'];
        }

        if (array_key_exists('store', $config)) {
            try {
                $this->store = \SimpleSAML\Module\consent\Store::parseStoreConfig($config['store']);
            } catch (Exception $e) {
                Logger::error(
                    'Consent: Could not create consent storage: ' .
                    $e->getMessage(),
                );
            }
        }

        if (array_key_exists('showNoConsentAboutService', $config)) {
            if (!is_bool($config['showNoConsentAboutService'])) {
                throw new Error\Exception('Consent: showNoConsentAboutService must be a boolean.');
            }
            $this->showNoConsentAboutService = $config['showNoConsentAboutService'];
        }

        Assert::keyExists(
            $config,
            'identifyingAttribute',
            "Consent: Missing mandatory 'identifyingAttribute' config setting.",
        );
        Assert::stringNotEmpty(
            $config['identifyingAttribute'],
            "Consent: 'identifyingAttribute' must be a non-empty string.",
        );
        $this->identifyingAttribute = $config['identifyingAttribute'];
    }


    /**
     * Helper function to check whether consent is disabled.
     *
     * @param mixed  $option The consent.disable option. Either an array of array, an array or a boolean.
     * @param string $entityId The entityID of the SP/IdP.
     *
     * @return boolean True if disabled, false if not.
     */
    private static function checkDisable($option, string $entityId): bool
    {
        if (is_array($option)) {
            // Check if consent.disable array has one element that is an array
            if (count($option) === count($option, COUNT_RECURSIVE)) {
                // Array is not multidimensional.  Simple in_array search suffices
                return in_array($entityId, $option, true);
            }

            // Array contains at least one element that is an array, verify both possibilities
            if (in_array($entityId, $option, true)) {
                return true;
            }

            // Search in multidimensional arrays
            foreach ($option as $optionToTest) {
                if (!is_array($optionToTest)) {
                    continue; // bad option
                }

                if (!array_key_exists('type', $optionToTest)) {
                    continue; // option has no type
                }

                // Option has a type - switch processing depending on type value :
                if ($optionToTest['type'] === 'regex') {
                    // regex-based consent disabling

                    if (!array_key_exists('pattern', $optionToTest)) {
                        continue; // no pattern defined
                    }

                    if (preg_match($optionToTest['pattern'], $entityId) === 1) {
                        return true;
                    }
                } else {
                    // option type is not supported
                    continue;
                }
            } // end foreach

            // Base case : no match
            return false;
        } else {
            return (bool) $option;
        }
    }


    /**
     * Process a authentication response
     *
     * This function saves the state, and redirects the user to the page where the user can authorize the release of
     * the attributes. If storage is used and the consent has already been given the user is passed on.
     *
     * @param array &$state The state of the response.
     *
     *
     * @throws \SimpleSAML\Module\saml\Error\NoPassive if the request was passive and consent is needed.
     */
    public function process(array &$state): void
    {
        Assert::keyExists($state, 'Destination');
        Assert::keyExists($state['Destination'], 'entityid');
        Assert::keyExists($state['Destination'], 'metadata-set');
        Assert::keyExists($state['Source'], 'entityid');
        Assert::keyExists($state['Source'], 'metadata-set');

        $spEntityId = $state['Destination']['entityid'];
        $idpEntityId = $state['Source']['entityid'];

        $metadata = \SimpleSAML\Metadata\MetaDataStorageHandler::getMetadataHandler();

        /**
         * If the consent module is active on a bridge $state['saml:sp:IdP']
         * will contain an entry id for the remote IdP. If not, then the
         * consent module is active on a local IdP and nothing needs to be
         * done.
         */
        if (isset($state['saml:sp:IdP'])) {
            $idpEntityId = $state['saml:sp:IdP'];
            $idpmeta = $metadata->getMetaData($idpEntityId, 'saml20-idp-remote');
            $state['Source'] = $idpmeta;
        }

        $statsData = ['spEntityID' => $spEntityId];

        // Do not use consent if disabled
        if (
            isset($state['Source']['consent.disable']) &&
            self::checkDisable($state['Source']['consent.disable'], $spEntityId)
        ) {
            Logger::debug('Consent: Consent disabled for entity ' . $spEntityId . ' with IdP ' . $idpEntityId);
            Stats::log('consent:disabled', $statsData);
            return;
        }
        if (
            isset($state['Destination']['consent.disable']) &&
            self::checkDisable($state['Destination']['consent.disable'], $idpEntityId)
        ) {
            Logger::debug('Consent: Consent disabled for entity ' . $spEntityId . ' with IdP ' . $idpEntityId);
            Stats::log('consent:disabled', $statsData);
            return;
        }

        if ($this->store !== null) {
            $attributes = $state['Attributes'];
            Assert::keyExists(
                $attributes,
                $this->identifyingAttribute,
                "Consent: Missing '" . $this->identifyingAttribute . "' in user's attributes.",
            );

            $source = $state['Source']['metadata-set'] . '|' . $idpEntityId;
            $destination = $state['Destination']['metadata-set'] . '|' . $spEntityId;

            Assert::keyExists(
                $attributes,
                $this->identifyingAttribute,
                sprintf("Consent: No attribute '%s' was found in the user's attributes.", $this->identifyingAttribute),
            );

            $userId = $attributes[$this->identifyingAttribute][0];
            Assert::stringNotEmpty($userId);

            // Remove attributes that do not require consent
            foreach ($attributes as $attrkey => $attrval) {
                if (in_array($attrkey, $this->noconsentattributes, true)) {
                    unset($attributes[$attrkey]);
                }
            }

            Logger::debug('Consent: userid: ' . $userId);
            Logger::debug('Consent: source: ' . $source);
            Logger::debug('Consent: destination: ' . $destination);

            $hashedUserId = self::getHashedUserID($userId, $source);
            $targetedId = self::getTargetedID($userId, $source, $destination);
            $attributeSet = self::getAttributeHash($attributes, $this->includeValues);

            Logger::debug(
                'Consent: hasConsent() [' . $hashedUserId . '|' . $targetedId . '|' . $attributeSet . ']',
            );

            try {
                if ($this->store->hasConsent($hashedUserId, $targetedId, $attributeSet)) {
                    // Consent already given
                    Logger::stats('consent found');
                    Stats::log('consent:found', $statsData);
                    return;
                }

                Logger::stats('consent notfound');
                Stats::log('consent:notfound', $statsData);

                $state['consent:store'] = $this->store;
                $state['consent:store.userId'] = $hashedUserId;
                $state['consent:store.destination'] = $targetedId;
                $state['consent:store.attributeSet'] = $attributeSet;
            } catch (\Exception $e) {
                Logger::error('Consent: Error reading from storage: ' . $e->getMessage());
                Logger::stats('Consent failed');
                Stats::log('consent:failed', $statsData);
            }
        } else {
            Logger::stats('consent nostorage');
            Stats::log('consent:nostorage', $statsData);
        }

        $state['consent:focus'] = $this->focus;
        $state['consent:checked'] = $this->checked;
        $state['consent:hiddenAttributes'] = $this->hiddenAttributes;
        $state['consent:noconsentattributes'] = $this->noconsentattributes;
        $state['consent:showNoConsentAboutService'] = $this->showNoConsentAboutService;

        // user interaction necessary. Throw exception on isPassive request
        if (isset($state['isPassive']) && $state['isPassive'] === true) {
            Stats::log('consent:nopassive', $statsData);
            throw new Module\saml\Error\NoPassive(
                Constants::STATUS_REQUESTER,
                'Unable to give consent on passive request.',
            );
        }

        // Save state and redirect
        $id = Auth\State::saveState($state, 'consent:request');
        $url = Module::getModuleURL('consent/getconsent');

        $httpUtils = new Utils\HTTP();
        $httpUtils->redirectTrustedURL($url, ['StateId' => $id]);
    }


    /**
     * Generate a unique identifier of the user.
     *
     * @param string $userid The user id.
     * @param string $source The source id.
     *
     * @return string SHA1 of the user id, source id and salt.
     */
    public static function getHashedUserID(string $userid, string $source): string
    {
        $configUtils = new Utils\Config();
        return hash('sha1', $userid . '|' . $configUtils->getSecretSalt() . '|' . $source);
    }


    /**
     * Generate a unique targeted identifier.
     *
     * @param string $userid The user id.
     * @param string $source The source id.
     * @param string $destination The destination id.
     *
     * @return string SHA1 of the user id, source id, destination id and salt.
     */
    public static function getTargetedID(string $userid, string $source, string $destination): string
    {
        $configUtils = new Utils\Config();
        return hash('sha1', $userid . '|' . $configUtils->getSecretSalt() . '|' . $source . '|' . $destination);
    }


    /**
     * Generate unique identifier for attributes.
     *
     * Create a hash value for the attributes that changes when attributes are added or removed. If the attribute
     * values are included in the hash, the hash will change if the values change.
     *
     * @param array  $attributes The attributes.
     * @param bool   $includeValues Whether or not to include the attribute value in the generation of the hash.
     *
     * @return string SHA1 of the user id, source id, destination id and salt.
     */
    public static function getAttributeHash(array $attributes, bool $includeValues = false): string
    {
        if ($includeValues) {
            foreach ($attributes as &$values) {
                sort($values);
            }
            ksort($attributes);
            $hashBase = serialize($attributes);
        } else {
            $names = array_keys($attributes);
            sort($names);
            $hashBase = implode('|', $names);
        }
        return hash('sha1', $hashBase);
    }
}
