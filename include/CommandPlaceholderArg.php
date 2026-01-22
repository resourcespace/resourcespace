<?php

declare(strict_types=1);

namespace Montala\ResourceSpace;

use Exception;

final class CommandPlaceholderArg
{
    /**
     * A placeholders' value representing ONE command argument value (highly contextual).
     */
    private string $value;

    /**
     * Constructor
     *
     * @param string $value The actual placeholder value
     * @param null|callable $validator Use null for the default one, otherwise any callable where the value to be
     * tested is the first argument.
     */
    public function __construct(string $value, ?callable $validator)
    {
        $validator ??= [__CLASS__, 'defaultValidator'];
        if ($validator($value)) {
            $this->value = $value;
            return;
        }

        debug('Invalid placeholder argument value: ' . $value);
        throw new Exception('Invalid placeholder argument value: ' . $value);
    }

    /**
     * Input validation helper function for determining if there are any blocked metacharacters which could be used to
     * exploit OS command injections.
     */
    public static function defaultValidator(string $val): bool
    {
        // ; & | $ > < ` \ ! ' " ( ) including white space
        return !preg_match('/[;&|\$><`\\!\'"()\s]/', $val);
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Basic function to enable bypassing of any validation of the provided value.
     * Only to be used when the value has been constructed without any unpredictable user input or
     * has been thoroughly pre-sanitized
    */
    public static function alwaysValid(string $val): bool
    {
        return true;
    }
}
