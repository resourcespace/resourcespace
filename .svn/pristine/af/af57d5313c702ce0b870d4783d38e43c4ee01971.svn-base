<?php

declare(strict_types=1);

namespace SimpleSAML\Module\consentAdmin\Controller;

use Exception;
use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Metadata\MetaDataStorageHandler;
use SimpleSAML\Module\consent\Auth\Process\Consent;
use SimpleSAML\Module\consent\Store;
use SimpleSAML\Session;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Request;

use function array_key_exists;
use function in_array;
use function is_array;
use function sprintf;
use function strpos;
use function substr;

/**
 * Controller class for the consentadmin module.
 *
 * This class serves the different views available in the module.
 *
 * @package simplesamlphp/simplesamlphp-module-consentAdmin
 */
class ConsentAdmin
{
    /** @var \SimpleSAML\Configuration */
    protected Configuration $config;

    /** @var \SimpleSAML\Configuration */
    protected Configuration $moduleConfig;

    /** @var \SimpleSAML\Session */
    protected Session $session;

    /** @var \SimpleSAML\Metadata\MetaDataStorageHandler */
    protected MetaDataStorageHandler $metadataStorageHandler;

    /**
     * @var \SimpleSAML\Auth\Simple|string
     * @psalm-var \SimpleSAML\Auth\Simple|class-string
     */
    protected $authSimple = Auth\Simple::class;

    /**
     * @var \SimpleSAML\Module\consent\Auth\Process\Consent|string
     * @psalm-var \SimpleSAML\Module\consent\Auth\Process\Consent|class-string
     */
    protected $consent = Consent::class;

    /**
     * @var \SimpleSAML\Module\consent\Store|string
     * @psalm-var \SimpleSAML\Module\consent\Store|class-string
     */
    protected $store = Store::class;


    /**
     * Controller constructor.
     *
     * It initializes the global configuration and session for the controllers implemented here.
     *
     * @param \SimpleSAML\Configuration $config The configuration to use by the controllers.
     * @param \SimpleSAML\Session $session The session to use by the controllers.
     *
     * @throws \Exception
     */
    public function __construct(
        Configuration $config,
        Session $session,
    ) {
        $this->config = $config;
        $this->moduleConfig = Configuration::getConfig('module_consentAdmin.php');
        $this->metadataStorageHandler = MetaDataStorageHandler::getMetadataHandler();
        $this->session = $session;
    }


    /**
     * Inject the \SimpleSAML\Module\consent\Store dependency.
     *
     * @param \SimpleSAML\Module\consent\Store $store
     */
    public function setStore(Store $store): void
    {
        $this->store = $store;
    }


    /**
     * Inject the \SimpleSAML\Auth\Simple dependency.
     *
     * @param \SimpleSAML\Auth\Simple $authSimple
     */
    public function setAuthSimple(Auth\Simple $authSimple): void
    {
        $this->authSimple = $authSimple;
    }


    /**
     * Inject the \SimpleSAML\Metadata\MetaDataStorageHandler dependency.
     *
     * @param \SimpleSAML\Metadata\MetaDataStorageHandler $handler
     */
    public function setMetadataStorageHandler(MetadataStorageHandler $handler): void
    {
        $this->metadataStorageHandler = $handler;
    }


    /**
     * Inject the \SimpleSAML\Module\consent\Auth\Process\Consent dependency.
     *
     * @param \SimpleSAML\Module\consent\Auth\Process\Consent $consent
     */
    public function setConsent(Consent $consent): void
    {
        $this->consent = $consent;
    }


    /**
     * @param \Symfony\Component\HttpFoundation\Request $request The current request.
     *
     * @return void
     */
    public function logout(Request $request): void
    {
        $authority = $this->moduleConfig->getValue('authority');
        $as = new $this->authSimple($authority);

        $returnURL = $this->moduleConfig->getValue('returnURL');
        $as->logout($returnURL);
    }


    /**
     * @param \Symfony\Component\HttpFoundation\Request $request The current request.
     *
     * @return \SimpleSAML\XHTML\Template
     */
    public function main(Request $request): Template
    {
        $hashAttributes = $this->moduleConfig->getOptionalValue('attributes.hash', false);
        $excludeAttributes = $this->moduleConfig->getOptionalValue('attributes.exclude', []);

        // Check if valid local session exists
        $authority = $this->moduleConfig->getValue('authority');
        $as = new $this->authSimple($authority);
        $as->requireAuth();

        // Get released attributes
        $attributes = $as->getAttributes();

        // Get metadata storage handler
        $metadata = $this->metadataStorageHandler;

        /*
         * Get IdP id and metadata
         */
        $idp_entityid = $metadata->getMetaDataCurrentEntityID('saml20-idp-hosted');
        $idp_metadata = $metadata->getMetaData($idp_entityid, 'saml20-idp-hosted');

        // Calc correct source
        if ($as->getAuthData('saml:sp:IdP') !== null) {
            // from a remote idp (as bridge)
            $source = 'saml20-idp-remote|' . $as->getAuthData('saml:sp:IdP');
        } else {
            // from the local idp
            $source = $idp_metadata['metadata-set'] . '|' . $idp_entityid;
        }

        // Get user ID
        $userid_attributename = $this->moduleConfig->getOptionalString(
            'identifyingAttribute',
            'eduPersonPrincipalName',
        );
        $userids = $attributes[$userid_attributename];

        if (empty($userids)) {
            throw new Exception(sprintf(
                'Could not generate useridentifier for storing consent. Attribute [%s] was not available.',
                $userid_attributename,
            ));
        }

        $userid = $userids[0];

        // Get all SP metadata
        $all_sp_metadata = $metadata->getList('saml20-sp-remote');

        $sp_entityid = $request->query->get('cv');
        $action = $request->query->get('action');

        Logger::notice('consentAdmin: sp: ' . $sp_entityid . ' action: ' . $action);

        // Remove services, whitch have consent disabled
        if (isset($idp_metadata['consent.disable'])) {
            foreach ($idp_metadata['consent.disable'] as $disable) {
                if (array_key_exists($disable, $all_sp_metadata)) {
                    unset($all_sp_metadata[$disable]);
                }
            }
        }

        Logger::info('consentAdmin: ' . $idp_entityid);

        // Parse consent config
        $consent_storage = $this->store::parseStoreConfig($this->moduleConfig->getValue('consentadmin'));

        // Calc correct user ID hash
        $hashed_user_id = $this->consent::getHashedUserID($userid, $source);

        // If a checkbox have been clicked
        if ($action !== null && $sp_entityid !== null) {
            // init template to enable translation of status messages
            $template = new Template(
                $this->config,
                'consentAdmin:consentadminajax.twig',
                'consentAdmin:consentadmin',
            );

            // Get SP metadata
            $sp_metadata = $metadata->getMetaData($sp_entityid, 'saml20-sp-remote');

            // Run AuthProc filters
            list($targeted_id, $attribute_hash, $attributes) = $this->driveProcessingChain(
                $idp_metadata,
                $source,
                $sp_metadata,
                $sp_entityid,
                $attributes,
                $userid,
                $hashAttributes,
                $excludeAttributes,
            );

            // Add a consent (or update if attributes have changed and old consent for SP and IdP exists)
            if ($action == 'true') {
                $isStored = $consent_storage->saveConsent($hashed_user_id, $targeted_id, $attribute_hash);
            } else {
                if ($action == 'false') {
                    // Got consent, so this is a request to remove it
                    $consent_storage->deleteConsent($hashed_user_id, $targeted_id);
                    $isStored = false;
                } else {
                    Logger::info('consentAdmin: unknown action');
                    $isStored = null;
                }
            }
            $template->data['isStored'] = $isStored;
            return $template;
        }

        // Get all consents for user
        $user_consent_list = $consent_storage->getConsents($hashed_user_id);

        // Parse list of consents
        $user_consent = [];
        foreach ($user_consent_list as $c) {
            $user_consent[$c[0]] = $c[1];
        }

        // Init template
        $template = new Template($this->config, 'consentAdmin:consentadmin.twig', 'consentAdmin:consentadmin');
        $template->getLocalization()->addAttributeDomains();

        $sp_list = [];

        // Process consents for all SP
        foreach ($all_sp_metadata as $sp_entityid => $sp_values) {
            // Get metadata for SP
            $sp_metadata = $metadata->getMetaData($sp_entityid, 'saml20-sp-remote');

            // Run attribute filters
            list($targeted_id, $attribute_hash, $attributes) = $this->driveProcessingChain(
                $idp_metadata,
                $source,
                $sp_metadata,
                $sp_entityid,
                $attributes,
                $userid,
                $hashAttributes,
                $excludeAttributes,
            );

            // Check if consent exists
            if (array_key_exists($targeted_id, $user_consent)) {
                $sp_status = "changed";
                Logger::info('consentAdmin: changed');
                // Check if consent is valid. (Possible that attributes has changed)
                if ($user_consent[$targeted_id] == $attribute_hash) {
                    Logger::info('consentAdmin: ok');
                    $sp_status = "ok";
                }
                // Consent does not exist
            } else {
                Logger::info('consentAdmin: none');
                $sp_status = "none";
            }

            // Set description of SP
            $sp_description = null;
            if (!empty($sp_metadata['description']) && is_array($sp_metadata['description'])) {
                $sp_description = $sp_metadata['description'];
            }

            // Add a URL to the service if present in metadata
            $sp_service_url = isset($sp_metadata['ServiceURL']) ? $sp_metadata['ServiceURL'] : null;

            // Fill out array for the template
            $sp_list[$sp_entityid] = [
                'spentityid'       => $sp_entityid,
                'name'             => $template->getEntityDisplayName($sp_metadata),
                'description'      => $sp_description,
                'consentStatus'    => $sp_status,
                'consentValue'     => $sp_entityid,
                'attributes_by_sp' => $attributes,
                'serviceurl'       => $sp_service_url,
            ];
        }

        $template->data['header'] = 'Consent Administration';
        $template->data['spList'] = $sp_list;
        $template->data['showDescription'] = $this->moduleConfig->getBoolean('showDescription');

        return $template;
    }


    /**
     * Runs the processing chain and ignores all filter which have user
     * interaction.
     *
     * @param array $idp_metadata
     * @param string $source
     * @param array $sp_metadata
     * @param string $sp_entityid
     * @param array $attributes
     * @param string $userid
     * @param bool $hashAttributes
     * @param array $excludeAttributes
     * @return array
     */
    private function driveProcessingChain(
        array $idp_metadata,
        string $source,
        array $sp_metadata,
        string $sp_entityid,
        array $attributes,
        string $userid,
        bool $hashAttributes,
        array $excludeAttributes,
    ): array {
        /*
         * Create a new processing chain
         */
        $pc = new Auth\ProcessingChain($idp_metadata, $sp_metadata, 'idp');

        /*
         * Construct the state.
         * REMEMBER: Do not set Return URL if you are calling processStatePassive
         */
        $authProcState = [
            'Attributes'  => $attributes,
            'Destination' => $sp_metadata,
            'SPMetadata'  => $sp_metadata,
            'Source'      => $idp_metadata,
            'IdPMetadata' => $idp_metadata,
            'isPassive'   => true,
        ];

        /* we're being bridged, so add that info to the state */
        if (strpos($source, '-idp-remote|') !== false) {
            /** @var int $i */
            $i = strpos($source, '|');
            $authProcState['saml:sp:IdP'] = substr($source, $i + 1);
        }

        /*
         * Call processStatePAssive.
         * We are not interested in any user interaction, only modifications to the attributes
         */
        $pc->processStatePassive($authProcState);

        $attributes = $authProcState['Attributes'];
        // Remove attributes that do not require consent/should be excluded
        foreach ($attributes as $attrkey => $attrval) {
            if (in_array($attrkey, $excludeAttributes)) {
                unset($attributes[$attrkey]);
            }
        }

        /*
         * Generate identifiers and hashes
         */
        $destination = $sp_metadata['metadata-set'] . '|' . $sp_entityid;

        $targeted_id = $this->consent::getTargetedID($userid, $source, $destination);
        $attribute_hash = $this->consent::getAttributeHash($attributes, $hashAttributes);

        Logger::info('consentAdmin: user: ' . $userid);
        Logger::info('consentAdmin: target: ' . $targeted_id);
        Logger::info('consentAdmin: attribute: ' . $attribute_hash);

        // Return values
        return [$targeted_id, $attribute_hash, $attributes];
    }
}
