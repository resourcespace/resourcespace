<?php

#
#
# General functions, useful across the whole solution; not specific to one area
#
# PLEASE NOTE - Don't add search/resource/collection/user etc. functions here - use the separate include files.
#

use Montala\ResourceSpace\CommandPlaceholderArg;

/**
 * Retrieve a user-submitted parameter from the browser via post/get/cookies, in that order.
 *
 * @param string $param The parameter name
 * @param string $default A default value to return if no matching parameter was found
 * @param boolean $force_numeric Ensure a number is returned. (DEPRECATED)
 * @param callable $type_check Validate param type. Default is to check param values are strings.
 */
function getval($param, $default, $force_numeric = false, ?callable $type_check = null)
{
    /*
    TODO: remove in favour of type_check. Example:
    - getval('some_param_name', 0, true);
    + getval('some_param_name', 0, 'is_numeric'); # Once $force_numeric arg is removed

    For now $force_numeric has a higher precedence while still in place.
    */
    if ($force_numeric) {
        $type_check = 'is_numeric';
    } elseif ($type_check === null) {
        $type_check = 'is_string';
    }

    if (array_key_exists($param, $_POST)) {
        return $type_check($_POST[$param]) ? $_POST[$param] : $default;
    } elseif (array_key_exists($param, $_GET)) {
        return $type_check($_GET[$param]) ? $_GET[$param] : $default;
    } elseif (array_key_exists($param, $_COOKIE)) {
        return $type_check($_COOKIE[$param]) ? $_COOKIE[$param] : $default;
    } else {
        return $default;
    }
}

/**
 * Escape a value prior to using it in SQL.
 * IMPORTANT! NO LONGER NEEDED with prepared statements. This is only used when exporting SQL scripts.
 *
 * @param  string $text
 * @return string
 */
function escape_check($text)
{
    global $db;

    $db_connection = $db["read_write"];
    if (db_use_multiple_connection_modes() && db_get_connection_mode() == "read_only") {
        $db_connection = $db["read_only"];
        db_clear_connection_mode();
    }

    $text = mysqli_real_escape_string($db_connection, (string) $text);

    # turn all \\' into \'
    while (strpos($text, "\\\\'") !== false) {
        $text = str_replace("\\\\'", "\\'", $text);
    }

    # Remove any backslashes that are not being used to escape single quotes.
    $text = str_replace("\\'", "{bs}'", $text);
    $text = str_replace("\\n", "{bs}n", $text);
    $text = str_replace("\\r", "{bs}r", $text);
    $text = str_replace("\\", "", $text);
    $text = str_replace("{bs}'", "\\'", $text);
    $text = str_replace("{bs}n", "\\n", $text);
    $text = str_replace("{bs}r", "\\r", $text);

    return $text;
}

/**
 * For comparing escape_checked strings against mysql content because
 * just doing $text=str_replace("\\","",$text); does not undo escape_check
 *
 * @param  mixed $text
 * @return string
 */
function unescape($text)
{
    # Remove any backslashes that are not being used to escape single quotes.
    $text = str_replace("\\'", "\'", $text);
    $text = str_replace("\\n", "\n", $text);
    $text = str_replace("\\r", "\r", $text);
    $text = str_replace("\\", "", $text);
    return $text;
}

/**
* Formats a MySQL ISO date
*
* Always use the 'wordy' style from now on as this works better internationally.
*
* @uses offset_user_local_timezone()
*
* @var  string   $date       ISO format date which can be a BCE date (ie. with negative year -yyyy)
* @var  boolean  $time       When TRUE and full date is present then append the hh:mm time part if present
* @var  boolean  $wordy      When TRUE return month name, otherwise return month number
* @var  boolean  $offset_tz  Set to TRUE to offset based on time zone, FALSE otherwise
*
* @return string Returns an empty string if date not set/invalid
*/
function nicedate($date, $time = false, $wordy = true, $offset_tz = false)
{
    global $lang, $date_d_m_y, $date_yyyy;

    $date = trim((string)$date);
    if ($date == '') {
        return '';
    }

    // Pad out a date time value so that it can pass through strtotime if time part is incomplete
    if ($time && strlen($date) < 16) {
        $date_parts = explode(' ', $date);
        $time_parts = explode(':', $date_parts[1] ?? '');
        $time_parts = [
            $time_parts[0] ?? '' ?: '00',
            $time_parts[1] ?? '' ?: '00'
        ];
        $date = $date_parts[0] . ' ' . implode(':', $time_parts);
    }

    $date_timestamp = strtotime($date);
    if ($date_timestamp === false) {
        return '';
    }

    // Check whether unix timestamp is a BCE date
    $year_zero = PHP_INT_MIN === (int)-2147483648 ? PHP_INT_MIN : strtotime("0000-00-00");
    $bce_offset = ($date_timestamp < $year_zero) ? 1 : 0;
    // BCE dates cannot return year in truncated form
    if ($bce_offset == 1 && !$date_yyyy) {
        return '';
    }

    $original_time_part = substr($date, $bce_offset + 11, 5);
    if ($offset_tz && ($original_time_part !== false || $original_time_part != '')) {
        $date = offset_user_local_timezone($date, 'Y-m-d H:i');
    }

    $y = substr($date, 0, $bce_offset + 4);
    if (!$date_yyyy) {
        $y = substr($y, 2, 2);  // Only truncate year for non-BCE dates
    }

    if ($y == "") {
        return "-";
    }

    $month_part = substr($date, $bce_offset + 5, 2);
    if (!is_numeric($month_part)) {
        return $y;
    }
    $m = $wordy ? ($lang["months_list"][$month_part - 1] ?? "") : $month_part;
    if ($m == "") {
        return $y;
    }

    $d = substr($date, $bce_offset + 8, 2);
    if ($d == "" || $d == "00") {
        return "{$m} {$y}";
    }

    $t = $time ? " @ " . substr($date, $bce_offset + 11, 5) : "";

    if ($date_d_m_y) {
        return $d . " " . $m . " " . $y . $t;
    } else {
        return $m . " " . $d . " " . $y . $t;
    }
}

/**
* Generates a text representation of the age from a given date
*
* This can be used to give a more user friendly indication of how old something is.
*
* @param  string   $datetime   ISO format date which can be a BCE date (ie. with negative year -yyyy)
*
* @return string   Returns a string representing the age calculated from $datetime, otherwise an empty string if date is invalid
*/
function date_to_age(string $datetime): string 
{
    global $lang;
    
    $now = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->getTimestamp() - $then->getTimestamp();

    if ($diff < 60) {
        return $lang['just_now'];
    } elseif ($diff < 3600) {
        $minutes = floor($diff / 60);
        $template = $minutes == 1 ? $lang['min_ago'] : $lang['mins_ago'];
        return mb_strtolower(str_replace('%TIME%', $minutes, $template), 'UTF-8');
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        $template = $hours == 1 ? $lang['hour_ago'] : $lang['hours_ago'];
        return mb_strtolower(str_replace('%TIME%', $hours, $template), 'UTF-8');
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        $template = $days == 1 ? $lang['day_ago'] : $lang['days_ago'];
        return mb_strtolower(str_replace('%TIME%', $days, $template), 'UTF-8');
    } elseif ($diff < 2592000) {
        $weeks = floor($diff / 604800);
        $template = $weeks == 1 ? $lang['week_ago'] : $lang['weeks_ago'];
        return mb_strtolower(str_replace('%TIME%', $weeks, $template), 'UTF-8');
    } elseif ($diff < 31536000) {
        $months = floor($diff / 2592000);
        $template = $months == 1 ? $lang['month_ago'] : $lang['months_ago'];
        return mb_strtolower(str_replace('%TIME%', $months, $template), 'UTF-8');
    } else {
        // For anything over a year, just show the 'wordy' date
        return nicedate($datetime, false, true, true);
    }
}

/**
* Generates preview text of a certain line length and count
*
* Can be used for generating previews of text output, such as messages.
*
* @param  string   $text          The text to generate the preview of, can contain HTML
* @param  int      $line_length   The number of characters that the preview can have on each line
* @param  int      $line_count    The number of lines to be generated in the preview
* @param  bool     $add_ellipsis  Should ellipsis (...) be added if the original $text exceeds $line_count
*
* @return string Returns a string of the preview text, or an empty string if the preview is unable to be generated
*/
function preview_from_text(string $text, int $line_length, int $line_count, bool $add_ellipsis = true): string
{
    if ($line_count <= 0 || $line_length <= 0) {
        return "";
    }

    // Normalize the text and strip out any HTML
    // this is to ensure the wrapping works and should NOT be relied on for output encoding safety
    $normalized_text = preg_replace('/<br\s*\/?>/i', "\n", $text);
    $normalized_text = html_entity_decode(strip_tags($normalized_text));
    $normalized_text = str_replace(["\r\n", "\r"], "\n", $normalized_text);

    // Wrap long lines at $line_length but don't break words
    $wrapped_text = wordwrap($normalized_text, $line_length, "\n", true);

    $lines = explode("\n", $wrapped_text);    
    $preview_lines = array_slice($lines, 0, $line_count);
    $text_preview = implode("\n", $preview_lines);

    if (count($lines) > $line_count && $add_ellipsis) {
        $text_preview .= '...';
    }

    return $text_preview;

}

/**
 * Redirect to the provided URL using a HTTP header Location directive. Exits after redirect
 *
 * @param  string $url  URL to redirect to
 * @return never
 */
function redirect(string $url)
{
    global $baseurl,$baseurl_short;

    // Header may not contain NUL bytes
    $url = str_replace("\0", '', $url);

    if (getval("ajax", "") != "") {
        # When redirecting from an AJAX loaded page, forward the AJAX parameter automatically so headers and footers are removed.
        if (strpos($url, "?") !== false) {
            $url .= "&ajax=true";
        } else {
            $url .= "?ajax=true";
        }
    }

    if (substr($url, 0, 1) == "/") {
        # redirect to an absolute URL
        header("Location: " . $baseurl . str_replace($baseurl_short, "/", $url));
    } else {
        $extra_slash = substr($baseurl, -1, 1) === '/' || $baseurl === $url ? '' : '/';
        if (url_starts_with($baseurl . $extra_slash, $url)) {
            // Base url has already been added
            header("Location: " . $url);
            exit();
        }

        # redirect to a relative URL
        header("Location: " . $baseurl . "/" . $url);
    }
    exit();
}

/**
 * replace multiple spaces with a single space
 *
 * @param  mixed $text
 * @return string
 */
function trim_spaces($text)
{
    while (strpos($text, "  ") !== false) {
        $text = str_replace("  ", " ", $text);
    }
    return trim($text);
}

/**
 *  Removes whitespace from the beginning/end of all elements in an array
 *
 * @param  array $array
 * @param  string $trimchars
 * @return array
 */
function trim_array($array, $trimchars = '')
{
    if (isset($array[0]) && empty($array[0]) && !(emptyiszero($array[0]))) {
        $unshiftblank = true;
    }
    $array = array_filter($array, 'emptyiszero');
    $array_trimmed = array();
    $index = 0;

    foreach ($array as $el) {
        $el = trim($el);
        if (strlen($trimchars) > 0) {
            // also trim off extra characters they want gone
            $el = trim($el, $trimchars);
        }
        // Add to the returned array if there is anything left
        if (strlen($el) > 0) {
            $array_trimmed[$index] = $el;
            $index++;
        }
    }
    if (isset($unshiftblank)) {
        array_unshift($array_trimmed, "");
    }
    return $array_trimmed;
}

/**
 * Takes a value as returned from a check-list field type and reformats to be more display-friendly.
 * Check-list fields have a leading comma.
 *
 * @param  string $list
 * @return string
 */
function tidylist($list)
{
    $list = trim((string) $list);
    if (strpos($list, ",") === false) {
        return $list;
    }
    $list = explode(",", $list);
    if (trim($list[0]) == "") {
        array_shift($list);
    } # remove initial comma used to identify item is a list
    return join(", ", trim_array($list));
}

/**
 * Trims $text to $length if necessary. Tries to trim at a space if possible. Adds three full stops if trimmed...
 *
 * @param  string $text
 * @param  integer $length
 * @return string
 */
function tidy_trim($text, $length)
{
    $text = trim($text);
    if (strlen($text) > $length) {
        $text = mb_substr($text, 0, $length - 3, 'utf-8');
        # Trim back to the last space
        $t = strrpos($text, " ");
        $c = strrpos($text, ",");
        if ($c !== false) {
            $t = $c;
        }
        if ($t > 5) {
            $text = substr($text, 0, $t);
        }
        $text = $text . "...";
    }
    return $text;
}

/**
 * Returns the average length of the strings in an array
 *
 * @param  array $array
 * @return float
 */
function average_length($array)
{
    if (count($array) == 0) {
        return 0;
    }
    $total = 0;
    for ($n = 0; $n < count($array); $n++) {
        $total += strlen(i18n_get_translated($array[$n]));
    }
    return $total / count($array);
}

/**
 * Returns a list of activity types for which we have stats data (Search, User Session etc.)
 *
 * @return array
 */
function get_stats_activity_types()
{
    return ps_array("SELECT DISTINCT activity_type `value` FROM daily_stat ORDER BY activity_type", array());
}

/**
 * Replace escaped newlines with real newlines.
 *
 * @param  string $text
 * @return string
 */
function newlines($text)
{
    $text = str_replace("\\n", "\n", $text);
    $text = str_replace("\\r", "\r", $text);
    return $text;
}

/**
 * Returns a list of all available editable site text (content). If $find is specified
 * a search is performed across page, name and text fields.
 *
 * @param  string $findpage
 * @param  string $findname
 * @param  string $findtext
 * @return array
 */
function get_all_site_text($findpage = "", $findname = "", $findtext = "")
{
    global $defaultlanguage,$languages,$applicationname,$storagedir,$homeanim_folder, $language;

    $findname = trim($findname);
    $findpage = trim($findpage);
    $findtext = trim($findtext);

    $return = array();

    // en should always be included as it is the fallback language of the system
    $search_languages = array('en');

    if ('en' == $language) {
        $search_languages = array('en');
    } else {
        $search_languages = array($language, 'en');
    }

    // When searching text, search all languages to pick up matches for languages other than the default. Add array so that default is first then we can skip adding duplicates.
    if ('' != $findtext) {
        $search_languages = $search_languages + array_keys($languages);
    }

        global $language, $lang; // Need to save these for later so we can revert after search
        $languagesaved = $language;
        $langsaved = $lang;

    foreach ($search_languages as $search_language) {
        # Reset $lang and include the appropriate file to search.
        $lang = array();

        # Include language file
        $searchlangfile = __DIR__ . "/../languages/" . safe_file_name($search_language) . ".php";
        if (file_exists($searchlangfile)) {
            include $searchlangfile;
        }
        include __DIR__ . "/../languages/" . safe_file_name($search_language) . ".php";

        # Include plugin languages in reverse order as per boot.php
        global $plugins;
        $language = $search_language;
        for ($n = count($plugins) - 1; $n >= 0; $n--) {
            if (!isset($plugins[$n])) {
                continue;
            }
            register_plugin_language($plugins[$n]);
        }

        # Find language strings.
        ksort($lang);
        foreach ($lang as $key => $text) {
            $pagename = "";
            $s = explode("__", $key);
            if (count($s) > 1) {
                $pagename = $s[0];
                $key = $s[1];
            }

            if (
                !is_array($text) # Do not support overrides for array values (used for months)... complex UI needed and very unlikely to need overrides.
                &&
                ($findname == "" || stripos($key, $findname) !== false)
                &&
                ($findpage == "" || stripos($pagename, $findpage) !== false)
                &&
                ($findtext == "" || stripos($text, $findtext) !== false)
            ) {
                $testrow = array();
                $testrow["page"] = $pagename;
                $testrow["name"] = $key;
                $testrow["text"] = $text;
                $testrow["language"] = $defaultlanguage;
                $testrow["group"] = "";
                // Make sure this isn't already set for default/another language
                if (!in_array($testrow, $return)) {
                    $row["page"] = $pagename;
                    $row["name"] = $key;
                    $row["text"] = $text;
                    $row["language"] = $search_language;
                    $row["group"] = "";
                    $return[] = $row;
                }
            }
        }
    }

        // Need to revert to saved values
        $language = $languagesaved;
        $lang = $langsaved;

        # If searching, also search overridden text in site_text and return that also.
    if ($findtext != "" || $findpage != "" || $findname != "") {
        if ($findtext != "") {
            $search = "text LIKE ? HAVING language = ? OR language = ? ORDER BY (CASE WHEN language = ? THEN 3 WHEN language = ? THEN 2 ELSE 1 END)";
            $search_param = array("s", '%' . $findtext . '%', "s", $language, "s", $defaultlanguage, "s", $language, "s", $defaultlanguage);
        }

        if ($findpage != "") {
            $search = "page LIKE ? HAVING language = ? OR language = ? ORDER BY (CASE WHEN language = ? THEN 2 ELSE 1 END)";
            $search_param = array("s", '%' . $findpage . '%', "s", $language, "s", $defaultlanguage, "s", $language);
        }

        if ($findname != "") {
            $search = "name LIKE ? HAVING language = ? OR language = ? ORDER BY (CASE WHEN language = ? THEN 2 ELSE 1 END)";
            $search_param = array("s", '%' . $findname . '%', "s", $language, "s", $defaultlanguage, "s", $language);
        }

        $site_text = ps_query("select `page`, `name`, `text`, ref, `language`, specific_to_group, custom from site_text where $search", $search_param);

        foreach ($site_text as $text) {
            $row["page"] = $text["page"];
            $row["name"] = $text["name"];
            $row["text"] = $text["text"];
            $row["language"] = $text["language"];
            $row["group"] = $text["specific_to_group"];
            // Make sure we dont'include the default if we have overwritten
            $customisedtext = false;
            for ($n = 0; $n < count($return); $n++) {
                if ($row["page"] == $return[$n]["page"] && $row["name"] == $return[$n]["name"] && $row["language"] == $return[$n]["language"] && $row["group"] == $return[$n]["group"]) {
                    $customisedtext = true;
                    $return[$n] = $row;
                }
            }
            if (!$customisedtext) {
                $return[] = $row;
            }
        }
    }

    // Clean returned array so it contains unique records by name
    $unique_returned_records = array();
    $existing_lang_names     = array();
    $i                       = 0;

    foreach ($return as $returned_record) {
        if (!in_array($returned_record['name'], $existing_lang_names)) {
            $existing_lang_names[$i]     = $returned_record['name'];
            $unique_returned_records[$i] = $returned_record;
        }

        $i++;
    }
    // Reverse again so that the default language appears first in results
    return array_values($unique_returned_records);
}

/**
 * Returns a specific site text entry.
 *
 * @param  string $page
 * @param  string $name
 * @param  string $getlanguage
 * @param  string $group
 * @return string
 */
function get_site_text($page, $name, $getlanguage, $group)
{
    global $defaultlanguage, $lang, $language; // Registering plugin text uses $language and $lang
    global $applicationname, $storagedir, $homeanim_folder; // These are needed as they are referenced in lang files

    $params = array("s", $page, "s", $name, "s", $getlanguage);
    if ($group == "") {
        $stg_sql_cond = ' is null';
    } else {
        $stg_sql_cond = ' = ?';
        $params = array_merge($params, array("i", $group));
    }

    $text = ps_query("select `page`, `name`, `text`, ref, `language`, specific_to_group, custom from site_text where page = ? and name = ? and language = ? and specific_to_group $stg_sql_cond", $params);
    if (count($text) > 0) {
                return $text[0]["text"];
    }
        # Fall back to default language.
    $text = ps_query("select `page`, `name`, `text`, ref, `language`, specific_to_group, custom from site_text where page = ? and name = ? and language = ? and specific_to_group $stg_sql_cond", $params);
    if (count($text) > 0) {
                return $text[0]["text"];
    }

        # Fall back to default group.
    $text = ps_query("select `page`, `name`, `text`, ref, `language`, specific_to_group, custom from site_text where page = ? and name = ? and language = ? and specific_to_group is null", array("s", $page, "s", $name, "s", $defaultlanguage));
    if (count($text) > 0) {
        return $text[0]["text"];
    }

    # Fall back to language strings.
    if ($page == "") {
        $key = $name;
    } else {
        $key = $page . "__" . $name;
    }

    # Include specific language(s)
    $defaultlangfile = __DIR__ . "/../languages/" . safe_file_name($defaultlanguage) . ".php";
    if (file_exists($defaultlangfile)) {
        include $defaultlangfile;
    }
    $getlangfile = __DIR__ . "/../languages/" . safe_file_name($getlanguage) . ".php";
    if (file_exists($getlangfile)) {
        include $getlangfile;
    }

    # Include plugin languages in reverse order as per boot.php
    global $plugins;
    $language = $defaultlanguage;
    for ($n = count($plugins) - 1; $n >= 0; $n--) {
        if (!isset($plugins[$n])) {
            continue;
        }
        register_plugin_language($plugins[$n]);
    }

    $language = $getlanguage;
    for ($n = count($plugins) - 1; $n >= 0; $n--) {
        if (!isset($plugins[$n])) {
            continue;
        }
        register_plugin_language($plugins[$n]);
    }

    if (array_key_exists($key, $lang)) {
        return $lang[$key];
    } elseif (array_key_exists("all_" . $key, $lang)) {
        return $lang["all_" . $key];
    } else {
        return "";
    }
}

/**
 * Check if site text section is custom, i.e. deletable.
 *
 * @param  mixed $page
 * @param  mixed $name
 */
function check_site_text_custom($page, $name): bool
{
    $check = ps_query("select custom from site_text where page = ? and name = ?", array("s", $page, "s", $name));

    return $check[0]["custom"] ?? false;
}

/**
 * Saves the submitted site text changes to the database.
 *
 * @param  string $page
 * @param  string $name
 * @param  string $language
 * @param  integer $group
 * @return void
 */
function save_site_text($page, $name, $language, $group)
{
    global $lang,$custom,$newcustom,$defaultlanguage,$newhelp;

    if (!is_int_loose($group)) {
        $group = null;
    }
    $text = strip_unicode_points(getval("text", ""));
    if ($newcustom) {
        $params = ["s",$page,"s",$name];
        $test = ps_query("SELECT ref,page,name,text,language,specific_to_group,custom FROM site_text WHERE page=? AND name=?", $params);
        if (count($test) > 0) {
            return true;
        }
    }
    if (is_null($custom) || trim($custom) == "") {
        $custom = 0;
    }
    if (getval("deletecustom", "") != "") {
        $params = ["s",$page,"s",$name];
        ps_query("DELETE FROM site_text WHERE page=? AND name=?", $params);
    } elseif (getval("deleteme", "") != "") {
        $params = ["s",$page,"s",$name,"i",$group];
        ps_query("DELETE FROM site_text WHERE page=? AND name=? AND specific_to_group <=> ?", $params);
    } elseif (getval("copyme", "") != "") {
        $params = ["s",$page,"s",$name,"s",$text,"s",$language,"i",$group,"i",$custom];
        ps_query("INSERT INTO site_text(page,name,text,language,specific_to_group,custom) VALUES (?,?,?,?,?,?)", $params);
    } elseif (getval("newhelp", "") != "") {
        $params = ["s",$newhelp];
        $check = ps_query("SELECT ref,page,name,text,language,specific_to_group,custom FROM site_text where page = 'help' and name=?", $params);
        if (!isset($check[0])) {
            $params = ["s",$page,"s",$newhelp,"s","","s",$language,"i",$group];
            ps_query("INSERT INTO site_text(page,name,text,language,specific_to_group) VALUES (?,?,?,?,?)", $params);
        }
    } else {
        $params = ["s",$page,"s",$name,"s",$language,"i",$group];
        $curtext = ps_query("SELECT ref,page,name,text,language,specific_to_group,custom FROM site_text WHERE page=? AND name=? AND language=? AND specific_to_group <=> ?", $params);
        if (count($curtext) == 0) {
            # Insert a new row for this language/group.
            $params = ["s",$page,"s",$name,"s",$text,"s",$language,"i",$group,"i",$custom];
            ps_query("INSERT INTO site_text(page,name,text,language,specific_to_group,custom) VALUES (?,?,?,?,?,?)", $params);
            log_activity($lang["text"], LOG_CODE_CREATED, $text, 'site_text', null, "'{$page}','{$name}','{$language}',{$group}");
        } else {
            # Update existing row
            $params = ["s",$text,"s",$page,"s",$name,"s",$language,"i",$group];
            ps_query("UPDATE site_text SET text=? WHERE page=? AND name=? AND language=? AND specific_to_group <=> ?", $params);
            log_activity($lang["text"], LOG_CODE_EDITED, $text, 'site_text', null, "'{$page}','{$name}','{$language}',{$group}");
        }

        # Language clean up - remove all entries that are exactly the same as the default text.
        $params = ["s",$page,"s",$name,"s",$defaultlanguage,"i",$group];
        $defaulttext = ps_value("SELECT text value FROM site_text WHERE page=? AND name=? AND language=? AND specific_to_group<=>?", $params, "");

        $params = ["s",$page,"s",$name,"s",$defaultlanguage,"s",trim($defaulttext)];
        ps_query("DELETE FROM site_text WHERE page=? AND name=? AND language != ? AND trim(text)=?", $params);
    }

    // Clear cache
    clear_query_cache("sitetext");
}

/**
 * Return a human-readable string representing $bytes in either KB or MB.
 *
 * @param  integer $bytes file size to format in bytes
 * @param  bool    $html if set to true output will include &nbsp; instead of a space
 * @return string
 */
function formatfilesize(int $bytes, bool $html = true): string
{
    # Binary mode
    $multiple = 1024;
    $lang_suffix = "-binary";

    $space = $html ? "&nbsp;" : " ";

    # Decimal mode, if configured
    global $byte_prefix_mode_decimal;
    if ($byte_prefix_mode_decimal) {
        $multiple = 1000;
        $lang_suffix = "";
    }

    global $lang;
    if ($bytes < $multiple) {
        return number_format((float)$bytes) . $space . escape($lang["byte-symbol"]);
    } elseif ($bytes < pow($multiple, 2)) {
        return number_format((float)ceil($bytes / $multiple)) . $space . escape($lang["kilobyte-symbol" . $lang_suffix]);
    } elseif ($bytes < pow($multiple, 3)) {
        return number_format((float)$bytes / pow($multiple, 2), 1) . $space . escape($lang["megabyte-symbol" . $lang_suffix]);
    } elseif ($bytes < pow($multiple, 4)) {
        return number_format((float)$bytes / pow($multiple, 3), 1) . $space . escape($lang["gigabyte-symbol" . $lang_suffix]);
    } else {
        return number_format((float)$bytes / pow($multiple, 4), 1) . $space . escape($lang["terabyte-symbol" . $lang_suffix]);
    }
}

/**
 * Converts human readable file size (e.g. 10 MB, 200.20 GB) into bytes.
 *
 * @param string $str
 * @return int the result is in bytes
 */
function filesize2bytes($str)
{

    $bytes = 0;

    $bytes_array = array(
        'b' => 1,
        'kb' => 1024,
        'mb' => 1024 * 1024,
        'gb' => 1024 * 1024 * 1024,
        'tb' => 1024 * 1024 * 1024 * 1024,
        'pb' => 1024 * 1024 * 1024 * 1024 * 1024,
    );

    $bytes = floatval($str);

    if (preg_match('#([KMGTP]?B)$#si', $str, $matches) && !empty($bytes_array[strtolower($matches[1])])) {
        $bytes *= $bytes_array[strtolower($matches[1])];
    }

    $bytes = intval(round($bytes, 2));

    #add leading zeroes (as this can be used to format filesize data in resource_data for sorting)
    return sprintf("%010d", $bytes);
}

/**
 * Get the mime type for a file on disk
 *
 * @param string $path
 * @param string $ext
 * @param ?bool $file_based_detection Determine the MIME type:
 * - null: Check by extension or using exiftool
 * - true: Only file based (uses exiftool)
 * - false: Only based on extension
 */
function get_mime_type($path, $ext = null, ?bool $file_based_detection = null): array
{
    if (empty($ext)) {
        $ext = parse_filename_extension($path);
    }

    if (!$file_based_detection && ($found_by_ext = get_mime_types_by_extension($ext)) && $found_by_ext !== []) {
        return $found_by_ext;
    }

    # Get mime type via exiftool if possible
    $exiftool_fullpath = get_utility_path("exiftool");
    if (($file_based_detection || $file_based_detection === null) && $exiftool_fullpath != false) {
        $command = $exiftool_fullpath . " -s -s -s -t -mimetype " . escapeshellarg($path);
        $file_mime_type = trim(run_command($command));

        if ($file_mime_type !== '') {
            return [$file_mime_type];
        }
    }

    return ['application/octet-stream'];
}

/**
 * Find matching MIME type(s) for a file extension.
 *
 * @param string $extension
 * @return list<string>
 */
function get_mime_types_by_extension(string $extension): array
{
    $extension = mb_strtolower(trim($extension));
    $mime_types = $GLOBALS['mime_types_by_extension'] ?? [];
    $matches = isset($mime_types[$extension])
        ? (is_string($mime_types[$extension]) ? [$mime_types[$extension]] : $mime_types[$extension])
        : [];

    return array_values(array_filter(array_map(trim(...), $matches)));
}

/**
 * Get the global MIME types associated with the configured banned extensions.
 */
function get_unsafe_mime_types(): array
{
    $mime_types = $GLOBALS['mime_types_by_extension'] ?? [];
    $banned_extensions_mimes = array_intersect_key($mime_types, array_flip($GLOBALS['banned_extensions']));
    return array_unique(array_merge(...array_values(array_map(static fn($v) => (array) $v, $banned_extensions_mimes))));
}

/**
 * Convert the permitted resource type extension to MIME type. Used by upload_batch.php
 *
 * @param  string $extension    File extension
 * @return string               MIME type e.g. image/jpeg
 */
function allowed_type_mime($allowedtype)
{
    if (strpos($allowedtype, "/") === false) {
        // Get extended list of mime types to convert legacy extensions to Uppy mime type syntax.
        $found_types = get_mime_types_by_extension($allowedtype);
        /** {@see include/mime_types.php} */
        return $found_types === [] ? $allowedtype : reset($found_types);
    } else {
        // already a mime type
        return $allowedtype;
    }
}

/**
 * Send a mail - but correctly encode the message/subject in quoted-printable UTF-8.
 *
 * NOTE: $from is the name of the user sending the email,
 * while $from_name is the name that should be put in the header, which can be the system name
 * It is necessary to specify two since in all cases the email should be able to contain the user's name.
 *
 * Old mail function remains the same to avoid possible issues with phpmailer
 * send_mail_phpmailer allows for the use of text and html (multipart) emails,
 * and the use of email templates in Manage Content.
 *
 * @param  string $email            Email address to send to
 * @param  string $subject          Email subject
 * @param  string $message          Message text
 * @param  string $from             From address - defaults to $email_from
 * @param  string $reply_to         Reply to address - defaults to $email_from
 * @param  string $html_template    Optional template (this is a $lang entry with placeholders)
 * @param  array $templatevars      Used to populate email template placeholders
 * @param  string $from_name        Email from name
 * @param  string $cc               Optional CC addresses
 * @param  string $bcc              Optional BCC addresses
 * @param  array $files             Optional array of file paths to attach in the format [filename.txt => /path/to/file.txt]
 */
function send_mail($email, $subject, $message, $from = "", $reply_to = "", $html_template = "", $templatevars = array(), $from_name = "", $cc = "", $bcc = "", $files = array())
{
    global $applicationname, $use_phpmailer, $email_from, $email_notify, $always_email_copy_admin, $baseurl, $userfullname;
    global $email_footer, $header_colour_style_override, $userref, $email_rate_limit, $lang, $useremail_rate_limit_active;

    if (defined("RS_TEST_MODE")) {
        return false;
    }

    /*
    Checking email is valid. Email argument can be an RFC 2822 compliant string so handle multi addresses as well
    IMPORTANT: FILTER_VALIDATE_EMAIL is not fully RFC 2822 compliant, an email like "Another User <anotheruser@example.com>"
    will be invalid
    */
    $rfc_2822_multi_delimiters = array(', ', ',');
    $email = str_replace($rfc_2822_multi_delimiters, '**', $email);
    $check_emails = explode('**', $email);
    $valid_emails = array();
    foreach ($check_emails as $check_email) {
        if (!filter_var($check_email, FILTER_VALIDATE_EMAIL) || check_email_invalid($check_email)) {
            debug("send_mail: Invalid e-mail address - '{$check_email}'");
            continue;
        }

        $valid_emails[] = $check_email;
    }
    // No/invalid email address? Exit.
    if (empty($valid_emails)) {
        debug("send_mail: No valid e-mail address found!");
        return false;
    }
    // Valid emails? then make it back into an RFC 2822 compliant string
    $email = implode(', ', $valid_emails);

    if (isset($email_rate_limit)) {
        // Limit the number of e-mails sent across the system per hour.
        $count = ps_value("select count(*) value from mail_log where date >= DATE_SUB(now(),interval 1 hour)", [], 0);
        if (($count + count($valid_emails)) > $email_rate_limit) {
            if (isset($userref) && !($useremail_rate_limit_active ?? false)) {
                // Rate limit not previously active, activate and warn them.
                ps_query("update user set email_rate_limit_active=1 where ref=?", ["i",$userref]);
                // MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL to prevent sending email via message_add() as this will cause loop.
                message_add([$userref], $lang["email_rate_limit_active"], '', null, MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL);
            }
            debug("E-mail not sent due to email_rate_limit being exceeded");
            return $lang["email_rate_limit_active"]; // Don't send the e-mail and return the error.
        } else {
            // It's OK to send mail, if rate limit was previously active, reset it
            if ($useremail_rate_limit_active ?? false) {
                ps_query("update user set email_rate_limit_active=0 where ref=?", ["i",$userref]);
                // Send them a message
                // MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL to prevent sending email via message_add() as this will cause loop.
                message_add([$userref], $lang["email_rate_limit_inactive"], '', null, MESSAGE_ENUM_NOTIFICATION_TYPE_EMAIL);
            }
        }
    }

    if ($always_email_copy_admin) {
        $bcc .= "," . $email_notify;
    }

    $subject = strip_tags($subject);

    // Validate all files to attach are valid and copy any that are URLs locally
    $attachfiles = array();
    $deletefiles = array();
    foreach ($files as $filename => $file) {
        if (substr($file, 0, 4) == "http") {
            $ctx = stream_context_create(array(
                'http' => array(
                    'method' => 'POST',
                    'timeout' => 2,
                    "ignore_errors" => true,
                    )
                ));
            $filedata = file_get_contents($file, false, $ctx); # File is a URL, not a binary object. Go and fetch the file.
            $file = get_temp_dir() . "/mail_" . uniqid() . ".bin";
            file_put_contents($file, $filedata);
            $deletefiles[] = $file;
        } elseif (!file_exists($file)) {
            debug("file missing: " . $file);
            continue;
        }
        $attachfiles[$filename] = $file;
    }

    # Send a mail - but correctly encode the message/subject in quoted-printable UTF-8.
    if ($use_phpmailer) {
        send_mail_phpmailer($email, $subject, $message, $from, $reply_to, $html_template, $templatevars, $from_name, $cc, $bcc, $attachfiles);
        cleanup_files($deletefiles);
        return true;
    }

    # Include footer

    # Work out correct EOL to use for mails (should use the system EOL).
    if (defined("PHP_EOL")) {
        $eol = PHP_EOL;
    } else {
        $eol = "\r\n";
    }

    $headers = '';
    $quoted_printable_encoding = true;

    if (count($attachfiles) > 0) {
        //add boundary string and mime type specification
        $random_hash = md5(date('r', time()));
        $headers .= "Content-Type: multipart/mixed; boundary=\"PHP-mixed-" . $random_hash . "\"" . $eol;

        $body = "This is a multi-part message in MIME format." . $eol . "--PHP-mixed-" . $random_hash . $eol;
        $body .= "Content-Type: text/plain; charset=\"utf-8\"" . $eol . "Content-Transfer-Encoding: 8bit" . $eol . $eol;
        $body .= $message . $eol . $eol . $eol;
        # Attach all the files (paths have already been checked)
        foreach ($attachfiles as $filename => $file) {
            $filedata = file_get_contents($file);
            $attachment = chunk_split(base64_encode($filedata));
            $body .= "--PHP-mixed-" . $random_hash . $eol;
            $body .= "Content-Type: application/octet-stream; name=\"" . $filename . "\"" . $eol;
            $body .= "Content-Transfer-Encoding: base64" . $eol;
            $body .= "Content-Disposition: attachment; filename=\"" . $filename . "\"" . $eol . $eol;
            $body .= $attachment;
        }
        $body .= "--PHP-mixed-" . $random_hash . "--" . $eol; # Final terminating boundary.

        $message = $body;
        $quoted_printable_encoding = false; // Ensure attachment names and utf8 text do not get corrupted
    }

    $message .= $eol . $eol . $eol . $email_footer;

    $subject_for_mail_log = $subject;
    if ($quoted_printable_encoding) {
        $message = rs_quoted_printable_encode($message);
        $subject = rs_quoted_printable_encode_subject($subject);
    }

    if ($from == "") {
        $from = $email_from;
    }
    if ($reply_to == "") {
        $reply_to = $email_from;
    }
    if ($from_name == "") {
        $from_name = $applicationname;
    }

    if (substr($reply_to, -1) == ",") {
        $reply_to = substr($reply_to, 0, -1);
    }

    $reply_tos = explode(",", $reply_to);

    $headers .= "From: ";
    #allow multiple emails, and fix for long format emails
    for ($n = 0; $n < count($reply_tos); $n++) {
        if ($n != 0) {
            $headers .= ",";
        }
        if (strstr($reply_tos[$n], "<")) {
            $rtparts = explode("<", $reply_tos[$n]);
            $headers .= $rtparts[0] . " <" . $rtparts[1];
        } else {
            mb_internal_encoding("UTF-8");
            $headers .= mb_encode_mimeheader($from_name, "UTF-8") . " <" . $reply_tos[$n] . ">";
        }
    }
    $headers .= $eol;
    $headers .= "Reply-To: $reply_to" . $eol;

    if ($cc != "") {
        #allow multiple emails, and fix for long format emails
        $ccs = explode(",", $cc);
        $headers .= "Cc: ";
        for ($n = 0; $n < count($ccs); $n++) {
            if ($n != 0) {
                $headers .= ",";
            }
            if (strstr($ccs[$n], "<")) {
                $ccparts = explode("<", $ccs[$n]);
                $headers .= $ccparts[0] . " <" . $ccparts[1];
            } else {
                mb_internal_encoding("UTF-8");
                $headers .= mb_encode_mimeheader((string) $userfullname, "UTF-8") . " <" . $ccs[$n] . ">";
            }
        }
        $headers .= $eol;
    }

    if ($bcc != "") {
        #add bcc
        $bccs = explode(",", $bcc);
        $headers .= "Bcc: ";
        for ($n = 0; $n < count($bccs); $n++) {
            if ($n != 0) {
                $headers .= ",";
            }
            if (strstr($bccs[$n], "<")) {
                $bccparts = explode("<", $bccs[$n]);
                $headers .= $bccparts[0] . " <" . $bccparts[1];
            } else {
                mb_internal_encoding("UTF-8");
                $headers .= mb_encode_mimeheader($userfullname, "UTF-8") . " <" . $bccs[$n] . ">";
            }
        }
        $headers .= $eol;
    }

    $headers .= "Date: " . date("r") .  $eol;
    $headers .= "Message-ID: <" . date("YmdHis") . $from . ">" . $eol;
    $headers .= "MIME-Version: 1.0" . $eol;
    $headers .= "X-Mailer: PHP Mail Function" . $eol;
    if (!is_html($message)) {
        $headers .= "Content-Type: text/plain; charset=\"UTF-8\"" . $eol;
    } else {
        $headers .= "Content-Type: text/html; charset=\"UTF-8\"" . $eol;
        // Add CSS links so email can use the styles
        $messageprefix = '<link href="' . $baseurl . '/css/global.css" rel="stylesheet" type="text/css" media="screen,projection,print" />';
        $messageprefix .= '<link href="' . $baseurl . '/css/light.css" rel="stylesheet" type="text/css" media="screen,projection,print" />';
        $messageprefix .= '<link href="' . $baseurl . '/css/css_override.php" rel="stylesheet" type="text/css" media="screen,projection,print" />';
        $message = $messageprefix . $message;
    }
    $headers .= "Content-Transfer-Encoding: quoted-printable" . $eol;
    log_mail($email, $subject_for_mail_log, $reply_to);
    mail($email, $subject, $message, $headers);
    cleanup_files($deletefiles);
    return true;
}

/**
 * if ($use_phpmailer==true) this function is used instead.
 *
 * Mail templates can include lang, server, site_text, and POST variables by default
 * ex ( [lang_mycollections], [server_REMOTE_ADDR], [text_footer] , [message]
 *
 * additional values must be made available through $templatevars
 * For example, a complex url or image path that may be sent in an
 * email should be added to the templatevars array and passed into send_mail.
 * available templatevars need to be well-documented, and sample templates
 * need to be available.
 *
 * @param  string $email           Email address to send to
 * @param  string $subject          Email subject
 * @param  string $message          Message text
 * @param  string $from             From address - defaults to $email_from
 * @param  string $reply_to         Reply to address - defaults to $email_from
 * @param  string $html_template    Optional template (this is a $lang entry with placeholders)
 * @param  array $templatevars     Used to populate email template placeholders
 * @param  string $from_name        Email from name
 * @param  string $cc               Optional CC addresses
 * @param  string $bcc              Optional BCC addresses
 * @param  array $files             Optional array of file paths to attach in the format [filename.txt => /path/to/file.txt]
 * @return void
 */

function send_mail_phpmailer($email, $subject, $message = "", $from = "", $reply_to = "", $html_template = "", $templatevars = array(), $from_name = "", $cc = "", $bcc = "", $files = array())
{
    # Include footer
    global $header_colour_style_override, $email_from;

    if (check_email_invalid($email)) {
        return false;
    }

    $from_system = false;
    if ($from == "") {
        $from = $email_from;
        $from_system = true;
    }
    if ($reply_to == "") {
        $reply_to = $email_from;
    }
    global $applicationname;
    if ($from_name == "") {
        $from_name = $applicationname;
    }

    #check for html template. If exists, attempt to include vars into message
    if ($html_template != "") {
        # Attempt to verify users by email, which allows us to get the email template by lang and usergroup
        $to_usergroup = ps_query("select lang, usergroup from user where email = ?", array("s", $email), "");

        if (count($to_usergroup) != 0) {
            $to_usergroupref = $to_usergroup[0]['usergroup'];
            $to_usergrouplang = $to_usergroup[0]['lang'];
        } else {
            $to_usergrouplang = "";
        }

        if ($to_usergrouplang == "") {
            global $defaultlanguage;
            $to_usergrouplang = $defaultlanguage;
        }

        if (isset($to_usergroupref)) {
            $modified_to_usergroupref = hook("modifytousergroup", "", $to_usergroupref);
            if (is_int($modified_to_usergroupref)) {
                $to_usergroupref = $modified_to_usergroupref;
            }
            $results = ps_query("SELECT `language`, `name`, `text` FROM site_text WHERE (`page` = 'all' OR `page` = '') AND `name` = ? AND specific_to_group = ?;", array("s", $html_template, "i", $to_usergroupref));
        } else {
            $results = ps_query("SELECT `language`, `name`, `text` FROM site_text WHERE (`page` = 'all' OR `page` = '') AND `name` = ? AND specific_to_group IS NULL;", array("s", $html_template));
        }

        global $site_text;
        for ($n = 0; $n < count($results); $n++) {
            $site_text[$results[$n]["language"] . "-" . $results[$n]["name"]] = $results[$n]["text"];
        }

        $language = $to_usergrouplang;

        if (array_key_exists($language . "-" . $html_template, $site_text)) {
            $template = $site_text[$language . "-" . $html_template];
        } else {
            global $languages;

            # Can't find the language key? Look for it in other languages.
            reset($languages);
            foreach ($languages as $key => $value) {
                if (array_key_exists($key . "-" . $html_template, $site_text)) {
                    $template = $site_text[$key . "-" . $html_template];
                    break;
                }
            }
            // Fall back to language file if not in site text
            global $lang;
            if (!isset($template)) {
                if (isset($lang["all__" . $html_template])) {
                    $template = $lang["all__" . $html_template];
                } elseif (isset($lang[$html_template])) {
                    $template = $lang[$html_template];
                }
            }
        }

        if (isset($template) && $template != "") {
            preg_match_all('/\[[^\]]*\]/', $template, $test);
            $setvalues = [];
            foreach ($test[0] as $placeholder) {
                $placeholder = str_replace("[", "", $placeholder);
                $placeholder = str_replace("]", "", $placeholder);

                if (substr($placeholder, 0, 5) == "lang_") {
                    // Get lang variables (ex. [lang_mycollections])
                    global $lang;
                    switch (substr($placeholder, 5)) {
                        case "emailcollectionmessageexternal":
                            $setvalues[$placeholder] = str_replace('[applicationname]', $applicationname, $lang["emailcollectionmessageexternal"]);
                            break;
                        case "emailcollectionmessage":
                            $setvalues[$placeholder] = str_replace('[applicationname]', $applicationname, $lang["emailcollectionmessage"]);
                            break;
                        default:
                            $setvalues[$placeholder] = $lang[substr($placeholder, 5)];
                            break;
                    }
                } elseif (substr($placeholder, 0, 5) == "text_") {
                    // Get text string (legacy)
                    $setvalues[$placeholder] = text(substr($placeholder, 5));
                } elseif ($placeholder == "client_ip") {
                    // Get server variables (ex. [server_REMOTE_ADDR] for a user request)
                    $setvalues[$placeholder] = get_ip();
                } elseif ($placeholder == 'img_headerlogo') {
                    // Add header image to email if not using template
                    $img_url = get_header_image(true);
                    $img_div_style = "max-height:50px;padding: 5px;";
                    $img_div_style .= "background: " . ((isset($header_colour_style_override) && $header_colour_style_override != '') ? $header_colour_style_override : "rgba(0, 0, 0, 0.6)") . ";";
                    $setvalues[$placeholder] = '<div style="' . $img_div_style . '"><img src="' . $img_url . '" style="max-height:50px;"  /></div><br /><br />';
                } elseif ($placeholder == "embed_thumbnail") {
                    # [embed_thumbnail] (requires url in templatevars['thumbnail'])
                    $thumbcid = uniqid('thumb');
                    $embed_thumbnail = true;
                    $setvalues[$placeholder] = "<img style='border:1px solid #d1d1d1;' src='cid:$thumbcid' />";
                } else {
                    // Not recognised, skip this
                    continue;
                }

                # Don't overwrite templatevars that have been explicitly passed
                if (!isset($templatevars[$placeholder]) && isset($setvalues[$placeholder])) {
                    $templatevars[$placeholder] = $setvalues[$placeholder];
                }
            }

            foreach ($templatevars as $key => $value) {
                $template = str_replace("[" . $key . "]", nl2br($value), $template);
            }

            $body = $template;
        }
    }

    if (!isset($body)) {
        $body = $message;
    }

    global $use_smtp,$smtp_secure,$smtp_host,$smtp_port,$smtp_auth,$smtp_username,$smtp_password,$debug_log,$smtpautotls, $smtp_debug_lvl;
    $mail = new PHPMailer\PHPMailer\PHPMailer();
    // use an external SMTP server? (e.g. Gmail)
    if ($use_smtp) {
        $mail->IsSMTP(); // enable SMTP
        $mail->SMTPAuth = $smtp_auth;  // authentication enabled/disabled
        $mail->SMTPSecure = $smtp_secure; // '', 'tls' or 'ssl'
        $mail->SMTPAutoTLS = $smtpautotls;
        $mail->SMTPDebug = ($debug_log ? $smtp_debug_lvl : 0);
        $mail->Debugoutput = function (string $msg, int $debug_lvl) {
            debug("SMTPDebug: {$msg}");
        };
        $mail->Host = $smtp_host; // hostname
        $mail->Port = $smtp_port; // port number
        $mail->Username = $smtp_username; // username
        $mail->Password = $smtp_password; // password
    }
    $reply_tos = explode(",", $reply_to);

    if (!$from_system) {
        // only one from address is possible, so only use the first one:
        if (strstr($reply_tos[0], "<")) {
            $rtparts = explode("<", $reply_tos[0]);
            $mail->From = str_replace(">", "", $rtparts[1]);
            $mail->FromName = $rtparts[0];
        } else {
            $mail->From = $reply_tos[0];
            $mail->FromName = $from_name;
        }
    } else {
        $mail->From = $from;
        $mail->FromName = $from_name;
    }

    // if there are multiple addresses, that's what replyto handles.
    for ($n = 0; $n < count($reply_tos); $n++) {
        if (strstr($reply_tos[$n], "<")) {
            $rtparts = explode("<", $reply_tos[$n]);
            $mail->AddReplyto(str_replace(">", "", $rtparts[1]), $rtparts[0]);
        } else {
            $mail->AddReplyto($reply_tos[$n], $from_name);
        }
    }

    # modification to handle multiple comma delimited emails
    # such as for a multiple $email_notify
    $emails = $email;
    $emails = explode(',', $emails);
    $emails = array_map('trim', $emails);
    foreach ($emails as $email) {
        if (strstr($email, "<")) {
            $emparts = explode("<", $email);
            $mail->AddAddress(str_replace(">", "", $emparts[1]), $emparts[0]);
        } else {
            $mail->AddAddress($email);
        }
    }

    if ($cc != "") {
        # modification for multiple is also necessary here, though a broken cc seems to be simply removed by phpmailer rather than breaking it.
        $ccs = $cc;
        $ccs = explode(',', $ccs);
        $ccs = array_map('trim', $ccs);
        global $userfullname;
        foreach ($ccs as $cc) {
            if (strstr($cc, "<")) {
                $ccparts = explode("<", $cc);
                $mail->AddCC(str_replace(">", "", $ccparts[1]), $ccparts[0]);
            } else {
                $mail->AddCC($cc, $userfullname);
            }
        }
    }
    if ($bcc != "") {
        # modification for multiple is also necessary here, though a broken cc seems to be simply removed by phpmailer rather than breaking it.
        $bccs = $bcc;
        $bccs = explode(',', $bccs);
        $bccs = array_map('trim', $bccs);
        global $userfullname;
        foreach ($bccs as $bccemail) {
            if (strstr($bccemail, "<")) {
                $bccparts = explode("<", $bccemail);
                $mail->AddBCC(str_replace(">", "", $bccparts[1]), $bccparts[0]);
            } else {
                $mail->AddBCC($bccemail, $userfullname);
            }
        }
    }

    $mail->CharSet = "utf-8";

    if (is_html($body)) {
        $mail->IsHTML(true);

        // Standardise line breaks
        $body = str_replace(["\r\n","\r","\n"], "", $body); // HTML supplied - not considering these.
        $body = str_replace(["<br/>","<br>"], "<br />", $body);

        // Remove any sequences of three or more line breaks with doubles
        while (strpos($body, "<br /><br /><br />") !== false) {
            $body = str_replace("<br /><br /><br />", "<br /><br />", $body);
        }

        // Also remove any unnecessary line breaks that were already formatted by HTML paragraphs
        $body = str_replace(["</p><br /><br />","</p><br />"], "</p>", $body);
    } else {
        $mail->IsHTML(false);
    }

    $mail->Subject = $subject;
    $mail->Body    = $body;

    if (isset($embed_thumbnail) && isset($templatevars['thumbnail'])) {
        $mail->AddEmbeddedImage($templatevars['thumbnail'], $thumbcid, $thumbcid, 'base64', 'image/jpeg');
    }

    if (count($files) > 0) {
        # Attach all the files
        foreach ($files as $filename => $file) {
            if (substr($file, 0, 4) == "http") {
                $ctx = stream_context_create(array(
                    'http' => array(
                        'method' => 'POST',
                        'timeout' => 2,
                        "ignore_errors" => true,
                        )
                    ));
                $file = file_get_contents($file, false, $ctx); # File is a URL, not a binary object. Go and fetch the file.
            } elseif (!file_exists($file)) {
                debug("file missing: " . $file);
                continue;
            }

            $mail->AddAttachment($file, $filename);
        }
    }

    if (is_html($body)) {
        $mail->AltBody = $mail->html2text($body);
    }

    log_mail($email, $subject, $reply_to);

    $GLOBALS["use_error_exception"] = true;
    try {
        $mail->Send();
    } catch (Exception $e) {
        echo "Message could not be sent. <p>";
        debug("PHPMailer Error: email: " . $email . " - " . $e->errorMessage());
        exit;
    }
    unset($GLOBALS["use_error_exception"]);
}

/**
 *  Log email
 *
 * Data logged is:
 * Time
 * To address
 * From, User ID or 0 for system emails (cron etc.)
 * Subject
 *
 * @param  string $email
 * @param  string $subject
 * @param  string $sender    The email address of the sender
 * @return void
 */
function log_mail($email, $subject, $sender)
{
    global $userref;
    if (isset($userref)) {
        $from = $userref;
    } else {
        $from = 0;
    }
    $sub = mb_strcut($subject, 0, 100);

    // Record a separate log entry for each email recipient
    $email_recipients = explode(', ', $email);
    $sql = array();
    $params = array();
    foreach ($email_recipients as $email_recipient) {
        $sql[] = '(NOW(), ?, ?, ?, ?)';
        $params = array_merge($params, array("s", $email_recipient, "i", $from, "s", $sub, "s", $sender));
    }

    // Write log to database
    ps_query("INSERT into mail_log (`date`, mail_to, mail_from, `subject`, sender_email) VALUES " . implode(", ", $sql) . ";", $params);
}

/**
 * Quoted printable encoding is rather simple.
 * Each character in the string $string should be encoded if:
 *      Character code is <0x20 (space)
 *      Character is = (as it has a special meaning: 0x3d)
 *      Character is over ASCII range (>=0x80)
 *
 * @param  string $string
 * @param  integer $linelen
 * @param  string $linebreak
 * @param  integer $breaklen
 * @param  boolean $encodecrlf
 * @return string
 */
function rs_quoted_printable_encode($string, $linelen = 0, $linebreak = "=\r\n", $breaklen = 0, $encodecrlf = false)
{
    $len = strlen($string);
    $result = '';
    for ($i = 0; $i < $len; $i++) {
        if ($linelen >= 76) { // break lines over 76 characters, and put special QP linebreak
                $linelen = $breaklen;
                $result .= $linebreak;
        }
            $c = ord($string[$i]);
        if (($c == 0x3d) || ($c >= 0x80) || ($c < 0x20)) { // in this case, we encode...
            if ((($c == 0x0A) || ($c == 0x0D)) && (!$encodecrlf)) { // but not for linebreaks
                    $result .= chr($c);
                    $linelen = 0;
                    continue;
            }
                $result .= '=' . str_pad(strtoupper(dechex($c)), 2, '0');
                $linelen += 3;
                continue;
        }
            $result .= chr($c); // normal characters aren't encoded
            $linelen++;
    }
    return $result;
}

/**
 * As rs_quoted_printable_encode() but for e-mail subject
 *
 * @param  string $string
 * @param  string $encoding
 * @return string
 */
function rs_quoted_printable_encode_subject($string, $encoding = 'UTF-8')
{
    // use this function with headers, not with the email body as it misses word wrapping
    $len = strlen($string);
    $result = '';
    $enc = false;

    for ($i = 0; $i < $len; ++$i) {
        $c = $string[$i];
        if (ctype_alpha($c)) {
            $result .= $c;
        } elseif ($c == ' ') {
            $result .= '_';
            $enc = true;
        } else {
            $result .= sprintf("=%02X", ord($c));
            $enc = true;
        }
    }

    //L: so spam agents won't mark your email with QP_EXCESS
    if (!$enc) {
        return $string;
    }

    return '=?' . $encoding . '?q?' . $result . '?=';
}

/**
 * A generic pager function used by many display lists in ResourceSpace.
 *
 * Requires the following globals to be set or passed inb the $options array
 * $url         - Current page url
 * $curpage     - Current page
 * $totalpages  - Total number of pages
 *
 * @param  boolean $break
 * @param  boolean $scrolltotop
 * @param  array   $options - array of options to use instead of globals
 * @return void
 */
function pager($break = true, $scrolltotop = true, $options = array())
{
    global $curpage, $url, $url_params, $totalpages, $offset, $per_page, $jumpcount, $pagename, $confirm_page_change, $lang;

    $curpage = $options['curpage'] ?? $curpage;
    $url = $options['url'] ?? $url;
    $url_params = $options['url_params'] ?? $url_params;
    $totalpages = $options['totalpages'] ?? $totalpages;
    $offset = $options['offset'] ?? $offset;
    $per_page = $options['per_page'] ?? $per_page;
    $jumpcount = $options['jumpcount'] ?? $jumpcount;
    $confirm_page_change = $options['confirm_page_change'] ?? $confirm_page_change;

    $modal  = ('true' == getval('modal', ''));
    $scroll =  $scrolltotop ? "true" : "false";
    $jumpcount++;

    // If pager URL includes query string params, remove them and store in $url_params array
    if (!isset($url_params) && strpos($url, "?") !== false) {
        $urlparts = explode("?", $url);
        parse_str($urlparts[1], $url_params);
        $url = $urlparts[0];
    }

    unset($url_params["offset"]);

    if ($totalpages != 0 && $totalpages != 1) { ?>     
        <span class="TopInpageNavRight">
            <?php if ($break) { ?>
                &nbsp;<br />
            <?php }

            if ($curpage > 1) { ?>
                <a
                    class="prevPageLink"
                    title="<?php echo escape($lang["previous"]) ?>"
                    href="<?php echo generateURL($url, (isset($url_params) ? $url_params : array()), array("go" => "prev","offset" => ($offset - $per_page)));?>"
                    onclick="<?php echo $confirm_page_change;?> return <?php echo $modal ? 'Modal' : 'CentralSpace'; ?>Load(this, <?php echo $scroll; ?>);"
                >
            <?php } ?>
            <i aria-hidden="true" class="icon-arrow-left"></i><?php echo ($curpage > 1) ? "</a>" : ''; ?>&nbsp;&nbsp;

            <div class="JumpPanel" id="jumppanel<?php echo $jumpcount?>" style="display:none;">
                <?php echo escape($lang["jumptopage"]) ?>: 
                <input
                    type="text"
                    size="1"
                    id="jumpto<?php echo $jumpcount?>"
                    onkeydown="
                        var evt = event || window.event;
                        if (evt.keyCode == 13) {
                            var jumpto = document.getElementById('jumpto<?php echo $jumpcount?>').value;
                            if (jumpto < 1) {
                                jumpto = 1;
                            };
                            if (jumpto > <?php echo $totalpages?>) {
                                jumpto = <?php echo $totalpages?>;
                            };
                            <?php echo $modal ? 'Modal' : 'CentralSpace'; ?>Load('<?php echo generateURL($url, (isset($url_params) ? $url_params : array()), array("go" => "page")); ?>&amp;offset=' + ((jumpto - 1) * <?php echo urlencode($per_page) ?>), <?php echo $scroll; ?>);
                        }">
                &nbsp;
                <a
                    aria-hidden="true"
                    class="icon-circle-x"
                    href="#"
                    onclick="
                        document.getElementById('jumppanel<?php echo $jumpcount?>').style.display='none';
                        document.getElementById('jumplink<?php echo $jumpcount?>').style.display='inline';">
                </a>
            </div>

            <a
                href="#"
                id="jumplink<?php echo $jumpcount?>"
                title="<?php echo escape($lang["jumptopage"]) ?>"
                onclick="
                    document.getElementById('jumppanel<?php echo $jumpcount?>').style.display='inline';
                    document.getElementById('jumplink<?php echo $jumpcount?>').style.display='none';
                    document.getElementById('jumpto<?php echo $jumpcount?>').focus();
                    return false;">
                <?php echo escape($lang["page"]) ?>&nbsp;<?php echo escape($curpage) ?>&nbsp;<?php echo escape($lang["of"]) ?>&nbsp;<?php echo $totalpages?>
            </a>
            &nbsp;&nbsp;
            <?php if ($curpage < $totalpages) { ?>
                <a
                    class="nextPageLink"
                    title="<?php echo escape($lang["next"]) ?>"
                    href="<?php echo generateURL($url, (isset($url_params) ? $url_params : array()), array("go" => "next","offset" => ($offset + $per_page)));?>"
                    onclick="<?php echo $confirm_page_change;?> return <?php echo $modal ? 'Modal' : 'CentralSpace'; ?>Load(this, <?php echo $scroll; ?>);">
            <?php } ?>
            
            <i aria-hidden="true" class="icon-arrow-right"></i>

            <?php if ($curpage < $totalpages) { ?>
                </a>
            <?php } ?>
        </span>

    <?php } else { ?>
        <span class="HorizontalWhiteNav">&nbsp;</span>
        <div style="display: <?php echo ($pagename == "search") ? "block" : "inline"; ?>">&nbsp;</div>
        <?php
    }
}

/**
 * Remove the extension part of a filename
 *
 * @param  mixed $strName   The filename
 * @return string           The filename minus the extension
 */
function remove_extension($strName)
{
    $ext = strrchr((string) $strName, '.');
    if ($ext !== false) {
        $strName = substr($strName, 0, -strlen($ext));
    }
    return $strName;
}

/**
 * Retrieve a list of permitted extensions for the given resource type.
 *
 * @param  integer $resource_type
 * @return string
 */
function get_allowed_extensions_by_type($resource_type)
{
    return ps_value("select allowed_extensions value from resource_type where ref = ?", array("i", $resource_type), "", "schema");
}

/**
 * Detect if a path is relative or absolute.
 * If it is relative, we compute its absolute location by assuming it is
 * relative to the application root (parent folder).
 *
 * @param string $path A relative or absolute path
 * @param boolean $create_if_not_exists Try to create the path if it does not exists. Default to False.
 * @access public
 * @return string A absolute path
 */
function getAbsolutePath($path, $create_if_not_exists = false)
{
    if (preg_match('/^(\/|[a-zA-Z]:[\\/]{1})/', $path)) { // If the path start by a '/' or 'c:\', it is an absolute path.
        $folder = $path;
    } else // It is a relative path.
        {
        $folder = sprintf('%s%s..%s%s', __DIR__, DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR, $path);
    }

    if ($create_if_not_exists && !file_exists($folder)) { // Test if the path need to be created.
        mkdir($folder, 0777);
    } // Test if the path need to be created.

    return $folder;
}

/**
 * Find the files present in a folder, and sub-folder.
 *
 * @param string $path The path to look into.
 * @param boolean $recurse Trigger the recursion, default to True.
 * @param boolean $include_hidden Trigger the listing of hidden files / hidden directories, default to False.
 * @access public
 * @return array A list of files present in the inspected folder (paths are relative to the inspected folder path).
 */
function getFolderContents($path, $recurse = true, $include_hidden = false)
{
    if (!is_dir($path)) { // Test if the path is not a folder.
        return array();
    } // Test if the path is not a folder.

    $directory_handle = opendir($path);
    if ($directory_handle === false) { // Test if the directory listing failed.
        return array();
    } // Test if the directory listing failed.

    $files = array();
    while (($file = readdir($directory_handle)) !== false) { // For each directory listing entry.
        if (
            ! in_array($file, array('.', '..')) // Test if file is not unix parent and current path.
            && ($include_hidden || ! preg_match('/^\./', $file)) // Test if the file can be listed.
        ) {
                $complete_path = $path . DIRECTORY_SEPARATOR . $file;
            if (is_dir($complete_path) && $recurse) { // If the path is a directory, and need to be explored.
                    $sub_dir_files = getFolderContents($complete_path, $recurse, $include_hidden);
                foreach ($sub_dir_files as $sub_dir_file) { // For each subdirectory contents.
                    $files[] = $file . DIRECTORY_SEPARATOR . $sub_dir_file;
                } // For each subdirectory contents.
            } elseif (is_file($complete_path)) { // If the path is a file.
                    $files[] = $file;
            }
        } // Test if file is not unix parent and current path.
    } // For each directory listing entry.

    // We close the directory handle:
    closedir($directory_handle);

    // We sort the files alphabetically.
    natsort($files);

    return $files;
}

/**
 * Returns filename component of path
 * This version is UTF-8 proof.
 *
 * @param string $file A path.
 * @access public
 * @return string Returns the base name of the given path.
 */
function mb_basename($file)
{
    $regex_file = preg_split('/[\\/]+/', $file);
    return end($regex_file);
}

/**
 * Remove the extension part of a filename.
 *
 * @param string $name A file name.
 * @access public
 * @return string Return the file name without the extension part.
 */
function strip_extension($name, $use_ext_list = false)
{
        $s = strrpos($name, '.');
    if ($s === false) {
        return $name;
    } else {
        global $download_filename_strip_extensions;
        if ($use_ext_list && isset($download_filename_strip_extensions)) {
            // Use list of specified extensions if config present.
            $fn_extension = substr($name, $s + 1);
            if (in_array(strtolower($fn_extension), $download_filename_strip_extensions)) {
                    return substr($name, 0, $s);
            } else {
                return $name;
            }
        } else {
            // Attempt to remove file extension from string where download_filename_strip_extensions is not configured.
            return substr($name, 0, $s);
        }
    }
}

/**
 * Checks to see if a process lock exists for the given process name.
 *
 * @param  string $name     Name of lock to check
 * @return boolean TRUE if a current process lock is in place, false if not
 */
function is_process_lock($name)
{
    global $storagedir,$process_locks_max_seconds;

    # Check that tmp/process_locks exists, create if not.
    # Since the get_temp_dir() method does this checking, omit: if(!is_dir($storagedir . "/tmp")){mkdir($storagedir . "/tmp",0777);}
    if (!is_dir(get_temp_dir() . "/process_locks")) {
        mkdir(get_temp_dir() . "/process_locks", 0777);
    }

    $file = get_temp_dir() . "/process_locks/" . $name;
    if (!file_exists($file)) {
        return false;
    }
    if (!is_readable($file)) {
        return true;
    } // Lock exists and cannot read it so must assume it's still valid

    $GLOBALS["use_error_exception"] = true;
    try {
        $time = trim(file_get_contents($file));
        if ((time() - (int) $time) > $process_locks_max_seconds) {
            return false;
        } # Lock has expired
    } catch (Exception $e) {
        debug("is_process_lock: Attempt to get file contents '$file' failed. Reason: {$e->getMessage()}");
    }
    unset($GLOBALS["use_error_exception"]);

    return true; # Lock is valid
}

/**
 * Set a process lock
 *
 * @param  string $name
 * @return boolean
 */
function set_process_lock($name)
{
    file_put_contents(get_temp_dir() . "/process_locks/" . $name, time());
    // make sure this is editable by the server in case a process lock could be set by different system users
    chmod(get_temp_dir() . "/process_locks/" . $name, 0777);
    return true;
}

/**
 * Clear a process lock
 *
 * @param  string $name
 * @return boolean
 */
function clear_process_lock($name)
{
    if (!file_exists(get_temp_dir() . "/process_locks/" . $name)) {
        return false;
    }
    unlink(get_temp_dir() . "/process_locks/" . $name);
    return true;
}

/**
 * Custom function for retrieving a file size. A resolution for PHP's issue with large files and filesize().
 *
 * @param  string $path
 * @return integer|bool  The file size in bytes
 */
function filesize_unlimited($path)
{
    if ('WINNT' == PHP_OS) {
        if (class_exists('COM')) {
            try {
                $filesystem = new COM('Scripting.FileSystemObject');
                $file       = $filesystem->GetFile($path);

                return $file->Size();
            } catch (com_exception $e) {
                return false;
            }
        }

        return exec('for %I in (' . escapeshellarg($path) . ') do @echo %~zI');
    } elseif ('Darwin' == PHP_OS || 'FreeBSD' == PHP_OS) {
        $bytesize = exec("stat -f '%z' " . escapeshellarg($path));
    } else {
        $bytesize = exec("stat -c '%s' " . escapeshellarg($path));
    }

    if (!is_int_loose($bytesize)) {
        $GLOBALS["use_error_exception"] = true;
        try {
            $bytesize = filesize($path); # Bomb out, the output wasn't as we expected. Return the filesize() output.
        } catch (Throwable $e) {
            return false;
        }
        unset($GLOBALS["use_error_exception"]);
    }

    if (is_int_loose($bytesize)) {
        return (int) $bytesize;
    }
    return false;
}

/**
 * Strip the leading comma from a string
 *
 * @param  string $val
 * @return string
 */
function strip_leading_comma($val)
{
    return preg_replace('/^\,/', '', (string) $val);
}

/**
 * Determines where the tmp directory is.  There are three options here:
 * 1. tempdir - If set in config.php, use this value.
 * 2. storagedir ."/tmp" - If storagedir is set in config.php, use it and create a subfolder tmp.
 * 3. generate default path - use filestore/tmp if all other attempts fail.
 * 4. if a uniqid is provided, create a folder within tmp and return the full path
 *
 * @param bool $asUrl - If we want the return to be like http://my.resourcespace.install/path set this as true.
 * @return string Path to the tmp directory.
 */
function get_temp_dir($asUrl = false, $uniqid = "")
{
    global $storagedir, $tempdir;
    // Set up the default.
    $result = dirname(__DIR__) . "/filestore/tmp";

    // if $tempdir is explicity set, use it.
    if (isset($tempdir)) {
        // Make sure the dir exists.
        if (!is_dir($tempdir)) {
            // If it does not exist, create it.
            mkdir($tempdir, 0777);
        }
        $result = $tempdir;
    }
    // Otherwise, if $storagedir is set, use it.
    elseif (isset($storagedir)) {
        // Make sure the dir exists.
        if (!is_dir($storagedir . "/tmp")) {
            // If it does not exist, create it.
            mkdir($storagedir . "/tmp", 0777);
        }
        $result = $storagedir . "/tmp";
    } else {
        // Make sure the dir exists.
        if (!is_dir($result)) {
            // If it does not exist, create it.
            mkdir($result, 0777);
        }
    }

    if ($uniqid != "") {
        $uniqid = md5($uniqid);
        $result .= "/$uniqid";
        if (!is_dir($result)) {
            $GLOBALS["use_error_exception"] = true;
            try {
                mkdir($result, 0777, true);
            } catch (Exception $e) {
                debug("get_temp_dir: {$result} ERROR-" . $e->getMessage());
            }
            unset($GLOBALS["use_error_exception"]);
        }
    }

    // return the result.
    if ($asUrl) {
        $result = convert_path_to_url($result);
        $result = str_replace('\\', '/', $result);
    }

    return $result;
}

/**
 * Converts a path to a url relative to the installation.
 *
 * @param string $abs_path: The absolute path.
 * @return Url that is the relative path.
 */
function convert_path_to_url($abs_path)
{
    // Get the root directory of the app:
    $rootDir = dirname(__DIR__);
    // Get the baseurl:
    global $baseurl;
    // Replace the $rootDir with $baseurl in the path given:
    return str_ireplace($rootDir, $baseurl, $abs_path);
}

/**
* Escaping an unsafe command string
*
* @param  string  $cmd   Unsafe command to run
* @param  array   $args  List of placeholders and their values which will have to be escapedshellarg()d.
*
* @return string Escaped command string
*/
function escape_command_args($cmd, array $args): string
{
    debug_function_call(__FUNCTION__, func_get_args());
    if ($args === []) {
        return $cmd;
    }

    foreach ($args as $placeholder => $value) {
        if (strpos($cmd, $placeholder) === false) {
            debug("Unable to find arg '{$placeholder}' in '{$cmd}'. Make sure the placeholder exists in the command string");
            continue;
        } elseif (!($value instanceof CommandPlaceholderArg)) {
            $value = new CommandPlaceholderArg($value, null);
        }

        $cmd = str_replace($placeholder, escapeshellarg($value->__toString()), $cmd);
    }

    return $cmd;
}

/**
* Utility function which works like system(), but returns the complete output string rather than just the last line of it.
*
* @uses escape_command_args()
*
* @param  string   $command    Command to run
* @param  boolean  $geterrors  Set to TRUE to include errors in the output
* @param  array    $params     List of placeholders and their values which will have to be escapedshellarg()d.
* @param  int      $timeout    Maximum time in seconds before a command is forcibly stopped
*
* @return string Command output
*/
function run_command($command, $geterrors = false, array $params = array(), int $timeout = 0)
{
    global $debug_log,$config_windows;

    $command = escape_command_args($command, $params);
    debug("CLI command: $command");

    $cmd_tmp_file = false;
    if ($config_windows && mb_strlen($command) > 8191) {
        // Windows systems have a hard time with the long paths (often used for video generation)
        // This work-around creates a batch file containing the command, then executes that.
        $unique_key = generateSecureKey(32);
        $cmd_tmp_file = get_temp_dir() . "/run_command_" . $unique_key . ".bat";
        file_put_contents($cmd_tmp_file, $command);
        $command = $cmd_tmp_file;
    }

    $descriptorspec = array(
        1 => array("pipe", "w") // stdout is a pipe that the child will write to
    );
    if ($debug_log || $geterrors) {
        if ($config_windows) {
            $pid = getmypid();
            $log_location = get_temp_dir() . "/error_" . md5($command . serialize($params) . $pid) . ".txt";
            $descriptorspec[2] = array("file", $log_location, "w"); // stderr is a file that the child will write to
        } else {
            $descriptorspec[2] = array("pipe", "w"); // stderr is a pipe that the child will write to
        }
    }

    $child_process = -1;
    if (
        !$config_windows && $timeout > 0
        && shell_exec('which timeout') !== null
    ) {
        $command = 'timeout ' . escapeshellarg($timeout) . ' ' . $command;
    } elseif ($timeout > 0 && function_exists('pcntl_fork')) {
        // Branch child process if a timeout is specified and the timeout utility isn't available
        // Create output file so that we can retrieve the output of the child process from the parent process
        $pid = getmypid();
        $command_output = get_temp_dir() . '/output_' . md5($command . serialize($params) . $pid) . ".txt";
        $child_process  = pcntl_fork();
    }
    // Await output from child, or timeout.
    if ($child_process > 0) {
        $start_time = time();
        while ((time() - $start_time) < $timeout) {
            if (file_exists($command_output)) {
                $output = file_get_contents($command_output);
                unlink($command_output);
                return $output;
            }
        }
        // Kill the child process to free up resources
        exec(($config_windows ? 'taskkill /F /PID ' : 'kill ') . escapeshellarg($child_process));
        return "";
    }

    $process = @proc_open($command, $descriptorspec, $pipe, null, null, array('bypass_shell' => true));

    if (!is_resource($process)) {
        if ($cmd_tmp_file) {
            unlink($cmd_tmp_file);
        }
        return '';
    }

    $output = trim(stream_get_contents($pipe[1]));
    if ($geterrors) {
        $output .= trim($config_windows ? file_get_contents($log_location) : stream_get_contents($pipe[2]));
    }
    if ($debug_log) {
        debug("CLI output: $output");
        debug("CLI errors: " . trim($config_windows ? file_get_contents($log_location) : stream_get_contents($pipe[2])));
    }
    if ($config_windows && isset($log_location)) {
        unlink($log_location);
    }
    proc_close($process);
    if ($cmd_tmp_file) {
        unlink($cmd_tmp_file);
    }
    // If this is a child process put the output into the output file and kill the process here.
    if ($child_process === 0) {
        file_put_contents($command_output, $output);
        exit();
    }
    return $output;
}

/**
 * Similar to run_command but returns an array with the resulting output (stdout & stderr) fetched concurrently
 * for improved performance.
 *
 * @param  mixed $command   Command to run
 *
 * @return array Command output
 */
function run_external($command)
{
    global $debug_log;

    $pipes = array();
    $output = array();
    # Pipes for stdin, stdout and stderr
    $descriptorspec = array(0 => array("pipe", "r"), 1 => array("pipe", "w"), 2 => array("pipe", "w"));

    debug("CLI command: $command");

    # Execute the command
    $process = proc_open($command, $descriptorspec, $pipes);

    # Ensure command returns an external PHP resource
    if (!is_resource($process)) {
        return false;
    }

    # Immediately close the input pipe
    fclose($pipes[0]);

    # Set both output streams to non-blocking mode
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);

    while (true) {
        $read = array();

        if (!feof($pipes[1])) {
            $read[] = $pipes[1];
        }

        if (!feof($pipes[2])) {
            $read[] = $pipes[2];
        }

        if (!$read) {
            break;
        }

        $write = null;
        $except = null;
        $ready = stream_select($read, $write, $except, 2);

        if ($ready === false) {
            break;
        }

        foreach ($read as $r) {
            # Read a line and strip newline and carriage return from the end
            $line = rtrim(fgets($r, 1024), "\r\n");
            $output[] = $line;
        }
    }

    # Close the output pipes
    fclose($pipes[1]);
    fclose($pipes[2]);

    debug("CLI output: " . implode("\n", $output));

    proc_close($process);

    return $output;
}

/**
 * Display a styledalert() modal error and optionally return the browser to the previous page after 2 seconds
 *
 * @param  string $error    Error text to display
 * @param  boolean $back    Return to previous page?
 * @param  integer $code    (Optional) HTTP code to return
 *
 * @return void
 */
function error_alert($error, $back = true, $code = 403)
{
    http_response_code($code);

    extract($GLOBALS, EXTR_SKIP);
    if ($back) {
        include __DIR__ . "/header.php";
    }
    ?>

    <script type='text/javascript'>
        if (typeof ModalClose === 'function') {
            ModalClose();
        }

        if (typeof styledalert === 'function') {
            styledalert('<?php echo escape($lang["error"]) ?>', '<?php echo escape($error) ?>');
        } else {
            alert('<?php echo escape($error) ?>');
        }

        <?php if ($back) { ?>
            window.setTimeout(function(){history.go(-1);},2000);
        <?php } ?>
    </script>

    <?php
    if ($back) {
        include __DIR__ . "/footer.php";
    }
}

/**
 * When displaying metadata, applies trim/wordwrap.
 *
 * @param  string $value
 * @return string
 */
function format_display_field($value)
{
    global $results_title_trim,$results_title_wordwrap,$df,$x,$search;

    $value = strip_tags_and_attributes($value);

    $string = i18n_get_translated($value);
    $string = TidyList($string);

    if (isset($df[$x]['type']) && $df[$x]['type'] == FIELD_TYPE_TEXT_BOX_FORMATTED_AND_TINYMCE) {
        $string = strip_tags_and_attributes($string); // This will allow permitted tags and attributes, no links
    } else {
        $string = escape($string);
    }

    return $string;
}

/**
 * Formats a string with a collapsible more / less section
 *
 * @param  string $string
 * @param  integer $max_words_before_more
 * @return string
 */
function format_string_more_link($string, $max_words_before_more = 30)
{
    $words = preg_split('/[\t\f ]/', $string);
    if (count($words) < $max_words_before_more) {
        return $string;
    }
    global $lang;
    $unique_id = uniqid();
    $return_value = "";
    for ($i = 0; $i < count($words); $i++) {
        if ($i > 0) {
            $return_value .= ' ';
        }
        if ($i == $max_words_before_more) {
            $return_value .= '<a id="' . $unique_id . 'morelink" href="#" onclick="jQuery(\'#' . $unique_id . 'morecontent\').show(); jQuery(this).hide();">' .
                strtoupper($lang["action-more"]) . ' &gt;</a><span id="' . $unique_id . 'morecontent" style="display:none;">';
        }
        $return_value .= $words[$i];
    }
    $return_value .= ' <a href="#" onclick="jQuery(\'#' . $unique_id . 'morelink\').show(); jQuery(\'#' . $unique_id . 'morecontent\').hide();">&lt; ' .
        strtoupper($lang["action-less"]) . '</a></span>';
    return $return_value;
}

/**
 * Render a performance footer with metrics.
 *
 * @return void
 */
function draw_performance_footer()
{
    global $config_show_performance_footer,$querycount,$querytime,$querylog,$pagename,$hook_cache_hits,$hook_cache;
    $performance_footer_id = uniqid("performance");
    if ($config_show_performance_footer) {
        # --- If configured (for debug/development only) show query statistics

        if ($pagename == "collections") { ?>
            <br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/><br/>
            <div style="float:left;">
        <?php } else { ?>
            <div style="float:right; margin-right: 10px;">
        <?php } ?>

        <table class="InfoTable" style="float: right;margin-right: 10px;">
            <tr>
                <td>Page Load</td>
                <td><?php echo show_pagetime();?></td>
            </tr>
            <?php if (isset($hook_cache_hits) && isset($hook_cache)) { ?>
                <tr>
                    <td>Hook cache hits</td>
                    <td><?php echo $hook_cache_hits;?></td>
                </tr>    
                <tr>
                    <td>Hook cache entries</td>
                    <td><?php echo count($hook_cache); ?></td>
                </tr>
                <?php
            } ?>
            <tr>
                <td>Query count</td>
                <td><?php echo $querycount?></td>
            </tr>
            <tr>
                <td>Query time</td>
                <td><?php echo round($querytime, 4)?></td>
            </tr>
            <?php $dupes = 0;
            foreach ($querylog as $query => $values) {
                if ($values['dupe'] > 1) {
                    $dupes++;
                }
            }
            ?>
            <tr>
                <td>Dupes</td>
                <td><?php echo $dupes?></td>
            </tr>
            <tr>
                <td colspan=2>
                    <a href="#" onClick="document.getElementById('querylog<?php echo $performance_footer_id?>').style.display='block';return false;">
                        <?php echo LINK_CARET ?>details
                    </a>
                </td>
            </tr>
        </table>

        <table class="InfoTable" style="float: right;margin-right: 10px;display:none;" id="querylog<?php echo $performance_footer_id?>">
            <?php foreach ($querylog as $query => $details) { ?>
                <tr>
                    <td><?php echo escape(format_query($query,$details['params'])); ?></td>
                </tr>
            <?php } ?>
        </table>
    </div>
    </div>
        <?php
    }
}

/**
 * Format SQL queries for display purposes
 * Note: these queries are no longer known to be safe from sql injection
 *
 * @param  string $query
 * @param  array $params
 * @return string
 */
function format_query(string $query, array $params): string
{
    $values = array_map(function ($index, $value) use ($params) {
        return $index % 2 === 1 ? ($params[$index - 1] === 's' ? "'" . addslashes($value) . "'" : $value) : null;
    }, array_keys($params), $params);

    $values = array_filter($values, function ($value) {
        return $value !== null;
    });

    // Replace '?' with '%s' for sprintf
    $formatted_query = str_replace('?', '%s', $query);

    // Use sprintf with array unpacking
    return sprintf($formatted_query, ...$values);
}

/**
 * Abstracted mysqli_affected_rows()
 *
 * @return mixed
 */
function sql_affected_rows()
{
    global $db;
    return mysqli_affected_rows($db["read_write"]);
}

/**
 * Returns the path to the ImageMagick utilities such as 'convert'.
 *
 * @param  string $utilityname
 * @param  string $exeNames
 * @param  string $checked_path
 * @return string
 */
function get_imagemagick_path($utilityname, $exeNames, &$checked_path)
{
    global $imagemagick_path;
    if (!isset($imagemagick_path)) {
        # ImageMagick convert path not configured.
        return false;
    }

    // Check if magick exists first for IM 7+
    $im7_check = get_executable_path(
            $imagemagick_path,
            array("unix" => "magick", "win" => "magick.exe"),
            $checked_path);

    if ($im7_check === false) {
        //IM 7+ is not present, fall back to looking for IM 6 utilities
        return get_executable_path($imagemagick_path, $exeNames, $checked_path);
    } else {
        // convert uses just magick
        // composite, identify and mogrify use the syntax magick <command_type>
        if (in_array($utilityname, ['composite','identify','mogrify'])) {
            $im7_check .= ' ' . $utilityname;
        }

        return $im7_check;
    }
}

/**
* Returns the full path to a utility, if installed or FALSE otherwise.
* Note: this function doesn't check that the utility is working.
*
* @uses get_imagemagick_path()
* @uses get_executable_path()
*
* @param string $utilityname
* @param string $checked_path
*
* @return string|boolean Returns full path to utility tool or FALSE
*/
function get_utility_path($utilityname, &$checked_path = null)
{
    global $ghostscript_path, $ghostscript_executable, $ffmpeg_path, $exiftool_path, $pdftotext_path,
           $blender_path, $archiver_path, $archiver_executable, $python_path, $fits_path;

    $checked_path = null;

    switch (strtolower($utilityname)) {
        case 'im-convert':
            return get_imagemagick_path(
                'convert',
                array(
                    'unix' => 'convert',
                    'win'  => 'convert.exe'
                ),
                $checked_path
            );

        case 'im-identify':
            return get_imagemagick_path(
                'identify',
                array(
                    'unix' => 'identify',
                    'win'  => 'identify.exe'
                ),
                $checked_path
            );

        case 'im-composite':
            return get_imagemagick_path(
                'composite',
                array(
                    'unix' => 'composite',
                    'win'  => 'composite.exe'
                ),
                $checked_path
            );

        case 'im-mogrify':
            return get_imagemagick_path(
                'mogrify',
                array(
                    'unix' => 'mogrify',
                    'win'  => 'mogrify.exe'
                ),
                $checked_path
            );

        case 'ghostscript':
            // Ghostscript path not configured
            if (!isset($ghostscript_path)) {
                return false;
            }

            // Ghostscript executable not configured
            if (!isset($ghostscript_executable)) {
                return false;
            }

            // Note that $check_exe is set to true. In that way get_utility_path()
            // becomes backwards compatible with get_ghostscript_command()
            $path = get_executable_path(
                $ghostscript_path,
                array(
                    'unix' => $ghostscript_executable,
                    'win'  => $ghostscript_executable
                ),
                $checked_path,
                true
            );
            if ($path === false) {
                return false;
            }
            return $path . ' -dPARANOIDSAFER';

        case 'ffmpeg':
            // FFmpeg path not configured
            if (!isset($ffmpeg_path)) {
                return false;
            }

            $return = get_executable_path(
                $ffmpeg_path,
                array(
                    'unix' => 'ffmpeg',
                    'win'  => 'ffmpeg.exe'
                ),
                $checked_path
            );

            // Support 'avconv' as well
            if (false === $return) {
                return get_executable_path(
                    $ffmpeg_path,
                    array(
                        'unix' => 'avconv',
                        'win'  => 'avconv.exe'
                    ),
                    $checked_path
                );
            }
            return $return;

        case 'ffprobe':
            // FFmpeg path not configured
            if (!isset($ffmpeg_path)) {
                return false;
            }

            $return = get_executable_path(
                $ffmpeg_path,
                array(
                    'unix' => 'ffprobe',
                    'win'  => 'ffprobe.exe'
                ),
                $checked_path
            );

            // Support 'avconv' as well
            if (false === $return) {
                return get_executable_path(
                    $ffmpeg_path,
                    array(
                        'unix' => 'avprobe',
                        'win'  => 'avprobe.exe'
                    ),
                    $checked_path
                );
            }
            return $return;

        case 'exiftool':
            global $exiftool_global_options;

            $path = get_executable_path(
                $exiftool_path,
                array(
                    'unix' => 'exiftool',
                    'win'  => 'exiftool.exe'
                ),
                $checked_path
            );

            if ($path === false) {
                return false;
            }
            return $path . " {$exiftool_global_options} ";

        case 'pdftotext':
            if (!isset($pdftotext_path) || $pdftotext_path === '') {
                return false;
            }

            return get_executable_path(
                $pdftotext_path,
                [
                    'unix' => 'pdftotext',
                    'win'  => 'pdftotext.exe'
                ],
                $checked_path
            );

        case 'blender':
            if (!isset($GLOBALS['blender_path']) || $GLOBALS['blender_path'] === '') {
                return false;
            }

            return get_executable_path(
                $GLOBALS['blender_path'],
                [
                    'unix' => 'blender',
                    'win'  => 'blender.exe'
                ],
                $checked_path
            );

        case 'archiver':
            // Archiver path not configured
            if (!isset($archiver_path)) {
                return false;
            }

            // Archiver executable not configured
            if (!isset($archiver_executable)) {
                return false;
            }

            return get_executable_path(
                $archiver_path,
                array(
                    'unix' => $archiver_executable,
                    'win'  => $archiver_executable
                ),
                $checked_path
            );

        case 'python':
        case 'opencv':
            // Python path not configured
            if (!isset($python_path) || '' == $python_path) {
                return false;
            }

            $python3 = get_executable_path(
                $python_path,
                [
                    'unix' => 'python3',
                    'win'  => 'python.exe'
                ],
                $checked_path,
                true
            );

            return $python3 ?: get_executable_path(
                $python_path,
                [
                    'unix' => 'python',
                    'win'  => 'python.exe'
                ],
                $checked_path,
                true
            );

        case 'fits':
            // FITS path not configured
            if (!isset($fits_path) || '' == $fits_path) {
                return false;
            }

            return get_executable_path(
                $fits_path,
                array(
                    'unix' => 'fits.sh',
                    'win'  => 'fits.bat'
                ),
                $checked_path
            );

        case 'php':
            global $php_path;

            if (!isset($php_path) || $php_path == '') {
                return false;
            }

            $executable = array(
                'unix' => 'php',
                'win'  => 'php.exe'
            );

            return get_executable_path($php_path, $executable, $checked_path);

        case 'unoconv':
            if (
                // On Windows, the utility is available only via Python's package
                ($GLOBALS['config_windows'] && (!isset($GLOBALS['unoconv_python_path']) || $GLOBALS['unoconv_python_path'] === ''))
                || (!isset($GLOBALS['unoconv_path']) || $GLOBALS['unoconv_path'] === '')
            ) {
                return false;
            }

            return get_executable_path(
                $GLOBALS['config_windows'] ? $GLOBALS['unoconv_python_path'] : $GLOBALS['unoconv_path'],
                [
                    'unix' => 'unoconv',
                    'win'  => 'python.exe'
                ],
                $checked_path
            );

        case 'unoconvert':
            return get_executable_path(
                $GLOBALS['config_windows'] ? $GLOBALS['unoconv_python_path'] : $GLOBALS['unoconv_path'],
                [
                    'unix' => 'unoconvert',
                    'win'  => 'unoconvert.exe'
                ],
                $checked_path
            );

        case 'calibre':
            if (!isset($GLOBALS['calibre_path']) || $GLOBALS['calibre_path'] === '') {
                return false;
            }

            return get_executable_path(
                $GLOBALS['calibre_path'],
                [
                    'unix' => 'ebook-convert',
                    'win'  => 'ebook-convert.exe'
                ],
                $checked_path
            );
    }

    // No utility path found
    return false;
}

/**
* Get full path to utility
*
* @param string  $path
* @param array   $executable
* @param string  $checked_path
* @param boolean $check_exe
*
* @return string|boolean
*/
function get_executable_path($path, $executable, &$checked_path, $check_exe = false)
{
    global $config_windows;

    $os = php_uname('s');

    if ($config_windows || stristr($os, 'windows')) {
        $checked_path = $path . "\\" . $executable['win'];

        if (file_exists($checked_path)) {
            return escapeshellarg($checked_path) . hook('executable_add', '', array($path, $executable, $checked_path, $check_exe));
        }

        // Also check the path with a suffixed ".exe"
        if ($check_exe) {
            $checked_path_without_exe = $checked_path;
            $checked_path             = $path . "\\" . $executable['win'] . '.exe';

            if (file_exists($checked_path)) {
                return escapeshellarg($checked_path) . hook('executable_add', '', array($path, $executable, $checked_path, $check_exe));
            }

            // Return the checked path without the suffixed ".exe"
            $checked_path = $checked_path_without_exe;
        }
    } else {
        $checked_path = stripslashes($path) . '/' . $executable['unix'];

        if (file_exists($checked_path)) {
            return escapeshellarg($checked_path) . hook('executable_add', '', array($path, $executable, $checked_path, $check_exe));
        }
    }

    // No path found
    return false;
}

/**
 * Clean up the resource data cache to keep within $cache_array_limit
 *
 * @return void
 */
function truncate_cache_arrays()
{
    $cache_array_limit = 2000;
    if (count($GLOBALS['get_resource_data_cache']) > $cache_array_limit) {
        $GLOBALS['get_resource_data_cache'] = array();
        // future improvement: get rid of only oldest, instead of clearing all?
        // this would require a way to guage the age of the entry.
    }
    if (count($GLOBALS['get_resource_path_fpcache']) > $cache_array_limit) {
        $GLOBALS['get_resource_path_fpcache'] = array();
    }
}

/**
 * Work out of a string is likely to be in HTML format.
 *
 * @param  mixed $string
 * @return boolean
 */
function is_html($string)
{
    return preg_match("/<[^<]+>/", $string, $m) != 0;
}

/**
 * Set a cookie.
 *
 * Note: The argument $daysexpire is not the same as the argument $expire in the PHP internal function setcookie.
 *
 * @param  string $name
 * @param  string $value
 * @param  integer $daysexpire
 * @param  string $path
 * @param  string $domain
 * @param  boolean $secure
 * @param  boolean $httponly
 * @param  string $samesite
 * @return void
 */
function rs_setcookie($name, $value, $daysexpire = 0, $path = "", $domain = "", $secure = false, $httponly = true, $samesite = "Strict")
{

    if (!is_string_loose($value)) {
        return;
    }

    global $baseurl_short, $baseurl_short;
    if ($path == "") {
        $path =  $baseurl_short;
    }

    if (php_sapi_name() == "cli") {
        return true;
    } # Bypass when running from the command line (e.g. for the test scripts).

    if ($daysexpire == 0) {
        $expire = 0;
    } else {
        $expire = time() + (3600 * 24 * $daysexpire);
    }

    if ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] === getservbyname("https", "tcp"))) {
        $secure = true;
    }

    // Set new cookie, first remove any old previously set pages cookies to avoid clashes;
    setcookie($name, "", [
        'expires'   => time() - 3600,
        'path'      => $path . "pages",
        'domain'    => $domain,
        'secure'    => $secure,
        'httponly'  => $httponly,
        'samesite'  => $samesite,
    ]);
    setcookie($name, (string) $value, [
        'expires'   => (int) $expire,
        'path'      => $path,
        'domain'    => $domain,
        'secure'    => $secure,
        'httponly'  => $httponly,
        'samesite'  => $samesite,
    ]);
}

/**
 * Get an array of all the states that a user has edit access to
 *
 * @param  integer $userref
 * @return array
 */
function get_editable_states($userref)
{
    global $additional_archive_states, $lang;
    if ($userref == -1) {
        return false;
    }
    $editable_states = array();
    $x = 0;
    for ($n = -2; $n <= 3; $n++) {
        if (checkperm("e" . $n)) {
            $editable_states[$x]['id'] = $n;
            $editable_states[$x]['name'] = $lang["status" . $n];
            $x++;
        }
    }
    foreach ($additional_archive_states as $additional_archive_state) {
        if (checkperm("e" . $additional_archive_state)) {
            $editable_states[$x]['id'] = $additional_archive_state;
            $editable_states[$x]['name'] = $lang["status" . $additional_archive_state];
            $x++;
        }
    }
    return $editable_states;
}

/**
 * Returns true if $html is valid HTML, otherwise an error string describing the problem.
 *
 * @param  mixed $html
 * @return bool|string
 */
function validate_html($html)
{
    $parser = xml_parser_create();
    xml_parse_into_struct($parser, "<div>" . str_replace("&", "&amp;", $html) . "</div>", $vals, $index);
    $errcode = xml_get_error_code($parser);
    if ($errcode !== 0) {
        $line = xml_get_current_line_number($parser);

        $error = escape(xml_error_string($errcode)) . "<br />Line: " . $line . "<br /><br />";
        $s = explode("\n", $html);
        $error .= "<pre>" ;
        $error .= isset($s[$line - 2]) ? trim(escape($s[$line - 2])) . "<br />" : "";
        $error .= isset($s[$line - 1]) ? "<strong>" . trim(escape($s[$line - 1])) . "</strong><br />" : "";
        $error .= isset($s[$line]) ? trim(escape($s[$line])) . "<br />" : "";
        $error .= "</pre>";
        return $error;
    } else {
        return true;
    }
}

/**
* Utility function to generate URLs with query strings easier, with the ability
* to override existing query string parameters when needed.
*
* @param  string  $url
* @param  array   $parameters  Default query string params (e.g "k", which appears on most of ResourceSpace URLs)
* @param  array   $set_params  Override existing query string params
*/
function generateURL(string $url, array $parameters = array(), array $set_params = array()): string
{
    foreach ($set_params as $set_param => $set_value) {
        if ('' != $set_param) {
            $parameters[$set_param] = $set_value;
        }
    }

    $query_string_params = array();

    foreach ($parameters as $parameter => $parameter_value) {
        if (!is_array($parameter_value)) {
            $query_string_params[$parameter] = (string) $parameter_value;
        }
    }

    # Ability to hook in and change the URL.
    $hookurl = hook("generateurl", "", array($url));
    if ($hookurl !== false) {
        $url = $hookurl;
    }

    return $url . '?' . http_build_query($query_string_params);
}

/**
* Utility function used to move the element of one array from a position
* to another one in the same array
* Note: the manipulation is done on the same array
*
* @param  array    $array
* @param  integer  $from_index  Array index we are moving from
* @param  integer  $to_index    Array index we are moving to
*
* @return void
*/
function move_array_element(array &$array, $from_index, $to_index)
{
    $out = array_splice($array, $from_index, 1);
    array_splice($array, $to_index, 0, $out);
}

/**
 * Check if a value that may equate to false in PHP is actually a zero
 *
 * @param  mixed $value
 * @return boolean
 */
function emptyiszero($value)
{
    return $value !== null && $value !== false && trim($value) !== '';
}

/**
* Get data for each image that should be used on the slideshow.
* The format of the returned array should be:
* Array
* (
*     [0] => Array
*         (
*             [ref] => 1
*             [resource_ref] =>
*             [homepage_show] => 1
*             [login_show] => 1
*             [file_path] => /var/www/filestore/system/slideshow_1bf4796ac6f051a/1.jpg
*             [checksum] => 1539875502
*         )
*
*     [1] => Array
*         (
*             [ref] => 4
*             [resource_ref] => 19
*             [homepage_show] => 1
*             [login_show] => 0
*             [file_path] => /var/www/filestore/system/slideshow_1bf4796ac6f051a/4.jpg
*             [checksum] => 1542818794
*             [link] => http://localhost/?r=19
*         )
*
* )
*
* @return array
*/
function get_slideshow_files_data()
{
    global $baseurl, $homeanim_folder;

    $homeanim_folder_path = dirname(__DIR__) . "/{$homeanim_folder}";

    $slideshow_records = ps_query("SELECT ref, resource_ref, homepage_show, login_show FROM slideshow", array(), "slideshow");

    $slideshow_files = array();

    foreach ($slideshow_records as $slideshow) {
        $slideshow_file = $slideshow;

        $image_file_path = "{$homeanim_folder_path}/{$slideshow['ref']}.jpg";
        if (!file_exists($image_file_path) || !is_readable($image_file_path)) {
            continue;
        }

        $slideshow_file['checksum'] = filemtime($image_file_path);
        $slideshow_file['file_path'] = $image_file_path;
        $slideshow_file['file_url'] = generateURL(
            "{$baseurl}/pages/download.php",
            array(
                'slideshow' => $slideshow['ref'],
                'nc' => $slideshow_file['checksum'],
            )
        );

        if ((int) $slideshow['resource_ref'] > 0) {
            $slideshow_file['link'] = generateURL($baseurl, array('r' => $slideshow['resource_ref']));
        }

        $slideshow_files[] = $slideshow_file;
    }

    return $slideshow_files;
}

/**
 * Returns a sanitised row from the table in a safe form for use in a form value,
 * suitable overwritten by POSTed data if it has been supplied.
 *
 * @param  array $row
 * @param  string $name
 * @param  string $default
 * @return string
 */
function form_value_display($row, $name, $default = "")
{
    if (!is_array($row)) {
        return false;
    }
    if (array_key_exists($name, $row)) {
        $default = $row[$name];
    }
    return escape((string) getval($name, $default));
}

/**
 * Change the user's user group
 *
 * @param  integer $user
 * @param  integer $usergroup
 * @return void
 */
function user_set_usergroup($user, $usergroup)
{
    ps_query("update user set usergroup = ? where ref = ?", array("i", $usergroup, "i", $user));
}

/**
 * Generates a random string of requested length.
 *
 * Used to generate initial spider and scramble keys.
 *
 * @param  int    $length Length of desired string of bytes
 * @return string         Random character string
 */
function generateSecureKey(int $length = 64): string
{
    return bin2hex(openssl_random_pseudo_bytes($length / 2));
}

/**
 * Check if current page is a modal and set global $modal variable if not already set
 *
 * @return boolean  true if modal, false otherwise
 */
function IsModal()
{
    global $modal;
    if (isset($modal) && $modal) {
        return true;
    }
    return getval("modal", "") == "true";
}

/**
* Generates a CSRF token (Encrypted Token Pattern)
*
* @uses generateSecureKey()
* @uses rsEncrypt()
*
* @param  string  $session_id  The current user session ID
* @param  string  $form_id     A unique form ID
*
* @return  string  Token
*/
function generateCSRFToken($session_id, $form_id)
{
    // IMPORTANT: keep nonce at the beginning of the data array
    $data = json_encode(array(
        "nonce"     => generateSecureKey(128),
        "session"   => $session_id,
        "timestamp" => time(),
        "form_id"   => $form_id
    ));

    return rsEncrypt($data, $session_id);
}

/**
* Checks if CSRF Token is valid
*
* @uses rs_validate_token()
*
* @return boolean  Returns TRUE if token is valid or CSRF is not enabled, FALSE otherwise
*/
function isValidCSRFToken($token_data, $session_id)
{
    global $CSRF_enabled;

    if (!$CSRF_enabled) {
        return true;
    }

    return rs_validate_token($token_data, $session_id);
}

/**
* Render the CSRF Token input tag
*
* @uses generateCSRFToken()
*
* @param string $form_id The id/ name attribute of the form
*
* @return void
*/
function generateFormToken($form_id)
{
    global $CSRF_enabled, $CSRF_token_identifier, $usersession;

    if (!$CSRF_enabled) {
        return;
    }

    $token = generateCSRFToken($usersession, $form_id);
    ?>
    <input type="hidden" name="<?php echo $CSRF_token_identifier; ?>" value="<?php echo $token; ?>">
    <?php
}

/**
* Render the CSRF Token for AJAX use
*
* @uses generateCSRFToken()
*
* @param string $form_id The id/ name attribute of the form or just the calling function for this type of request
*
* @return string
*/
function generateAjaxToken($form_id)
{
    global $CSRF_enabled, $CSRF_token_identifier, $usersession;

    if (!$CSRF_enabled) {
        return "";
    }

    $token = generateCSRFToken($usersession, $form_id);

    return "{$CSRF_token_identifier}: \"{$token}\"";
}

/**
 * Create a CSRF token as a JS object
 *
 * @param string $name The name of the token identifier (e.g API function called)
 * @return string JS object with CSRF data (identifier & token) if CSRF is enabled, empty object otherwise
 */
function generate_csrf_js_object(string $name): string
{
    return $GLOBALS['CSRF_enabled']
        ? json_encode([$GLOBALS['CSRF_token_identifier'] => generateCSRFToken($GLOBALS['usersession'] ?? null, $name)])
        : '{}';
}

/**
 * Create an HTML data attribute holding a CSRF token (JS) object
 *
 * @param string $fct_name The name of the API function called (e.g create_resource)
 */
function generate_csrf_data_for_api_native_authmode(string $fct_name): string
{
    return $GLOBALS['CSRF_enabled']
        ? sprintf(' data-api-native-csrf="%s"', escape(generate_csrf_js_object($fct_name)))
        : '';
}

/**
* Enforce using POST requests
*
* @param  boolean  $ajax  Set to TRUE if request is done via AJAX
*
* @return  boolean|void  Returns true if request method is POST or sends 405 header otherwise
*/
function enforcePostRequest($ajax)
{
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        return true;
    }

    header("HTTP/1.1 405 Method Not Allowed");

    $ajax = filter_var($ajax, FILTER_VALIDATE_BOOLEAN);
    if ($ajax) {
        global $lang;

        $return["error"] = array(
            "status" => 405,
            "title"  => $lang["error-method-not_allowed"],
            "detail" => $lang["error-405-method-not_allowed"]
        );

        echo json_encode($return);
        exit();
    }

    return false;
}

/**
* Check if ResourceSpace is up to date or an upgrade is available
*
* @uses get_sysvar()
* @uses set_sysvar()
*
* @return boolean
*/
function is_resourcespace_upgrade_available()
{
    $cvn_cache = get_sysvar('centralised_version_number');
    $last_cvn_update = get_sysvar('last_cvn_update');

    $centralised_version_number = $cvn_cache;
    debug("RS_UPGRADE_AVAILABLE: cvn_cache = {$cvn_cache}");
    debug("RS_UPGRADE_AVAILABLE: last_cvn_update = $last_cvn_update");
    if ($last_cvn_update !== false) {
        $cvn_cache_interval = DateTime::createFromFormat('Y-m-d H:i:s', $last_cvn_update)->diff(new DateTime());

        if ($cvn_cache_interval->days >= 1) {
            $centralised_version_number = false;
        }
    }

    if ($centralised_version_number === false) {
        $default_socket_timeout_cache = ini_get('default_socket_timeout');
        ini_set('default_socket_timeout', 5); //Set timeout to 5 seconds incase server cannot access resourcespace.com
        $use_error_exception_cache = $GLOBALS["use_error_exception"] ?? false;
        $GLOBALS["use_error_exception"] = true;
        try {
            $centralised_version_number = file_get_contents('https://www.resourcespace.com/current_release.txt');
        } catch (Exception $e) {
            $centralised_version_number = false;
        }
        $GLOBALS["use_error_exception"] = $use_error_exception_cache;
        ini_set('default_socket_timeout', $default_socket_timeout_cache);
        debug("RS_UPGRADE_AVAILABLE: centralised_version_number = $centralised_version_number");
        if ($centralised_version_number === false) {
            debug("RS_UPGRADE_AVAILABLE: unable to get centralised_version_number from https://www.resourcespace.com/current_release.txt");
            set_sysvar('last_cvn_update', date('Y-m-d H:i:s'));
            return false;
        }

        set_sysvar('centralised_version_number', $centralised_version_number);
        set_sysvar('last_cvn_update', date('Y-m-d H:i:s'));
    }

    $get_version_details = function ($version) {
        $version_data = explode('.', $version);

        if (empty($version_data)) {
            return array();
        }

        $return = array(
            'major' => isset($version_data[0]) ? (int) $version_data[0] : 0,
            'minor' => isset($version_data[1]) ? (int) $version_data[1] : 0,
            'revision' => isset($version_data[2]) ? (int) $version_data[2] : 0,
        );

        if ($return['major'] == 0) {
            return array();
        }

        return $return;
    };

    $product_version = trim(str_replace('SVN', '', $GLOBALS['productversion']));
    $product_version_data = $get_version_details($product_version);

    $cvn_data = $get_version_details($centralised_version_number);

    debug("RS_UPGRADE_AVAILABLE: product_version = $product_version");
    debug("RS_UPGRADE_AVAILABLE: centralised_version_number = $centralised_version_number");

    if (empty($product_version_data) || empty($cvn_data)) {
        return false;
    }

    if (
        ($product_version_data['major'] < $cvn_data['major'])
        || ($product_version_data['major'] == $cvn_data['major'] && $product_version_data['minor'] < $cvn_data['minor'])
    ) {
        return true;
    }

    return false;
}

/**
 * Fetch a count of recently active users
 *
 * @param  int  $days   How many days to look back
 *
 * @return integer
 */
function get_recent_users($days)
{
    return ps_value("SELECT count(*) value FROM user WHERE datediff(now(), last_active) <= ?", array("i", $days), 0);
}

/**
 * Return the total number of approved
 *
 * @return integer  The number of approved users
 */
function get_total_approved_users(): int
{
    return ps_value("SELECT COUNT(*) value FROM user WHERE approved = 1", [], 0);
}

/**
 * Return the number of resources in the system with optional filter by archive state
 *
 * @param  int|bool $status     Archive state to filter by if required
 * @return int                  Number of resources in the system, filtered by status if provided
 */
function get_total_resources($status = false): int
{
    $sql = new PreparedStatementQuery("SELECT COUNT(*) value FROM resource WHERE ref>0", []);

    if (is_int($status)) {
        $sql->sql .= " AND archive=?";
        $sql->parameters = array_merge($sql->parameters, ["i",$status]);
    }
    return ps_value($sql->sql, $sql->parameters, 0);
}

/**
* Check if script last ran more than the failure notification days
* Note: Never/ period longer than allowed failure should return false
*
* @param string   $name                   Name of the sysvar to check the record for
* @param integer  $fail_notify_allowance  How long to allow (in days) before user can consider script has failed
* @param string   $last_ran_datetime      Datetime (string format) when script was last run
*
* @return boolean
*/
function check_script_last_ran($name, $fail_notify_allowance, &$last_ran_datetime)
{
    $last_ran_datetime = (trim($last_ran_datetime) === '' ? $GLOBALS['lang']['status-never'] : $last_ran_datetime);

    if (trim($name) === '') {
        return false;
    }

    $script_last_ran = ps_value("SELECT `value` FROM sysvars WHERE name = ?", array("s", $name), '');
    $script_failure_notify_seconds = intval($fail_notify_allowance) * 24 * 60 * 60;

    if ('' != $script_last_ran) {
        $last_ran_datetime = date('l F jS Y @ H:m:s', strtotime($script_last_ran));

        // It's been less than user allows it to last run, meaning it is all good!
        if (time() < (strtotime($script_last_ran) + $script_failure_notify_seconds)) {
            return true;
        }
    }

    return false;
}

/**
* Counting errors found in a collection of items. An error is found when an item has an "error" key.
*
* @param  array  $a  Collection of items that may contain errors.
*
* @return integer
*/
function count_errors(array $a)
{
    return array_reduce(
        $a,
        function ($carry, $item) {
            if (isset($item["error"])) {
                return ++$carry;
            }

            return $carry;
        },
        0
    );
}

/**
 * Function can be used to order a multi-dimensional array using a key and corresponding value
 *
 * @param   array   $array2search   multi-dimensional array in which the key/value pair may be present
 * @param   string  $search_key     key of the key/value pair used for search
 * @param   string  $search_value   value of the key/value pair to search
 * @param   array   $return_array   array to which the matching elements in the search array are pushed - also returned by function
 *
 * @return  array   $return_array
 */
function search_array_by_keyvalue($array2search, $search_key, $search_value, $return_array)
{
    if (!isset($search_key, $search_value, $array2search, $return_array) || !is_array($array2search) || !is_array($return_array)) {
        exit("Error: invalid input to search_array_by_keyvalue function");
    }

    // loop through array to search
    foreach ($array2search as $sub_array) {
        // if the search key exists and its value matches the search value
        if (array_key_exists($search_key, $sub_array) && ($sub_array[$search_key] == $search_value)) {
            // push the sub array to the return array
            array_push($return_array, $sub_array);
        }
    }

    return $return_array;
}

/**
* Temporary bypass access controls for a particular function
*
* When functions check for permissions internally, in order to keep backwards compatibility it may be better if we
* temporarily bypass the permissions instead of adding a parameter to the function for this. It will allow developers to
* keep the code clean.
*
* IMPORTANT: never make this function public to the API.
*
* Example code:
* $log = bypass_permissions(array("v"), "get_resource_log", array($ref));
*
* @param array     $perms  Permission list to be bypassed
* @param callable  $f      Callable that we need to bypas permissions for
* @param array     $p      Parameters to be passed to the callable if required
*
* @return mixed
*/
function bypass_permissions(array $perms, callable $f, array $p = array())
{
    global $userpermissions;

    if (empty($perms)) {
        return call_user_func_array($f, $p);
    }

    // fake having these permissions temporarily
    $o_perm = $userpermissions;
    $userpermissions = array_values(array_merge($userpermissions ?? [], $perms));

    $result = call_user_func_array($f, $p);

    $userpermissions = $o_perm;

    return $result;
}

/**
* Set a system variable (which is stored in the sysvars table) - set to null to remove
*
* @param   mixed  $name          Variable name
* @param   mixed  $value         String to set a new value; null to remove any existing value.
* @param   bool   $clear_cache   Set to false to skip the clearing of any cached values (default true).
* @return  bool
*/
function set_sysvar($name, $value = null, bool $clear_cache = true): bool
{
    global $sysvars;
    db_begin_transaction("set_sysvar");
    ps_query("DELETE FROM `sysvars` WHERE `name` = ?", array("s", $name));
    if ($value != null) {
        ps_query("INSERT INTO `sysvars` (`name`, `value`) values (?, ?)", array("s", $name, "s", $value));
    }
    db_end_transaction("set_sysvar");

    //Update the $sysvars array or get_sysvar() won't be aware of this change
    $sysvars[$name] = $value;

    // Clear query cache so the change takes effect
    if ($clear_cache) {
        clear_query_cache("sysvars");
    }

    return true;
}

/**
 * Get a system variable (which is received from the sysvars table)
 *
 * @param  string $name
 * @param  string $default  Returned if no matching variable was found
 * @return string
 */
function get_sysvar($name, $default = false)
{
    // Check the global array.
    global $sysvars;
    if (isset($sysvars) && array_key_exists($name, $sysvars)) {
        return $sysvars[$name];
    }

    // Load from db or return default
    return ps_value("SELECT `value` FROM `sysvars` WHERE `name` = ?", array("s", $name), $default, "sysvars");
}

/**
 * Plugin architecture.  Look for hooks with this name (and corresponding page, if applicable) and run them sequentially.
 * Utilises a cache for significantly better performance.
 * Enable $draw_performance_footer in config.php to see stats.
 *
 * @param  string $name
 * @param  string $pagename
 * @param  array $params
 * @param  boolean $last_hook_value_wins
 * @return mixed
 */
function hook($name, $pagename = "", $params = array(), $last_hook_value_wins = false)
{
    global $hook_cache;
    if ($pagename == '') {
        global $pagename;
    }

    # the index name for the $hook_cache
    $hook_cache_index = $name . "|" . $pagename;

    # we have already processed this hook name and page combination before so return from cache
    if (isset($hook_cache[$hook_cache_index])) {
        # increment stats
        global $hook_cache_hits;
        $hook_cache_hits++;

        unset($GLOBALS['hook_return_value']);
        $empty_global_return_value = true;
        // we use $GLOBALS['hook_return_value'] so that hooks can directly modify the overall return value
        // $GLOBALS["hook_return_value"] will be unset by new calls to hook() -  when using $GLOBALS["hook_return_value"] make sure
        // the value is used or stored locally before calling hook() or functions using hook().

        foreach ($hook_cache[$hook_cache_index] as $function) {
            $function_return_value = call_user_func_array($function, $params);

            if ($function_return_value === null) {
                continue;   // the function did not return a value so skip to next hook call
            }

            if (
                !$last_hook_value_wins && !$empty_global_return_value &&
                isset($GLOBALS['hook_return_value']) &&
                (gettype($GLOBALS['hook_return_value']) == gettype($function_return_value)) &&
                (is_array($function_return_value) || is_string($function_return_value) || is_bool($function_return_value))
            ) {
                if (is_array($function_return_value)) {
                    // We merge the cached result with the new result from the plugin and remove any duplicates
                    // Note: in custom plugins developers should work with the full array (ie. superset) rather than just a sub-set of the array.
                    //       If your plugin needs to know if the array has been modified previously by other plugins use the global variable "hook_return_value"
                    $numeric_key = false;
                    foreach ($GLOBALS['hook_return_value'] as $key => $value) {
                        if (is_numeric($key)) {
                            $numeric_key = true;
                        } else {
                            $numeric_key = false;
                        }
                        break;
                    }
                    if ($numeric_key) {
                        $merged_arrays = array_merge($GLOBALS['hook_return_value'], $function_return_value);
                        $GLOBALS['hook_return_value'] = array_intersect_key($merged_arrays, array_unique(array_column($merged_arrays, 'value'), SORT_REGULAR));
                    } else {
                        $GLOBALS['hook_return_value'] = array_unique(array_merge_recursive($GLOBALS['hook_return_value'], $function_return_value), SORT_REGULAR);
                    }
                } elseif (is_string($function_return_value)) {
                    $GLOBALS['hook_return_value'] .= $function_return_value;        // appends string
                } elseif (is_bool($function_return_value)) {
                    $GLOBALS['hook_return_value'] = $GLOBALS['hook_return_value'] || $function_return_value;        // boolean OR
                }
            } else {
                $GLOBALS['hook_return_value'] = $function_return_value;
                $empty_global_return_value = false;
            }
        }

        return isset($GLOBALS['hook_return_value']) ? $GLOBALS['hook_return_value'] : false;
    }

    # we have not encountered this hook and page combination before so go add it
    global $plugins;

    # this will hold all of the functions to call when hitting this hook name and page combination
    $function_list = array();

    for ($n = 0; $n < count($plugins); $n++) {
        # "All" hooks
        $function = isset($plugins[$n]) ? "Hook" . ucfirst((string) $plugins[$n]) . "All" . ucfirst((string) $name) : "";

        if (function_exists($function)) {
            $function_list[] = $function;
        } else {
            # Specific hook
            $function = isset($plugins[$n]) ? "Hook" . ucfirst((string) $plugins[$n]) . ucfirst((string) $pagename) . ucfirst((string) $name) : "";
            if (function_exists($function)) {
                $function_list[] = $function;
            }
        }
    }

    // Support a global, non-plugin format of hook function that can be defined in config overrides.
    $function = "GlobalHook" . ucfirst((string) $name);
    if (function_exists($function)) {
        $function_list[] = $function;
    }

    # add the function list to cache
    $hook_cache[$hook_cache_index] = $function_list;

    # do a callback to run the function(s) - this will not cause an infinite loop as we have just added to cache for execution.
    return hook($name, $pagename, $params, $last_hook_value_wins);
}

/**
 * Performs a string replace once a text-only node is encountered, 
 * otherwise loops and calls itself to iterate over child nodes
 * Not intended to be called directly - use html_find_and_replace
 *
 * @param  string $findstring
 * @param  string $replacestring can be blank if using for removal
 * @param  DOMNode $node
 * @return void
 */
function html_find_and_replace_node(string $findstring, string $replacestring, DOMNode $node): void 
{
    
    if($node->nodeName == '#text') {
        $node->textContent = str_replace($findstring, $replacestring, $node->textContent);
    } elseif($node->childNodes->count() > 0) {
        foreach($node->childNodes as $cn) {
            html_find_and_replace_node($findstring, $replacestring, $cn);
        }
    }
}

/**
 * Loads HTML fragment into a DOMDocument instance to parse and 
 * perform a recursive find/replace on text-only nodes. 
 * Returns modified HTML if possible, otherwise the original HTML.
 *
 * @param  string $findstring
 * @param  string $replacestring can be blank if using for removal
 * @param  string $html
 * @return string
 */
function html_find_and_replace(string $findstring, string $replacestring, string $html): string 
{

    if (!is_string($html) || 0 === strlen($html)) {
        return $html;
    }

    $html = htmlspecialchars_decode($html);
    // Return character codes for non-ASCII characters (UTF-8 characters more than a single byte - 0x80 / 128 decimal or greater).
    // This will prevent them being lost when loaded into libxml.
    // Second parameter represents convert mappings array - in UTF-8 convert characters of 2,3 and 4 bytes, 0x80 to 0x10FFFF, with no offset and add mask to return character code.
    $html = mb_encode_numericentity($html, array(0x80, 0x10FFFF, 0, 0xFFFFFF), 'UTF-8');

    
    libxml_use_internal_errors(true);

    // Load the fragment into DOMDocument object
    $doc           = new DOMDocument();
    $doc->encoding = 'UTF-8';

    $process_html = false;

    if($html != strip_tags($html)) {
        $process_html = $doc->loadHTML($html);
    }    

    // If DOMDocumment can parse the fragment, attempt to search through and replace
    if ($process_html) {

        foreach ($doc->getElementsByTagName('*') as $element) {

            // Call the recursive find/replace function for each element
            html_find_and_replace_node($findstring, $replacestring, $element);
        }

        $output_html = $doc->saveHTML();

        // Remove the extraneous tags added when loading fragment into DOMDocument object
        if (false !== strpos($output_html, '<body>')) {
            $body_o_tag_pos = strpos($output_html, '<body>');
            $body_c_tag_pos = strpos($output_html, '</body>');

            $output_html = substr($output_html, $body_o_tag_pos + 6, $body_c_tag_pos - ($body_o_tag_pos + 6));
        }

        $output_html = html_entity_decode($output_html, ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8');

        return $output_html;

    } else {
        return $html;
    }

}

/**
* Utility function to remove unwanted HTML tags and attributes.
* Note: if $html is a full page, developers should allow html and body tags.
*
* @param   string  $html         HTML string
* @param   array   $tags         Extra tags to be allowed
* @param   array   $attributes   Extra attributes to be allowed
*
* @return  mixed   Normally a string however if anything but a string is supplied, return type will be as supplied.
*/
function strip_tags_and_attributes($html, array $tags = array(), array $attributes = array())
{
    global $permitted_html_tags, $permitted_html_attributes;

    if (!is_string($html) || 0 === strlen($html)) {
        return $html;
    }

    $html = htmlspecialchars_decode($html);
    // Return character codes for non-ASCII characters (UTF-8 characters more than a single byte - 0x80 / 128 decimal or greater).
    // This will prevent them being lost when loaded into libxml.
    // Second parameter represents convert mappings array - in UTF-8 convert characters of 2,3 and 4 bytes, 0x80 to 0x10FFFF, with no offset and add mask to return character code.
    $html = mb_encode_numericentity($html, array(0x80, 0x10FFFF, 0, 0xFFFFFF), 'UTF-8');

    // Basic way of telling whether we had any tags previously
    // This allows us to know that the returned value should actually be just text rather than HTML
    // (DOMDocument::saveHTML() returns a text string as a string wrapped in a <p> tag)
    $is_html = ($html != strip_tags($html));

    $allowed_tags = array_merge($permitted_html_tags, $tags);
    $allowed_attributes = array_merge($permitted_html_attributes, $attributes);

    // Step 1 - Check DOM
    libxml_use_internal_errors(true);

    $doc           = new DOMDocument();
    $doc->encoding = 'UTF-8';

    $process_html = $doc->loadHTML($html);

    if ($process_html) {
        foreach ($doc->getElementsByTagName('*') as $tag) {
            if (!in_array($tag->tagName, $allowed_tags)) {
                $tag->parentNode->removeChild($tag);

                continue;
            }

            if (!$tag->hasAttributes()) {
                continue;
            }

            foreach ($tag->attributes as $attribute) {
                if (!in_array($attribute->nodeName, $allowed_attributes)) {
                    $tag->removeAttribute($attribute->nodeName);
                } elseif (
                    in_array($attribute->nodeName, ['src', 'href', 'action', 'onmouseover'])
                    && sanitise_url($attribute->value) === '#'
                ) {
                    $tag->removeAttribute($attribute->nodeName);
                } elseif (
                    $attribute->nodeName === 'style'
                    // Find url('...'), url("...") or url(...) rules that would have to be already safe
                    && preg_match_all('/url\(\s*(?:\'|")?(.*?)(?:\'|")?\s*\);?/i', $attribute->value, $matches) >= 1
                    && array_map(sanitise_url(...), $matches[1]) !== $matches[1]
                ) {
                    $tag->removeAttribute($attribute->nodeName);
                }
            }
        }

        $html = $doc->saveHTML();

        if (false !== strpos($html, '<body>')) {
            $body_o_tag_pos = strpos($html, '<body>');
            $body_c_tag_pos = strpos($html, '</body>');

            $html = substr($html, $body_o_tag_pos + 6, $body_c_tag_pos - ($body_o_tag_pos + 6));
        }
    }

    // Step 2 - Use regular expressions
    // Note: this step is required because PHP built-in functions for DOM sometimes don't
    // pick up certain attributes. I was getting errors of "Not yet implemented." when debugging
    preg_match_all('/[a-z]+=".+"/iU', $html, $attributes);

    foreach ($attributes[0] as $attribute) {
        $attribute_name = stristr($attribute, '=', true);

        if (!in_array($attribute_name, $allowed_attributes)) {
            $html = str_replace(' ' . $attribute, '', $html);
        }
    }

    $html = trim($html, "\r\n");

    if (!$is_html) {
        // DOMDocument::saveHTML() returns a text string as a string wrapped in a <p> tag
        $html = strip_tags($html);
    }

    $html = html_entity_decode($html, ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8');
    return $html;
}

/**
* Remove paragraph tags from start and end of text.
* Inner paragraph tags are untouched
*
* @param string $text HTML string
*
* @return string Returns the text without surrounding <p> and </p> tags.
*/
function strip_paragraph_tags(string $text): string
{
    // Match either <p> exactly, or <p ... attributes> at the start of $text
    // Match </p> exactly at the end of $text
    // Everything else between is lazily matched across multiple lines, 
    // so remains untouched
    return preg_replace('/^<p\b[^>]*>(.*?)<\/p>$/s', '$1', $text);
}

/**
* Helper function to quickly return the inner HTML of a specific tag element from a DOM document.
* Example usage:
* get_inner_html_from_tag(strip_tags_and_attributes($unsafe_html), "p");
*
* @param string $txt HTML string
* @param string $tag DOM document tag element (e.g a, div, p)
*
* @return string Returns the inner HTML of the first tag requested and found. Returns empty string if caller code
*                requested the wrong tag.
*/
function get_inner_html_from_tag(string $txt, string $tag)
{
    //Convert to html before loading into libxml as we will lose non-ASCII characters otherwise
    $html = htmlspecialchars_decode(htmlentities($txt, ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8', false));

    if ($html == strip_tags($txt)) {
        return $txt;
    }

    $inner_html = "";

    libxml_use_internal_errors(true);
    $doc = new DOMDocument();
    $doc->encoding = "UTF-8";
    $process_html = $doc->loadHTML($html);
    $found_tag_elements = $doc->getElementsByTagName($tag);

    if ($process_html && $found_tag_elements->length > 0) {
        $found_first_tag_el = $found_tag_elements->item(0);

        foreach ($found_first_tag_el->childNodes as $child_node) {
            $tmp_doc = new DOMDocument();
            $tmp_doc->encoding = "UTF-8";

            // Import the node, and all its children, to the temp document and then append it to the doc
            $tmp_doc->appendChild($tmp_doc->importNode($child_node, true));

            $inner_html .= $tmp_doc->saveHTML();
        }
    }

    $inner_html = html_entity_decode($inner_html, ENT_NOQUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8');
    return $inner_html;
}

/**
 * Returns the page load time until this point.
 *
 */
function show_pagetime(): string
{
    global $pagetime_start;
    $time = microtime();
    $time = explode(' ', $time);
    $time = $time[1] + $time[0];
    $total_time = round(($time - $pagetime_start), 4);
    return $total_time . " sec";
}

/**
 * Determines where the debug log will live.  Typically, same as tmp dir (See general.php: get_temp_dir().
 * Since general.php may not be included, we cannot use that method so I have created this one too.
 *
 * @return string - The path to the debug_log directory.
 */
function get_debug_log_dir()
{
    global $tempdir, $storagedir;

    // Set up the default.
    $result = dirname(__DIR__) . "/filestore/tmp";

    // if $tempdir is explicity set, use it.
    if (isset($tempdir)) {
        // Make sure the dir exists.
        if (!is_dir($tempdir)) {
            // If it does not exist, create it.
            mkdir($tempdir, 0777);
        }
        $result = $tempdir;
    }
    // Otherwise, if $storagedir is set, use it.
    elseif (isset($storagedir)) {
        // Make sure the dir exists.
        if (!is_dir($storagedir . "/tmp")) {
            // If it does not exist, create it.
            mkdir($storagedir . "/tmp", 0777);
        }
        $result = $storagedir . "/tmp";
    } else {
        // Make sure the dir exists.
        if (!is_dir($result)) {
            // If it does not exist, create it.
            mkdir($result, 0777);
        }
    }
    // return the result.
    return $result;
}

/**
 * Output debug information to the debug log, if debugging is enabled.
 *
 * @param  string $text
 * @param  mixed $resource_log_resource_ref Update the resource log if resource reference passed.
 * @param  string $resource_log_code    If updating the resource log, the code to use
 * @return boolean
 */
function debug($text, $resource_log_resource_ref = null, $resource_log_code = LOG_CODE_TRANSFORMED)
{
    # Update the resource log if resource reference passed.
    if (!is_null($resource_log_resource_ref)) {
        resource_log($resource_log_resource_ref, $resource_log_code, '', '', '', $text);
    }

    # Output some text to a debug file.
    # For developers only
    global $debug_log, $debug_log_override, $debug_log_location, $debug_extended_info;
    if (!$debug_log && !$debug_log_override) {
        return true;
    } # Do not execute if switched off.

    # Cannot use the general.php: get_temp_dir() method here since general may not have been included.
    $GLOBALS["use_error_exception"] = true;
    try {
        if (isset($debug_log_location)) {
            $debugdir = dirname($debug_log_location);
            if (!is_dir($debugdir)) {
                mkdir($debugdir, 0755, true);
            }
        } else {
            $debug_log_location = get_debug_log_dir() . "/debug.txt";
        }

        if (!file_exists($debug_log_location)) {
            $f = fopen($debug_log_location, "a");
            if (strpos($debug_log_location, $GLOBALS['storagedir']) !== false) {
                // Probably in a browseable location. Set the permissions if we can to prevent browser access (will not work on Windows)
                chmod($debug_log_location, 0222);
            }
        } else {
            $f = fopen($debug_log_location, "a");
        }
    } catch (Exception $e) {
        return false;
    }
    unset($GLOBALS["use_error_exception"]);

    $extendedtext = "";
    if (isset($debug_extended_info) && $debug_extended_info && function_exists("debug_backtrace")) {
        $trace_id = isset($GLOBALS['debug_trace_id']) ? "[traceID {$GLOBALS['debug_trace_id']}]" : '';
        $backtrace = debug_backtrace(0);
        $btc = count($backtrace);
        $callingfunctions = array();
        $page = $backtrace[$btc - 1]['file'] ?? pagename();
        $debug_line = $backtrace[0]['line'] ?? 0;
        for ($n = $btc; $n > 0; $n--) {
            if ($page == "" && isset($backtrace[$n]["file"])) {
                $page = $backtrace[$n]["file"];
            }

            if (isset($backtrace[$n]["function"]) && !in_array($backtrace[$n]["function"], array("sql_connect","sql_query","sql_value","sql_array","ps_query","ps_value","ps_array"))) {
                if (in_array($backtrace[$n]["function"], array("include","include_once","require","require_once")) && isset($backtrace[$n]["args"][0])) {
                    $callingfunctions[] = $backtrace[$n]["args"][0];
                } else {
                    $trace_line = isset($backtrace[$n]['line']) ? ":{$backtrace[$n]['line']}" : '';
                    $callingfunctions[] = $backtrace[$n]["function"] . $trace_line;
                }
            }
        }
        $extendedtext .= "{$trace_id}[" . $page . "] "
            . (count($callingfunctions) > 0 ? "(" . implode("->", $callingfunctions)  . "::{$debug_line}) " : "(::{$debug_line}) ");
    }

    fwrite($f, date("Y-m-d H:i:s") . " " . $extendedtext . $text . "\n");
    fclose($f);
    return true;
}

/**
 * Recursively removes a directory.
 *
 * @param string $path Directory path to remove.
 * @param array $ignore List of directories to ignore.
 *
 * @return boolean
 */
function rcRmdir($path, $ignore = array())
{
    if (!is_valid_rs_path($path)) {
        // Not a valid path to a ResourceSpace file source
        return false;
    }
    debug("rcRmdir: " . $path);
    if (is_dir($path)) {
        $foldercontents = new DirectoryIterator($path);
        foreach ($foldercontents as $object) {
            if ($object->isDot() || in_array($path, $ignore)) {
                continue;
            }
            $objectname = $object->getFilename();

            if ($object->isDir() && $object->isWritable()) {
                $success = rcRmdir($path . DIRECTORY_SEPARATOR . $objectname, $ignore);
            } else {
                $success = try_unlink($path . DIRECTORY_SEPARATOR . $objectname);
            }

            if (!$success) {
                debug("rcRmdir: Unable to delete " . $path . DIRECTORY_SEPARATOR . $objectname);
                return false;
            }
        }
    }

    $GLOBALS['use_error_exception'] = true;
    try {
        $success = rmdir($path);
    } catch (Throwable $t) {
        $success = false;
        debug(sprintf('rcRmdir: failed to remove directory "%s". Reason: %s', $path, $t->getMessage()));
    }
    unset($GLOBALS['use_error_exception']);

    debug("rcRmdir: " . $path . " - " . ($success ? "SUCCESS" : "FAILED"));
    return $success;
}

/**
 * Update the daily statistics after a loggable event.
 *
 * The daily_stat table contains a counter for each 'activity type' (i.e. download) for each object (i.e. resource) per day.
 *
 * @param  string $activity_type
 * @param  integer $object_ref
 * @param  integer $to_add      Optional, how many counts to add, defaults to 1.
 * @return void
 */
function daily_stat($activity_type, $object_ref, int $to_add = 1)
{
    global $disable_daily_stat;

    if ($disable_daily_stat === true) {
        return;
    }  //can be used to speed up heavy scripts when stats are less important
    $date = getdate();
    $year = $date["year"];
    $month = $date["mon"];
    $day = $date["mday"];

    if ($object_ref == "") {
        $object_ref = 0;
    }

    # Find usergroup
    global $usergroup;
    if ((!isset($usergroup)) || ($usergroup == "")) {
        $usergroup = 0;
    }

    # External or not?
    global $k;
    $external = 0;
    if (getval("k", "") != "") {
        $external = 1;
    }

    # First check to see if there's a row
    $count = ps_value("select count(*) value from daily_stat where year = ? and month = ? and day = ? and usergroup = ? and activity_type = ? and object_ref = ? and `external` = ?", array("i", $year, "i", $month, "i", $day, "i", $usergroup, "s", $activity_type, "i", $object_ref, "i", $external), 0, "daily_stat"); // Cache this as it can be moderately intensive and is called often.
    if ($count == 0) {
        # insert
        ps_query("insert into daily_stat (year, month, day, usergroup, activity_type, object_ref, `external`, count) values (? ,? ,? ,? ,? ,? ,? , ?)", array("i", $year, "i", $month, "i", $day, "i", $usergroup, "s", $activity_type, "i", $object_ref, "i", $external, "i", $to_add), false, -1, true, 0);
        clear_query_cache("daily_stat"); // Clear the cache to flag there's a row to the query above.
    } else {
        # update
        ps_query("update daily_stat set count = count+? where year = ? and month = ? and day = ? and usergroup = ? and activity_type = ? and object_ref = ? and `external` = ?", array("i",$to_add,"i", $year, "i", $month, "i", $day, "i", $usergroup, "s", $activity_type, "i", $object_ref, "i", $external), false, -1, true, 0);
    }
}

/**
 * Returns the current page name minus the extension, e.g. "home" for pages/home.php
 *
 * @return string
 */
function pagename()
{
    $name = safe_file_name(getval('pagename', ''));
    if (!empty($name)) {
        return $name;
    }

    $url = str_replace("\\", "/", $_SERVER["PHP_SELF"]); // To work with Windows command line scripts
    $urlparts = explode("/", $url);

    return $urlparts[count($urlparts) - 1];
}

function pluginname(): string
{
    $pluginname = safe_file_name(getval('pluginname', ''));
    if (!empty($pluginname)) {
        return $pluginname;
    }

    $url = str_replace("\\", "/", $_SERVER["PHP_SELF"]); // To work with Windows command line scripts
    $urlparts = explode("/", $url);

    if (array_search('plugins', $urlparts) !== false && isset($urlparts[array_search('plugins', $urlparts) + 1])) {
        return $urlparts[array_search('plugins', $urlparts) + 1];
    }

    return "";
}

/**
 *  Returns the site content from the language strings. These will already be overridden with site_text content if present.
 *
 * @param  string $name
 * @return string
 */
function text($name)
{
    global $pagename,$lang;

    $key = $pagename . "__" . $name;
    if (array_key_exists($key, $lang)) {
        return $lang[$key];
    } elseif (array_key_exists("all__" . $name, $lang)) {
        return $lang["all__" . $name];
    } elseif (array_key_exists($name, $lang)) {
        return $lang[$name];
    }

    return "";
}

/**
 * Gets a list of site text sections, used for a multi-page help area.
 *
 * @param  mixed $page
 */
function get_section_list($page): array
{

    global $usergroup;

    return ps_array("select distinct name value from site_text where page = ? and name <> 'introtext' and (specific_to_group IS NULL or specific_to_group = ?) order by name", array("s", $page, "i", $usergroup));
}
/**
 * Returns a more friendly user agent string based on the passed user agent. Used in the user area to establish browsers used.
 *
 * @param  mixed $agent The user agent string
 * @return string
 */
function resolve_user_agent($agent)
{
    if ($agent == "") {
        return "-";
    }
    $agent = strtolower($agent);
    $bmatches = array( # Note - order is important - first come first matched
                    "firefox" => "Firefox",
                    "chrome" => "Chrome",
                    "opera" => "Opera",
                    "safari" => "Safari",
                    "applewebkit" => "Safari",
                    "msie 3." => "IE3",
                    "msie 4." => "IE4",
                    "msie 5.5" => "IE5.5",
                    "msie 5." => "IE5",
                    "msie 6." => "IE6",
                    "msie 7." => "IE7",
                    "msie 8." => "IE8",
                    "msie 9." => "IE9",
                    "msie 10." => "IE10",
                    "trident/7.0" => "IE11",
            "msie" => "IE",
            "trident" => "IE",
                    "netscape" => "Netscape",
                    "mozilla" => "Mozilla"
                    #catch all for mozilla references not specified above
                    );
    $osmatches = array(
                    "iphone" => "iPhone",
                    "nt 10.0" => "Windows 10",
                    "nt 6.3" => "Windows 8.1",
                    "nt 6.2" => "Windows 8",
                    "nt 6.1" => "Windows 7",
                    "nt 6.0" => "Vista",
                    "nt 5.2" => "WS2003",
                    "nt 5.1" => "XP",
                    "nt 5.0" => "2000",
                    "nt 4.0" => "NT4",
                    "windows 98" => "98",
                    "linux" => "Linux",
                    "freebsd" => "FreeBSD",
                    "os x" => "OS X",
                    "mac_powerpc" => "Mac",
                    "sunos" => "Sun",
                    "psp" => "Sony PSP",
                    "api" => "Api Client"
                    );
    $b = "???";
    $os = "???";
    foreach ($bmatches as $key => $value) {
        if (!strpos($agent, $key) === false) {
            $b = $value;
            break;
        }
    }
    foreach ($osmatches as $key => $value) {
        if (!strpos($agent, $key) === false) {
            $os = $value;
            break;
        }
    }
    return $os . " / " . $b;
}

/**
 * Returns the current user's IP address, using HTTP proxy headers if present.
 *
 * @return string
 */
function get_ip()
{
    global $ip_forwarded_for;

    if (
        $ip_forwarded_for
        && isset($_SERVER)
        && array_key_exists('HTTP_X_FORWARDED_FOR', $_SERVER)
    ) {
            return $_SERVER["HTTP_X_FORWARDED_FOR"];
    }

    # Returns the IP address for the current user.
    if (array_key_exists("REMOTE_ADDR", $_SERVER)) {
        return $_SERVER["REMOTE_ADDR"];
    }

    # Can't find an IP address.
    return "???";
}

/**
 * For a value such as 10M return the kilobyte equivalent such as 10240. Used  by check.php
 *
 * @param  mixed $value
 */
function ResolveKB($value): string
{
    $value = trim(strtoupper($value));
    if (substr($value, -1, 1) == "K") {
        return substr($value, 0, strlen($value) - 1);
    }
    if (substr($value, -1, 1) == "M") {
        return substr($value, 0, strlen($value) - 1) * 1024;
    }
    if (substr($value, -1, 1) == "G") {
        return substr($value, 0, strlen($value) - 1) * 1024 * 1024;
    }
    return $value;
}

/**
* Trim a filename that is longer than 255 characters while keeping its extension (if present)
*
* @param string s File name to trim
*
* @return string
*/
function trim_filename(string $s)
{
    $str_len = mb_strlen($s);
    if ($str_len <= 255) {
        return $s;
    }

    $extension = pathinfo($s, PATHINFO_EXTENSION);
    if (is_null($extension) || $extension == "") {
        return mb_strcut($s, 0, 255);
    }

    $ext_len = mb_strlen(".{$extension}");
    $len = 255 - $ext_len;
    $s = mb_strcut($s, 0, $len);
    $s .= ".{$extension}";

    return $s;
}

/**
* Flip array keys to use one of the keys of the values it contains. All elements (ie values) of the array must contain
* the key (ie. they are arrays). Helper function to greatly increase search performance on huge PHP arrays.
* Normal use is: array_flip_by_value_key($huge_array, 'ref');
*
*
* IMPORTANT: make sure that for the key you intend to use all elements will have a unique value set.
*
* Example: Result after calling array_flip_by_value_key($nodes, 'ref');
*     [20382] => Array
*         (
*             [ref] => 20382
*             [name] => Example node
*             [parent] => 20381
*         )
*
* @param array  $a
* @param string $k A values' key to use as an index/key in the main array, ideally an integer
*
* @return array
*/
function array_flip_by_value_key(array $a, string $k)
{
    $return = array();
    foreach ($a as $val) {
        $return[$val[$k]] = $val;
    }
    return $return;
}

/**
* Reshape array using the keys of its values. All values must contain the selected keys.
*
* @param array  $a Array to reshape
* @param string $k The current elements' key to be used as the KEY in the new array. MUST be unique otherwise elements will be lost
* @param string $v The current elements' key to be used as the VALUE in the new array
*
* @return array
*/
function reshape_array_by_value_keys(array $a, string $k, string $v)
{
    $return = array();
    foreach ($a as $val) {
        $return[$val[$k]] = $val[$v];
    }
    return $return;
}

/**
* Permission check for "j[ref]"
*
* @param integer $ref Featured collection category ref
*
* @return boolean
*/
function permission_j(int $ref)
{
    return checkperm("j{$ref}");
}

/**
* Permission check for "-j[ref]"
*
* @param integer $ref Featured collection sub-category ref
*
* @return boolean
*/
function permission_negative_j(int $ref)
{
    return checkperm("-j{$ref}");
}

/**
 * Delete temporary files
 *
 * @param  array $files array of file paths
 * @return void
 */
function cleanup_files($files)
{
    // Clean up any temporary files
    foreach ($files as $deletefile) {
        try_unlink($deletefile);
    }
}

/**
 * Validate if value is integer or string integer
 *
 * @param  mixed $var - variable to check
 * @return boolean true if variable resolves to integer value
 */
function is_int_loose($var)
{
    if (is_array($var)) {
        return false;
    }
    return (string)(int)$var === (string)$var;
}

/**
 * Helper function to check if value is a positive integer looking type.
 *
 * @param int|float|string $V Value to be tested
 */
function is_positive_int_loose($V): bool
{
    return is_int_loose($V) && $V > 0;
}

/**
 * Helper function to check if value is a positive or zero integer looking type.
 *
 * @param int|float|string $V Value to be tested
 */
function is_positive_or_zero_int_loose($V): bool
{
    return is_int_loose($V) && $V >= 0;
}

/**
 * Helper function to check if value is an array containing only 
 * positive or zero integer looking types
 *
 * @param mixed $var value to be tested
 */
function is_array_of_pos_or_zero_ints($var): bool 
{
    return is_array($var) && count($var) === count(array_filter($var, 'is_positive_or_zero_int_loose'));
}

/**
 * Helper function to check if a value is able to be cast to a string
 *
 * @param mixed $var value to be tested
 */
function is_string_loose($var): bool
{
    return !is_array($var) && $var == (string)$var;
}

/**
 * Does the provided $ip match the string $ip_restrict? Used for restricting user access by IP address.
 *
 * @param  string $ip
 * @param  string $ip_restrict
 * @return boolean|integer
 */
function ip_matches($ip, $ip_restrict)
{
    global $system_login;
    if ($system_login) {
        return true;
    }

    if (substr($ip_restrict, 0, 1) == '!') {
        return @preg_match('/' . substr($ip_restrict, 1) . '/su', $ip);
    }

    # Allow multiple IP addresses to be entered, comma separated.
    $i = explode(",", $ip_restrict);

    # Loop through all provided ranges
    for ($n = 0; $n < count($i); $n++) {
        $ip_restrict = trim($i[$n]);

        # Match against the IP restriction.
        $wildcard = strpos($ip_restrict, "*");

        if ($wildcard !== false) {
            # Wildcard
            if (substr($ip, 0, $wildcard) == substr($ip_restrict, 0, $wildcard)) {
                return true;
            }
        } else {
            # No wildcard, straight match
            if ($ip == $ip_restrict) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Ensures filename is unique in $filenames array and adds resulting filename to the array
 *
 * @param  string $filename     Requested filename to be added. Passed by reference
 * @param  array $filenames     Array of filenames already in use. Passed by reference
 * @return string               New filename
 */
function set_unique_filename(&$filename, &$filenames)
{
    global $lang;
    if (in_array($filename, $filenames)) {
        $path_parts = pathinfo($filename);
        if (isset($path_parts['extension']) && isset($path_parts['filename'])) {
            $filename_ext = $path_parts['extension'];
            $filename_wo  = $path_parts['filename'];
            // Run through function to guarantee unique filename
            $filename = makeFilenameUnique($filenames, $filename_wo, $lang["_dupe"], $filename_ext);
        }
    }
    $filenames[] = $filename;
    return $filename;
}

/**
* Build a specific permission closure which can be applied to a list of items.
*
* @param string $perm Permission string to build (e.g f-, F, T, X, XU)
*
* @return Closure
*/
function build_permission(string $perm)
{
    return function ($v) use ($perm) {
        return "{$perm}{$v}";
    };
}

/**
 * Attempt to validate remote code.
 *
 * IMPORTANT: Never use this function or eval() on any code received externally from a source that can't be trusted!
 *
 * @param  string  $code  Remote code to validate
 *
 * @return boolean
 */
function validate_remote_code(string $code)
{
    $GLOBALS['use_error_exception'] = true;
    try {
        extract($GLOBALS, EXTR_SKIP);
        eval($code);
    } catch (Throwable $t) {
        debug('validate_remote_code: Failed to validate remote code. Reason: ' . $t->getMessage());
        $invalid = true;
    }
    unset($GLOBALS['use_error_exception']);

    return !isset($invalid);
}

/**
 * Get system status information
 *
 * @param  bool  $basic  Optional, set to true to perform a quick "system up" check only.
 * @return array
 */
function get_system_status(bool $basic = false)
{
    $return = [
        'results' => [
            // Example of a test result
            // 'name' => [
            //     'status' => 'OK/FAIL',
            //     'info' => 'Any relevant information',
            //     'severity' => 'SEVERITY_CRITICAL/SEVERITY_WARNING/SEVERITY_NOTICE'
            //     'severity_text' => Text for severity using language strings e.g. $GLOBALS["lang"]["severity-level_" . SEVERITY_CRITICAL]
            // ]
        ],
        'status' => 'FAIL',
    ];
    $fail_tests = 0;
    $rs_root = dirname(__DIR__);

    // Checking requirements must be done before boot.php. If that's the case always stop after testing for required PHP modules
    // otherwise the function will break because of undefined global variables or functions (as expected).
    $check_requirements_only = false;
    if (!defined('SYSTEM_REQUIRED_PHP_MODULES')) {
        include_once $rs_root . '/include/definitions.php';
        $check_requirements_only = true;
    }

    // Check database connectivity.
    $check = ps_value('SELECT count(ref) value FROM resource_type', array(), 0);
    if ($check <= 0) {
        $return['results']['database_connection'] = [
            'status' => 'FAIL',
            'info' => 'SQL query produced unexpected result',
            'severity' => SEVERITY_CRITICAL,
            'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_CRITICAL],
        ];

        return $return;
    }

    // End of basic check.
    if ($basic) {
        // Return early, this is a rapid check of DB connectivity only.
        return ['status' => 'OK'];
    }

    // Check required PHP modules
    $missing_modules = [];
    foreach (SYSTEM_REQUIRED_PHP_MODULES as $module => $test_fn) {
        if (!function_exists($test_fn)) {
            $missing_modules[] = $module;
        }
    }
    if (count($missing_modules) > 0) {
        $return['results']['required_php_modules'] = [
            'status' => 'FAIL',
            'info' => 'Missing PHP modules: ' . implode(', ', $missing_modules),
            'severity' => SEVERITY_CRITICAL,
            'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_CRITICAL],
        ];

        // Return now as this is considered fatal to the system. If not, later checks might crash process because of missing one of these modules.
        return $return;
    } elseif ($check_requirements_only) {
        return ['results' => [], 'status' => 'OK'];
    }

    // Check PHP version is supported
    if (PHP_VERSION_ID < PHP_VERSION_SUPPORTED) {
        $return['results']['php_version'] = [
            'status' => 'FAIL',
            'info' => 'PHP version not supported',
            'severity' => SEVERITY_WARNING,
            'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_WARNING],
        ];
        ++$fail_tests;
    }

    // Check configured utility paths
    $missing_utility_paths = [];
    foreach (RS_SYSTEM_UTILITIES as $sysu_name => $sysu) {
        // Check only required (core to ResourceSpace) and configured utilities
        if ($sysu['required'] && isset($GLOBALS[$sysu['path_var_name']]) && get_utility_path($sysu_name) === false) {
            $missing_utility_paths[$sysu_name] = $sysu['path_var_name'];
        }
    }
    if (!empty($missing_utility_paths)) {
        $return['results']['system_utilities'] = [
            'status' => 'FAIL',
            'info' => 'Unable to get utility path',
            'affected_utilities' => array_unique(array_keys($missing_utility_paths)),
            'affected_utility_paths' => array_unique(array_values($missing_utility_paths)),
            'severity' => SEVERITY_WARNING,
            'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_WARNING],
        ];

        return $return;
    }

    // Check database encoding.
    global $mysql_db;
    $badtables = ps_query("SELECT TABLE_NAME, TABLE_COLLATION FROM information_schema.tables WHERE TABLE_SCHEMA=? AND `TABLE_COLLATION` NOT LIKE 'utf8%';", array("s",$mysql_db));
    if (count($badtables) > 0) {
        $return['results']['database_encoding'] = [
            'status' => 'FAIL',
            'info' => 'Database encoding not utf8. e.g. ' . $badtables[0]["TABLE_NAME"] . ": " . $badtables[0]["TABLE_COLLATION"],
            'severity' => SEVERITY_WARNING,
            'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_WARNING],
        ];
        ++$fail_tests;
    }

    // Check write access to filestore
    if (!is_writable($GLOBALS['storagedir'])) {
        $return['results']['filestore_writable'] = [
            'status' => 'FAIL',
            'info' => '$storagedir is not writeable',
            'severity' => SEVERITY_CRITICAL,
            'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_CRITICAL],
        ];

        return $return;
    }

    // Check ability to create a file in filestore
    $hash = md5(time());
    $file = sprintf('%s/write_test_%s.txt', $GLOBALS['storagedir'], $hash);
    if (file_put_contents($file, $hash) === false) {
        $return['results']['create_file_in_filestore'] = [
            'status' => 'FAIL',
            'info' => 'Unable to write to configured $storagedir. Folder permissions are: ' . fileperms($GLOBALS['storagedir']),
            'severity' => SEVERITY_WARNING,
            'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_WARNING],
        ];

        return $return;
    }

    if (!file_exists($file) || !is_readable($file)) {
        $return['results']['filestore_file_exists_and_is_readable'] = [
            'status' => 'FAIL',
            'info' => 'Hash not saved or unreadable in file ' . $file,
            'severity' => SEVERITY_WARNING,
            'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_WARNING],
        ];

        return $return;
    }

    $check = file_get_contents($file);
    if (file_exists($file)) {
        $GLOBALS['use_error_exception'] = true;
        try {
            unlink($file);
        } catch (Throwable $t) {
            $return['results']['filestore_file_delete'] = [
                'status' => 'FAIL',
                'info' => sprintf('Unable to delete file "%s". Reason: %s', $file, $t->getMessage()),
                'severity' => SEVERITY_WARNING,
                'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_WARNING],
            ];

            ++$fail_tests;
        }
        $GLOBALS['use_error_exception'] = false;
    }
    if ($check !== $hash) {
        $return['results']['filestore_file_check_hash'] = [
            'status' => 'FAIL',
            'info' => sprintf('Test write to disk returned a different string ("%s" vs "%s")', $hash, $check),
            'severity' => SEVERITY_WARNING,
            'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_WARNING],
        ];

        return $return;
    }

    global $file_integrity_checks;
    if ($file_integrity_checks) {
        // Check for resources that have failed integrity checks
        $exclude_sql = [];
        $exclude_params = [];
        $exclude_types = array_merge($GLOBALS["file_integrity_ignore_resource_types"], $GLOBALS["data_only_resource_types"]);
        if (count($exclude_types) > 0) {
            $exclude_sql[] = "resource_type NOT IN (" . ps_param_insert(count($exclude_types)) . ")";
            $exclude_params = array_merge($exclude_params, ps_param_fill($exclude_types, "i"));
        }
        if (count($GLOBALS["file_integrity_ignore_states"]) > 0) {
            $exclude_sql[] = "archive NOT IN (" . ps_param_insert(count($GLOBALS["file_integrity_ignore_states"])) . ")";
            $exclude_params = array_merge($exclude_params, ps_param_fill($GLOBALS["file_integrity_ignore_states"], "i"));
        }
        $failedquery = "SELECT COUNT(*) value FROM resource WHERE ref>0 AND integrity_fail=1 AND no_file=0"
            . (count($exclude_sql) > 0 ? " AND " . join(" AND ", $exclude_sql) : "");
        $failed = ps_value($failedquery, $exclude_params, 0);
        if ($failed > 0) {
            $return['results']['files_integrity_fail'] = [
                'status' => 'FAIL',
                'info' => "Files have failed integrity checks",
                'severity' => SEVERITY_WARNING,
                'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_WARNING],
            ];
        }
        // Also check for resources that have not been verified in the last two weeks
        $norecentquery = "SELECT COUNT(*) value FROM resource WHERE ref>0 AND no_file=0 "
        . (count($exclude_sql) > 0 ? " AND " . join(" AND ", $exclude_sql) : "")
        . "AND DATEDIFF(NOW(),last_verified) > 14";
        $notchecked = ps_value($norecentquery, $exclude_params, 0);
        if ($notchecked > 0) {
            $return['results']['recent_file_verification'] = [
                'status' => 'FAIL',
                'info' => $notchecked . " resources have not had recent file integrity checks",
                'severity' => SEVERITY_WARNING,
                'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_WARNING],
            ];
        }
    }

    // Check filestore folder browseability
    $cfb = check_filestore_browseability();
    if (!$cfb['index_disabled']) {
        $return['results']['filestore_indexed'] = [
            'status' => 'FAIL',
            'info' => $cfb['info'],
            'severity' => SEVERITY_CRITICAL,
            'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_CRITICAL],
        ];
        return $return;
    }

    // Check write access to sql_log
    if (isset($GLOBALS['mysql_log_transactions']) && $GLOBALS['mysql_log_transactions']) {
        $mysql_log_location = $GLOBALS['mysql_log_location'] ?? '';
        $mysql_log_dir = dirname($mysql_log_location);
        if (!is_writeable($mysql_log_dir) || (file_exists($mysql_log_location) && !is_writeable($mysql_log_location))) {
            $return['results']['mysql_log_location'] = [
                'status' => 'FAIL',
                'info' => 'Invalid $mysql_log_location specified in config file',
                'severity' => SEVERITY_CRITICAL,
                'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_CRITICAL],
            ];
            return $return;
        }
    }

    // Check write access to debug_log
    $debug_log_location = $GLOBALS['debug_log_location'] ?? get_debug_log_dir() . '/debug.txt';
    $debug_log_dir = dirname($debug_log_location);
    if (!is_writeable($debug_log_dir) || (file_exists($debug_log_location) && !is_writeable($debug_log_location))) {
        $debug_log = isset($GLOBALS['debug_log']) && $GLOBALS['debug_log'];
        $return['results']['debug_log_location'] = [
            'status' => 'FAIL',
            'info' => 'Invalid $debug_log_location specified in config file',
        ];

        if ($debug_log) {
            $return['results']['debug_log_location']['severity'] = SEVERITY_CRITICAL;
            $return['results']['debug_log_location']['severity_text'] = $GLOBALS["lang"]["severity-level_" . SEVERITY_CRITICAL];
            return $return;
        } else {
            ++$fail_tests;
        }
    }

    // Check that the cron process executed within the last day (FAIL)
    $last_cron = strtotime(get_sysvar('last_cron', ''));
    $diff_days = (time() - $last_cron) / (60 * 60 * 24);
    if ($diff_days > 1.5) {
        $return['results']['cron_process'] = [
            'status' => 'FAIL',
            'info' => 'Cron was executed ' . round($diff_days, 1) . ' days ago.',
            'severity' => SEVERITY_WARNING,
            'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_WARNING],
        ];
        ++$fail_tests;
    }

    // Check free disk space is sufficient -  WARN
    $avail = disk_total_space($GLOBALS['storagedir']);
    $free = disk_free_space($GLOBALS['storagedir']);
    $calc = $free / $avail;

    if ($calc < 0.01) {
        $return['results']['free_disk_space'] = [
            'status' => 'FAIL',
            'info' => 'Less than 1% disk space free.',
            'severity' => SEVERITY_CRITICAL,
            'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_CRITICAL],
        ];
        return $return;
    } elseif ($calc < 0.05) {
        $return['results']['free_disk_space'] = [
            'status' => 'FAIL',
            'info' => 'Less than 5% disk space free.',
            'severity' => SEVERITY_WARNING,
            'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_WARNING],
        ];
        ++$fail_tests;
    }

    // Check the disk space against the quota limit - WARN (FAIL if exceeded)
    if (isset($GLOBALS['disksize'])) {
        $avail = $GLOBALS['disksize'] * (1000 * 1000 * 1000); # Get quota in bytes
        $used = get_total_disk_usage(); # Total usage in bytes
        $percent = ceil(((int) $used / $avail) * 100);

        if ($percent >= 95) {
            $return['results']['quota_limit'] = [
                'status' => 'FAIL',
                'info' => $percent . '% used',
                'avail' => $avail, 'used' => $used, 'percent' => $percent,
                'severity' => SEVERITY_WARNING,
                'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_WARNING],
            ];
            ++$fail_tests;
        } else {
            $return['results']['quota_limit'] = [
                'status' => 'OK',
                'info' => $percent . '% used.',
                'avail' => $avail, 'used' => $used, 'percent' => $percent
            ];
        }
    }

    // Return the version number
    $return['results']['version'] = [
        'status' => 'OK',
        'info' => $GLOBALS['productversion'],
    ];

    // Return the SVN information, if possible
    $svn_data = '';

    // - If a SVN branch, add on the branch name.
    $svninfo = run_command('svn info '  . $rs_root);
    $matches = [];
    if (preg_match('/\nURL: .+\/branches\/(.+)\\n/', $svninfo, $matches) != 0) {
        $svn_data .= ' BRANCH ' . $matches[1];
    }

    // - Add on the SVN revision if we can find it.
    // If 'svnversion' is available, run this as it will produce a better output with 'M' signifying local modifications.
    $matches = [];
    $svnversion = run_command('svnversion ' . $rs_root);
    if ($svnversion != '') {
        # 'svnversion' worked - use this value and also flag local mods using a detectable string.
        $svn_data .= ' r' . str_replace('M', '(mods)', $svnversion);
    } elseif (preg_match('/\nRevision: (\d+)/i', $svninfo, $matches) != 0) {
        // No 'svnversion' command, but we found the revision in the results from 'svn info'.
        $svn_data .= ' r' . $matches[1];
    }
    if ($svn_data !== '') {
        $return['results']['svn'] = [
            'status' => 'OK',
            'info' => $svn_data];
    }

    // Return a list with names of active plugins
    $return['results']['plugins'] = [
        'status' => 'OK',
        'info' => implode(', ', array_column(get_active_plugins(), 'name')),
    ];

    // Return active user count (last 7 days)
    $return['results']['recent_user_count'] = [
        'status' => 'OK',
        'info' => get_recent_users(7),
        'within_year' => get_recent_users(365),
        'total_approved' => get_total_approved_users()
    ];

    // Return current number of resources including count of 
    // non-ingested resources (staticsync)
    $return['results']['resource_count'] = [
        'status' => 'OK',
        'total' => get_total_resources(),
        'active' => get_total_resources(0),
        'non_ingested' => get_non_ingested_resources(),        
    ];

    // Return bandwidth usage last 30 days
    $return['results']['download_bandwidth_last_30_days_gb'] = [
        'status' => 'OK',
        'total' => round(daily_stat_past_month_by_activity('Downloaded KB') / (1024 * 1024), 3)
    ];

    // Return file extensions with counts
    $return['results']['files_by_extension'] = [
        'status' => 'OK',
        'total' => ps_query("select file_extension,count(*) `count`,round(sum(disk_usage)/power(1024,3),2) disk_usage_gb from resource where length(file_extension)>0 group by file_extension order by `count` desc;", [])
    ];

    // Debug log check
    if (isset($GLOBALS['debug_log']) && $GLOBALS['debug_log']) {

        $debug_log_sysvar = get_sysvar("debug_log_enabled");

        if (!$debug_log_sysvar) {
            // Add debug_log_enabled sysvar
            set_sysvar("debug_log_enabled", date('Y-m-d H:i:s'));
        } else {
            // Compare debug_log_enabled to current time
            $debug_log_enabled_datetime = DateTime::createFromFormat('Y-m-d H:i:s', $debug_log_sysvar);
            $errors = DateTime::getLastErrors();
            $date_parse_error  = ($debug_log_enabled_datetime === 0 
                                    || (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0)));

            if (!$date_parse_error) {
                $now = new DateTime();            
                $seconds_elapsed = abs($now->getTimestamp() - $debug_log_enabled_datetime->getTimestamp());
            } else {
                $seconds_elapsed = false;
            }   

            if ($seconds_elapsed === false || $seconds_elapsed > $GLOBALS['debug_log_warning_duration']) {
                // Debug logging has been switched on for too long or cannot be determined
                $return['results']['debug_log'] = [
                    'status'        => 'FAIL',
                    'info'          => 'Debug Log is enabled - time',
                    'severity'      => SEVERITY_WARNING,
                    'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_WARNING],
                ];
                ++$fail_tests;
            } else {
                // Debug logging duration OK - proceed to check filesize
                $debug_log_location = $GLOBALS['debug_log_location'] ?? get_debug_log_dir() . '/debug.txt';

                if (file_exists($debug_log_location)) {
                    $debug_log_size = filesize_unlimited($debug_log_location);
                } else {
                    $debug_log_size = false;
                }

                if ($debug_log_size === false || $debug_log_size > $GLOBALS['debug_log_warning_size']) {
                    // Debug log file size is too large or cannot be determined
                    $return['results']['debug_log'] = [
                        'status'        => 'FAIL',
                        'info'          => 'Debug Log is enabled - size',
                        'severity'      => SEVERITY_WARNING,
                        'severity_text' => $GLOBALS["lang"]["severity-level_" . SEVERITY_WARNING],
                    ];
                    ++$fail_tests;
                }

            }
        }

    } else {
        // debug_log has been switched off, blank the sysvar
        set_sysvar("debug_log_enabled");
    }

    // Check if plugins have any warnings
    $extra_checks = hook('extra_checks');
    if ($extra_checks !== false && is_array($extra_checks)) {
        foreach ($extra_checks as $check_name => $extra_check) {
            $return['results'][$check_name] = [
                'status' => $extra_check['status'],
                'info' => $extra_check['info'],
                ];
            if (isset($extra_check['severity'])) {
                // Severity is optional and may not be returned by some plugins
                $return['results'][$check_name]['severity'] = $extra_check['severity'];
                $return['results'][$check_name]['severity_text'] = $GLOBALS["lang"]["severity-level_" .  $extra_check['severity']];
            }

            $warn_details = $extra_check['details'] ?? [];
            if ($warn_details !== []) {
                $return['results'][$check_name]['details'] = $warn_details;
            }

            if ($extra_check['status'] == 'FAIL') {
                ++$fail_tests;
            }
        }
    }

    if ($fail_tests > 0) {
        $return['status'] = 'FAIL';
    } else {
        $return['status'] = 'OK';
    }
    return $return;
}

/**
 * Try and delete a file without triggering a fatal error
 *
 * @param  string $deletefile   Full path to file
 * @return bool|string          Returns TRUE on success or a string containing error
 */
function try_unlink($deletefile)
{
    $GLOBALS["use_error_exception"] = true;
    try {
        $deleted = unlink($deletefile);
    } catch (Throwable $t) {
        $message = "Unable to delete : " . $deletefile . ". Reason" . $t->getMessage();
        debug($message);
        return $message;
    }
    unset($GLOBALS["use_error_exception"]);
    return $deleted;
}

function try_getimagesize(string $filename, &$image_info = null)
{
    $GLOBALS["use_error_exception"] = true;
    try {
        $return = getimagesize($filename, $image_info);
    } catch (Throwable $e) {
        $return = false;
    }
    unset($GLOBALS["use_error_exception"]);
    return $return;
}

/**
 * Check filestore folder browseability.
 * For security reasons (e.g data breach) the filestore location shouldn't be indexed by the web server (in Apache2 - disable autoindex module)
 *
 * @return array Returns data structure with following keys:-
 *               - status: An end user status of OK/FAIL
 *               - info: Any extra relevant information (aimed at end users)
 *               - filestore_url: ResourceSpace URL to the filestore location
 *               - index_disabled: PHP bool (used by code). FALSE if web server allows indexing/browsing the filestore, TRUE otherwise
 */
function check_filestore_browseability()
{
    $filestore_url = $GLOBALS['storageurl'] ?? "{$GLOBALS['baseurl']}/filestore";
    $timeout = 5;
    $return = [
        'status' => $GLOBALS['lang']['status-fail'],
        'info' => $GLOBALS['lang']['noblockedbrowsingoffilestore'],
        'filestore_url' => $filestore_url,
        'index_disabled' => false,
    ];

    $GLOBALS['use_error_exception'] = true;
    try {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $filestore_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 2);
        curl_exec($ch);
        $response_status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        unset($ch);
    } catch (Throwable $t) {
        $return['status'] = $GLOBALS['lang']['unknown'];
        $return['info'] = $GLOBALS['show_error_messages'] && $GLOBALS['show_detailed_errors'] ? $t->getMessage() : '';
        return $return;
    }
    unset($GLOBALS['use_error_exception']);

    // Web servers (RFC 2616) shouldn't return a "200 OK" if the server has indexes disabled. Usually it's "404 Not Found".
    if ($response_status_code !== 200) {
        $return['status'] = $GLOBALS['lang']['status-ok'];
        $return['info'] = '';
        $return['index_disabled'] = true;
    }

    return $return;
}

/**
 * Check CLI version found for ImageMagick is as expected.
 *
 * @param string $version_output The version output for ImageMagick
 * @param array  $utility        Utility structure. {@see RS_SYSTEM_UTILITIES}
 *
 * @return array Returns array as expected by the check.php page
 * - utility - New utility value for its display name
 * - found - PHP bool representing whether we've found what we were expecting in the version output.
 */
function check_imagemagick_cli_version_found(string $version_output, array $utility)
{
    $expected = ['ImageMagick', 'GraphicsMagick'];

    foreach ($expected as $utility_name) {
        if (mb_strpos($version_output, $utility_name) !== false) {
            $utility['display_name'] = $utility_name;
        }
    }

    return [
        'utility' => $utility,
        'found' => in_array($utility['display_name'], $expected),
    ];
}

/**
 * Check CLI version found for Exiftool is as expected.
 *
 * @param string $version_output The version output for ImageMagick
 * @param array  $utility        Utility structure. {@see RS_SYSTEM_UTILITIES}
 *
 * @return array Returns array as expected by the check.php page
 * - utility - New utility value for its display name
 * - found - PHP bool representing whether we've found what we were expecting in the version output.
 * - error_message - optional error message if an issue is detected
 */
function check_exiftool_cli_version_found(string $version_output, array $utility): array
{
    global $lang;

    if (preg_match('/Warning: Library/', $version_output) === 1) {
        return [
            'utility' => $utility,
            'found' => false,
            'error_message' => "{$lang['status-warning']}: {$lang['exiftoolconflictingversions']}<br /> {$version_output}",
        ];        
    } else {
        return [
            'utility' => $utility,
            'found' => preg_match("/^([0-9]+)+\.([0-9]+)/", $version_output) === 1,
        ];
    }
}

/**
 * Check CLI numeric version found for a utility is as expected.
 *
 * @param string $version_output The version output
 * @param array  $utility        Utility structure. {@see RS_SYSTEM_UTILITIES}
 *
 * @return array Returns array as expected by the check.php page
 * - utility - not used
 * - found - PHP bool representing whether we've found what we were expecting in the version output.
 */
function check_numeric_cli_version_found(string $version_output, array $utility)
{
    return [
        'utility' => $utility,
        'found' => preg_match("/^([0-9]+)+\.([0-9]+)/", $version_output) === 1,
    ];
}

/**
 * Check CLI version found for a utility is as expected by looking up for its name.
 *
 * @param string $version_output The version output for the utility
 * @param array  $utility        Utility structure. {@see RS_SYSTEM_UTILITIES}
 *
 * @return array Returns array as expected by the check.php page
 * - utility - not used
 * - found - PHP bool representing whether we've found what we were expecting in the version output.
 */
function check_utility_cli_version_found_by_name(string $version_output, array $utility, array $lookup_names)
{
    $version_output = strtolower($version_output);
    $lookup_names = array_filter($lookup_names);

    foreach ($lookup_names as $utility_name) {
        if (mb_strpos($version_output, strtolower($utility_name)) !== false) {
            $found = true;
            break;
        }
    }

    return [
        'utility' => $utility,
        'found' => isset($found),
    ];
}

/**
 * Check we're running on the command line, exit otherwise. Security feature for the scripts in /pages/tools/
 *
 *
 * @return void
 */
function command_line_only()
{
    if ('cli' != PHP_SAPI) {
        http_response_code(401);
        exit('Access denied - Command line only!');
    }
}

/**
 * Helper function to quickly build a list of values, all prefixed the same way.
 *
 * ```php
 * $fieldXs = array_map(prefix_value('field'), [3, 88]);
 * ```
 *
 * @param string $prefix Prefix value to prepend.
 */
function prefix_value(string $prefix): Closure
{
    return static fn (string $value): string => $prefix . $value;
}

/**
* Utility function to check string is a valid date/time with a specific format.
*
* @param string $datetime Date/time value
* @param string $format The format that date/time value should be in. {@see https://www.php.net/manual/en/datetimeimmutable.createfromformat.php}
* @return boolean
*/
function validateDatetime(string $datetime, string $format = 'Y-m-d H:i:s'): bool
{
    $date = DateTimeImmutable::createFromFormat($format, $datetime);
    return $date && $date->format($format) === $datetime;
}

/**
 *  @param string $haystack Value to be checked
 *  @param string $needle Substing to seach for in the haystack
 *
 *  @return bool True if the haystack ends with the needle otherwise false
 */
function string_ends_with($haystack, $needle)
{
    return substr($haystack, strlen($haystack) - strlen($needle), strlen($needle)) === $needle;
}

/**
 * Helper function to set the order_by key of an array to zero.
 *
 * @param array item Item for which we need to set the order_by
 * @return array Same item with the order_by key zero.
 */
function set_order_by_to_zero(array $item): array
{
    $item['order_by'] = 0;
    return $item;
}

/**
 * Helper function to cast functions that only echo things out (e.g render functions) to string type.
 *
 * @param callable $fn Function to cast
 * @param array $args Provide function's arguments (if applicable)
 */
function cast_echo_to_string(callable $fn, array $args = []): string
{
    ob_start();
    $fn(...$args);
    $result = ob_get_contents();
    ob_end_clean();
    return $result;
}

/**
 * Helper function to parse input to a list of a particular type.
 *
 * @example include/api_bindings.php Used in api_get_resource_type_fields() or api_create_resource_type_field()
 *
 * @param string $csv CSV of raw data
 * @param callable(string) $type Function checking each CSV item, as required by your context, to determine if it should
 *                               be allowed in the result set
 */
function parse_csv_to_list_of_type(string $csv, callable $type): array
{
    $list = explode(',', $csv);
    $return = [];
    foreach ($list as $value) {
        $value = trim($value);
        if ($type($value)) {
            $return[] = $value;
        }
    }
    return $return;
}

/**
 * Remove metadata field properties during execution lockout
 *
 * @param array $rtf Resource type field data structure
 * @return array Returns without the relevant properties if execution lockout is enabled
 */
function execution_lockout_remove_resource_type_field_props(array $rtf): array
{
    $props = [
        'autocomplete_macro',
        'value_filter',
        'exiftool_filter',
        'onchange_macro',
    ];
    return $GLOBALS['execution_lockout'] ? array_diff_key($rtf, array_flip($props)) : $rtf;
}

/**
 * Update global variable watermark to point to the correct file. Watermark set on System Configuration page will override a watermark
 * set in config.php. config.default.php will apply otherwise (blank) so no watermark will be applied.
 *
 * @return void
 */
function set_watermark_image()
{
    global $watermark, $storagedir;

    $wm = (string) $watermark;

    if (trim($wm) !== '' && substr($wm, 0, 13) == '[storage_url]') {
        $GLOBALS["watermark"] = str_replace('[storage_url]', $storagedir, $watermark);  # Watermark from system configuration page
    } elseif (trim($wm) !== '') {
        $GLOBALS["watermark"] = __DIR__ . "/../" . $watermark;  # Watermark from config.php - typically "gfx/watermark.png"
    }
}

/** DPI calculations */
function compute_dpi($width, $height, &$dpi, &$dpi_unit, &$dpi_w, &$dpi_h)
{
    global $lang, $imperial_measurements,$sizes,$n;

    if (isset($sizes[$n]['resolution']) && $sizes[$n]['resolution'] != 0 && is_int($sizes[$n]['resolution'])) {
        $dpi = $sizes[$n]['resolution'];
    } elseif (!isset($dpi) || $dpi == 0) {
        $dpi = 300;
    }

    if ((isset($sizes[$n]['unit']) && trim(strtolower($sizes[$n]['unit'])) == "inches") || $imperial_measurements) {
        # Imperial measurements
        $dpi_unit = $lang["inch-short"];
        $dpi_w = round($width / $dpi, 1);
        $dpi_h = round($height / $dpi, 1);
    } else {
        $dpi_unit = $lang["centimetre-short"];
        $dpi_w = round(($width / $dpi) * 2.54, 1);
        $dpi_h = round(($height / $dpi) * 2.54, 1);
    }
}

/** MP calculation */
function compute_megapixel(int $width, int $height): float
{
    return round(($width * $height) / 1000000, 2);
}

/**
 * Get size info as a paragraphs HTML tag
 * @param array $size Preview size information
 * @param array|null $originalSize Original preview size information
 */
function get_size_info(array $size, ?array $originalSize = null): string
{
    global $lang, $ffmpeg_supported_extensions;

    $newWidth  = intval($size['width']);
    $newHeight = intval($size['height']);

    if ($originalSize != null && $size !== $originalSize) {
        // Compute actual pixel size
        $imageWidth  = $originalSize['width'];
        $imageHeight = $originalSize['height'];
        if ($imageWidth > $imageHeight) {
            // landscape
            if ($imageWidth == 0) {
                return '<p>&ndash;</p>';
            }
            $newWidth = $size['width'];
            $newHeight = round(($imageHeight * $newWidth + $imageWidth - 1) / $imageWidth);
        } else {
            // portrait or square
            if ($imageHeight == 0) {
                return '<p>&ndash;</p>';
            }
            $newHeight = $size['height'];
            $newWidth = round(($imageWidth * $newHeight + $imageHeight - 1) / $imageHeight);
        }
    }

    $output = sprintf(
        '<p>%s &times; %s %s',
        escape($newWidth),
        escape($newHeight),
        escape($lang['pixels']),
    );

    $mp = compute_megapixel($newWidth, $newHeight);
    if ($mp >= 0) {
        $output .= sprintf(
            ' (%s %s)',
            escape($mp),
            escape($lang['megapixel-short']),
        );
    }

    $output .= '</p>';

    if (
        !isset($size['extension'])
        || !in_array(strtolower($size['extension']), $ffmpeg_supported_extensions)
    ) {
            # Do DPI calculation only for non-videos
            compute_dpi($newWidth, $newHeight, $dpi, $dpi_unit, $dpi_w, $dpi_h);
            $output .= sprintf(
                '<p>%1$s %2$s &times; %3$s %2$s %4$s %5$s %6$s</p>',
                escape($dpi_w),
                escape($dpi_unit),
                escape($dpi_h),
                escape($lang['at-resolution']),
                escape($dpi),
                escape($lang['ppi']),
            );
    }

    if (isset($size["filesize"])) {
        $output .= sprintf('<p>%s</p>', strip_tags_and_attributes($size["filesize"]));
    }

    return $output;
}

/**
 * Simple function to check if a given extension is associated with a JPG file
 *
 * @param string $extension     File extension
 */
function is_jpeg_extension(string $extension): bool
{
    return in_array(mb_strtolower($extension), ["jpg","jpeg"]);
}

/**
 * Input validation helper function to check a URL is ours (e.g. if it's our base URL). Mostly used for redirect URLs.
 *
 * @param string $base The value the URL is expected to start with. Due to the structure of a URL, you can also check
 * for (partial) paths.
 * @param mixed $val URL to check
 */
function url_starts_with(string $base, $val): bool
{
    return is_string($val) && filter_var($val, FILTER_VALIDATE_URL) && mb_strpos($val, $base) === 0;
}

/**
 * Input validation helper function to check if a URL is safe (from XSS) in an HTML context.
 *
 * @param mixed $val URL to check
 */
function is_safe_url($url): bool
{
    if (!(is_string($url) && filter_var($url, FILTER_VALIDATE_URL))) {
        return false;
    }

    $url_parts = parse_url($url);
    if ($url_parts === false) {
        return false;
    } elseif (!in_array($url_parts['scheme'], ['http', 'https', 'mailto'])) {
        return false;
    }

    // Check URL components don't contain XSS payloads except the port (an int) and query strings (see dedicated logic)
    foreach (array_diff_key($url_parts, ['port' => 1, 'query' => 1]) as $part_name => $value) {
        if ($part_name === 'fragment') {
            if (!preg_match('/^[\w.~!$&%()*+,;=:@\/?-]*$/', $value)) {
                // Non-RFC 3986 compliant fragment detected
                return false;
            }
        } elseif ($value !== escape($value)) {
            return false;
        }
    }

    // Check query strings (QS), if applicable. We're parsing the QS but without decoding its components otherwise we
    // we could end up with false positives.
    $qs_params = array_filter(explode('&', $url_parts['query'] ?? ''));
    foreach ($qs_params as $pair) {
        [$param, $value] = array_pad(array_filter(explode('=', $pair, 2)), 2, '');

        if ($param !== escape($param) || $value !== escape($value)) {
            debug("[WARN] Suspicious query string parameter ({$param} with value: {$value}) found in URL - {$url}");
            return false;
        }
    }

    return true;
}

/**
 * Sanitise URL for HTML rendering purposes.
 * ResourceSpace only works with absolute (via $baseurl) and relative root (via $baseurl_short) URLs.
 *
 * @uses is_safe_url()
 *
 * @param string $val Untrusted URL
 * @return string Returns the provided value (URL) if considered safe, "#" otherwise.
 */
function sanitise_url(string $val): string
{
    // Convert root-relative URL paths to absolute ones
    $url = mb_strpos($val, $GLOBALS['baseurl_short']) === 0
        ? $GLOBALS['baseurl'] . '/' . mb_substr($val, mb_strlen($GLOBALS['baseurl_short']))
        : $val;

    // Support document-relative URL by prepending our base URL
    if (
        preg_match(
            '~^
            (?!(?: /| \#| //| [A-Za-z][A-Za-z0-9+.-]*:)) # exclude: root-relative, fragment only, protocol-relative, or scheme based
            (?:(?:\.\./ | \./)*)                 # optional leading relative prefixes (./ or ../)
            (?:[A-Za-z0-9._-]+/)*                # zero or more directory segments
            [A-Za-z0-9._-]+\.[a-zA-Z0-9_-]{1,10} # filename
            (?:\?[^\s#]*)?                       # optional query string
            (?:\#.*)?                            # optional fragment
            $~xm',
            $url,
            $matches
        ) === 1
    ) {
        $url = "{$GLOBALS['baseurl']}/{$url}";
    }

    if (is_safe_url($url)) {
        return $val;
    }

    debug("[WARN] Sanitising unsafe URL ({$val})");
    return '#';
}

/**
 * Input validation helper function for sorting (ASC/DESC).
 * @param mixed $val User input value to be validated
 */
function validate_sort_value($val): bool
{
    return is_string($val) && in_array(mb_strtolower($val), ['asc', 'desc']);
}

/**
 * Input validation helper function for a CSV of integers (mostly used for IDs).
 * @param mixed $val User input value to be validated
 */
function validate_digit_csv($val): bool
{
    return is_string($val) && preg_match('/^\d+,? ?(, ?\d+ ?,? ?)*$/', $val) === 1;
}

/**
 * Helper function to get an array of values with a subset of their original keys.
 *
 * @param list<string> List of keys to extract from the values
 */
function get_sub_array_with(array $keys): callable
{
    return fn(array $input): array => array_intersect_key($input, array_flip($keys));
}

/**
 * Server side check to backup front end javascript validation.
 *
 * @param  string  $password   Password supplied when creating or editing external share.

 */
function enforceSharePassword(string $password): void
{
    global $share_password_required, $lang;
    if ($share_password_required && trim($password) === '') {
        exit(escape($lang["error-permissiondenied"]));
    }
}

/**
 * Helper function to call the JS CentralSpaceLoad().
 * @return never
 */
function js_call_CentralSpaceLoad(string $url)
{
    exit("<script>CentralSpaceLoad('{$url}');</script>");
}

/**
 * Get expiration date of a given PEM certificate
 *
 * @param string $cert  Certificate text
 *
 * @return string|bool  Expiry date. False if unable to parse certificate
 *
 */
function getCertificateExpiry(string $cert)
{
    /* Construct a PEM formatted certificate */
    $pemCert = "-----BEGIN CERTIFICATE-----\n" . chunk_split($cert, 64) . "-----END CERTIFICATE-----\n";
    $data = openssl_x509_parse($pemCert);
    return $data ? date('Y-m-d H:i:s', $data['validTo_time_t']) : false;
}

/**
 * Is the provided colour a valid CSS colour and therefore safe to display?
 *
 * @param  string $color    The colour string
 * @return boolean          True if a valid colour, false if not
 */
function isValidCssColor(string $colour)
{
    $regex = '/^(#([a-fA-F0-9]{3}|[a-fA-F0-9]{4}|[a-fA-F0-9]{6}|[a-fA-F0-9]{8})'
           . '|rgb\((\d{1,3},\s?){2}\d{1,3}\)'
           . '|rgba\((\d{1,3},\s?){3}(0|0?\.\d+|1)\)'
           . '|hsl\(\d{1,3},\s?\d{1,3}%,\s?\d{1,3}%\)'
           . '|hsla\(\d{1,3},\s?\d{1,3}%,\s?\d{1,3}%,\s?(0|0?\.\d+|1)\)'
           . '|[a-zA-Z]+)$/';
    return preg_match($regex, trim($colour)) === 1;
}

/**
 * Convert HSL to RGB.
 *
 * @param float $h Hue (0-360).
 * @param float $s Saturation (0-1).
 * @param float $l Lightness (0-1).
 * @return array Array with RGB values (0-255).
 */
function hslToRgb($h, $s, $l)
{
    $c = (1 - abs(2 * $l - 1)) * $s;
    $x = $c * (1 - abs(fmod($h / 60, 2) - 1));
    $m = $l - $c / 2;

    if ($h < 60) {
        $r = $c;
        $g = $x;
        $b = 0;
    } elseif ($h < 120) {
        $r = $x;
        $g = $c;
        $b = 0;
    } elseif ($h < 180) {
        $r = 0;
        $g = $c;
        $b = $x;
    } elseif ($h < 240) {
        $r = 0;
        $g = $x;
        $b = $c;
    } elseif ($h < 300) {
        $r = $x;
        $g = 0;
        $b = $c;
    } else {
        $r = $c;
        $g = 0;
        $b = $x;
    }

    return [
        (int)(($r + $m) * 255),
        (int)(($g + $m) * 255),
        (int)(($b + $m) * 255)
    ];
}

/**
 * Check TinyMCE plugin list against an array of valid options
 * The passed list is checked against TINYMCE_VALID_PLUGINS.
 * The autoresize plugin is also included by default.
 * 
 * @param string $plugins A comma-separated list of plugins for TinyMCE
 * @return string The list of plugins with any invalid options removed
 */
function check_tinymce_plugins(string $plugins = ""): string 
{

    // Ensure autoresize plugin is included so min_height can be used
    if (mb_strpos($plugins, 'autoresize') === false) {
        $plugins .= ',autoresize';
    }

    $configured_plugins = array_map('trim', explode(',', $plugins));
    $valid_plugins = array_intersect_key(TINYMCE_VALID_PLUGINS, array_flip($configured_plugins));

    return implode(', ', array_keys($valid_plugins));
}

/**
 * Check TinyMCE toolbar configuration to ensure 
 * it contains only alphanumeric characters, spaces and 
 * the pipe (|) symbol.
 * 
 * @param string $toolbar The requested configuration for the toolbar
 * @return string The configured toolbar with any invalid characters removed
 */
function check_tinymce_toolbar(string $toolbar = ""): string 
{
    //Remove anything non-alphanumeric, pipes or spaces
    return preg_replace('/[^a-zA-Z0-9|\s]/', '', $toolbar);
}


/**
 * Return the number of resources in the system that are not ingested into the filestore i.e. with 'file_path' set
 *
 * @return int  Number of non-ingested resources in the system
 */
function get_non_ingested_resources(): int
{
    return ps_value("SELECT COUNT(*) value FROM resource WHERE file_path IS NOT NULL AND file_path <> ''", [], 0);
}

/**
 * Return URL of the application favicon
 *
 * @return string  Favicon URL
 */
function get_favicon_url(): string
{
    global $header_favicon, $baseurl, $storageurl;

    if ($header_favicon == '') {
        $header_favicon = 'gfx/interface/favicon.png';
    }

    $favicon = "{$baseurl}/{$header_favicon}";

    if (strpos($header_favicon, '[storage_url]') !== false) {
        $favicon = str_replace('[storage_url]', $storageurl, $header_favicon);
    }

    return $favicon;
}

/**
 *  Determine what is a manually uploaded preview based off of data in the resource log
 * 
 *  @param int $ref resource ID
 * 
 *  @return bool returns true if a resource is using its original preview otherwise false.  
 */
function is_original_preview(int $ref): bool
{
    return ps_value("SELECT resource `value` FROM resource_log rl1
                WHERE resource = ? AND type = 'up'
                AND NOT EXISTS (SELECT * FROM resource_log rl2 
                                    WHERE rl1.resource = rl2.resource AND rl1.date < rl2.date
                                    AND rl2.type IN ('t', 'f', 'u'))
            ", ['i', $ref], '') === '';
}

/**
 * Return the page title to use for the given page and optionally plugin.
 *
 * @param string $page   Page name without any leading filepath or trailing file extension.
 * @param string $plugin Optional. Used to denote which plugin the page is part of.
 *
 * @return string If a language string is found, returns "%%APPLICATION_NAME%% - %%PAGENAME%%";
 *                otherwise, returns "%%APPLICATION_NAME%%".
 */
function get_page_title(string $page, string $plugin = ""): string
{
    global $lang, $applicationname;
    if ($plugin != "" && isset($lang["page-title_{$plugin}_{$page}"])) {
        $page_title = " - " . $lang["page-title_{$plugin}_{$page}"];
    } elseif ($plugin == "" && isset($lang["page-title_$page"])) {
        $page_title =  " - " . $lang["page-title_$page"];
    } else {
        $page_title = "";
    }
    return $applicationname . $page_title;
}

/**
 * Log the bandwidth used by download.php
 * 
 * @param int $usage The total bandwidth used measured in KB
 */
function log_bandwidth(int $bandwidth_usage): void
{
    global $usergroup, $k;

    $date  = getdate();
    $update_params = [
        'i', $bandwidth_usage,
        'i', (trim($k) == '' ? 0 : 1),
        'i', $date["year"],
        'i', $date["mon"],
        'i', $date["mday"],
        'i', $usergroup ?? 0
    ];
    ps_query("UPDATE daily_stat SET count = count + ? WHERE activity_type = 'Downloaded KB' AND `external` = ? AND `year` = ? and `month` = ? and `day` = ? and usergroup = ? AND object_ref = 0", $update_params);

    $select_params = [
        'i', $date["year"],
        'i', $date["mon"],
        'i', $date["mday"],
        'i', $usergroup ?? 0
    ];
    $current_usage = ps_value("SELECT count AS `value` FROM daily_stat WHERE activity_type = 'Downloaded KB' AND `year` = ? and `month` = ? and `day` = ? and usergroup = ? AND object_ref = 0", $select_params, 0);
    if ($current_usage == 0) {
        ps_query("INSERT INTO daily_stat (count, `external`, `year`, `month`, `day`, usergroup, activity_type, object_ref) VALUES (?, ?, ?, ?, ?, ?, 'Downloaded KB', 0)", $update_params);
    }
}

/**
 * Strip Unicode points from a text value.
 * 
 * @param string $value Text value subject
 * @param list<string> $blocklist List of unicode points (e.g. \x{FEFF}), ranges allowed too (e.g. \x{200B}-\x{200D}) 
 */
function strip_unicode_points(string $value, array $blocklist = []): string
{
    if ($blocklist === []) {
        $blocklist = [
            // U+200B - zero-width space
            // U+200C - zero-width non-joiner
            // U+200D - zero-width joiner
            '\x{200B}-\x{200D}',
            '\x{FEFF}', # Byte Order Mark (BOM) or the zero-width no-break space (deprecated use)
        ];
    }

    return preg_replace('/[' . implode('', $blocklist) . ']/u', '', $value);
}

/**
 * Strip any characters not present in the allow list. Mostly useful for search/indexing purposes where we care more
 * about the words themselves and less about linking or any other type of characters.
 *
 * @param string $text Initial text value
 * @param list<string> $allowlist List of allowed characters. IMPORTANT: it's used to create a regex pattern.
 */
function allow_unicode_characters(string $text, array $allowlist = []): string
{
    // Regex explanation - match a single character not present in the list below:
    // \p{L} - any kind of letter from any language
    // \p{N} - any kind of numeric character in any script
    // \s - any kind of invisible character (equivalent to [\p{Z}\h\v])
    return trim_spaces(
        preg_replace('/[^\p{L}\p{N}\s' . implode('', array_map(preg_quote(...), $allowlist)) . ']+/u', '', $text)
    );
}

/**
 *  Add or update the value used for paging for the current page to the list of stored pager cookies
 * 
 * @param int $per_page value to be stored
 */

function set_per_page_cookie(int $per_page): void
{
    global $pagename;

    if (!is_positive_int_loose($per_page)) {
        return;
    }

    $pager_cookie = $_COOKIE['pager'] ?? '{}';
    $pager_cookie = json_decode($pager_cookie, true);

    $pager_cookie[$pagename] = $per_page;
    rs_setcookie('pager', json_encode($pager_cookie), 1);
}

/**
 *  Retrieve the pager cookie for the current page. 
 *  This function also clears the per_page and per_page_list cookies
 */
function get_per_page_cookie(): int
{
    global $pagename, $default_perpage_list;

    $pager_cookie = $_COOKIE['pager'] ?? '{}';
    $pager_cookie = json_decode($pager_cookie, true);

    rs_setcookie('per_page_list', '', -1);
    rs_setcookie('per_page', '', -1);

    return $pager_cookie[$pagename] ?? $default_perpage_list;
}

/**
 * Parse a comma-separated list of integers and integer ranges into a sorted array.
 * Accepts input such as:
 *   - "*"
 *   - "1,2,5"
 *   - "1-3,7,10-12"
 *   - "1-3,10-*"
 * 
 * @param string $input          Comma-separated list of integers and/or ranges (e.g. "1,3-5,10")
 * @param int    $max_val        Optional upper bound for allowed values. Use 0 for no limit
 * @param bool   $optional       If true, an empty input string is allowed. If false, empty input is an error
 * @param bool   $allow_wildcard If true, an asterisk can be used as a wildcard (e.g * for all, 10-* for 10 and above)
 *
 * @return array ok: bool         True when parsing succeeds, false when any validation error occurs
 *               numbers: int[]   Sorted list of unique integers when ok is true and allow_wildcard is false, otherwise empty
 *               errors: string[] List of error messages when ok is false, otherwise empty
 */
function parse_int_ranges(string $input, int $max_val = 0, bool $optional = false, bool $allow_wildcard = false): array
{
    global $lang;

    $errors = [];
    $numbers  = [];

    $input = trim($input);

    if ($input === '') {
        if ($optional) {
            return ['ok'      => true, 
                    'numbers' => [],
                    'errors'  => []];
        }
        return [
            'ok'      => false,
            'numbers' => [],
            'errors'  => [$lang['int_ranges_empty']],
        ];
    }

    // Sole wildcard: "*" is only valid if it is the entire input
    if ($input === '*') {
        if ($allow_wildcard) {
            return ['ok' => true, 'numbers' => [], 'errors' => []];
        }
        return [
            'ok'      => false,
            'numbers' => [],
            'errors'  => [str_replace('%%PART%%', '*', $lang['int_ranges_not_valid'])],
        ];
    }

    // Split by commas
    $parts = explode(',', $input);

    foreach ($parts as $raw_part) {
        $part = trim($raw_part);

        if ($part === '') {
            // Skip empty segments (e.g. "1,,2")
            continue;
        }

        // Disallow "*" alone when passed as a token
        if ($part === '*') {
            $errors[] = str_replace("%%PART%%", $part, $lang['int_ranges_not_valid']);
            continue;
        }

        // Range: start-end
        if (preg_match('/^(\d+)\s*-\s*(\d+|\*)$/', $part, $m)) {
            $start    = (int) $m[1];
            $endRaw   = $m[2];

            if ($start < 1) {
                $errors[] = str_replace("%%PART%%", $part, $lang['int_ranges_range_below_1']);
                continue;
            }

            // Wildcard range: start-*
            if ($endRaw === '*') {
                if (!$allow_wildcard) {
                    $errors[] = str_replace("%%PART%%", $part, $lang['int_ranges_not_valid']);
                    continue;
                }

                // If bounded, start must be within max
                if ($max_val !== 0 && $start > $max_val) {
                    $errors[] = str_replace(["%%PART%%", "%%MAX_VAL%%"], [$part, $max_val], $lang['int_ranges_range_above_max']);
                    continue;
                }

                // Valid (bounded or unbounded)
                continue;
            }

            // Normal numeric range: start-end
            $end = (int) $endRaw;

            if ($end < 1) {
                $errors[] = str_replace("%%PART%%", $part, $lang['int_ranges_range_below_1']);
                continue;
            }

            if ($start > $end) {
                $errors[] = str_replace("%%PART%%", $part, $lang['int_ranges_range_reversed']);
                continue;
            }

            if ($max_val !== 0 && ($start > $max_val || $end > $max_val)) {
                $errors[] = str_replace(["%%PART%%","%%MAX_VAL%%"], [$part, $max_val], $lang['int_ranges_range_above_max']);
                continue;
            }

            $numbers = array_merge($numbers, range($start, $end));
            continue;
        }

        // Single number
        if (ctype_digit($part)) {
            $num = (int) $part;

            if ($num < 1) {
                $errors[] = str_replace("%%PART%%", $part, $lang['int_ranges_single_below_1']);
                continue;
            }

            if ($max_val !== 0 && $num > $max_val) {
                $errors[] = str_replace(["%%PART%%","%%MAX_VAL%%"], [$num, $max_val], $lang['int_ranges_single_above_max']);
                continue;
            }

            $numbers[] = $num;
            continue;
        }

        // Anything else is invalid
        $errors[] = str_replace("%%PART%%", $part, $lang['int_ranges_not_valid']);
    }

    if (!empty($errors)) {
        return [
            'ok'      => false,
            'numbers' => [],
            'errors'  => $errors,
        ];
    }

    $numbers = array_unique($numbers);
    sort($numbers);

    return [
        'ok'      => true,
        'numbers' => ($allow_wildcard) ? [] : $numbers,
        'errors'  => [],
    ];
}

/**
 * Build a SQL WHERE fragment and bound parameters from a list of integers and ranges.
 *
 * Parses a comma-separated input string containing single integers and/or ranges
 * and can take optional wildcards (e.g. "1,3-5,10,12-*") 
 * Converts each item into a parameterized SQL condition:
 *   - Single value:   "<field> = ?"
 *   - Range:          "<field> BETWEEN ? AND ?"
 *   - Sole wildcard:  No filter, so 1=1 is used
 *   - Range wildcard: "<field> >= ?"
 *
 * All generated conditions are joined using OR and wrapped in parentheses.
 * Parameters are returned in the order they appear in the WHERE fragment.
 *
 * @param string $input          Comma-separated list of integers and/or ranges (e.g. "1,3-5,10")
 * @param string $field          Database column name to use in the generated SQL expressions
 * @param int    $max_val        Optional upper bound for allowed values. Use 0 for no limit
 * @param bool   $allow_wildcard If true, an asterisk can be used as a wildcard (e.g * for all, 10-* for 10 and above)
 * 
 * @return array ok: bool            True when the WHERE clause is successfully built, false on validation errors
 *               where: string|null  Parenthesized SQL fragment joined with OR when ok is true, otherwise null
 *               params: array       Ordered list of bind types and values
 *               errors: string[]    List of error messages when ok is false, otherwise empty
 */
function build_range_where_condition(string $input, string $field, int $max_val = 0, bool $allow_wildcard = false): array
{
    $conditions = [];
    $params     = [];
    $errors     = [];

    $input = trim($input);

    if ($input === '') {
        return [
            'ok'     => false,
            'where'  => null,
            'params' => [],
            'errors' => ['Input is empty.'],
        ];
    }

    if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*|`[^`]+`)(\.([A-Za-z_][A-Za-z0-9_]*|`[^`]+`))*$/', $field)) {
        return [
            'ok'     => false,
            'where'  => null,
            'params' => [],
            'errors' => ['Invalid field name.'],
        ];
    }

    // Sole wildcard: "*" is only valid if it is the entire input
    if ($input === '*') {
        if (!$allow_wildcard) {
            return [
                'ok'     => false,
                'where'  => null,
                'params' => [],
                'errors' => ['"*" is not a valid number or range (use formats like 3, 5-9, or 9-*).'],
            ];
        }

        return [
            'ok'     => true,
            'where'  => '(1=1)',
            'params' => [],
            'errors' => [],
        ];
    }

    // Split by commas
    $parts = explode(',', $input);

    foreach ($parts as $raw_part) {
        $part = trim($raw_part);

        if ($part === '') {
            continue;
        }

        // Explicitly disallow "*" alone when a token
        if ($part === '*') {
            $errors[] = "\"$part\" is not a valid number or range (use formats like 3, 5-9, or 9-*).";
            continue;
        }

        // Range: "start-end" or "start-*"
        if (preg_match('/^(\d+)\s*-\s*(\d+|\*)$/', $part, $m)) {
            $start  = (int) $m[1];
            $endRaw = $m[2];

            if ($start < 1) {
                $errors[] = "Values must be 1 or greater in range \"$part\".";
                continue;
            }

            // Wildcard range: start-*
            if ($endRaw === '*') {
                if (!$allow_wildcard) {
                    $errors[] = "\"$part\" is not a valid number or range (use formats like 3 or 5-9).";
                    continue;
                }

                // Bounded wildcard: start-* with max_val > 0 -> between start and max_val
                if ($max_val > 0) {
                    if ($start > $max_val) {
                        $errors[] = "Range \"$part\" exceeds maximum value of $max_val.";
                        continue;
                    }

                    $conditions[] = "$field BETWEEN ? AND ?";
                    $params[]     = "i";
                    $params[]     = $start;
                    $params[]     = "i";
                    $params[]     = $max_val;
                    continue;
                }

                // Unbounded wildcard: max_val === 0 -> field >= start
                $conditions[] = "$field >= ?";
                $params[]     = "i";
                $params[]     = $start;
                continue;
            }

            // Normal numeric range: start-end
            $end = (int) $endRaw;

            if ($end < 1) {
                $errors[] = "Values must be 1 or greater in range \"$part\".";
                continue;
            }

            if ($start > $end) {
                $errors[] = "Start of range \"$part\" cannot be greater than end.";
                continue;
            }

            if ($max_val !== 0 && ($start > $max_val || $end > $max_val)) {
                $errors[] = "Range \"$part\" exceeds maximum value of $max_val.";
                continue;
            }

            $conditions[] = "$field BETWEEN ? AND ?";
            $params[]     = "i";
            $params[]     = $start;
            $params[]     = "i";
            $params[]     = $end;
            continue;
        }
        
        // Single number
        if (ctype_digit($part)) {
            $num = (int)$part;

            if ($num < 1) {
                $errors[] = "Values must be 1 or greater (\"$part\").";
                continue;
            }

            if ($max_val !== 0 && $num > $max_val) {
                $errors[] = "Value \"$num\" exceeds maximum of $max_val.";
                continue;
            }

            $conditions[] = "$field = ?";
            $params[]     = "i";
            $params[]     = $num;
            continue;
        }

        $errors[] = "\"$part\" is not a valid number or range (use formats like 3, 5-9, or 9-*).";
    }

    if (!empty($errors)) {
        return [
            'ok'     => false,
            'where'  => null,
            'params' => [],
            'errors' => $errors,
        ];
    }

    if (empty($conditions)) {
        return [
            'ok'     => false,
            'where'  => null,
            'params' => [],
            'errors' => ['No valid values found.'],
        ];
    }

    // Join all conditions with OR, wrap in parentheses
    $where = '(' . implode(' OR ', $conditions) . ')';

    return [
        'ok'     => true,
        'where'  => $where,
        'params' => $params,
        'errors' => [],
    ];

}
