<?php

# Language functions
# Functions for the translation of the application

/**
 * Translates field names / values using two methods: First it checks if $text exists in the current $lang (after $text is sanitized and $mixedprefix - one by one if an array - and $suffix are added). If not found in the $lang, it tries to translate $text using the i18n_get_translated function.
 *
 * @param  string $text
 * @param  string $mixedprefix
 * @param  string $suffix
 * @return string
 */
function lang_or_i18n_get_translated($text, $mixedprefix, $suffix = "")
{
    $text = trim((string) $text);
    global $lang;

    if (is_array($mixedprefix)) {
        $prefix = $mixedprefix;
    } else {
        $prefix = array($mixedprefix);
    }
    for ($n = 0; $n < count($prefix); $n++) {
        $langindex = $prefix[$n] . strip_tags(strtolower(str_replace(array(", ", " ", "\t", "/", "(", ")"), array("-", "_", "_", "and", "", ""), $text))) . $suffix;

        # Checks if there is a $lang (should be defined for all standard field names / values).
        if (isset($lang[$langindex])) {
            $return = $lang[$langindex];
            break;
        }
    }
    if (isset($return)) {
        return $return;
    } else {
        return i18n_get_translated($text);
    } # Performs an i18n translation (of probably a custom field name / value).
}

/**
 * For field names / values using the i18n syntax, return the version in the current user's language. Format is ~en:Somename~es:Someothername
 *
 * @param  string|null $text
 * @param  string|null $lang_domain As an optional fallback if an i18n string is not provided, check the system language translations for a matching translation instead.

 * @return string
 */
function i18n_get_translated($text, $lang_domain = null)
{
    $text ??= '';

    global $language,$defaultlanguage,$lang;

    $asdefaultlanguage = $defaultlanguage;

    if (!isset($asdefaultlanguage)) {
        $asdefaultlanguage = 'en';
    }

    # Split
    $s = explode("~", $text);

    # Not a translatable field?
    if (count($s) < 2) {
        if (!is_null($lang_domain)) {
            # Search the provided $lang domain for a match and return that if present.
            $key = $lang_domain . "-" . preg_replace("/[^a-z0-9\-]/", '', strtolower(str_replace(" ", "-", $text)));
            if (array_key_exists($key, $lang)) {
                return $lang[$key];
            }
        }
        return $text;
    }

    # Find the current language and return it
    $default = "";
    for ($n = 1; $n < count($s); $n++) {
        # Not a translated string, return as-is
        if (substr($s[$n], 2, 1) != ":" && substr($s[$n], 5, 1) != ":" && substr($s[$n], 0, 1) != ":") {
            return $text;
        }

        # Support both 2 character and 5 character language codes (for example en, en-US).
        $p = strpos($s[$n], ':');
        $textLanguage = substr($s[$n], 0, $p);
        if (strtolower($textLanguage) == strtolower($language)) {
            return substr($s[$n], $p + 1);
        }

        if (strtolower($textLanguage) == strtolower($asdefaultlanguage) || $p == 0 || $n == 1) {
            $default = substr($s[$n], $p + 1);
        }
    }

    # Translation not found? Return default language
    # No default language entry? Then consider this a broken language string and return the string unprocessed.
    if ($default != "") {
        return $default;
    } else {
        return $text;
    }
}

/**
 * Translates collection names
 *
 * @param  mixed $mixedcollection
 * @param  string $index
 * @return string
 */
function i18n_get_collection_name($mixedcollection, $index = "name")
{
    global $lang;

    # The function handles both strings and arrays.
    if (!is_array($mixedcollection)) {
        $name_untranslated = $mixedcollection ?? "";
    } else {
        $name_untranslated = $mixedcollection[$index] ?? "";

        # Check if it is a Smart Collection
        if (isset($mixedcollection['savedsearch']) && ($mixedcollection['savedsearch'] != null)) {
            return escape($lang['smartcollection'] . ": " . i18n_get_translated($name_untranslated));
        }
    }

    # Check if it is a Default Collection (n)
    $name_translated = preg_replace('/(^Default Collection)(|(\s\d+))$/', $lang["mycollection"] . '$2', $name_untranslated, -1, $translated);
    if ($translated == 1) {
        return escape($name_translated);
    }

    # Check if it is a Upload YYMMDDHHMMSS
    $upload_date = preg_replace('/(^Upload)\s(\d{12})$/', '$2', $name_untranslated, -1, $translated);
    if ($translated != 1) {
        $upload_date = preg_replace('/(^Upload)\s(\d{14})$/', '$2', $name_untranslated, -1, $translated);
    }

    if ($translated == 1) {
        # Translate date into MySQL ISO format to be able to use nicedate()
        if (strlen($upload_date) == 14) {
            $year = substr($upload_date, 0, 4);
            $upload_date = substr($upload_date, 2);
        } else {
            $year = substr($upload_date, 0, 2);
            if ((int)$year > (int)date('y')) {
                $year = ((int)substr(date('Y'), 0, 2) - 1) . $year;
            } else {
                $year = substr(date('Y'), 0, 2) . $year;
            }
        }
        $month = substr($upload_date, 2, 2);
        $day = substr($upload_date, 4, 2);
        $hour = substr($upload_date, 6, 2);
        $minute = substr($upload_date, 8, 2);
        $second = substr($upload_date, 10, 2);
        $date = nicedate("$year-$month-$day $hour:$minute:$second", true);
        return escape($lang['upload'] . ' ' . $date);
    }

    # Check if it is a Research Request
    if (substr($name_untranslated, 0, 9) == "Research:") {
        return escape("{$lang["research"]}: " . i18n_get_translated(substr($name_untranslated, 9)));
    }

    # Ordinary collection - translate with i18n_get_translated
    return escape(i18n_get_translated($name_untranslated));
}

/**
 * For field names / values using the i18n syntax, return all language versions, as necessary for indexing.
 *
 * @param  string $text The text to process
 * @return string
 */
function i18n_get_indexable($text)
{
    // Make sure keywords don't get squashed together, then trim
    $text = str_replace(array("<br />","<br>","\\r","\\n","&nbsp;"), " ", $text);
    $text = trim($text);

    $text = preg_replace('/~(.*?):/', ',', $text);// remove i18n strings, which shouldn't be in the keywords

    # For multiple keywords, parse each keyword.
    if (substr($text, 0, 1) != "," && (strpos($text, ",") !== false) && (strpos($text, "~") !== false)) {
        $s = explode(",", $text);
        $out = "";
        for ($n = 0; $n < count($s); $n++) {
            if ($n > 0) {
                $out .= ",";
            }
            $out .= i18n_get_indexable(trim($s[$n]));
        }
        return $out;
    }

    # Split
    $s = explode("~", $text);

    # Not a translatable field?
    if (count($s) < 2) {
        return $text;
    }

    $out = "";
    for ($n = 1; $n < count($s); $n++) {
        if (substr($s[$n], 2, 1) != ":") {
            return $text;
        }
        if ($out != "") {
            $out .= ",";
        }
        $out .= substr($s[$n], 3);
    }
    return $out;
}

/**
 * For a string in the language format, return all translations as an associative array
 * E.g. "en"->"English translation";
 * "fr"->"French translation"
 *
 * @param  string $value A string in the language format
 * @return array
 */
function i18n_get_translations($value)
{
    #
    global $defaultlanguage;
    if (strpos($value, "~") === false) {
        return array($defaultlanguage => $value);
    }
    $s = explode("~", $value);
    $return = array();
    for ($n = 1; $n < count($s); $n++) {
        $e = explode(":", $s[$n], 2);
        if (count($e) == 2 && strlen($e[0]) > 1) {
            $return[$e[0]] = $e[1];
        }
    }
    return $return;
}

/**
 * Returns a string with all occurrences of the $mixedplaceholder in $subject replaced with the $mixedreplace.
 * If $mixedplaceholder is a string but $mixedreplace is an array, the $mixedreplace is imploded to a string using $separator.
 * The replace values are formatted according to the formatting of the placeholders.
 * The placeholders may be written in UPPERCASE, lowercase or Uppercasefirst.
 * Each placeholder will be replaced by the replace value,
 * written with the same case as the placeholder.
 * It's possible to also include "?" as a placeholder for legacy reasons.
 *
 * Example #1:
 * str_replace_formatted_placeholder("%extension", $resource["file_extension"], $lang["originalfileoftype"], true)
 * will search for the three words "%EXTENSION", "%extension" and "%Extension" and also the char "?"
 * in the string $lang["originalfileoftype"]. If the found placeholder is %extension
 * it will be replaced by the value of $resource["file_extension"],
 * written in lowercase. If the found placeholder instead would have been "?" the value
 * would have been written in UPPERCASE.
 *
 * Example #2:
 * str_replace_formatted_placeholder("%resourcetypes%", $searched_resource_types_names_array,
 * $lang["resourcetypes-collections"], false, $lang["resourcetypes_separator"])
 * will search for the three words "%RESOURCETYPES%", "%resourcetypes%" and "%Resourcetypes%"
 * in the string $lang["resourcetypes-collections"]. If the found placeholder is %resourcetypes%
 * all elements in $searched_resource_types_names_array will be written in lowercase and separated by
 * $lang["resourcetypes_separator"] before the resulting string will replace the placeholder.
 *
 * @param  mixed $mixedplaceholder
 * @param  mixed $mixedreplace
 * @param  mixed $subject
 * @param  mixed $question_mark
 * @param  string $separator
 * @return string
 */
function str_replace_formatted_placeholder($mixedplaceholder, $mixedreplace, $subject, $question_mark = false, $separator = ", ")
{
    # Creates a multi-dimensional array of the placeholders written in different case styles.
    $array_placeholder = array();
    if (is_array($mixedplaceholder)) {
        $placeholder = $mixedplaceholder;
    } else {
        $placeholder = array($mixedplaceholder);
    }
    for ($n = 0; $n < count($placeholder); $n++) {
        $array_placeholder[$n] = array(strtoupper((string)$placeholder[$n]), strtolower((string)$placeholder[$n]), ucfirstletter((string)$placeholder[$n]));
    }

    # Creates a multi-dimensional array of the replace values written in different case styles.
    if (is_array($mixedreplace)) {
        $replace = $mixedreplace;
    } else {
        $replace = array($mixedreplace);
    }
    for ($n = 0; $n < count($replace); $n++) {
        $array_replace[$n] = array(strtoupper((string)$replace[$n]), strtolower((string)$replace[$n]), ucfirst(strtolower((string)$replace[$n])));
    }

    # Adds "?" to the arrays if required.
    if ($question_mark) {
        $array_placeholder[] = "?";
        $array_replace[] = strtoupper((string)$replace[0]);
    }

    # Replaces the placeholders with the replace values and returns the new string.
    $result = $subject;
    if (count($placeholder) == 1 && count($replace) > 1) {
        # The placeholder shall be replaced by an imploded array.
        $array_replace_strings = array(implode($separator, array_map(function ($column) {
            return $column[0];
        }, $array_replace)), implode($separator, array_map(function ($column) {
            return $column[1];
        }, $array_replace)), implode($separator, array_map(function ($column) {
            return $column[2];
        }, $array_replace)));
        $result = str_replace($array_placeholder[0], $array_replace_strings, $result);
    } else {
        for ($n = 0; $n < count($placeholder); $n++) {
            if (!isset($array_replace[$n][0])) {
                break;
            } else {
                $result = str_replace($array_placeholder[$n], $array_replace[$n], $result);
            }
        }
    }
    return $result;
}

/**
 * Returns a string with the first LETTER of $string capitalized.
 * Compare with ucfirst($string) which returns a string with first CHAR of $string capitalized:
 * ucfirstletter("abc") / ucfirstletter("%abc") returns "Abc" / "%Abc"
 * ucfirst("abc") / ucfirst("%abc") returns "Abc" / "%abc"
 *
 * Search for the first letter ([a-zA-Z]), which may or may not be followed by other characters (.*).
 * Replaces the found substring ('$0') with the same substring but now with the first character capitalized, using ucfirst().
 * Note the /e modifier: If this modifier is set, preg_replace() does normal substitution of backreferences in the replacement
 * string, evaluates it as PHP code, and uses the result for replacing the search string.
 *
 * @param  mixed $string
 * @return string|array|null
 */
function ucfirstletter($string)
{
    return preg_replace_callback("/[a-zA-Z].*/", "ucfirstletter_callback", $string);
}

/**
 * Callback used by ucfirstletter
 *
 * @param  array $matches
 * @return void
 */
function ucfirstletter_callback($matches)
{
    return ucfirst($matches[0]);
}

/**
 * Normalize the text if function available
 *
 * @param  string $keyword          Keyword to normalize
 * @param  bool   $user_language    Flag to enable normalizing based on the current user language.
 *                                  Some languages consider characters with accents to be different characters
 *                                  and therefore order them after z and other consider them to be the same as the
 *                                  character without the accent.
 *                                  See https://www.php.net/manual/en/class.normalizer.php for more information
 * @return string                   Normalized keyword
 */
function normalize_keyword($keyword, bool $user_language = false)
{
    global $keywords_remove_diacritics,$language_normalize_mapping;
    if (function_exists('normalizer_normalize')) {
        if ($user_language && key_exists($GLOBALS["language"], $language_normalize_mapping)) {
            $form = $language_normalize_mapping[$GLOBALS["language"]];
        } else {
            $form = Normalizer::FORM_C;
        }

        $keyword = normalizer_normalize($keyword, $form);
    }

    if ($keywords_remove_diacritics) {
        $keyword = remove_accents($keyword);
    }
    return $keyword;
}

/**
* This function and seems_utf8 are reused from WordPress. See documentation/licenses/wordpress.txt for license information
*
* Converts all accent characters to ASCII characters.
*
* If there are no accent characters, then the string given is just returned.
*
* @param string $string Text that might have accent characters
* @return string Filtered string with replaced "nice" characters.
*/
function remove_accents($string)
{
    if (!preg_match('/[\x80-\xff]/', $string)) {
        return $string;
    }

    if (seems_utf8($string)) {
        $chars = array(
            // Decompositions for Latin-1 Supplement
            chr(195) . chr(128) => 'A', chr(195) . chr(129) => 'A',
            chr(195) . chr(130) => 'A', chr(195) . chr(131) => 'A',
            chr(195) . chr(132) => 'A', chr(195) . chr(133) => 'A',
            chr(195) . chr(135) => 'C', chr(195) . chr(136) => 'E',
            chr(195) . chr(137) => 'E', chr(195) . chr(138) => 'E',
            chr(195) . chr(139) => 'E', chr(195) . chr(140) => 'I',
            chr(195) . chr(141) => 'I', chr(195) . chr(142) => 'I',
            chr(195) . chr(143) => 'I', chr(195) . chr(145) => 'N',
            chr(195) . chr(146) => 'O', chr(195) . chr(147) => 'O',
            chr(195) . chr(148) => 'O', chr(195) . chr(149) => 'O',
            chr(195) . chr(150) => 'O', chr(195) . chr(153) => 'U',
            chr(195) . chr(154) => 'U', chr(195) . chr(155) => 'U',
            chr(195) . chr(156) => 'U', chr(195) . chr(157) => 'Y',
            chr(195) . chr(159) => 's', chr(195) . chr(160) => 'a',
            chr(195) . chr(161) => 'a', chr(195) . chr(162) => 'a',
            chr(195) . chr(163) => 'a', chr(195) . chr(164) => 'a',
            chr(195) . chr(165) => 'a', chr(195) . chr(167) => 'c',
            chr(195) . chr(168) => 'e', chr(195) . chr(169) => 'e',
            chr(195) . chr(170) => 'e', chr(195) . chr(171) => 'e',
            chr(195) . chr(172) => 'i', chr(195) . chr(173) => 'i',
            chr(195) . chr(174) => 'i', chr(195) . chr(175) => 'i',
            chr(195) . chr(177) => 'n', chr(195) . chr(178) => 'o',
            chr(195) . chr(179) => 'o', chr(195) . chr(180) => 'o',
            chr(195) . chr(181) => 'o', chr(195) . chr(182) => 'o',
            chr(195) . chr(182) => 'o', chr(195) . chr(185) => 'u',
            chr(195) . chr(186) => 'u', chr(195) . chr(187) => 'u',
            chr(195) . chr(188) => 'u', chr(195) . chr(189) => 'y',
            chr(195) . chr(191) => 'y',
            // Decompositions for Latin Extended-A
            chr(196) . chr(128) => 'A', chr(196) . chr(129) => 'a',
            chr(196) . chr(130) => 'A', chr(196) . chr(131) => 'a',
            chr(196) . chr(132) => 'A', chr(196) . chr(133) => 'a',
            chr(196) . chr(134) => 'C', chr(196) . chr(135) => 'c',
            chr(196) . chr(136) => 'C', chr(196) . chr(137) => 'c',
            chr(196) . chr(138) => 'C', chr(196) . chr(139) => 'c',
            chr(196) . chr(140) => 'C', chr(196) . chr(141) => 'c',
            chr(196) . chr(142) => 'D', chr(196) . chr(143) => 'd',
            chr(196) . chr(144) => 'D', chr(196) . chr(145) => 'd',
            chr(196) . chr(146) => 'E', chr(196) . chr(147) => 'e',
            chr(196) . chr(148) => 'E', chr(196) . chr(149) => 'e',
            chr(196) . chr(150) => 'E', chr(196) . chr(151) => 'e',
            chr(196) . chr(152) => 'E', chr(196) . chr(153) => 'e',
            chr(196) . chr(154) => 'E', chr(196) . chr(155) => 'e',
            chr(196) . chr(156) => 'G', chr(196) . chr(157) => 'g',
            chr(196) . chr(158) => 'G', chr(196) . chr(159) => 'g',
            chr(196) . chr(160) => 'G', chr(196) . chr(161) => 'g',
            chr(196) . chr(162) => 'G', chr(196) . chr(163) => 'g',
            chr(196) . chr(164) => 'H', chr(196) . chr(165) => 'h',
            chr(196) . chr(166) => 'H', chr(196) . chr(167) => 'h',
            chr(196) . chr(168) => 'I', chr(196) . chr(169) => 'i',
            chr(196) . chr(170) => 'I', chr(196) . chr(171) => 'i',
            chr(196) . chr(172) => 'I', chr(196) . chr(173) => 'i',
            chr(196) . chr(174) => 'I', chr(196) . chr(175) => 'i',
            chr(196) . chr(176) => 'I', chr(196) . chr(177) => 'i',
            chr(196) . chr(178) => 'IJ',chr(196) . chr(179) => 'ij',
            chr(196) . chr(180) => 'J', chr(196) . chr(181) => 'j',
            chr(196) . chr(182) => 'K', chr(196) . chr(183) => 'k',
            chr(196) . chr(184) => 'k', chr(196) . chr(185) => 'L',
            chr(196) . chr(186) => 'l', chr(196) . chr(187) => 'L',
            chr(196) . chr(188) => 'l', chr(196) . chr(189) => 'L',
            chr(196) . chr(190) => 'l', chr(196) . chr(191) => 'L',
            chr(197) . chr(128) => 'l', chr(197) . chr(129) => 'L',
            chr(197) . chr(130) => 'l', chr(197) . chr(131) => 'N',
            chr(197) . chr(132) => 'n', chr(197) . chr(133) => 'N',
            chr(197) . chr(134) => 'n', chr(197) . chr(135) => 'N',
            chr(197) . chr(136) => 'n', chr(197) . chr(137) => 'N',
            chr(197) . chr(138) => 'n', chr(197) . chr(139) => 'N',
            chr(197) . chr(140) => 'O', chr(197) . chr(141) => 'o',
            chr(197) . chr(142) => 'O', chr(197) . chr(143) => 'o',
            chr(197) . chr(144) => 'O', chr(197) . chr(145) => 'o',
            chr(197) . chr(146) => 'OE',chr(197) . chr(147) => 'oe',
            chr(197) . chr(148) => 'R',chr(197) . chr(149) => 'r',
            chr(197) . chr(150) => 'R',chr(197) . chr(151) => 'r',
            chr(197) . chr(152) => 'R',chr(197) . chr(153) => 'r',
            chr(197) . chr(154) => 'S',chr(197) . chr(155) => 's',
            chr(197) . chr(156) => 'S',chr(197) . chr(157) => 's',
            chr(197) . chr(158) => 'S',chr(197) . chr(159) => 's',
            chr(197) . chr(160) => 'S', chr(197) . chr(161) => 's',
            chr(197) . chr(162) => 'T', chr(197) . chr(163) => 't',
            chr(197) . chr(164) => 'T', chr(197) . chr(165) => 't',
            chr(197) . chr(166) => 'T', chr(197) . chr(167) => 't',
            chr(197) . chr(168) => 'U', chr(197) . chr(169) => 'u',
            chr(197) . chr(170) => 'U', chr(197) . chr(171) => 'u',
            chr(197) . chr(172) => 'U', chr(197) . chr(173) => 'u',
            chr(197) . chr(174) => 'U', chr(197) . chr(175) => 'u',
            chr(197) . chr(176) => 'U', chr(197) . chr(177) => 'u',
            chr(197) . chr(178) => 'U', chr(197) . chr(179) => 'u',
            chr(197) . chr(180) => 'W', chr(197) . chr(181) => 'w',
            chr(197) . chr(182) => 'Y', chr(197) . chr(183) => 'y',
            chr(197) . chr(184) => 'Y', chr(197) . chr(185) => 'Z',
            chr(197) . chr(186) => 'z', chr(197) . chr(187) => 'Z',
            chr(197) . chr(188) => 'z', chr(197) . chr(189) => 'Z',
            chr(197) . chr(190) => 'z', chr(197) . chr(191) => 's',
            // Euro Sign
            chr(226) . chr(130) . chr(172) => 'E',
            // GBP (Pound) Sign
            chr(194) . chr(163) => '');

        $string = strtr($string, $chars);
    } else {
        // Assume ISO-8859-1 if not UTF-8
        $chars['in'] = chr(128) . chr(131) . chr(138) . chr(142) . chr(154) . chr(158)
            . chr(159) . chr(162) . chr(165) . chr(181) . chr(192) . chr(193) . chr(194)
            . chr(195) . chr(196) . chr(197) . chr(199) . chr(200) . chr(201) . chr(202)
            . chr(203) . chr(204) . chr(205) . chr(206) . chr(207) . chr(209) . chr(210)
            . chr(211) . chr(212) . chr(213) . chr(214) . chr(216) . chr(217) . chr(218)
            . chr(219) . chr(220) . chr(221) . chr(224) . chr(225) . chr(226) . chr(227)
            . chr(228) . chr(229) . chr(231) . chr(232) . chr(233) . chr(234) . chr(235)
            . chr(236) . chr(237) . chr(238) . chr(239) . chr(241) . chr(242) . chr(243)
            . chr(244) . chr(245) . chr(246) . chr(248) . chr(249) . chr(250) . chr(251)
            . chr(252) . chr(253) . chr(255);

        $chars['out'] = "EfSZszYcYuAAAAAACEEEEIIIINOOOOOOUUUUYaaaaaaceeeeiiiinoooooouuuuyy";

        $string = strtr($string, $chars['in'], $chars['out']);
        $double_chars['in'] = array(chr(140), chr(156), chr(198), chr(208), chr(222), chr(223), chr(230), chr(240), chr(254));
        $double_chars['out'] = array('OE', 'oe', 'AE', 'DH', 'TH', 'ss', 'ae', 'dh', 'th');
        $string = str_replace($double_chars['in'], $double_chars['out'], $string);
    }

    return $string;
}

/**
 * Looks for particular patterns to attempt to determine if the provided string is in UTF8 format
 *
 * @param  string $str
 * @return boolean True if it's possibly UTF8
 */
function seems_utf8($str)
{
    $length = strlen($str);

    for ($i = 0; $i < $length; $i++) {
        $c = ord($str[$i]);

        if ($c < 0x80) {
            $n = 0; # 0bbbbbbb
        } elseif (($c & 0xE0) == 0xC0) {
            $n = 1; # 110bbbbb
        } elseif (($c & 0xF0) == 0xE0) {
            $n = 2; # 1110bbbb
        } elseif (($c & 0xF8) == 0xF0) {
            $n = 3; # 11110bbb
        } elseif (($c & 0xFC) == 0xF8) {
            $n = 4; # 111110bb
        } elseif (($c & 0xFE) == 0xFC) {
            $n = 5; # 1111110b
        } else {
            return false; # Does not match any model
        }

        for ($j = 0; $j < $n; $j++) { # n bytes matching 10bbbbbb follow ?
            if ((++$i == $length) || ((ord($str[$i]) & 0xC0) != 0x80)) {
                return false;
            }
        }
    }
    return true;
}

/**
 * Use the browser settings to determine the default / preferred language
 *
 * @param  boolean $strict_mode
 * @return string The language string the user may prefer
 */
function http_get_preferred_language($strict_mode = false)
{
    global $languages;

    if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        return null;
    }

    $accepted_languages = preg_split('/,\s*/', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
    $current_lang = false;
    $current_quality = 0;
    $language_map = array();

    foreach ($languages as $key => $value) {
        $language_map[strtolower($key)] = $key;
    }

    foreach ($accepted_languages as $accepted_language) {
        $res = preg_match('/^([a-z]{1,8}(?:-[a-z]{1,8})*)(?:;\s*q=(0(?:\.[0-9]{1,3})?|1(?:\.0{1,3})?))?$/i', $accepted_language, $matches);

        if (!$res) {
            continue;
        }

        $lang_code = explode('-', $matches[1]);

        // Use specified quality, if any
        if (isset($matches[2])) {
            $lang_quality = (float)$matches[2];
        } else {
            $lang_quality = 1.0;
        }

        while (count($lang_code)) {
            $short = strtolower(join('-', $lang_code));
            if (array_key_exists($short, $language_map) && $lang_quality > $current_quality) {
                $current_lang = $language_map[$short];
                $current_quality = $lang_quality;
            }

            if ($strict_mode) {
                break;
            }

            array_pop($lang_code);
        }
    }

    return $current_lang;
}

/**
 * Set the user's current language based on get/post/cookie values as appropriate.
 *
 * @return string The language string set
 */
function setLanguage()
{
    global $browser_language,$disable_languages,$defaultlanguage,$languages,$baseurl_short;
    $language = "";
    if (isset($_GET["language"]) && array_key_exists((string)$_GET["language"], $languages)) {
        return $_GET["language"];
    }
    if (isset($_POST["language"]) && array_key_exists((string)$_POST["language"], $languages)) {
        return $_POST["language"];
    }
    if (isset($_COOKIE["language"]) && array_key_exists((string)$_COOKIE["language"], $languages)) {
        return $_COOKIE["language"];
    }

    if (!$disable_languages && $browser_language && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
        $language = http_get_preferred_language();
        if (!empty($language) && array_key_exists($language, $languages)) {
            return $language;
        }
    }
    if (($disable_languages || $language === "") && isset($defaultlanguage)) {
        return $defaultlanguage;
    }
    # Final case.
    return 'en';
}

/**
 * Load all site text for the given page and language into the global $lang array
 *
 * @param  array $lang          Passed by reference
 * @param  string $pagename     Pagename
 * @param  string $language     Language
 * @return void
 */
function lang_load_site_text(&$lang, $pagename, $language = "")
{
    global $defaultlanguage, $site_text;

    $site_text = array();
    $results = ps_query("SELECT language,name,text FROM site_text WHERE (page=? OR page='all' OR page='') AND (specific_to_group IS NULL OR specific_to_group=0)", array("s",$pagename), "sitetext");

    for ($n = 0; $n < count($results); $n++) {
        $site_text[$results[$n]["language"] . "-" . $results[$n]["name"]] = $results[$n]["text"];
    }

    $query = " SELECT `name`,
                `text`,
                `page`,
                `language`, specific_to_group 
            FROM site_text
            WHERE (`language` = ? OR `language` = ?)
            AND (specific_to_group IS NULL OR specific_to_group = 0)
        ";
    $parameters = array("s",$language,"s",$defaultlanguage);

    if ($pagename != "admin_content") {
        // Load all content on the admin_content page to allow management.
        $query .= "AND (page = ? OR page = 'all' OR page = '' " .  (($pagename == "dash_tile") ? " OR page = 'home'" : "") . ")";
        $parameters[] = "s";
        $parameters[] = $pagename;
    }

    $results = ps_query($query, $parameters, "sitetext");

    // Create a new array to hold customised text at any stage, may be overwritten in authenticate.php. Needed so plugin lang file can be overidden if plugin only enabled for specific groups
    $GLOBALS['customsitetext'] = array();

    // Set the default language first, user language second so we can override the default with any language specific entries
    foreach ([$defaultlanguage, $language] as $check_lang) {
        foreach ($results as $result) {
            if ($result['language'] != $check_lang) {
                continue;
            }

            if ($result['page'] == '') {
                $lang[$result['name']] = $result['text'];
                $GLOBALS['customsitetext'][$result['name']] = $result['text'];
            } else {
                $lang["{$result['page']}__{$result['name']}"] = $result['text'];
            }
        }
    }
}

/**
 * Return an array of all available language strings for the given id, with the language code as the key
 *
 * @param string $langid    The identifier of the lang string
 *
 * @return array
 *
 */
function i18n_get_all_translations(string $langid): array
{
    global $lang;
    $savedlang = $lang;
    $alltranslations = [];
    foreach ($GLOBALS["languages"] as $langcode => $availlanguage) {
        if ($langcode != "en") {
            if (substr($langcode, 2, 1) != '-') {
                $langcode = substr($langcode, 0, 2);
            }

            $use_error_exception_cache = $GLOBALS["use_error_exception"] ?? false;
            $GLOBALS["use_error_exception"] = true;
            try {
                include __DIR__ . "/../languages/" . safe_file_name($langcode) . ".php";
            } catch (Exception $e) {
                debug("Unable to include language file $langcode.php" . $e->getMessage());
            }
            $GLOBALS["use_error_exception"] = $use_error_exception_cache;
        }
        $alltranslations[$langcode] = $lang[$langid];
    }
    // Revert to original
    $lang = $savedlang;
    return $alltranslations;
}

/**
 * Merge values for across multiple translation strings.
 * Where a value is missing for a language used elsewhere i18n_get_translated will be used.
 * If no value is provided and no English (en) string is found then blank will be returned.
 *
 * Example input: ["~en:cheese~fr:fromage","~en:bread~fr:pain"]
 *
 * Example output: ["~en:cheese, bread","~fr:fromage, pain"]
 *
 * @param  array $values    Translation strings to merge.
 *
 * @return array            Each language string as a comma separated list.
 */
function i18n_merge_translations(array $values): array
{
    if (count($values) <= 1) {
        return $values;
    }

    $values_split = array_map('i18n_get_translations', $values);

    $languages = [];
    foreach ($values_split as $value) {
        $languages = array_unique(array_merge($languages, array_keys($value)));
    }

    $options = [];
    foreach ($values_split as $value) {
        foreach ($languages as $language) {
            if (key_exists($language, $value)) {
                $options[$language][] = $value[$language];
            } else {
                $options[$language][] = i18n_get_translated($value['en'] ?? "");
            }
        }
    }

    return array_map(
        fn(string $lang, array $values): string => "~$lang:" . implode(", ", $values),
        array_keys($options),
        array_values($options)
    );
}

/**
 * Get the name of a language in your own language plus its native name.
 * Used for language selection dropdowns.
 *
 * @param   string  $language_key   Key of $languages array, e.g. "fr"
 * @param   string  $language_value The value (full name) from the $languages array, e.g. "Français"
 */
function get_display_language($language_key, $language_value): string
{
    global $lang;
    $display_lang = $lang["language-" . $language_key];

    if ($lang["language-" . $language_key ] !== $language_value) {
        $display_lang .= " (" . $language_value . ")";
    }

    return $display_lang;
}

/**
 * Simple check to determine if the text string supplied contains i18n language translations.
 * Returns true if match found e.g.  '~en:test~en-US:test'. Returns false if no code is found. e.g. 'test'.
 *
 * @param  string  $text   Text string to check e.g. a node name or field value.
 */
function is_i18n_language_string($text): bool
{
    return preg_match('/~(.*?):/', $text) === 1;
}
