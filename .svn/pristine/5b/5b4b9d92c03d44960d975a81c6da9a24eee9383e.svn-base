<?php

declare(strict_types=1);

namespace SimpleSAML\Module\radius\Auth\Source;

use Exception;
use Dapphp\Radius\Radius as RadiusClient;
use SimpleSAML\Assert\Assert;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\Logger;
use SimpleSAML\Module\core\Auth\UserPassBase;
use SimpleSAML\Utils;

use function array_key_exists;
use function array_merge;
use function is_array;
use function sprintf;
use function strtok;
use function var_export;

/**
 * RADIUS authentication source.
 *
 * This class is based on www/auth/login-radius.php.
 *
 * @package SimpleSAMLphp
 */
class Radius extends UserPassBase
{
    public const RADIUS_USERNAME = 1;
    public const RADIUS_VENDOR_SPECIFIC = 26;
    public const RADIUS_NAS_IDENTIFIER = 32;

    /**
     * @var array The list of radius servers to use.
     */
    private array $servers;

    /**
     * @var string The hostname of the radius server.
     */
    private string $hostname;

    /**
     * @var int The port of the radius server.
     */
    private int $port;

    /**
     * @var string The secret used when communicating with the radius server.
     */
    private string $secret;

    /**
     * @var int The timeout for contacting the radius server.
     */
    private int $timeout;

    /**
     * @var string|null The realm to be added to the entered username.
     */
    private ?string $realm;

    /**
     * @var string|null The attribute name where the username should be stored.
     */
    private ?string $usernameAttribute = null;

    /**
     * @var int|null The vendor for the RADIUS attributes we are interrested in.
     */
    private ?int $vendor = null;

    /**
     * @var int The vendor-specific attribute for the RADIUS attributes we are
     *     interrested in.
     */
    private int $vendorType;

    /**
     * @var string|null The NAS-Identifier that should be set in Access-Request packets.
     */
    private ?string $nasIdentifier = null;

    /**
     * @var bool Debug modus
     */
    private bool $debug;


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

        // Parse configuration.
        $cfg = Configuration::loadFromArray(
            $config,
            'Authentication source ' . var_export($this->authId, true)
        );

        $this->servers = $cfg->getArray('servers');
        // For backwards compatibility
        if (empty($this->servers)) {
            $this->hostname = $cfg->getString('hostname');
            $this->port = $cfg->getOptionalIntegerRange('port', 1, 65535, 1812);
            $this->secret = $cfg->getString('secret');
            $this->servers[] = [
                'hostname' => $this->hostname,
                'port' => $this->port,
                'secret' => $this->secret
            ];
        }
        $this->debug = $cfg->getOptionalBoolean('debug', false);
        $this->timeout = $cfg->getOptionalInteger('timeout', 5);
        $this->realm = $cfg->getOptionalString('realm', null);
        $this->usernameAttribute = $cfg->getOptionalString('username_attribute', null);
        $this->nasIdentifier = $cfg->getOptionalString('nas_identifier', null);

        $this->vendor = $cfg->getOptionalInteger('attribute_vendor', null);
        if ($this->vendor !== null) {
            $this->vendorType = $cfg->getInteger('attribute_vendor_type');
        }
    }


    /**
     * Attempt to log in using the given username and password.
     *
     * @param string $username  The username the user wrote.
     * @param string $password  The password the user wrote.
     * @return array[] Associative array with the user's attributes.
     */
    protected function login(string $username, string $password): array
    {
        $radius = new RadiusClient();
        $response = false;

        // Try to add all radius servers, trigger a failure if no one works
        foreach ($this->servers as $server) {
            $radius->setServer($server['hostname']);
            $radius->setAuthenticationPort($server['port']);
            $radius->setSecret($server['secret']);
            $radius->setDebug($this->debug);
            $radius->setTimeout($this->timeout);
            $radius->setIncludeMessageAuthenticator();

            $httpUtils = new Utils\HTTP();
            $radius->setNasIpAddress($_SERVER['SERVER_ADDR'] ?: $httpUtils->getSelfHost());

            if ($this->nasIdentifier !== null) {
                $radius->setAttribute(self::RADIUS_NAS_IDENTIFIER, $this->nasIdentifier);
            }

            if ($this->realm !== null) {
                $radius->setRadiusSuffix('@' . $this->realm);
            }
            $response = $radius->accessRequest($username, $password);

            if ($response !== false) {
                break;
            }
        }

        if ($response === false) {
            $errorCode = $radius->getErrorCode();
            switch ($errorCode) {
                case $radius::TYPE_ACCESS_REJECT:
                    throw new Error\Error('WRONGUSERPASS');
                case $radius::TYPE_ACCESS_CHALLENGE:
                    throw new Exception('Radius authentication error: Challenge requested, but not supported.');
                default:
                    throw new Exception(sprintf(
                        'Error during radius authentication; %s (%d)',
                        $radius->getErrorMessage(),
                        $errorCode
                    ));
            }
        }

        // If we get this far, we have a valid login

        $attributes = [];
        if ($this->usernameAttribute !== null) {
            $attributes[$this->usernameAttribute] = [$username];
        }

        if ($this->vendor === null) {
            /*
             * We aren't interested in any vendor-specific attributes. We are
             * therefore done now.
             */
            return $attributes;
        } else {
            foreach ($radius->getReceivedAttributes() as $content) {
                if ($content[0] == 26) { // is a Vendor-Specific attribute
                    $vsa = $radius->decodeVendorSpecificContent($content[1]);

                    // matches configured Vendor and Type
                    if ($vsa[0][0] === $this->vendor && $vsa[0][1] === $this->vendorType) {
                        // SAML attributes expected in a URN=value, so split at first =
                        $decomposed = explode("=", $vsa[0][2], 2);
                        $attributes[$decomposed[0]][] = $decomposed[1];
                    }
                }
            }
        }

        return array_merge($attributes, $this->getAttributes($radius));
    }


    /**
     * @param \Dapphp\Radius\Radius $radius
     * @return array
     */
    private function getAttributes(RadiusClient $radius): array
    {
        // get AAI attribute sets.
        $resa = $radius->getReceivedAttributes();
        $attributes = [];

        // Use the received user name
        if ($resa['attr'] === self::RADIUS_USERNAME && $this->usernameAttribute !== null) {
            $attributes[$this->usernameAttribute] = [$resa['data']];
            return $attributes;
        }

        if ($resa['attr'] !== self::RADIUS_VENDOR_SPECIFIC) {
            return $attributes;
        }

        $resv = $resa['data'];
        if ($resv === false) {
            throw new Exception(sprintf(
                'Error getting vendor specific attribute',
                $radius->getErrorMessage(),
                $radius->getErrorCode()
            ));
        }

        $vendor = $resv['vendor'];
        $attrv = $resv['attr'];
        $datav = $resv['data'];

        if ($vendor !== $this->vendor || $attrv !== $this->vendorType) {
            return $attributes;
        }

        $attrib_name = strtok($datav, '=');
        /** @psalm-suppress TooFewArguments */
        $attrib_value = strtok('=');

        // if the attribute name is already in result set, add another value
        if (array_key_exists($attrib_name, $attributes)) {
            $attributes[$attrib_name][] = $attrib_value;
        } else {
            $attributes[$attrib_name] = [$attrib_value];
        }

        return $attributes;
    }
}
