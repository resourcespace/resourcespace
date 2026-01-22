<?php

declare(strict_types=1);

namespace SimpleSAML\Module\authorize\Auth\Process;

use Exception;
use SimpleSAML\Assert\Assert;
use SimpleSAML\Auth;
use SimpleSAML\Module;
use SimpleSAML\Utils;

use function array_diff;
use function array_key_exists;
use function array_keys;
use function array_push;
use function implode;
use function is_array;
use function is_bool;
use function is_string;
use function preg_match;
use function var_export;

/**
 * Filter to authorize only certain users.
 * See docs directory.
 *
 * @package SimpleSAMLphp
 */

class Authorize extends Auth\ProcessingFilter
{
    /**
     * Flag to deny/unauthorize the user a attribute filter IS found
     *
     * @var bool
     */
    protected bool $deny = false;

    /**
     * Flag to turn the REGEX pattern matching on or off
     *
     * @var bool
     */
    protected bool $regex = true;

    /**
     * Array of localised rejection messages
     *
     * @var array
     */
    protected array $reject_msg = [];

    /**
     * Flag to toggle generation of errorURL
     *
     * @var bool
     */
    protected bool $errorURL = true;

    /**
     * Array of valid users. Each element is a regular expression. You should
     * user \ to escape special chars, like '.' etc.
     *
     * @param array
     */
    protected array $valid_attribute_values = [];

    /**
     * Flag to allow re-authentication when user is not authorized
     * @var bool
     */
    protected bool $allow_reauthentication = false;

    /**
     * The attribute to show in the error page
     * @var string|null
     */
    protected ?string $show_user_attribute = null;

    /**
     * Initialize this filter.
     * Validate configuration parameters.
     *
     * @param array $config  Configuration information about this filter.
     * @param mixed $reserved  For future use.
     */
    public function __construct(array $config, $reserved)
    {
        parent::__construct($config, $reserved);

        // Check for the deny option
        // Must be bool specifically, if not, it might be for an attrib filter below
        if (isset($config['deny']) && is_bool($config['deny'])) {
            $this->deny = $config['deny'];
            unset($config['deny']);
        }

        // Check for the regex option
        // Must be bool specifically, if not, it might be for an attrib filter below
        if (isset($config['regex']) && is_bool($config['regex'])) {
            $this->regex = $config['regex'];
            unset($config['regex']);
        }

        // Check for the reject_msg option; Must be array of languages
        if (isset($config['reject_msg']) && is_array($config['reject_msg'])) {
            $this->reject_msg = $config['reject_msg'];
            unset($config['reject_msg']);
        }

        // Check for the errorURL option
        // Must be bool specifically, if not, it might be for an attrib filter below
        if (isset($config['errorURL']) && is_bool($config['errorURL'])) {
            $this->errorURL = $config['errorURL'];
            unset($config['errorURL']);
        }

        if (isset($config['allow_reauthentication']) && is_bool($config['allow_reauthentication'])) {
            $this->allow_reauthentication = $config['allow_reauthentication'];
            unset($config['allow_reauthentication']);
        }

        if (isset($config['show_user_attribute']) && is_string($config['show_user_attribute'])) {
            $this->show_user_attribute = $config['show_user_attribute'];
            unset($config['show_user_attribute']);
        }

        foreach ($config as $attribute => $values) {
            if (is_string($values)) {
                $arrayUtils = new Utils\Arrays();
                $values = $arrayUtils->arrayize($values);
            } elseif (!is_array($values)) {
                throw new Exception(sprintf(
                    'Filter Authorize: Attribute values is neither string nor array: %s',
                    var_export($attribute, true),
                ));
            }

            foreach ($values as $value) {
                if (!is_string($value)) {
                    throw new Exception(sprintf(
                        'Filter Authorize: Each value should be a string for attribute: %s value: %s config: %s',
                        var_export($attribute, true),
                        var_export($value, true),
                        var_export($config, true),
                    ));
                }
            }
            $this->valid_attribute_values[$attribute] = $values;
        }
    }


    /**
     * Apply filter to validate attributes.
     *
     * @param array &$state  The current request
     */
    public function process(array &$state): void
    {
        Assert::keyExists($state, 'Attributes');

        $authorize = $this->deny;
        $attributes = &$state['Attributes'];
        $ctx = [];

        // Store the rejection message array in the $state
        if (!empty($this->reject_msg)) {
            $state['authprocAuthorize_reject_msg'] = $this->reject_msg;
        }
        $state['authprocAuthorize_errorURL'] = $this->errorURL;
        $state['authprocAuthorize_allow_reauthentication'] = $this->allow_reauthentication;
        $arrayUtils = new Utils\Arrays();
        foreach ($this->valid_attribute_values as $name => $patterns) {
            if (array_key_exists($name, $attributes)) {
                foreach ($patterns as $pattern) {
                    $values = $arrayUtils->arrayize($attributes[$name]);
                    foreach ($values as $value) {
                        if ($this->regex) {
                            $matched = preg_match($pattern, $value);
                        } else {
                            $matched = ($value === $pattern);
                        }

                        if ($matched) {
                            $authorize = ($this->deny ? false : true);
                            array_push($ctx, $name);
                            break 3;
                        }
                    }
                }
            }
        }

        if (!$authorize) {
            if ($this->show_user_attribute !== null && array_key_exists($this->show_user_attribute, $attributes)) {
                $userAttribute =  $attributes[$this->show_user_attribute][0] ?? null;
                if ($userAttribute !== null) {
                    $state['authprocAuthorize_user_attribute'] = $userAttribute;
                }
            }

            // Try to hint at which attributes may have failed as context for errorURL processing
            if ($this->deny) {
                $state['authprocAuthorize_ctx'] = implode(' ', $ctx);
            } else {
                $state['authprocAuthorize_ctx'] = implode(
                    ' ',
                    array_diff(array_keys($this->valid_attribute_values), $ctx),
                );
            }
            $this->unauthorized($state);
        }
    }


    /**
     * When the process logic determines that the user is not
     * authorized for this service, then forward the user to
     * an 403 unauthorized page.
     *
     * Separated this code into its own method so that child
     * classes can override it and change the action. Forward
     * thinking in case a "chained" ACL is needed, more complex
     * permission logic.
     *
     * @param array $state
     */
    protected function unauthorized(array &$state): void
    {
        // Save state and redirect to 403 page
        $id = Auth\State::saveState($state, 'authorize:Authorize');
        $url = Module::getModuleURL('authorize/error/forbidden');
        $httpUtils = new Utils\HTTP();
        $httpUtils->redirectTrustedURL($url, ['StateId' => $id]);
    }
}
