<?php

declare(strict_types=1);

namespace SimpleSAML\Module\ldap\Connector;

use SimpleSAML\Utils;

use function dechex;
use function ord;
use function str_pad;
use function str_replace;
use function strlen;
use function substr;

trait LdapHelpers
{
    /**
     * Escapes the given VALUES according to RFC 2254 so that they can be safely used in LDAP filters.
     *
     * Any control characters with an ASCII code < 32 as well as the characters with special meaning in
     * LDAP filters "*", "(", ")", and "\" (the backslash) are converted into the representation of a
     * backslash followed by two hex digits representing the hexadecimal value of the character.
     *
     * @param string|string[] $values Array of values to escape
     * @param bool $singleValue
     * @return string|string[] Array $values, but escaped
     */
    public function escapeFilterValue($values = [], bool $singleValue = true)
    {
        // Parameter validation
        $arrayUtils = new Utils\Arrays();
        $values = $arrayUtils->arrayize($values);

        foreach ($values as $key => $val) {
            if ($val === null) {
                $val = '\0'; // apply escaped "null" if string is empty
            } else {
                // Escaping of filter meta characters
                $val = str_replace('\\', '\5c', $val);
                $val = str_replace('*', '\2a', $val);
                $val = str_replace('(', '\28', $val);
                $val = str_replace(')', '\29', $val);

                // ASCII < 32 escaping
                $val = $this->asc2hex32($val);
            }

            $values[$key] = $val;
        }

        if ($singleValue) {
            return $values[0];
        }

        return $values;
    }


    /**
     * Converts all ASCII chars < 32 to "\HEX"
     *
     * @param string $string String to convert
     * @return string
     */
    public function asc2hex32(string $string): string
    {
        for ($i = 0; $i < strlen($string); $i++) {
            $char = substr($string, $i, 1);

            if (ord($char) < 32) {
                $hex = str_pad(dechex(ord($char)), 2, '0', STR_PAD_LEFT);
                $string = str_replace($char, '\\' . $hex, $string);
            }
        }

        return $string;
    }
}
