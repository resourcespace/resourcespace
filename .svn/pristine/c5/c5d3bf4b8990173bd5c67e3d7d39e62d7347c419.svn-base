<?php

declare(strict_types=1);

namespace SimpleSAML\Module\consent\Controller;

use Exception;
use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\HTTP\RunnableResponse;
use SimpleSAML\IdP;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Session;
use SimpleSAML\Stats;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller class for the consent module.
 *
 * This class serves the consent views available in the module.
 *
 * @package SimpleSAML\Module\consent
 */
class ConsentController
{
    /** @var \SimpleSAML\Configuration */
    protected $config;

    /** @var \SimpleSAML\Session */
    protected $session;

    /**
     * @var \SimpleSAML\Auth\State|string
     * @psalm-var \SimpleSAML\Auth\State|class-string
     */
    protected $authState = Auth\State::class;

    /**
     * @var \SimpleSAML\Logger|string
     * @psalm-var \SimpleSAML\Logger|class-string
     */
    protected $logger = Logger::class;


    /**
     * ConsentController constructor.
     *
     * @param \SimpleSAML\Configuration $config The configuration to use.
     * @param \SimpleSAML\Session $session The current user session.
     */
    public function __construct(Configuration $config, Session $session)
    {
        $this->config = $config;
        $this->session = $session;
    }


    /**
     * Inject the \SimpleSAML\Auth\State dependency.
     *
     * @param \SimpleSAML\Auth\State $authState
     */
    public function setAuthState(Auth\State $authState): void
    {
        $this->authState = $authState;
    }


    /**
     * Inject the \SimpleSAML\Logger dependency.
     *
     * @param \SimpleSAML\Logger $logger
     */
    public function setLogger(Logger $logger): void
    {
        $this->logger = $logger;
    }


    /**
     * Display consent form.
     *
     * @param \Symfony\Component\HttpFoundation\Request $request The current request.
     *
     * @return \SimpleSAML\XHTML\Template|\SimpleSAML\HTTP\RunnableResponse
     */
    public function getconsent(Request $request)
    {
        $this->logger::info('Consent - getconsent: Accessing consent interface');

        $stateId = $request->query->get('StateId');
        if ($stateId === null) {
            throw new Error\BadRequest('Missing required StateId query parameter.');
        }

        $state = $this->authState::loadState($stateId, 'consent:request');

        if (is_null($state)) {
            throw new Error\NoState();
        } elseif (array_key_exists('core:SP', $state)) {
            $spentityid = $state['core:SP'];
        } elseif (array_key_exists('saml:sp:State', $state)) {
            $spentityid = $state['saml:sp:State']['core:SP'];
        } else {
            $spentityid = 'UNKNOWN';
        }

        // The user has pressed the yes-button
        if ($request->query->get('yes') !== null) {
            if ($request->query->get('saveconsent') !== null) {
                $this->logger::stats('consentResponse remember');
            } else {
                $this->logger::stats('consentResponse rememberNot');
            }

            $statsInfo = [
                'remember' => $request->query->get('saveconsent'),
            ];
            if (isset($state['Destination']['entityid'])) {
                $statsInfo['spEntityID'] = $state['Destination']['entityid'];
            }
            Stats::log('consent:accept', $statsInfo);

            if (
                array_key_exists('consent:store', $state)
                && $request->query->get('saveconsent') === '1'
            ) {
                // Save consent
                $store = $state['consent:store'];
                $userId = $state['consent:store.userId'];
                $targetedId = $state['consent:store.destination'];
                $attributeSet = $state['consent:store.attributeSet'];

                $this->logger::debug(
                    'Consent - saveConsent() : [' . $userId . '|' . $targetedId . '|' . $attributeSet . ']',
                );
                try {
                    $store->saveConsent($userId, $targetedId, $attributeSet);
                } catch (Exception $e) {
                    $this->logger::error('Consent: Error writing to storage: ' . $e->getMessage());
                }
            }

            return new RunnableResponse([Auth\ProcessingChain::class, 'resumeProcessing'], [$state]);
        }

        // Prepare attributes for presentation
        $attributes = $state['Attributes'];
        $noconsentattributes = $state['consent:noconsentattributes'];

        // Remove attributes that do not require consent
        foreach ($attributes as $attrkey => $attrval) {
            if (in_array($attrkey, $noconsentattributes, true)) {
                unset($attributes[$attrkey]);
            }
        }
        $para = [
            'attributes' => &$attributes,
        ];

        // Reorder attributes according to attributepresentation hooks
        Module::callHooks('attributepresentation', $para);

        // Unset the values for attributes that need to be hidden
        if (array_key_exists('consent:hiddenAttributes', $state)) {
            foreach ($state['consent:hiddenAttributes'] as $hidden) {
                if (array_key_exists($hidden, $attributes)) {
                    $attributes[$hidden] = null;
                }
            }
        }

        // Make, populate and layout consent form
        $t = new Template($this->config, 'consent:consentform.twig');
        $l = $t->getLocalization();
        $l->addAttributeDomains();
        $t->data['attributes'] = $attributes;
        $t->data['checked'] = $state['consent:checked'];
        $t->data['stateId'] = $stateId;
        $t->data['source'] = $state['Source'];
        $t->data['destination'] = $state['Destination'];

        if (isset($state['Destination']['description'])) {
            $t->data['descr_purpose'] = $state['Destination']['description'];
        } elseif (isset($state['Destination']['UIInfo']['Description'])) {
            $t->data['descr_purpose'] = $state['Destination']['UIInfo']['Description'];
        }

        // Fetch privacy policy
        if (
            array_key_exists('UIInfo', $state['Destination']) &&
            array_key_exists('PrivacyStatementURL', $state['Destination']['UIInfo']) &&
            (!empty($state['Destination']['UIInfo']['PrivacyStatementURL']))
        ) {
            $privacypolicy = reset($state['Destination']['UIInfo']['PrivacyStatementURL']);
        } elseif (
            array_key_exists('UIInfo', $state['Source']) &&
            array_key_exists('PrivacyStatementURL', $state['Source']['UIInfo']) &&
            (!empty($state['Source']['UIInfo']['PrivacyStatementURL']))
        ) {
            $privacypolicy = reset($state['Source']['UIInfo']['PrivacyStatementURL']);
        } else {
            $privacypolicy = false;
        }
        if ($privacypolicy !== false) {
            $privacypolicy = str_replace(
                '%SPENTITYID%',
                urlencode($spentityid),
                $privacypolicy,
            );
        }
        $t->data['sppp'] = $privacypolicy;

        // Set focus element
        switch ($state['consent:focus']) {
            case 'yes':
                $t->data['autofocus'] = 'yesbutton';
                break;
            case 'no':
                $t->data['autofocus'] = 'nobutton';
                break;
            case null:
            default:
                break;
        }

        $t->data['usestorage'] = array_key_exists('consent:store', $state);

        return $t;
    }


    /**
     * @param \Symfony\Component\HttpFoundation\Request $request The current request.
     *
     * @return \SimpleSAML\XHTML\Template
     */
    public function noconsent(Request $request): Template
    {
        $stateId = $request->query->get('StateId');
        if ($stateId === null) {
            throw new Error\BadRequest('Missing required StateId query parameter.');
        }

        $state = $this->authState::loadState($stateId, 'consent:request');
        if (is_null($state)) {
            throw new Error\NoState();
        }

        $resumeFrom = Module::getModuleURL(
            'consent/getconsent',
            ['StateId' => $stateId],
        );

        $logoutLink = Module::getModuleURL(
            'consent/logout',
            ['StateId' => $stateId],
        );

        $aboutService = null;
        if (!isset($state['consent:showNoConsentAboutService']) || $state['consent:showNoConsentAboutService']) {
            if (isset($state['Destination']['UIInfo']['InformationURL'])) {
                $aboutService = reset($state['Destination']['UIInfo']['InformationURL']);
            }
        }

        $statsInfo = [];
        if (isset($state['Destination']['entityid'])) {
            $statsInfo['spEntityID'] = $state['Destination']['entityid'];
        }
        Stats::log('consent:reject', $statsInfo);

        $t = new Template($this->config, 'consent:noconsent.twig');
        $t->data['dstMetadata'] = $state['Destination'];
        $t->data['resumeFrom'] = $resumeFrom;
        $t->data['aboutService'] = $aboutService;
        $t->data['logoutLink'] = $logoutLink;
        return $t;
    }


    /**
     * @param \Symfony\Component\HttpFoundation\Request $request The current request.
     *
     * @return \SimpleSAML\HTTP\RunnableResponse
     */
    public function logout(Request $request): RunnableResponse
    {
        $stateId = $request->query->get('StateId', null);
        if ($stateId === null) {
            throw new Error\BadRequest('Missing required StateId query parameter.');
        }

        $state = $this->authState::loadState($stateId, 'consent:request');
        if (is_null($state)) {
            throw new Error\NoState();
        }
        $state['Responder'] = ['\SimpleSAML\Module\consent\Logout', 'postLogout'];

        $idp = IdP::getByState($state);
        return new RunnableResponse([$idp, 'handleLogoutRequest'], [&$state, $stateId]);
    }


    /**
     * @return \SimpleSAML\XHTML\Template
     */
    public function logoutcompleted(): Template
    {
        return new Template($this->config, 'consent:logout_completed.twig');
    }
}
