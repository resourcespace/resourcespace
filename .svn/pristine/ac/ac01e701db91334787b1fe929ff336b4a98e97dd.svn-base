<?php

declare(strict_types=1);

namespace SimpleSAML\Module\ldap\Auth;

use function array_merge_recursive;
use function explode;
use function in_array;
use function preg_match;
use function str_replace;
use function strpos;

/**
 * Class representing an InvalidCredential Result
 *
 * This is used for extended diagnostic information
 *
 * @package simplesamlphp/simplesamlphp-module-ldap
 */
class InvalidCredentialResult
{
    /**
     * List of Active Directory Bind Error Short Description's
     *
     * @see https://ldapwiki.com/wiki/Common%20Active%20Directory%20Bind%20Errors
     */
    public const LDAP_NO_SUCH_OBJECT = '525';
    public const ERROR_LOGON_FAILURE = '52e';
    public const ERROR_ACCOUNT_RESTRICTION = '52f';
    public const ERROR_INVALID_LOGON_HOURS = '530';
    public const ERROR_INVALID_WORKSTATION = '531';
    public const ERROR_PASSWORD_EXPIRED = '532';
    public const ERROR_ACCOUNT_DISABLED = '533';
    public const ERROR_TOO_MANY_CONTEXT_IDS = '568';
    public const ERROR_ACCOUNT_EXPIRED = '701';
    public const ERROR_PASSWORD_MUST_CHANGE = '773';
    public const ERROR_ACCOUNT_LOCKED_OUT = '775';

    /**
     * List of Simple Bind error codes
     *
     * N.B. - This is an incomplete list
     */
    public const NT_STATUS_PASSWORD_EXPIRED = 'PASSWORD_EXPIRED';
    public const NT_STATUS_PASSWORD_MUST_CHANGE = 'PASSWORD_MUST_CHANGE';
    public const NT_STATUS_LOGON_FAILURE = 'LOGON_FAILURE';

    /**
     * List of keys for the code mapping
     */
    public const KEY_INVALID_CREDENTIAL = 'invalid_credential';
    public const KEY_PASSWORD_ERROR = 'password_error';
    public const KEY_ACCOUNT_ERROR = 'account_error';
    public const KEY_RESTRICTION = 'restriction';

    /**
     * Map of keys to check the code against when using is* methods
     *
     * @var array
     */
    protected array $codeMap = [
        self::KEY_INVALID_CREDENTIAL => [
            self::ERROR_LOGON_FAILURE,
            self::LDAP_NO_SUCH_OBJECT,
            self::NT_STATUS_LOGON_FAILURE,
        ],
        self::KEY_PASSWORD_ERROR => [
            self::ERROR_PASSWORD_EXPIRED,
            self::ERROR_PASSWORD_MUST_CHANGE,
            self::NT_STATUS_PASSWORD_EXPIRED,
            self::NT_STATUS_PASSWORD_MUST_CHANGE,
        ],
        self::KEY_ACCOUNT_ERROR => [
            self::ERROR_ACCOUNT_DISABLED,
            self::ERROR_ACCOUNT_EXPIRED,
            self::ERROR_ACCOUNT_LOCKED_OUT,
        ],
        self::KEY_RESTRICTION => [
            self::ERROR_ACCOUNT_RESTRICTION,
            self::ERROR_INVALID_LOGON_HOURS,
            self::ERROR_INVALID_WORKSTATION,
            self::ERROR_TOO_MANY_CONTEXT_IDS,
        ],
    ];

    /**
     * For Simple Binds this is the part after NT_STATUS_
     * Otherwise it is the HEX code from `data ([0-9a-f]+)`
     *
     * @var string|null The error code.
     */
    protected ?string $code;

    /**
     * @var string the message as it came from LDAP
     */
    protected string $rawMessage;


    /**
     * Parses the message when possible to determine what the actual error is
     *
     * @param string $message
     *
     * @return \SimpleSAML\Module\ldap\Auth\InvalidCredentialResult
     */
    public static function fromDiagnosticMessage(string $message): self
    {
        if (strpos($message, 'Simple Bind Failed:') === 0) {
            list(, $tmp) = explode(':', $message, 2);
            $code = str_replace('NT_STATUS_', '', $tmp);
        } elseif (preg_match('/data\s(.*)?,/', $message, $match)) {
            $code = $match[1];
        } else {
            $code = null;
        }

        return new self($code, $message);
    }


    /**
     * @param string|null $code
     * @param string $rawMessage
     */
    protected function __construct(?string $code, string $rawMessage)
    {
        $this->code = $code;
        $this->rawMessage = $rawMessage;
    }


    /**
     * Returns the code that was pulled from the raw message
     *
     * @return string|null
     */
    public function getCode(): ?string
    {
        return $this->code;
    }


    /**
     * Returns the raw message
     *
     * @return string
     */
    public function getRawMessage(): string
    {
        return $this->rawMessage;
    }


    /**
     * Allows the default code mappings to be updated
     * @param array $codes
     * @return void
     */
    public function updateCodeMap(array $codes): void
    {
        $this->codeMap = array_merge_recursive($this->codeMap, $codes);
    }


    /**
     * Allows the default code mappings to be replaced
     *
     * @param array $codes
     * @return void
     */
    public function replaceCodeMap(array $codes): void
    {
        $this->codeMap = $codes;
    }


    /**
     * @return bool Whether or not the password had an error
     */
    public function isPasswordError(): bool
    {
        return in_array($this->code, $this->codeMap[self::KEY_PASSWORD_ERROR]);
    }


    /**
     * @return bool Whether or not the account had an error
     */
    public function isAccountError(): bool
    {
        return in_array($this->code, $this->codeMap[self::KEY_ACCOUNT_ERROR]);
    }


    /**
     * @return bool Whether or not there was an auth problem
     */
    public function isInvalidCredential(): bool
    {
        return in_array($this->code, $this->codeMap[self::KEY_INVALID_CREDENTIAL]);
    }


    /**
     * @return bool Whether or not there is a restriction in place
     */
    public function isRestricted(): bool
    {
        return in_array($this->code, $this->codeMap[self::KEY_RESTRICTION]);
    }
}
