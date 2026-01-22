<?php

declare(strict_types=1);

namespace SimpleSAML\Module\discopower;

use Exception;
use SimpleSAML\Assert\Assert;
use SimpleSAML\Auth;
use SimpleSAML\Configuration;
use SimpleSAML\Locale\Translate;
use SimpleSAML\Logger;
use SimpleSAML\Module;
use SimpleSAML\Session;
use SimpleSAML\Utils;
use SimpleSAML\XHTML\IdPDisco;
use SimpleSAML\XHTML\Template;

/**
 * This class implements a generic IdP discovery service, for use in various IdP discovery service pages. This should
 * reduce code duplication.
 *
 * This module extends the basic IdP disco handler, and add features like filtering and tabs.
 *
 * @package SimpleSAMLphp
 */
class PowerIdPDisco extends IdPDisco
{
    /**
     * The configuration for this instance.
     *
     * @var \SimpleSAML\Configuration
     */
    private Configuration $discoconfig;

    /**
     * The domain to use when saving common domain cookies. This is null if support for common domain cookies is
     * disabled.
     *
     * @var string|null
     */
    private ?string $cdcDomain;

    /**
     * The lifetime of the CDC cookie, in seconds. If set to null, it will only be valid until the browser is closed.
     *
     * @var int|null
     */
    private ?int $cdcLifetime;


    /**
     * The default sort weight for entries without 'discopower.weight'.
     *
     * @var int|null
     */
    private static ?int $defaultWeight = 100;

    /**
     * Initializes this discovery service.
     *
     * The constructor does the parsing of the request. If this is an invalid request, it will throw an exception.
     *
     * @param array  $metadataSets Array with metadata sets we find remote entities in.
     * @param string $instance The name of this instance of the discovery service.
     */
    public function __construct(array $metadataSets, string $instance)
    {
        parent::__construct($metadataSets, $instance);

        $this->discoconfig = Configuration::getConfig('module_discopower.php');

        $this->cdcDomain = $this->discoconfig->getOptionalString('cdc.domain', null);
        if ($this->cdcDomain !== null && $this->cdcDomain[0] !== '.') {
            // ensure that the CDC domain starts with a dot ('.') as required by the spec
            $this->cdcDomain = '.' . $this->cdcDomain;
        }

        $this->cdcLifetime = $this->discoconfig->getOptionalInteger('cdc.lifetime', null);

        self::$defaultWeight = $this->discoconfig->getOptionalInteger('defaultweight', 100);
    }


    /**
     * Log a message.
     *
     * This is an helper function for logging messages. It will prefix the messages with our discovery service type.
     *
     * @param string $message The message which should be logged.
     */
    protected function log(string $message): void
    {
        Logger::info('PowerIdPDisco.' . $this->instance . ': ' . $message);
    }


    /**
     * Compare two entities.
     *
     * This function is used to sort the entity list. It sorts based on weights,
     * and where those aren't available, English name. It puts larger weights
     * higher, and will always put IdP's with names configured before those with
     * only an entityID.
     *
     * @param array $a The metadata of the first entity.
     * @param array $b The metadata of the second entity.
     *
     * @return int How $a compares to $b.
     */
    public static function mcmp(array $a, array $b): int
    {
        // default weights
        if (!isset($a['discopower.weight']) || !is_int($a['discopower.weight'])) {
            $a['discopower.weight'] = self::$defaultWeight;
        }
        if (!isset($b['discopower.weight']) || !is_int($b['discopower.weight'])) {
            $b['discopower.weight'] = self::$defaultWeight;
        }
        if ($a['discopower.weight'] > $b['discopower.weight']) {
            return -1; // higher weights further up
        } elseif ($b['discopower.weight'] > $a['discopower.weight']) {
            return 1; // lower weights further down
        } elseif (isset($a['name']['en']) && isset($b['name']['en'])) {
            return strcasecmp($a['name']['en'], $b['name']['en']);
        } elseif (isset($a['name']['en'])) {
            return -1; // place name before entity ID
        } elseif (isset($b['name']['en'])) {
            return 1; // Place entity ID after name
        } else {
            return strcasecmp($a['entityid'], $b['entityid']);
        }
    }


    /**
     * Structure the list of IdPs in a hierarchy based upon the tags.
     *
     * @param array $list A list of IdPs.
     *
     * @return array The list of IdPs structured accordingly.
     */
    protected function idplistStructured(array $list): array
    {
        $slist = [];

        $order = $this->discoconfig->getOptionalArray('taborder', []);
        foreach ($order as $oe) {
            $slist[$oe] = [];
        }

        $enableTabs = $this->discoconfig->getOptionalArray('tabs', []);

        foreach ($list as $key => $val) {
            $tags = ['misc'];
            if (array_key_exists('tags', $val)) {
                $tags = $val['tags'];
            }


            foreach ($tags as $tag) {
                if (!empty($enableTabs) && !in_array($tag, $enableTabs)) {
                    continue;
                }
                $slist[$tag][$key] = $val;
            }
        }

        foreach ($slist as $tab => $tbslist) {
            uasort($slist[$tab], [self::class, 'mcmp']);
            // reorder with a hook if one exists
            Module::callHooks('discosort', $slist[$tab]);
        }

        return $slist;
    }


    /**
     * Do the actual filtering according the rules defined.
     *
     * @param array   $filter A set of rules regarding filtering.
     * @param array   $entry An entry to be evaluated by the filters.
     * @param boolean $default What to do in case the entity does not match any rules. Defaults to true.
     *
     * @return boolean True if the entity should be kept, false if it should be discarded according to the filters.
     */
    private function processFilter(array $filter, array $entry, bool $default = true): bool
    {
        if (in_array($entry['entityid'], $filter['entities.include'])) {
            return true;
        }
        if (in_array($entry['entityid'], $filter['entities.exclude'])) {
            return false;
        }

        if (array_key_exists('tags', $entry)) {
            foreach ($filter['tags.include'] as $fe) {
                if (in_array($fe, $entry['tags'])) {
                    return true;
                }
            }
            foreach ($filter['tags.exclude'] as $fe) {
                if (in_array($fe, $entry['tags'])) {
                    return false;
                }
            }
        }
        return $default;
    }


    /**
     * Filter a list of entities according to any filters defined in the parent class, plus discopower configuration
     * options regarding filtering.
     *
     * @param array $list A list of entities to filter.
     *
     * @return array The list in $list after filtering entities.
     */
    protected function filterList(array $list): array
    {
        $list = parent::filterList($list);

        try {
            $spmd = $this->metadata->getMetaData($this->spEntityId, 'saml20-sp-remote');
        } catch (Exception $e) {
            if (
                $this->discoconfig->getOptionalBoolean('useunsafereturn', false)
                && array_key_exists('return', $_GET)
            ) {
                /*
                 * Get the SP metadata from the other side of the protocol bridge by retrieving the state.
                 * Because the disco is not explicitly passed the state ID, we can use a crude hack to
                 * infer it from the return parameter. This should be relatively safe because we're not
                 * going to trust it for anything other than finding the `discopower.filter` elements,
                 * and because the SP could bypass all of this anyway by specifying a known IdP in scoping.
                 */
                try {
                    parse_str(parse_url($_GET['return'], PHP_URL_QUERY), $returnState);
                    $state = Auth\State::loadState($returnState['AuthID'], 'saml:sp:sso');
                    if ($state && array_key_exists('SPMetadata', $state)) {
                        $spmd = $state['SPMetadata'];
                        $this->log('Updated SP metadata from ' . $this->spEntityId . ' to ' . $spmd['entityid']);
                    }
                } catch (Exception $e) {
                    return $list;
                }
            } else {
                return $list;
            }
        }

        if (!isset($spmd) || !array_key_exists('discopower.filter', $spmd)) {
            return $list;
        }
        $filter = $spmd['discopower.filter'];

        if (!array_key_exists('entities.include', $filter)) {
            $filter['entities.include'] = [];
        }
        if (!array_key_exists('entities.exclude', $filter)) {
            $filter['entities.exclude'] = [];
        }
        if (!array_key_exists('tags.include', $filter)) {
            $filter['tags.include'] = [];
        }
        if (!array_key_exists('tags.exclude', $filter)) {
            $filter['tags.exclude'] = [];
        }

        $defaultrule = true;
        if (
            array_key_exists('entities.include', $spmd['discopower.filter'])
            || array_key_exists('tags.include', $spmd['discopower.filter'])
        ) {
            $defaultrule = false;
        }

        $returnlist = [];
        foreach ($list as $key => $entry) {
            if ($this->processFilter($filter, $entry, $defaultrule)) {
                $returnlist[$key] = $entry;
            }
        }
        return $returnlist;
    }


    /**
     * Handles a request to this discovery service.
     *
     * The IdP disco parameters should be set before calling this function.
     */
    public function handleRequest(): void
    {
        $this->start();

        // no choice made. Show discovery service page
        $idpList = $this->getIdPList();
        $idpList = $this->idplistStructured($this->filterList($idpList));
        $preferredIdP = $this->getRecommendedIdP();

        $t = new Template($this->config, 'discopower:disco.twig');
        $translator = $t->getTranslator();

        $t->data['return'] = $this->returnURL;
        $t->data['returnIDParam'] = $this->returnIdParam;
        $t->data['entityID'] = $this->spEntityId;
        $t->data['defaulttab'] = $this->discoconfig->getOptionalInteger('defaulttab', 0);

        $idpList = $this->processMetadata($t, $idpList);

        $t->data['idplist'] = $idpList;
        $t->data['faventry'] = null;
        foreach ($idpList as $tab => $slist) {
            if (!empty($preferredIdP) && array_key_exists($preferredIdP, $slist)) {
                $t->data['faventry'] = $slist[$preferredIdP];
                break;
            }
        }

        if (isset($t->data['faventry'])) {
            $t->data['autofocus'] = 'favouritesubmit';
        }

        /* store the tab list in the session */
        $session = Session::getSessionFromRequest();
        if (array_key_exists('faventry', $t->data)) {
            $session->setData('discopower:tabList', 'faventry', $t->data['faventry']);
        }
        $session->setData('discopower:tabList', 'tabs', array_keys($idpList));
        $session->setData('discopower:tabList', 'defaulttab', $t->data['defaulttab']);

        $httpUtils = new Utils\HTTP();
        $t->data['score'] = $this->discoconfig->getOptionalString('score', 'quicksilver');
        $t->data['preferredidp'] = $preferredIdP;
        $t->data['urlpattern'] = htmlspecialchars($httpUtils->getSelfURLNoQuery());
        $t->data['rememberenabled'] = $this->config->getOptionalBoolean('idpdisco.enableremember', false);
        $t->data['rememberchecked'] = $this->config->getOptionalBoolean('idpdisco.rememberchecked', false);
        foreach (array_keys($idpList) as $tab) {
            Assert::regex(
                $tab,
                '/^[a-z_][a-z0-9_-]+$/',
                'Tags can contain alphanumeric characters, hyphens and underscores.'
                . ' They must start with a A-Z or an underscore.',
            );

            $translatableTag = "{discopower:tabs:$tab}";
            if ($translator::translateSingularGettext($translatableTag) === $translatableTag) {
                $t->data['tabNames'][$tab] = $translator::noop($tab);
            } else {
                $t->data['tabNames'][$tab] = $translator::noop($translatableTag);
            }
        }
        $t->send();
    }


    /**
     * @param \SimpleSAML\XHTML\Template $t
     * @param array $metadata
     * @return array
     */
    private function processMetadata(Template $t, array $metadata): array
    {
        $basequerystring = '?' .
            'entityID=' . urlencode($t->data['entityID']) . '&' .
            'return=' . urlencode($t->data['return']) . '&' .
            'returnIDParam=' . urlencode($t->data['returnIDParam']) . '&idpentityid=';

        $httpUtils = new Utils\HTTP();
        foreach ($metadata as $tab => $idps) {
            foreach ($idps as $entityid => $entity) {
                $entity['actionUrl'] = $basequerystring . urlencode($entity['entityid']);
                if (array_key_exists('icon', $entity) && $entity['icon'] !== null) {
                    $entity['iconUrl'] = $httpUtils->resolveURL($entity['icon']);
                }
                $entity['keywords'] = implode(' ',
                    $t->getEntityPropertyTranslation('Keywords', $entity['UIInfo'] ?? []) ?? []
                );
                $metadata[$tab][$entityid] = $entity;
            }
        }
        return $metadata;
    }


    /**
     * Get the IdP entities saved in the common domain cookie.
     *
     * @return array List of IdP entities.
     */
    private function getCDC(): array
    {
        if (!isset($_COOKIE['_saml_idp'])) {
            return [];
        }

        $ret = (string) $_COOKIE['_saml_idp'];
        $ret = explode(' ', $ret);
        foreach ($ret as &$idp) {
            $idp = base64_decode($idp);
            if ($idp === false) {
                // not properly base64 encoded
                return [];
            }
        }

        return $ret;
    }


    /**
     * Save the current IdP choice to a cookie.
     *
     * This function overrides the corresponding function in the parent class, to add support for common domain cookie.
     *
     * @param string $idp The entityID of the IdP.
     */
    protected function setPreviousIdP(string $idp): void
    {
        if ($this->cdcDomain === null) {
            parent::setPreviousIdP($idp);
            return;
        }

        $list = $this->getCDC();

        $prevIndex = array_search($idp, $list, true);
        if ($prevIndex !== false) {
            unset($list[$prevIndex]);
        }
        $list[] = $idp;

        foreach ($list as &$value) {
            $value = base64_encode($value);
        }
        $newCookie = implode(' ', $list);

        while (strlen($newCookie) > 4000) {
            // the cookie is too long. Remove the oldest elements until it is short enough
            $tmp = explode(' ', $newCookie, 2);
            if (count($tmp) === 1) {
                // we are left with a single entityID whose base64 representation is too long to fit in a cookie
                break;
            }
            $newCookie = $tmp[1];
        }

        $params = [
            'lifetime' => $this->cdcLifetime,
            'domain'   => $this->cdcDomain,
            'secure'   => true,
            'httponly' => false,
        ];

        $httpUtils = new Utils\HTTP();
        $httpUtils->setCookie('_saml_idp', $newCookie, $params, false);
    }


    /**
     * Retrieve the previous IdP the user used.
     *
     * This function overrides the corresponding function in the parent class, to add support for common domain cookie.
     *
     * @return string|null The entity id of the previous IdP the user used, or null if this is the first time.
     */
    protected function getPreviousIdP(): ?string
    {
        if ($this->cdcDomain === null) {
            return parent::getPreviousIdP();
        }

        $prevIdPs = $this->getCDC();
        while (count($prevIdPs) > 0) {
            $idp = array_pop($prevIdPs);
            $idp = $this->validateIdP($idp);
            if ($idp !== null) {
                return $idp;
            }
        }

        return null;
    }
}
