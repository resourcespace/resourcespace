<?php
/**
 * Helper and rendering function for the configuration pages in the team center
 */

/**
 * Renders a select element.
 *
 * Takes an array of options (as returned from sql_query and returns a valid
 * select element.  The query must have a column aliased as value and label.
 * Option groups can be created as well with the optional $groupby parameter.
 * This function retrieves a language field in the form of
 * $lang['cfg-<fieldname>'] to use for the element label.
 *
 * <code>
 * $options = sql_select("SELECT name AS label, ref AS value FROM resource_type");
 *
 * render_select_option('myfield', $options, 18);
 * </code>
 *
 * @param string $fieldname Name to use for the field.
 * @param array $opt_array Array of options to fill the select with
 * @param mixed $selected If matches value the option is marked as selected
 * @param string $groupby Column to group by
 * @return string HTML output.
 */
function render_select_option($fieldname, $opt_array, $selected, $groupby = '')
{
    global $errorfields, $lang;

    $output = '';
    $output .= "<tr><th><label for=\"$fieldname\">" . $lang['cfg-' . $fieldname] . "</label></th>";
    $output .= "<td><select name=\"$fieldname\">";

    if ($groupby != '') {
        $cur_group = $opt_array[0][$groupby];
        $output .= "<optgroup label=\"$cur_group\">";
    }

    foreach ($opt_array as $option) {
        if ($groupby != '' && $cur_group != $option[$groupby]) {
            $cur_group = $option[$groupby];
            $output .= "</optgroup><optgroup label=\"$cur_group\">";
        }
        $output .= "<option ";
        $output .= $option['value'] == $selected ? 'selected="selected" ' : '';
        $output .= "value=\"{$option['value']}\">{$option['label']}</option>";
    }

    $output .= '</optgroup>';
    $output .= isset($errorfields[$fieldname]) ? '<span class="error">* ' . $errorfields[$fieldname] . '</span>' : '';
    $output .= '</td></tr>';

    return $output;
}

/**
 * Render a yes/no field with the given fieldname.
 *
 * This function will use $lang['cfg-<fieldname>'] as the text label for the
 * element.
 * @param string $fieldname Name of field.
 * @param bool $value Current field value
 * @return string HTML Output
 */
function render_bool_option($fieldname, $value)
{
    global $errorfields, $lang;

    $output = '';
    $output .= "<tr><th><label for=\"$fieldname\">" . $lang['cfg-' . $fieldname] . "</label></th>";
    $output .= "<td><select name=\"$fieldname\">";
    $output .= "<option value='true' ";
    $output .= $value ? 'selected' : '';
    $output .= ">Yes</option>";
    $output .= "<option value='false' ";
    $output .= !$value ? 'selected' : '';
    $output .= ">No</option></select>";
    $output .= isset($errorfields[$fieldname]) ? '<span class="error">* ' . $errorfields[$fieldname] . '</span>' : '';
    $output .= "</td></tr>";

    return $output;
}

/**
 * Renders a text field for a given field name.
 *
 * Uses $lang['cfg-<fieldname>'] as the field label.
 *
 * @param string $fieldname Name of field
 * @param string $value Current field value
 * @param int $size Size of text field, optional, defaults to 20
 * @param string $units Optional units parameter. Displays to right of text field.
 * @return string HTML Output
 */
function render_text_option($fieldname, $value, $size = 20, $units = '')
{
    global $errorfields, $lang;

    if (isset($errorfields[$fieldname]) && isset($_POST[$fieldname])) {
        $value = $_POST[$fieldname];
    }

    $output = '';
    $output .= "<tr><th><label for=\"$fieldname\">" . $lang['cfg-' . $fieldname] . "</label></th>";
    $output .= "<td><input type=\"text\" value=\"$value\" size=\"$size\" name=\"$fieldname\"/> $units ";
    $output .= isset($errorfields[$fieldname]) ? '<span class="error">* ' . $errorfields[$fieldname] . '</span>' : '';
    $output .= "</td></tr>";

    return $output;
}

/**
* Save/ Update config option for user preference or system config. For user group config see set_usergroup_config_option().
*
* @param  integer  $user_id      Current user ID. Use NULL for system wide config options
* @param  string   $param_name   Parameter name
* @param  string   $param_value  Parameter value
*
* @return boolean
*/
function set_config_option($user_id, $param_name, $param_value)
{
    // We do allow for param values to be empty strings or 0 (zero)
    if (empty($param_name) || is_null($param_value)) {
        return false;
    }

    // Prepare the value before inserting it
    $param_value = config_clean($param_value);
    $query = "INSERT INTO user_preferences (user,parameter,`value`) VALUES (?,?,?)";
    $current_param_value = null;

    if (is_null($user_id)) {
        $config_type = array(); # System config.
    } else {
        $config_type = array('user' => $user_id); # User config.
    }

    if (get_config_option($config_type, $param_name, $current_param_value, null)) {
        if ($current_param_value == $param_value) {
            return true;
        }
        $params[] = 's';
        $params[] = $param_value;
        if (is_null($user_id)) {
            $user_query = 'user IS NULL AND usergroup IS NULL';
        } else {
            $user_query = 'user = ?';
            $params[] = 'i';
            $params[] = $user_id;
        }

        $query = "UPDATE user_preferences SET `value` = ? WHERE " . $user_query . " AND parameter = ?";
        $params[] = "s";
        $params[] = $param_name;

        if (is_null($user_id)) {      // only log activity for system changes, i.e. when user not specified
            log_activity(null, LOG_CODE_EDITED, $param_value, 'user_preferences', 'value', "parameter='" . $param_name . "'", null, $current_param_value);
        }
    } else {
        $params  = ["i",$user_id,"s",$param_name,"s",$param_value,];
    }
    ps_query($query, $params);

    // Clear disk cache
    clear_query_cache("preferences");

    return true;
}

/**
 * Save or update config option for user group.
 *
 * @param  int     $usergroup_id   User group id
 * @param  string  $param_name     Config parameter name
 * @param  string  $param_value    Config parameter value
 */
function set_usergroup_config_option(int $usergroup_id, string $param_name, ?string $param_value): bool
{
    // We do allow for param values to be empty strings or 0 (zero)
    if (empty($param_name) || is_null($param_value)) {
        return false;
    }

    // Prepare the value before inserting it
    $param_value = config_clean($param_value);
    $query = "INSERT INTO user_preferences (usergroup, parameter, `value`) VALUES (?, ?, ?)";
    $current_param_value = null;

    if (get_config_option(['usergroup' => $usergroup_id], $param_name, $current_param_value, null)) {
        if ($current_param_value == $param_value) {
            return true;
        }

        $params[] = 's';
        $params[] = $param_value;
        $params[] = 'i';
        $params[] = $usergroup_id;

        $query = "UPDATE user_preferences SET `value` = ? WHERE usergroup = ? AND parameter = ?";
        $params[] = "s";
        $params[] = $param_name;
    } else {
        $params  = ["i", $usergroup_id, "s", $param_name, "s", $param_value];
    }

    ps_query($query, $params);

    // Clear disk cache
    clear_query_cache("preferences");

    return true;
}

/**
 * Delete entry from the user_preferences table completely (instead of setting to blank via set_config_option).
 * Used by system preferences page when deleting a file to allow fallback to value (if set) in config.php instead
 * of replacing it with blank from user_preference value.
 *
 * @param  array      $config_type  The type of config to delete supplied as an array. The following are possible:
*                                   array() - Supply an empty array to delete a system config value.
*                                   array('user' => 1) - Supply 'user' with the integer user reference to delete a user config value.
*                                   array('usergroup' => 2) - Supply 'usergroup' with the integer user reference to delete a user group config value.
 * @param  string     $param_name   Parameter name
 *
 * @return bool       True if preference was deleted else false.
 */
function delete_config_option(array $config_type, string $param_name): bool
{
    if (empty($param_name)) {
        return false;
    }

    if (isset($config_type['user']) && isset($config_type['usergroup'])) {
        return false; # Both user and usergroup cannot be supplied.
    }

    foreach ($config_type as $type_value) {
        if (!is_int_loose($type_value)) {
            return false;
        }
    }

    $current_param_value = null;
    if (get_config_option($config_type, $param_name, $current_param_value)) {
        if (isset($config_type['usergroup'])) {
            $user_query = 'usergroup = ?';
            $params[] = 'i';
            $params[] = $config_type['usergroup'];
        } elseif (isset($config_type['user'])) {
            $user_query = 'user = ?';
            $params[] = 'i';
            $params[] = $config_type['user'];
        } else {
            $user_query = 'user IS NULL';
        }

        $query = "DELETE FROM user_preferences WHERE " . $user_query . " AND parameter = ?";
        $params[] = "s";
        $params[] = $param_name;

        if (count($config_type) === 0) {
            // only log activity for system changes, i.e. when user not specified
            log_activity(null, LOG_CODE_DELETED, null, 'user_preferences', 'value', "parameter='" . $param_name . "'", null, $current_param_value);
        }

        ps_query($query, $params);

        // Clear disk cache
        clear_query_cache("preferences");

        return true;
    }

    return false;
}


/**
 * Remove system/user preferences
 *
 * @param ?int $user_id Database user ID
 * @param string $name  Configuration option (variable) name
 */
function remove_config_option(?int $user_id, string $name): bool
{
    if (trim($name) === '') {
        return false;
    }

    $user = is_null($user_id)
        ? new PreparedStatementQuery('user IS NULL')
        : new PreparedStatementQuery('user = ?', ['i', $user_id]);

    $psq = new PreparedStatementQuery(
        "DELETE FROM user_preferences WHERE {$user->sql} AND parameter = ?",
        array_merge($user->parameters, ['s', $name])
    );

    ps_query($psq->sql, $psq->parameters);
    clear_query_cache('preferences');
    return true;
}

/**
* Get config option value from the database. This may be a system wide config, a user group preference, a user preference, or the result of
* overriding a user group preference with a user preference if any are present.
*
* @param  array    $config_type     The type of config to retrieve supplied as an array. The following combinations are possible:
*                                   array() - Supply an empty array to return a system config value.
*                                   array('usergroup' => 2) - Supply 'usergroup' with the integer user group reference to return a user group config value.
*                                   array('user' => 1) - Supply 'user' with the integer user reference to return a user config value.
*                                   array('user' => 1, 'usergroup' => 2) - Supply 'user' with the integer user reference and 'usergroup' with the integer
*                                   user group reference to return the result of user group config overridden with user preference. Usergroup should always be
*                                   that of the supplied user. Don't supply parent group as this will be checked.
* @param  string   $name            Parameter name
* @param  string   $returned_value  The config value will be returned through this parameter which is passed by reference.
*                                   IMPORTANT: it falls back (defaults) to the globally scoped config option value if
*                                   there's nothing in the database.
* @param  mixed    $default         Optionally used to set a default that may not be the current
*                                   global setting e.g. for checking admin resource preferences
*
* @return boolean Indicates if the config option was found in the database or not.
*/
function get_config_option(array $config_type, $name, &$returned_value, $default = null)
{
    if (trim($name) === '') {
        return false;
    }

    foreach ($config_type as $type_value) {
        if (!is_int_loose($type_value)) {
            return false;
        }
    }

    $params[] = "s";
    $params[] = $name;

    if (count($config_type) === 0) {
        # Return system config.
        $user_query = 'user IS NULL AND usergroup IS NULL';
    } elseif (isset($config_type['usergroup']) && !isset($config_type['user'])) {
        # Return user group preference if present (without considering current user).
        # Also don't consider parent user group.
        $user_query = 'usergroup = ? AND user IS NULL';
        $params[] = 'i';
        $params[] = $config_type['usergroup'];
    } elseif (isset($config_type['user']) && !isset($config_type['usergroup'])) {
        # Special case when we want to check only user preference, not considering any user group values.
        $user_query = 'user = ?';
        $params[] = 'i';
        $params[] = $config_type['user'];
    } else {
        # Return user preference if present else user group preference if present.
        $config_type['usergroup'] = get_usergroup_parent_for_inherit_flag($config_type['usergroup'], 'preferences');
        $user_query = '(user = ? OR usergroup = ?) ORDER BY usergroup LIMIT 1';
        $params[] = 'i';
        $params[] = $config_type['user'];
        $params[] = 'i';
        $params[] = $config_type['usergroup'];
    }

    $query = "SELECT `value` FROM user_preferences WHERE parameter = ? AND " . $user_query;
    $config_option = ps_value($query, $params, null, "preferences");

    if (is_null($default) && isset($GLOBALS[$name])) {
        $default = $GLOBALS[$name];
    }

    if (is_null($config_option)) {
        $returned_value = $default;
        return false;
    }

    $returned_value = unescape($config_option);
    return true;
}


/**
* Get all user refs with a specific configuration option set from database
*
* @param  string  $option           Parameter name
* @param  string  $value            Parameter value
*
* @return array                     Array of user  references
*/
function get_config_option_users($option, $value)
{
    return ps_array("SELECT user value FROM user_preferences WHERE parameter = ? AND value = ?", array("s", $option, "s", $value), "preferences");
}

/**
* Get config option from database for a specific user, system wide or user group.
*
* @param  array    $config_type       The type of config to retrieve supplied as an array. The following combinations are possible:
*                                     array() - Supply an empty array to return a system config value.
*                                     array('user' => 1) - Supply 'user' with the integer user reference to return user config values.
*                                     array('usergroup' => 2) - Supply 'usergroup' with the integer user reference to return user group config values.
*                                     Note: Supplying both 'user' and 'usergroup' is invalid.
* @param  array    $returned_options  If a value does exist it will be returned through
*                                     this parameter which is passed by reference
* @return boolean
*/
function get_config_options(array $config_type, array &$returned_options)
{
    if (isset($config_type['user']) && isset($config_type['usergroup'])) {
        return false; # Both user and usergroup cannot be supplied.
    }

    foreach ($config_type as $type_value) {
        if (!is_int_loose($type_value)) {
            return false;
        }
    }

    $params = [];
    if (isset($config_type['user'])) {
        # Return user preferences.
        $sql = 'user = ?';
        $params = ['i', $config_type['user']];
    } elseif (isset($config_type['usergroup'])) {
        # Return user group config.
        $usergroup_id = get_usergroup_parent_for_inherit_flag($config_type['usergroup'], 'preferences');
        $sql = 'usergroup = ?';
        $params = ['i', $usergroup_id];
    } else {
        # Return system config.
        $sql = 'user IS NULL AND usergroup IS NULL';
    }

    $query = 'SELECT parameter, `value` FROM user_preferences WHERE ' . $sql;
    $config_options = ps_query($query, $params, "preferences");

    if (empty($config_options)) {
        return false;
    }

    // Strip out any system configs that are blocked from being edited in the UI that might have been set previously.
    global $system_config_hide;
    if (count($config_type) === 0 && count($system_config_hide) > 0) {
        $new_config_options = array();
        for ($n = 0; $n < count($config_options); $n++) {
            if (!in_array($config_options[$n]["parameter"], $system_config_hide)) {
                $new_config_options[] = $config_options[$n];
            } // Add if not blocked
        }
        $config_options = $new_config_options;
    }

    $returned_options = $config_options;

    return true;
}

/**
 * Check if the usergroup has a parent and the specified inherit flag is in use e.g. user group inherits permissions from parent.
 *
 * @param  int     $usergroup_id   User group of the current user / user group to test.
 * @param  string  $inherit_flag   Inherit flag to test for.
 *
 * @return int   User group id of parent if found and using supplied inherit flag else returns user group id supplied.
 */
function get_usergroup_parent_for_inherit_flag(int $usergroup_id, string $inherit_flag): int
{
    if (isset($GLOBALS['usergroup_parent_for_inherit_flag'][$usergroup_id])) {
        return $GLOBALS['usergroup_parent_for_inherit_flag'][$usergroup_id];
    }

    $result = $usergroup_id;

    $usergroup_properties = get_usergroup($usergroup_id);

    if ($usergroup_properties !== false && $usergroup_properties['parent'] > 0 && in_array($inherit_flag, $usergroup_properties['inherit'])) {
        $result = $usergroup_properties['parent'];
    }

    $GLOBALS['usergroup_parent_for_inherit_flag'][$usergroup_id] = $result; # Cache value for subsequent calls to this function.
    return $result;
}

/**
* Process configuration options from database either system wide, user group or user specific, setting the global variable.
* Three modes are possible: array() - Supply an empty array to load system config values.
*                           array('user' => 1) - Supply 'user' with the integer user reference to load user config values.
*                           array('usergroup' => 2) - Supply 'usergroup' with the integer user reference to load user group config values.
*                           Note: Supplying both 'user' and 'usergroup' is invalid.
* In some scenarios, calling this function twice will be required e.g. load user group config then override with user config.
* Note: calling this function will not revert user preferences applied previously e.g. during initialisation as a different user.
* If the current user's preferences shouldn't be shown, consider using $system_wide_config_options to reapply selected system values.
*
* @param  array   $config_type   Specify the type of config to be loaded. See details above.
*
*/
function process_config_options(array $config_type): void
{
    global $user_preferences;
    $config_options = array();

    // If the user doesn't have the ability to set his/her own preferences, then don't load it either
    if (isset($config_type['user']) && !$user_preferences) {
        return;
    }

    if (get_config_options($config_type, $config_options)) {
        foreach ($config_options as $config_option) {
            $param_value = $config_option['value'];
            $global_type =  isset($GLOBALS[$config_option['parameter']])
                ? gettype($GLOBALS[$config_option['parameter']])
                : '';

            // Everything is stored as a string so try and cast to the expected type
            if ($global_type === 'array') {
                if (trim($param_value) === '') {
                    $param_value = [];
                } elseif (validate_digit_csv($param_value)) {
                    $param_value = array_map(intval(...), explode(',', $param_value));
                } else {
                    // Not implemented - we don't support setting non-list (array) structures - undefined behaviour!
                }
            } elseif ($global_type === 'boolean') {
                $param_value = (bool) $param_value;
            } elseif (is_numeric($param_value) && '' !== $param_value) {
                $param_value = (int) $param_value;
            }

            $GLOBALS[$config_option['parameter']] = $param_value;
        }
    }
}

/**
 * Utility function to "clean" the passed $config. Cleaning consists of two parts:
 *  *    Suppressing really simple XSS attacks by refusing to allow strings
 *       containing the characters "<script" in upper, lower or mixed case.
 *  *    Unescaping instances of "'" and '"' that have been escaped by the
 *       lovely magic_quotes_gpc facility, if it's on.
 *
 * @param $config mixed thing to be cleaned.
 * @return a cleaned version of $config.
 */
function config_clean($config)
{
    if (is_array($config)) {
        foreach ($config as &$item) {
            $item = config_clean($item);
        }
    } elseif (is_string($config)) {
        if (strpos(strtolower($config), "<script") !== false) {
            $config = '';
        }
    }
    return $config;
}

/**
 * Generate arbitrary html
 *
 * @param string $content arbitrary HTML
 */
function config_html($content)
{
    echo $content;
}

/**
 * Return a data structure that will instruct the configuration page generator functions to add
 * arbitrary HTML
 *
 * @param string $content
 */
function config_add_html($content)
{
    return array('html', $content);
}

 /**
 * Generate an html text entry or password block
 *
 * @param string    $name       The name of the configuration variable to be added.
 * @param string    $label      The user text displayed to label the text block. Usually a $lang string.
 * @param string    $current    The current value of the config variable being set.
 * @param bool      $password   Whether this is a "normal" text-entry field or a password-style field.
 * @param int       $width      The width of the input field in pixels. Default: 420.
 * @param bool      $textarea   Render as a HTML <textarea>
 * @param string    $title      The title attribute of the element
 * @param bool      $autosave   Enable auto-saving of changes when focus is lost
 * @param bool      $hidden     Whether field is hidden on the page
 * @param string    $help_link  Help link to be displayed alongside the label.
 */
function config_text_input($name, $label, $current, $password = false, $width = 420, $textarea = false, $title = null, $autosave = false, $hidden = false, $help_link = "")
{
    global $lang;

    if (is_null($title)) {
        // This is how it was used on plugins setup page. Makes sense for developers when trying to debug and not much for non-technical users
        $title = str_replace('%cvn', $name, $lang['plugins-configvar']);
    }
    ?>

    <div class="Question" id="question_<?php echo escape($name); ?>" <?php echo $hidden ? 'style="display:none;"' : ''; ?>>
        <label for="<?php echo escape($name); ?>" title="<?php echo escape($title); ?>">
            <?php
            echo strip_tags_and_attributes($label, ['a'], ['href', 'target']);
            if ($help_link !== "") {
                render_help_link($help_link);
            }
            ?>
        </label>

        <?php
        if ($autosave) {
            ?>
            <div class="AutoSaveStatus">
                <span id="AutoSaveStatus-<?php echo escape($name); ?>" style="display:none;"></span>
            </div>
            <?php
        }

        if (!$textarea) {
            ?>
            <input id="<?php echo escape($name); ?>"
                name="<?php echo escape($name); ?>"
                type="<?php echo $password ? 'password' : 'text'; ?>"
                value="<?php echo escape((string) $current); ?>"
                <?php if ($autosave) { ?>
                    onfocusout="AutoSaveConfigOption('<?php echo escape($name); ?>');"
                <?php } ?>
                style="width:<?php echo (int) $width; ?>px"
            />
            <?php
        } else {
            ?>
            <textarea
                id="<?php echo escape($name); ?>"
                name="<?php echo escape($name); ?>"
                style="width:<?php echo (int) $width; ?>px"
            ><?php echo escape($current); ?></textarea>
            <?php
        }
        ?>
        <div class="clearerleft"></div>
    </div>

    <?php
}

/**
 * Return a data structure that will instruct the configuration page generator functions to
 * add a text entry configuration variable to the setup page.
 *
 * @param string    $config_var The name of the configuration variable to be added.
 * @param string    $label      The user text displayed to label the text block. Usually a $lang string.
 * @param bool      $password   Whether this is a "normal" text-entry field or a password-style field.
 * @param int       $width      The width of the input field in pixels. Default: 420.
 * @param bool      $textarea   Render as a HTML <textarea>
 * @param string    $title      The title attribute of the element
 * @param bool      $autosave   Enable auto-saving of changes when focus is lost
 * @param bool      $hidden     Whether field is hidden on the page
 * @param string    $help_link  Help link to be displayed alongside the label.
 */
function config_add_text_input($config_var, $label, $password = false, $width = 420, $textarea = false, $title = null, $autosave = false, $hidden = false, string $help_link = "")
{
    return array('text_input', $config_var, $label, $password, $width, $textarea, $title, $autosave, $hidden, $help_link);
}


/**
* Generate a data structure to instruct the configuration page generator to add a hidden input
*
* @param string $cf_var_name  Plugins' configuration variable name
* @param string $cf_var_value Value
*
* @return array
*/
function config_add_hidden_input(string $cf_var_name, string $cf_var_value = '')
{
    return array('text_hidden_input', $cf_var_name, $cf_var_value);
}

/**
* Generate an HTML input file with its own form
*
* @param string $name        HTML input file name attribute
* @param string $label
* @param string $form_action URL where the form should post to
* @param int    $width       Wdidth of the input file HTML tag. Default - 420
*/
function config_file_input($name, $label, $current, $form_action, $width = 420, $valid_extensions = array(), $file_preview = false)
{
    global $lang, $storagedir, $storageurl;

    if ($current !== '') {
        $origin_in_config = (substr($current, 0, 13) != '[storage_url]') && mb_strpos($current, 'system/config') === false;
        if ($origin_in_config) {
            # Current value may have originated in config.php - file uploader to consider this unset
            # to enable override of config.php by uploading a file.
            $current = '';
            $current_file = '';
            $current_file_url = '';
        } else {
            $current_file     = str_replace('[storage_url]', $storagedir, $current);
            $current_file_url = str_replace('[storage_url]', $storageurl, $current);
        }
    }
    ?>

    <div class="Question" id="question_<?php echo escape($name); ?>">
        <form method="POST" action="<?php echo escape($form_action); ?>" enctype="multipart/form-data">
            <label
                <?php if ($file_preview && $current !== "") { ?>
                    id="config-image-preview-label"
                <?php } ?>
                for="<?php echo escape($name); ?>"
            >
                <?php echo escape($label); ?>
            </label>
            <div class="AutoSaveStatus">
                <span id="AutoSaveStatus-<?php echo escape($name); ?>" style="display:none;"></span>
            </div>
            <?php
            if ($current !== '' && !file_exists($current_file)) {
                ?>
                <span><?php echo escape($lang['applogo_does_not_exists']); ?></span>
                <input type="submit" name="clear_<?php echo escape($name); ?>" value="<?php echo escape($lang["clearbutton"]); ?>">
                <?php
            } elseif ('' === $current || !get_config_option([], $name, $current_option) || $current_option === '') {
                ?>
                <input type="file" name="<?php echo escape($name); ?>" style="width:<?php echo (int) $width; ?>px">
                <input
                    type="submit"
                    name="upload_<?php echo escape($name); ?>"
                    <?php if (count($valid_extensions) > 0) {
                        echo 'onclick="return checkValidExtension_' . escape($name) . '()"';
                    } ?>
                    value="<?php echo escape($lang['upload']); ?>"
                >
                <?php
                if (count($valid_extensions) > 0) {
                    ?>
                    <script>
                        function checkValidExtension_<?php echo escape($name) ?>() {
                            let file_path = document.getElementsByName("<?php echo escape($name); ?>")[0].value;
                            let ext = file_path.toLowerCase().substr(file_path.lastIndexOf(".")+1);
                            let valid_extensions = [<?php
                            foreach ($valid_extensions as $extension) {
                                echo '"' . escape($extension) . '",';
                            } ?>];

                            if (file_path != "" && valid_extensions.includes(ext)) return true;
                            alert(<?php echo '"' . escape(str_replace('[extensions]', implode(', ', $valid_extensions), $lang['systemconfig_invalid_extension'])) . '"'?>);
                            return false;
                        }
                    </script>
                    <?php
                }
            } else {
                if (function_exists('mime_content_type')) {
                    $mime_type = explode("/", mime_content_type($current_file));
                } else {
                    $mime_type = explode("/", get_mime_type($current_file)[0]);
                }
                $file_type = end($mime_type);
                $file_size = str_replace("&nbsp;", " ", formatfilesize(filesize($current_file)));
                ?>
                <span style="width: 316px;display: inline-block;"><?php echo escape("$file_type ($file_size)"); ?></span>
                <input type="submit" name="delete_<?php echo escape($name); ?>" value="<?php echo escape($lang['action-delete']); ?>">
                <?php
            }
            generateFormToken($name);
            ?>
        </form>
        <?php
        if ($file_preview && $current !== "" && file_exists($current_file)) {
            ?>
            <div id="preview_<?php echo escape($name); ?>">
                <img class="config-image-preview"
                    src="<?php echo escape($current_file_url) . '?v=' . date("s") ?>"
                    alt="<?php echo escape($lang["preview"] . ' - ' . $label) ?>"
                >
            </div>
            <?php
        }
        ?>
        <div class="clearerleft"></div>
    </div>
    <?php
}

/**
 * Generate colour picker input
 *
 * @param string $name          HTML input name attribute
 * @param string $label
 * @param string $current       Current value
 * @param string $default       Default value
 * @param string $title         Title
 * @param boolean $autosave     Automatically save the value on change
 * @param string on_change_js   JavaScript run onchange of value (useful for "live" previewing of changes)
 */
function config_colouroverride_input($name, $label, $current, $default, $title = null, $autosave = false, $on_change_js = null, $hidden = false)
{
    global $lang;
    $checked = $current && $current != $default;
    if (is_null($title)) {
        // This is how it was used on plugins setup page. Makes sense for developers when trying to debug and not much for non-technical users
        $title = str_replace('%cvn', $name, $lang['plugins-configvar']);
    }
    ?>
    
    <div class="Question" style="min-height: 1.5em;" id="question_<?php echo escape($name); ?>" <?php echo $hidden ? "style=\"display:none;\"" : ''; ?>>
        <label for="<?php echo escape($name); ?>" title="<?php echo escape($title); ?>"><?php echo escape($label); ?></label>
        <div class="AutoSaveStatus">
            <span id="AutoSaveStatus-<?php echo escape($name); ?>" style="display:none;"></span>
        </div>
        <input
            type="checkbox"
            <?php if ($checked) { ?>
                checked="true"
            <?php } ?>
            onchange="
                jQuery('#container_<?php echo escape($name); ?>').toggle();
                if (!this.checked) {
                    // Unchecked, set the default. Must first change the type as a color picker can't hold the empty string.
                    jQuery('#<?php echo escape($name); ?>').attr('type','text');
                    jQuery('#<?php echo escape($name); ?>').val('<?php echo escape($default); ?>');
                    <?php
                    if (!empty($on_change_js)) {
                        echo $on_change_js;
                    }
                    if ($autosave) {
                        ?>AutoSaveConfigOption('<?php echo escape($name); ?>');
                        jQuery('#<?php echo escape($name); ?>').trigger('change');
                        <?php
                    }
                    if (!empty($on_change_js)) {
                        echo $on_change_js;
                    }
                    ?>
                } else {
                    jQuery('#<?php echo escape($name); ?>').attr('type','color');
                }"
            style="float: left;"
        />
        <div id="container_<?php echo escape($name); ?>"<?php echo !$checked ? 'style="display: none;"' : ''; ?>>
            &nbsp;
            <input
                id="<?php echo escape($name); ?>"
                name="<?php echo escape($name); ?>"
                type="<?php echo $checked ? "color" : "text"; ?>"
                value="<?php echo escape($current); ?>"
                onchange="<?php
                if ($autosave) {
                    ?>AutoSaveConfigOption('<?php echo escape($name); ?>');<?php
                }
                if (!empty($on_change_js)) {
                    echo $on_change_js;
                }
                ?>"
                default="<?php echo escape($default); ?>"
            />
        </div>
        <div class="clearerleft"></div>
    </div>
        
    <?php
}

/**
* Return a data structure that will be used to generate the HTML for
* uploading a file
*
* @param string  $name               HTML input file name attribute
* @param string  $label              Label for field
* @param string  $form_action        URL where the form should post to
* @param int     $width              Width of the input file HTML tag. Default - 420
* @param array   $valid_extensions   Optional array of file extensions that will be validated during upload, see config_process_file_input()
*/
function config_add_file_input($config_var, $label, $form_action, $width = 420, $valid_extensions = array(), $file_preview = false)
{
    return array('file_input', $config_var, $label, $form_action, $width, $valid_extensions, $file_preview);
}

/**
 * Generate an html single-select + options block
 *
 * @param string        $name      The name of the select block. Usually the name of the config variable being set.
 * @param string        $label     The user text displayed to label the select block. Usually a $lang string.
 * @param string        $current   The current value of the config variable being set.
 * @param string array  $choices   The array of the alternatives -- the options in the select block. The keys
 *                                 are used as the values of the options, and the values are the alternatives the user sees. (But
 *                                 see $usekeys, below.) Usually a $lang entry whose value is an array of strings.
 * @param boolean       $usekeys   Tells whether to use the keys from $choices as the values of the options. If set
 *                                 to false the values from $choices will be used for both the values of the options and the text
 *                                 the user sees. Defaulted to true.
 * @param integer       $width     The width of the input field in pixels. Default: 420.
 * @param string        $title     Title to be used for the label title. Default: null
 * @param boolean       $autosave  Flag to say whether the there should be an auto save message feedback through JS. Default: false
 *                                 Note: onChange event will call AutoSaveConfigOption([option name])
 */
function config_single_select($name, $label, $current, $choices, $usekeys = true, $width = 420, $title = null, $autosave = false, $on_change_js = null, $hidden = false, bool $reload_page = false)
{
    global $lang;

    if (is_null($title)) {
        // This is how it was used on plugins setup page. Makes sense for developers when trying to debug and not much for non-technical users
        $title = str_replace('%cvn', $name, $lang['plugins-configvar']);
    }
    ?>

    <div class="Question" id="question_<?php echo escape($name); ?>" <?php echo $hidden ? "style=\"display:none;\"" : ''; ?>>
        <label for="<?php echo escape($name); ?>" title="<?php echo escape($title); ?>">
            <?php echo strip_tags_and_attributes($label, ['a'], ['href', 'target']); ?>
        </label>
        <?php if ($autosave) { ?>
            <div class="AutoSaveStatus">
                <span id="AutoSaveStatus-<?php echo escape($name); ?>" style="display:none;"></span>
            </div>
        <?php } ?>
        <select
            id="<?php echo escape($name); ?>"
            name="<?php echo escape($name); ?>"
            <?php if ($autosave) { ?>
                onChange="<?php echo $on_change_js; ?>AutoSaveConfigOption('<?php echo escape($name); ?>'<?php echo $reload_page ? ", true" : ""?>);"
            <?php } ?>
            style="width:<?php echo (int) $width; ?>px">
            <?php foreach ($choices as $key => $choice) {
                $value = $usekeys ? $key : $choice; ?>
                <option value="<?php echo escape($value); ?>" <?php echo $current == $value ? ' selected' : ''; ?>>
                    <?php echo escape($choice); ?>
                </option>
            <?php } ?>
        </select>
        <div class="clearerleft"></div>
    </div>
    
    <?php
}


/**
 * Return a data structure that will instruct the configuration page generator functions to
 * add a single select configuration variable to the setup page.
 *
 * @param string $config_var the name of the configuration variable to be added.
 * @param string $label the user text displayed to label the select block. Usually a $lang string.
 * @param string array $choices the array of the alternatives -- the options in the select block. The keys
 *          are used as the values of the options, and the values are the alternatives the user sees. (But
 *          see $usekeys, below.) Usually a $lang entry whose value is an array of strings.
 * @param boolean $usekeys tells whether to use the keys from $choices as the values of the options. If set
 *          to false the values from $choices will be used for both the values of the options and the text
 *          the user sees. Defaulted to true.
 * @param integer $width the width of the input field in pixels. Default: 420.
 */
function config_add_single_select($config_var, $label, $choices = '', $usekeys = true, $width = 420, $title = null, $autosave = false, $on_change_js = null, $hidden = false, bool $reload_page = false)
{
    return array('single_select', $config_var, $label, $choices, $usekeys, $width, $title, $autosave, $on_change_js, $hidden, $reload_page);
}


/**
 * Generate an html boolean select block
 *
 * @param string        $name      The name of the select block. Usually the name of the config variable being set.
 * @param string        $label     The user text displayed to label the select block. Usually a $lang string.
 * @param boolean       $current   The current value (true or false) of the config variable being set.
 * @param string array  $choices   Array of the text to display for the two choices: False and True. Defaults
 *                                 to array('False', 'True') in the local language.
 * @param integer       $width     The width of the input field in pixels. Default: 420.
 * @param string        $title     Title to be used for the label title. Default: null
 * @param boolean       $autosave  Flag to say whether the there should be an auto save message feedback through JS. Default: false
 *                                 Note: onChange event will call AutoSaveConfigOption([option name])
 * @param string        $help      Help text to display for this question
 * @param boolean       $reload_page Reload the page after saving, useful for large CSS changes.
 */
function config_boolean_select(
    $name,
    $label,
    $current,
    $choices = '',
    $width = 420,
    $title = null,
    $autosave = false,
    $on_change_js = null,
    $hidden = false,
    string $help = '',
    bool $reload_page = false
) {
    global $lang;

    $help = trim($help);

    if ($choices == '') {
        $choices = $lang['false-true'];
    }

    if (is_null($title)) {
        // This is how it was used on plugins setup page. Makes sense for developers when trying to debug and not much for non-technical users
        $title = str_replace('%cvn', $name, $lang['plugins-configvar']);
    }

    $html_question_id = "question_{$name}";
    ?>
    <div class="Question" id="<?php echo escape($html_question_id); ?>" <?php echo $hidden ? "style=\"display:none;\"" : ''; ?>>
        <label for="<?php echo escape($name); ?>" title="<?php echo escape($title); ?>">
            <?php echo strip_tags_and_attributes($label, ['a'], ['href', 'target']); ?>
        </label>

        <?php if ($autosave) { ?>
            <div class="AutoSaveStatus">
                <span id="AutoSaveStatus-<?php echo escape($name); ?>" style="display:none;"></span>
            </div>
        <?php } ?>

        <select
            id="<?php echo escape($name); ?>"
            name="<?php echo escape($name); ?>"
            <?php if ($autosave) { ?>
                onChange="<?php echo $on_change_js; ?>AutoSaveConfigOption('<?php echo escape($name); ?>'<?php echo $reload_page ? ", true" : ""?>);"
            <?php } ?>
            style="width:<?php echo (int) $width; ?>px"
        >
            <option value="1" <?php echo ($current == '1') ? 'selected' : ''; ?>>
                <?php echo $choices[1]; ?>
            </option>
            <option value="0" <?php echo ($current == '0') ? 'selected' : ''; ?>>
                <?php echo $choices[0]; ?>
            </option>
        </select>

        <?php
        if ($help !== '') {
            render_question_form_helper($help, $html_question_id, []);
        }
        ?>
        <div class="clearerleft"></div>
    </div>
    <?php
}


/**
 * Return a data structure that will instruct the configuration page generator functions to
 * add a boolean configuration variable to the setup page.
 *
 * @param string $config_var the name of the configuration variable to be added.
 * @param string $label the user text displayed to label the select block. Usually a $lang string.
 * @param string array $choices array of the text to display for the two choices: False and True. Defaults
 *          to array('False', 'True') in the local language.
 * @param integer $width the width of the input field in pixels. Default: 420.
 * @param string $help Help text to display for this question
 * @param boolean $reload_page Reload the page after saving.
 */
function config_add_boolean_select($config_var, $label, $choices = '', $width = 420, $title = null, $autosave = false, $on_change_js = null, $hidden = false, string $help = '', bool $reload_page = false)
{
    return array('boolean_select', $config_var, $label, $choices, $width, $title, $autosave,$on_change_js,$hidden, $help, $reload_page);
}

/**
 * Generate an html checkbox options block
 *
 * @param string $name the name of the checkbox block.
 * @param string $label the user text displayed to label the checkbox block. Usually a $lang string.
 * @param string array $current the current array of selected values for the config variable being set.
 * @param string array $choices the array of choices -- the list of checkboxes. The keys are
 *          used to generate the values of the checkbox, and the values are the checkbox labels the user sees. (But see
 *          $usekeys, below.)
 * @param boolean $usekeys tells whether to use the keys from $choices as the values of the options.
 *          If set to false the values from $choices will be used for both the values of the options
 *          and the text the user sees. Defaulted to true.
 * @param integer $width the width of the input field in pixels. Default: 300.
 * @param integer $columns the number of columns to use
 */
function config_checkbox_select($name, $label, $current, $choices, $usekeys = true, $width = 300, $columns = 1, $autosave = false, $on_change_js = null, $hidden = false)
{
    global $lang;
    if (trim($current) != "") {
        $currentvalues = explode(",", $current);
    } else {
        $currentvalues = [];
    }
    $wrap = 0;
    ?>
    <div class="Question" id="question_<?php echo escape($name); ?>" <?php echo $hidden ? "style=\"display:none;\"" : ''; ?>>
        <label for="question<?php echo escape($name); ?>" ><?php echo escape($label)?></label>
        <?php if ($autosave) { ?>
            <div class="AutoSaveStatus">
                <span id="AutoSaveStatus-<?php echo escape($name); ?>" style="display:none;"></span>
            </div>
        <?php } ?>
     
        <table cellpadding=2 cellspacing=0>
            <tr>
                <?php
                foreach ($choices as $key => $choice) {
                    $value = $usekeys ? $key : $choice;
                    $wrap++;

                    if ($wrap > $columns) {
                        $wrap = 1;
                        ?>
                        </tr>
                        <tr>
                        <?php
                    }
                    ?>
                    <td width="1">
                        <input
                            type="checkbox"
                            name="<?php echo escape($name); ?>"
                            value="<?php echo escape($value); ?>"
                            id="<?php echo escape($name . '_' . $value); ?>"
                            <?php if ($autosave) { ?>
                                onChange="<?php echo $on_change_js; ?>AutoSaveConfigOption('<?php echo escape($name); ?>');"
                            <?php }
                            if (in_array($value, $currentvalues)) { ?>
                                checked
                            <?php } ?>
                        >
                    </td>
                    <td>
                        <label for="<?php echo escape($name . '_' . $value); ?>"><?php echo escape(i18n_get_translated($choice)); ?>&nbsp;</label>
                    </td>
                    <?php
                }
                ?>
            </tr>
        </table>
        <div class="clearerleft"></div>
    </div>
  
    <?php
}

/**
 * Return a data structure that will instruct the configuration page generator functions to
 * add a multi select configuration variable to the setup page.
 *
 * @param string $config_var the name of the configuration variable to be added.
 * @param string $label the user text displayed to label the select block. Usually a $lang string.
 * @param string array $choices the array of choices -- the options in the select block. The keys are
 *          used as the values of the options, and the values are the choices the user sees. (But see
 *          $usekeys, below.) Usually a $lang entry whose value is an array of strings.
 * @param boolean $usekeys tells whether to use the keys from $choices as the values of the options.
 *          If set to false the values from $choices will be used for both the values of the options
 *          and the text the user sees. Defaulted to true.
 * @param integer $width the width of the input field in pixels. Default: 300.
 */
function config_add_checkbox_select($config_var, $label, $choices, $usekeys = true, $width = 300, $columns = 1, $autosave = false, $on_change_js = null, $hidden = false)
{
    return array('checkbox_select', $config_var, $label, $choices, $usekeys, $width, $columns, $autosave, $on_change_js, $hidden);
}


function config_add_colouroverride_input($config_var, $label = '', $default = '', $title = '', $autosave = false, $on_change_js = null, $hidden = false)
{
    return array('colouroverride_input', $config_var, $label, $default, $title, $autosave, $on_change_js, $hidden);
}

/**
 * Return a data structure that will instruct the configuration page generator functions to
 * add a single RS field-type select configuration variable to the setup page.
 *
 * @param string $config_var the name of the configuration variable to be added.
 * @param string $label the user text displayed to label the select block. Usually a $lang string.
 * @param integer $width the width of the input field in pixels. Default: 300.
 * @param integer $rtype optional to specify a resource type to get fields for
 * @param integer array $ftypes an array of field types e.g. (4,6,10) will return only fields of a date type
 */
function config_add_single_ftype_select($config_var, $label, $width = 300, $rtype = false, $ftypes = array(), $autosave = false)
{
    return array('single_ftype_select', $config_var, $label, $width, $rtype, $ftypes,$autosave);
}

/**
 * Generate an html single-select + options block for selecting one of the RS field types. The
 * selected field type is posted as the value of the "ref" column of the selected field type.
 *
 * @param string $name the name of the select block. Usually the name of the config variable being set.
 * @param string $label the user text displayed to label the select block. Usually a $lang string.
 * @param integer $current the current value of the config variable being set
 * @param integer $width the width of the input field in pixels. Default: 300.
 */
function config_single_ftype_select($name, $label, $current, $width = 300, $rtype = false, $ftypes = array(), $autosave = false)
{
    global $lang;

    if ($rtype === false) {
        $rtype = '';
    }

    $fields = get_resource_type_fields($rtype, 'title, name', 'asc', '', $ftypes, true);
    ?>

    <div class="Question">
        <label for="<?php echo escape($name); ?>" title="<?php echo escape(str_replace('%cvn', $name, $lang['plugins-configvar'])); ?>">
            <?php echo escape($label); ?>
        </label>
    
        <?php if ($autosave) { ?>
            <div class="AutoSaveStatus">
                <span id="AutoSaveStatus-<?php echo escape($name); ?>" style="display:none;"></span>
            </div>
        <?php } ?>

        <select
            name="<?php echo escape($name); ?>"
            id="<?php echo escape($name); ?>"
            style="width:<?php echo (int) $width; ?>px"
            <?php if ($autosave) {
                ?> onChange="AutoSaveConfigOption('<?php echo escape($name); ?>');"<?php
            } ?>
        >
            <option value="" <?php echo $current == "" ? ' selected' : ''; ?>>
                <?php echo escape($lang["select"]); ?>
            </option>
            <?php foreach ($fields as $field) { ?>
                <option value="<?php echo (int) $field['ref']; ?>" <?php echo $current == $field['ref'] ? ' selected' : ''; ?>>
                    <?php echo escape(lang_or_i18n_get_translated($field['title'], 'fieldtitle-')); ?>
                </option>
            <?php } ?>
        </select>
        <div class="clearerleft"></div>
    </div>

    <?php
}

/**
* Generate Javascript function used for auto saving individual config options
*
* @param string $post_url URL to where the data will be posted
*/
function config_generate_AutoSaveConfigOption_function($post_url)
{
    global $lang;
    ?>
    
    <script>
        function AutoSaveConfigOption(option_name, reload_page = false) {
            jQuery('#AutoSaveStatus-' + option_name).html('<?php echo escape($lang["saving"]); ?>');
            jQuery('#AutoSaveStatus-' + option_name).show();

        const cast_to_digit_csv = (obj) => obj
            .map((idx, el) => jQuery(el).val())
            .get()
            .toString();
        let option_value = jQuery('#' + option_name).val();

        if (jQuery('input[name=' + option_name + ']').is(':checkbox')) {
            option_value = cast_to_digit_csv(jQuery('input[name=' + option_name + ']:checked'));
        } else if (jQuery('input[name="' + option_name + '[]"]').length > 0) {
            option_value = cast_to_digit_csv(jQuery('input[name="' + option_name + '[]"]:checked'));
        }

        var post_url  = '<?php echo $post_url; ?>';
        var post_data = {
            ajax: true,
            autosave: true,
            autosave_option_name: option_name,
            autosave_option_value: option_value,
            <?php echo generateAjaxToken($post_url); ?>
        };

            jQuery.post(post_url, post_data, function(response) {
                if (response.success === true) {
                    jQuery('#AutoSaveStatus-' + option_name).html('<?php echo escape($lang["saved"]); ?>');
                    jQuery('#AutoSaveStatus-' + option_name).fadeOut('slow');
                    if (reload_page) {
                        location.reload();
                    }
                } else if (response.success === false && response.message && response.message.length > 0) {
                    jQuery('#AutoSaveStatus-' + option_name).html("<?php echo escape($lang['save-error']); ?> " + response.message);
                } else {
                    jQuery('#AutoSaveStatus-' + option_name).html("<?php echo escape($lang['save-error']); ?>");
                }
            }, 'json');

            return true;
        }
    </script>
    
    <?php
}

function config_process_file_input(array $page_def, $file_location, $redirect_location)
{
    global $baseurl, $storagedir, $storageurl, $banned_extensions;

    $file_server_location = $storagedir . '/' . $file_location;

    // Make sure there is a target location
    if (!(file_exists($file_server_location) && is_dir($file_server_location))) {
        mkdir($file_server_location, 0777, true);
    }

    $redirect = false;

    foreach ($page_def as $page_element) {
        if ($page_element[0] !== 'file_input') {
            continue;
        }

        $config_name = $page_element[1];
        $valid_extensions = $page_element[5];

        // DELETE
        if (
            getval('delete_' . $config_name, '') !== ''
            && enforcePostRequest(false)
            && get_config_option([], $config_name, $delete_filename)
        ) {
            $delete_filename = str_replace('[storage_url]' . '/' . $file_location, $file_server_location, $delete_filename);

            if (file_exists($delete_filename)) {
                unlink($delete_filename);
                hook("configdeletefilesuccess", '', array($delete_filename));
            }

            delete_config_option([], $config_name);
            $redirect = true;
        }

        // CLEAR
        if (
            getval('clear_' . $config_name, '') !== ''
            && enforcePostRequest(false)
            && get_config_option([], $config_name, $missing_file)
        ) {
            $missing_file = str_replace('[storage_url]' . '/' . $file_location, $file_server_location, $missing_file);

            if (!file_exists($missing_file)) {
                delete_config_option([], $config_name);
                $redirect = true;
            }
        }

        // UPLOAD
        if (getval('upload_' . $config_name, '') !== '' && enforcePostRequest(false)) {
            if (isset($_FILES[$config_name]['tmp_name'])) {
                $uploaded_file_extension = parse_filename_extension($_FILES[$config_name]['name']);
                $uploaded_filename = sprintf('%s/%s.%s', $file_server_location, $config_name, $uploaded_file_extension);
                // We add a placeholder for storage_url so we can reach the file easily
                // without storing the full path in the database
                $saved_filename = sprintf('[storage_url]/%s/%s.%s', $file_location, $config_name, $uploaded_file_extension);

                $process_file_upload = process_file_upload(
                    $_FILES[$config_name],
                    new SplFileInfo($uploaded_filename),
                    ['allow_extensions' => $valid_extensions]
                );

                if (!$process_file_upload['success']) {
                    unset($uploaded_filename);
                }
            }

            if (isset($uploaded_filename) && set_config_option(null, $config_name, $saved_filename)) {
                $redirect = true;
                hook("configuploadfilesuccess", '', array($uploaded_filename));
            }
        }
    }

    if ($redirect) {
        redirect($redirect_location);
    }
}

/**
* Generates HTML foreach element found in the page definition
*
* @param array $page_def Array of all elements for which we need to generate HTML
*/
function config_generate_html(array $page_def)
{
    global $lang,$baseurl;

    foreach ($page_def as $def) {
        if (!isset($def[0])) {
            continue;
        }

        switch ($def[0]) {
            case 'html':
                config_html($def[1]);
                break;
            case 'text_input':
                config_text_input($def[1], $def[2], $GLOBALS[$def[1]], $def[3], $def[4], $def[5], $def[6], $def[7], $def[8], $def[9]);
                break;
            case 'file_input':
                config_file_input($def[1], $def[2], $GLOBALS[$def[1]], $def[3], $def[4], $def[5], $def[6]);
                break;
            case 'boolean_select':
                config_boolean_select($def[1], $def[2], $GLOBALS[$def[1]], $def[3], $def[4], $def[5], $def[6], $def[7], $def[8], $def[9], $def[10]);
                break;
            case 'single_select':
                config_single_select($def[1], $def[2], $GLOBALS[$def[1]], $def[3], $def[4], $def[5], $def[6], $def[7], $def[8], $def[9], $def[10]);
                break;
            case 'multi_select':
                config_multi_select($def[1], $def[2], $GLOBALS[$def[1]], $def[3], $def[4], $def[5]);
                break;
            case 'checkbox_select':
                config_checkbox_select($def[1], $def[2], $GLOBALS[$def[1]], $def[3], $def[4], $def[5], $def[6], $def[7], $def[8], $def[9]);
                break;
            case 'colouroverride_input':
                config_colouroverride_input($def[1], $def[2], $GLOBALS[$def[1]], $def[3], $def[4], $def[5], $def[6], $def[7]);
                break;
            case 'multi_rtype_select':
                config_multi_rtype_select($def[1], $def[2], $GLOBALS[$def[1]], $def[3]);
                break;
            case 'single_ftype_select':
                config_single_ftype_select($def[1], $def[2], $GLOBALS[$def[1]], $def[3], $def[4], $def[5], $def[6]);
                break;
            case 'multi_archive_select':
                config_multi_archive_select($def[1], $def[2], $GLOBALS[$def[1]], $def[3], $def[4]);
                break;
            case 'fixed_input':
                render_fixed_text_question($def[1], $def[2], $def[3]);
                break;
            case 'percent_range':
                config_percent_range($def[1], $def[2], $GLOBALS[$def[1]], $def[3], $def[4], $def[5]);
                break;
            default:
                break;
        }
    }
}


/**
* Merge all non image configurations
*
* @return array Returns merged array of non image configurations.
*/
function config_merge_non_image_types()
{
    global $non_image_types,$ffmpeg_supported_extensions,$unoconv_extensions,$ghostscript_extensions;

    return array_unique(
        array_map(
            'strtolower',
            array_merge(
                $non_image_types,
                $ffmpeg_supported_extensions,
                $unoconv_extensions,
                $ghostscript_extensions
            )
        )
    );
}

/**
 * Retrieves the header image URL based on the user's appearance preference and system configuration.
 *
 * @param bool $full Whether to return the full URL including the base URL.
 * @param bool $for_header Whether the image is being displayed in the header.
 * @param string $force_appearance Optionally force the appearance mode ('dark' or 'light').
 *
 * @return string The resolved URL for the header image.
 */
function get_header_image(bool $full = false, bool $for_header = false, string $force_appearance = "", bool $for_login = false): string
{
    global $linkedheaderimgsrc, $linkedheaderimgsrc_dark, $baseurl_short, $baseurl, $storageurl, $user_pref_appearance;

    $css_color_scheme = $_COOKIE['css_color_scheme'] ?? 'light';

    // Use custom header image
    if (trim($linkedheaderimgsrc) != "" || trim($linkedheaderimgsrc_dark) != "") {
        if ($force_appearance == "dark") {
            $header_img_src = trim($linkedheaderimgsrc_dark) != "" ? $linkedheaderimgsrc_dark : $linkedheaderimgsrc;
        } elseif ($force_appearance == "light") {
            $header_img_src = trim($linkedheaderimgsrc) != "" ? $linkedheaderimgsrc : $linkedheaderimgsrc_dark;
        } elseif ($user_pref_appearance == "light" && trim($linkedheaderimgsrc) != "") {
            $header_img_src = $linkedheaderimgsrc;
        } elseif ($user_pref_appearance == "dark" && trim($linkedheaderimgsrc_dark) != "" || ($user_pref_appearance == "device" && $css_color_scheme == "dark")) {
            $header_img_src = $linkedheaderimgsrc_dark;
        } else {
            $header_img_src = trim($linkedheaderimgsrc) != "" ? $linkedheaderimgsrc : $linkedheaderimgsrc_dark;
        }

        if (substr($header_img_src, 0, 4) !== 'http') {
            // Set via System Config page?
            if (substr($header_img_src, 0, 13) == '[storage_url]') {
                // Parse and replace the storage URL
                $header_img_src = str_replace('[storage_url]', $storageurl, $header_img_src);
            } else {
                // Set via config.php
                // if image source already has the baseurl short, then remove it and add it here
                if (substr($header_img_src, 0, 1) === '/') {
                    $header_img_src = substr($header_img_src, 1);
                }

                $header_img_src = $baseurl_short . $header_img_src;
            }

            if ($full && substr($header_img_src, 0, 1) === '/') {
                $header_img_src = $baseurl . substr($header_img_src, 1);
            }
        }
    } else {
        // Use default ResourceSpace logo
        if ($for_header) {
            // If displaying in header then take into account user appearance preference
            if ($force_appearance == "dark") {
                $header_img_src = $baseurl . '/gfx/titles/title.svg';
            } elseif ($force_appearance == "light") {
                $header_img_src = $baseurl . '/gfx/titles/title-black.svg';
            } elseif ($user_pref_appearance == 'dark' || ($user_pref_appearance == "device" && $css_color_scheme == "dark")) {
                $header_img_src = $baseurl . '/gfx/titles/title.svg';
            } else {
                $header_img_src = $baseurl . '/gfx/titles/title-black.svg';
            }
        } elseif ($for_login) {
            // When displaying in login return logo with white text
            $header_img_src = $baseurl . '/gfx/titles/title.svg';
        } else {
            // When displaying in other places simply return the default logo
            $header_img_src = $baseurl . '/gfx/titles/title-black.svg';
        }
    }

    return $header_img_src;
}

/**
* Used to block deletion of 'core' fields. Any variable added to the $corefields array will be checked before a field is deleted and if the field is referenced by one of these core variables the deletion will be blocked
*
* @param string $source Optional origin of variables e.g. 'Transform plugin'
* @param array $varnames Array of variable names
*
* @return void
*/
function config_register_core_fieldvars($source = "BASE", $varnames = array())
{
    global $corefields;

    if (!isset($corefields[$source])) {
        $corefields[$source] = array();
    }

    foreach ($varnames as $varname) {
        if (preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $varname)) {
            $corefields[$source][] = $varname;
        }
    }
}


/**
* Used to block deletion of 'core' fields.
*
* @param string $source What part (e.g plugin) relies on this list of metadata fields
* @param array  $refs   List of metadata field IDs to prevent being deleted
*/
function config_register_core_field_refs(string $source, array $refs)
{
    global $core_field_refs;

    $source = trim($source);
    $source = ($source !== '' ? $source : 'BASE');

    if (!isset($core_field_refs[$source])) {
        $core_field_refs[$source] = [];
    }

    foreach ($refs as $ref) {
        if (is_int_loose($ref) && $ref > 0) {
            $core_field_refs[$source][] = $ref;
        }
    }
}

/**
 * Run PHP code on array of variables. Used for modifying $GLOBALS.
 *
 * @param  array   $variables      Array of variables to apply override on.
 * @param  string  $code           Signed string containing the PHP code to run.
 * @param  string  $override_for   Context in which override is being made. Generally this will be 'usergroup' or 'resource_type' config overrides.
 *                                 This ensures we only revert config to its original state for the same origin of the override.
 *                                 For example, if applying resource type specific config, don't revert config changed at the user group level.
 *                                 This also prevents unexpected changes of config whilst pages are being loaded.
 *
 * @return void
 */
function override_rs_variables_by_eval(array $variables, string $code, string $override_for)
{
    global $configs_overwritten;

    // Remove all previous overwrides that have been set
    if (is_array($configs_overwritten) && isset($configs_overwritten[$override_for]) && count($configs_overwritten[$override_for]) != 0) {
        // We're processing config override for the same origin e.g. user group or resource type. Previously these were changed so revert them.
        foreach ($configs_overwritten[$override_for] as $option => $value) {
            if ($value === 'UNSET_override_rs_variables_by_eval') {
                unset($GLOBALS[$option]);
                unset($variables[$option]);
            } else {
                $variables[$option] = $value;
            }
        }
    }

    $temp_variables = $variables;

    // Copy keys to a new array to prevent loss of original values.
    // This is a temporary solution for PHP versions 7.4 and 8.0 where eval() is applied to the keys in both %$temp_variables and $variables;
    foreach ($variables as $variable => $var_val) {
        $original['copy_' . $variable] = $var_val;
    }

    extract($temp_variables, EXTR_REFS | EXTR_SKIP);
    $pre_eval_variables = array_keys(get_defined_vars());
    eval(eval_check_signed($code));
    $post_eval_variables = array_diff(array_keys(get_defined_vars()), $pre_eval_variables, ['pre_eval_variables']);
    foreach($post_eval_variables as $var) {
        if (!array_key_exists($var, $temp_variables)) {
            $temp_variables[$var] = $$var;
        }
    }

    $temp_array = [];
    foreach ($temp_variables as $temp_variable_name => $temp_variable_val) {
        if (!isset($original['copy_' . $temp_variable_name])) {
            $original['copy_' . $temp_variable_name] = null;
        }

        if ($original['copy_' . $temp_variable_name] !== $temp_variable_val) {
            $temp_array[$temp_variable_name] = $original['copy_' . $temp_variable_name];
            if ($original['copy_' . $temp_variable_name] === null) {
                $temp_array[$temp_variable_name] = 'UNSET_override_rs_variables_by_eval';
            }
        }
        $GLOBALS[$temp_variable_name] = $temp_variable_val;
    }

    $configs_overwritten[$override_for] = $temp_array;
}

/**
 * Update the resource_type_field - resource_type mappings
 *
 * @param int $ref                  Resource type field ref
 * @param array $resource_types     Array of resource type refs
 *
 * @return void
 *
 */
function update_resource_type_field_resource_types(int $ref, array $resource_types)
{
    ps_query("DELETE FROM resource_type_field_resource_type WHERE resource_type_field = ?", ["i",$ref]);

    if (in_array(0, $resource_types)) {
        // Global field, cannot have specific fields assigned
        ps_query("UPDATE resource_type_field SET global=1 WHERE ref = ?", ["i",$ref]);
    } elseif (count($resource_types) > 0) {
        $query = "INSERT INTO resource_type_field_resource_type (resource_type_field, resource_type) VALUES ";
        $valuestring = "(" . (int)$ref . (str_repeat(",?),(" . $ref, count($resource_types) - 1)) . ",?)";
        ps_query($query . $valuestring, ps_param_fill($resource_types, "i"));
        ps_query("UPDATE resource_type_field SET global=0 WHERE ref = ?", ["i",$ref]);
    }

    clear_query_cache("schema");
}

/**
 * Get all resource_type->resource-type_field associations
 *
 * @param array $fields Optional array of resource_type_field data returned by get_resource_type_fields()
 *
 * @return array    Array with resource_type_field ID as keys and arrays of resource_type IDs as values
 *
 */
function get_resource_type_field_resource_types(array $fields = [])
{
    $field_restypes = [];
    $allrestypes = array_column(get_resource_types("", false, true, true), "ref");

    if (count($fields) == 0) {
        $fields = get_resource_type_fields();
    }

    foreach ($fields as $field) {
        if ($field["global"] == 1) {
            $field_restypes[$field["ref"]] = $allrestypes;
        } else {
            $field_restypes[$field["ref"]] = explode(",", (string) $field["resource_types"]);
        }
    }

    return $field_restypes;
}

/**
 * Create a new resource type with the specified name
 *
 * @param $name         Name of new resouce type
 *
 * @return int| bool    ref of new resource type or false if invalid data passed
 *
 */
function create_resource_type($name)
{
    if (!checkperm('a') || trim($name) == "") {
        return false;
    }

    ps_query("INSERT INTO resource_type (name) VALUES (?) ", array("s",$name));
    $newid = sql_insert_id();
    clear_query_cache("schema");
    clear_restype_cache();
    return $newid;
}

/**
 * Save updated resource_type data
 *
 * @param int   $ref        Ref of resource type
 * @param array $savedata   Array of column values
 *
 * @return bool
 *
 */
function save_resource_type(int $ref, array $savedata)
{
    global $execution_lockout;

    $restypes = get_resource_types("", true, false, true);
    $restype_refs = array_column($restypes, "ref");

    if (!checkperm('a') || !in_array($ref, $restype_refs)) {
        return false;
    }

    $setcolumns = [];
    $setparams = [];
    $restypes = array_combine($restype_refs, $restypes);

    foreach ($savedata as $savecol => $saveval) {
        debug("checking for column " . $savecol . " in " . json_encode(($restype_refs), true));

        if ($saveval === $restypes[$ref][$savecol]) {
            // Unchanged value, skip
            continue;
        }

        switch ($savecol) {
            case "name":
                $setcolumns[] = "name";
                $setparams[] = "s";
                $setparams[] = mb_strcut($saveval, 0, 100);
                break;

            case "order_by":
            case "push_metadata":
            case "tab":
            case "colour":
            case "pull_images":
                $setcolumns[] = $savecol;
                $setparams[] = "i";
                $setparams[] = $saveval;
                break;

            case "config_options":
                if (!$execution_lockout) {
                    // Not allowed to save PHP if execution_lockout set.
                    $setcolumns[] = $savecol;
                    $setparams[] = "s";
                    $setparams[] = $saveval;
                }
                break;

            case "allowed_extensions":
                $setcolumns[] = $savecol;
                $setparams[] = "s";
                $setparams[] = $saveval;
                break;

            case "icon":
                $setcolumns[] = $savecol;
                $setparams[] = "s";
                $setparams[] = mb_strcut($saveval, 0, 120);
                break;

            default:
                // Invalid option, ignore
                break;
        }
    }

    if (count($setcolumns) === 0) {
        return false;
    }

    $setparams[] = "i";
    $setparams[] = $ref;

    ps_query(
        "UPDATE resource_type
            SET " . implode("=?,", $setcolumns) . "=?
            WHERE ref = ?",
        $setparams
    );

    for ($n = 0; $n < count($setcolumns); $n++) {
        log_activity(null, LOG_CODE_EDITED, $setparams[(2 * $n) + 1], 'resource_type', $setcolumns[$n], $ref, null, $restypes[$ref][$setcolumns[$n]]);
    }

    clear_query_cache("schema");
    clear_query_cache("resourcetypeicon");
    clear_restype_cache();
    return true;
}

/**
 * Force clear of restype_cache after editing resource type where get_resource_types() needs to pick up the new value.
 */
function clear_restype_cache(): void
{
    if (isset($GLOBALS["restype_cache"])) {
        unset($GLOBALS["restype_cache"]);
    }
}

/**
 * Get resource_type data
 *
 * @param int $ref
 *
 * @return array
 *
 */
function rs_get_resource_type(int $ref)
{
    return ps_query(
        "SELECT " . columns_in('resource_type') . "
        FROM resource_type
        WHERE ref = ?
        ORDER BY `name`",
        array("i",$ref),
        "schema"
    );
}

/**
 * Save resource type field - used on pages/admin/admin_resource_type_field_edit.php
 *
 * @param int   $ref        Field ID
 * @param array $columns    Array of column data
 * @param mixed $postdata   POST'd data
 *
 * @return bool
 *
 */
function save_resource_type_field(int $ref, array $columns, $postdata): bool
{
    global $regexp_slash_replace, $migrate_data, $onload_message, $lang, $baseurl;

    $existingfield = get_resource_type_field($ref);
    $params = [];
    $multiple_deduplicate = false;
    $resource_types = get_resource_types("", true, false, true);

    // Array of resource types to remove data from if no longer associated with field
    $remove_data_restypes = [];

    foreach ($resource_types as $resource_type) {
        $resource_type_array[$resource_type["ref"]] = $resource_type["name"];
    }

    $valid_columns = columns_in("resource_type_field", null, null, true);

    foreach ($columns as $column => $column_detail) {
        if (!in_array($column, $valid_columns)) {
            continue;
        }

        if ($column_detail[2] == 1) {
            $val = (int)(bool)($postdata[$column] ?? 0);
        } else {
            $val = trim($postdata[$column] ?? "");

            if ($column == 'regexp_filter') {
                $val = str_replace('\\', $regexp_slash_replace, $val);
            }

            if ($column == "type" && $val != $existingfield["type"] && (bool)($postdata["migrate_data"] ?? false)) {
                // Need to migrate field data
                $migrate_data = true;
            }

            if (
                $column == "type"
                && $val != $existingfield["type"]
                && $existingfield["type"] === FIELD_TYPE_CATEGORY_TREE
                && (
                    $postdata['type'] == FIELD_TYPE_CHECK_BOX_LIST
                    || $postdata['type'] == FIELD_TYPE_DYNAMIC_KEYWORDS_LIST
                )
            ) {
                // Need to deduplicate nodes if going from category tree to multiple value fixed field type
                $multiple_deduplicate = true;
            }


            // Set shortname if not already set or invalid
            if ($column == "name" && ($val == "" || in_array($val, array("basicday","basicmonth","basicyear")))) {
                $val = "field" . $ref;
            }

            if ($column === 'tab' && $val == 0) {
                $val = ''; # set to blank so the code will convert to SQL NULL later
            }
        }

        if (isset($sql)) {
            $sql .= ",";
        } else {
            $sql = "UPDATE resource_type_field SET ";
        }

        $sql .= "{$column}=";

        if ($val == "") {
            $sql .= "NULL";
        } else {
            $sql .= "?";
            $params[] = ($column_detail[2] == 1 ? "i" : "s"); // Set the type, boolean="i", other two are strings
            $params[] = $val;
        }

        if ($column == "global") {
            // Also need to update all resource_type_field -> resource_type associations
            $setrestypes = [];
            $currentrestypes = get_resource_type_field_resource_types([$existingfield]);

            if ($val == 0) {
                // Only need to check them if field is not global
                foreach ($resource_type_array as $resource_type => $resource_type_name) {
                    if (trim($postdata["field_restype_select_" . $resource_type] ?? "") != "") {
                        $setrestypes[] = $resource_type;
                    }
                }

                if (count($setrestypes) === 0) {
                    return false;
                }

                // Set to remove existing data from the resource types that had data stored
                if ($existingfield["type"] == 1) {
                    $remove_data_restypes = array_column($resource_type_array, "ref");
                } else {
                    $remove_data_restypes = array_diff($currentrestypes[$ref], $setrestypes);
                }
            }

            update_resource_type_field_resource_types($ref, $setrestypes);

            if (empty($setrestypes)) {
                $setrestypes = array_column(get_resource_types("", false, true, true), "ref");
            }

            log_activity(
                null,
                LOG_CODE_EDITED,
                implode(", ", $setrestypes),
                'resource_type_field',
                'Resource Types',
                $ref,
                null,
                implode(", ", $currentrestypes[$ref])
            );
        }

        log_activity(null, LOG_CODE_EDITED, $val, 'resource_type_field', $column, $ref);
    }

    // add field_constraint sql
    if (isset($postdata["field_constraint"]) && trim($postdata["field_constraint"]) != "") {
        $sql .= ",field_constraint=?";
        $params[] = "i";
        $params[] = (int)$postdata["field_constraint"];
    }

    // Add automatic nodes ordering if set (available only for fixed list fields - except category trees)
    $sql .= ", automatic_nodes_ordering = ?";
    $params[] = "i";
    $params[] = (1 == ($postdata['automatic_nodes_ordering'] ?? 0) ? 1 : 0);

    $sql .= " WHERE ref = ?";
    $params[] = "i";
    $params[] = $ref;

    ps_query($sql, $params);

    // Deduplicate node names if necessary
    if ($multiple_deduplicate) {
        $deduplicate_sql =
        "UPDATE node n1
            JOIN ( SELECT name, ref, ROW_NUMBER() OVER (PARTITION BY name ORDER BY ref) AS rn
                    FROM node where resource_type_field = ?
                ) n2 ON n1.ref = n2.ref
            SET n1.name = CONCAT(n2.name, '_', n2.rn - 1)
        WHERE n2.rn > 1";
        ps_query($deduplicate_sql, array("i",$ref));
    }

    clear_query_cache("schema");
    clear_query_cache("featured_collections");

    if (count($remove_data_restypes) > 0) {
        // Don't delete invalid nodes immediately in case of accidental/inadvertent change - just show a link to the cleanup page
        $cleanup_url = generateURL($baseurl . "/pages/tools/cleanup_invalid_nodes.php", ["cleanupfield" => $ref, "cleanuprestype" => implode(",", $remove_data_restypes)]);
        $onload_message = ["title" => $lang["cleanup_invalid_nodes"],"text" => str_replace("[cleanup_link]", "<br/><a href='" . $cleanup_url . "' target='_blank'>" . $lang["cleanup_invalid_nodes"] . "</a>", $lang["information_field_restype_deselect_cleanup"])];
    }

    hook('afterresourcetypefieldeditsave');

    return true;
}


/**
 * Get the definitions for resource_type_field columns, including properties and display configurations.
 *
 *
 * @return array An associative array of resource type field column definitions.
 */
function get_resource_type_field_columns()
{
    global $lang;

    $resource_type_field_column_definitions = execution_lockout_remove_resource_type_field_props([
        'active'                   => [$lang['property-field_active'],'',1,1],
        'global'                   => [$lang['property-resource_type'],'',1,0],
        'title'                    => [$lang['property-title'],'',0,1],
        'type'                     => [$lang['property-field_type'],'',0,1],
        'linked_data_field'        => [$lang['property-field_raw_edtf'],'',0,1],
        'name'                     => [$lang['property-shorthand_name'],$lang['information-shorthand_name'],0,1],
        'required'                 => [$lang['property-required'],'',1,1],
        'order_by'                 => [$lang['property-order_by'],'',0,0],
        'keywords_index'           => [$lang['property-index_this_field'],$lang["information_index_warning"] . " " . $lang['information-if_you_enable_indexing_below_and_the_field_already_contains_data-you_will_need_to_reindex_this_field'],1,1],
        'display_field'            => [$lang['property-display_field'],'',1,1],
        'full_width'               => [$lang['property-field_full_width'],'',1,1],
        'advanced_search'          => [$lang['property-enable_advanced_search'],'',1,1],
        'simple_search'            => [$lang['property-enable_simple_search'],'',1,1],
        'browse_bar'               => [$lang['field_show_in_browse_bar'],'',1,1],
        'read_only'                => [$lang['property-read_only_field'], '', 1, 1],
        'exiftool_field'           => [$lang['property-exiftool_field'],'',0,1],
        'fits_field'               => [$lang['property-fits_field'], $lang['information-fits_field'], 0, 1],
        'personal_data'            => [$lang['property-personal_data'],'',1,1],
        'hide_when_uploading'      => [$lang['property-hide_when_uploading'],'',1,1],
        'hide_when_restricted'     => [$lang['property-hide_when_restricted'],'',1,1],
        'help_text'                => [$lang['property-help_text'],'',2,1],
        'tooltip_text'             => [$lang['property-tooltip_text'],$lang['information-tooltip_text'],2,1],
        'tab'                      => [$lang['property-tab_name'], '', 0, 0],
        'partial_index'            => [$lang['property-enable_partial_indexing'],$lang['information-enable_partial_indexing'],1,1],
        'complete_index'           => [$lang['property-enable_complete_indexing'],$lang['information-enable_complete_indexing'],1,1],
        'iptc_equiv'               => [$lang['property-iptc_equiv'],'',0,1],
        'display_template'         => [$lang['property-display_template'],'',2,1],
        'display_condition'        => [$lang['property-display_condition'],$lang['information-display_condition'],2,1],
        'regexp_filter'            => [$lang['property-regexp_filter'],$lang['information-regexp_filter'],2,1],
        'smart_theme_name'         => [$lang['property-smart_theme_name'],'',0,1],
        'display_as_dropdown'      => [$lang['property-display_as_dropdown'],$lang['information-display_as_dropdown'],1,1],
        'external_user_access'     => [$lang['property-external_user_access'],'',1,1],
        'omit_when_copying'        => [$lang['property-omit_when_copying'],'',1,1],
        'include_in_csv_export'    => [$lang['property-include_in_csv_export'],'',1,1],
        'autocomplete_macro'       => [$lang['property-autocomplete_macro'],'',2,1],
        'exiftool_filter'          => [$lang['property-exiftool_filter'],'',2,1],
        'value_filter'             => [$lang['property-value_filter'],'',2,1],
        'onchange_macro'           => [$lang['property-onchange_macro'],$lang['information-onchange_macro'],2,1],
        'sort_method'              => [$lang['property-sort_method'], $lang['information-sort_method'], 0, 1],
    ]);

    $modify_resource_type_field_definitions = hook("modifyresourcetypefieldcolumns", "", array($resource_type_field_column_definitions));

    if ($modify_resource_type_field_definitions != '') {
        $resource_type_field_column_definitions = $modify_resource_type_field_definitions;
    }

    return $resource_type_field_column_definitions;
}

/**
 * Input validation helper function to determine if the $contact_sheet_preview_size config is valid.
 */
function is_valid_contact_sheet_preview_size(string $val): bool
{
    return preg_match('/^\d+x\d+$/', $val);
}

/**
 * Store a config option that has been removed from the UI so that it will still work until it has been set in config file
 *
 * @param string $option
 *
 * @return bool Returns true if option been saved, false if not
 *
 */
function save_removed_ui_config($option): bool
{
    if (isset($GLOBALS[$option]) && trim($GLOBALS[$option]) !== "") {
        // This has been removed from the UI - set to a config file option if it was previously set
        set_config_option(null, $option . "_REMOVED", $GLOBALS[$option]);
        return true;
    }
    return false;
}

/**
 * Check if there is a previously stored value for the given config option.
 * Intended for options that were previously set in the UI but now need to be set in config.
 *
 * @param string    $option
 * @param mixed     $default The default value to set for the config option if not set anywhere
 *
 * @return bool     True if the config option is overridden with a stored value from user_preferences, false if not
 *
 */
function check_removed_ui_config(string $option, $default = ""): bool
{
    if (!isset($GLOBALS[$option])) {
        $GLOBALS[$option] = $GLOBALS[$option . "_REMOVED"] ?? $default;
        return true;
    }
    return false;
}

/**
 * Return a data structure that will instruct the configuration page generator functions to
 * add a fixed input setting to the setup page. Uses render_fixed_text_question(). Useful for options removed from the UI
 *
 * @param string    $label      The user text displayed to label the text block. Usually a $lang string.
 * @param string    $value      The fixed text value to be displayed
 * @param string    $helptext   Optional text that will be displayed below the question
 *
 * @return array   Array of data that will be passed to the page generation code
 */
function config_add_fixed_input(string $label, string $value, string $helptext = ""): array
{
    return array('fixed_input', $label, $value, $helptext);
}

/**
 * Add config option search to user preferences / system configuration page.
 * Also requires config_filter_by_search() to process results. For examples, see the above pages.
 *
 * @param  string  $filter          Value from getval("filter", "")
 * @param  string  $only_modified   Value from getval("only_modified", "no")
 */
function render_config_filter_by_search(string $filter, string $only_modified): void
{
    global $lang;

    $only_modified = $only_modified == 'yes';
    $searching = $filter != "" || $only_modified;

    if (!$searching) {
        $filter = "";
    }

    ?>
    <form id="SearchSystemPages" class="inline_config_search" method="post" onsubmit="return CentralSpacePost(this);">
        <?php generateFormToken("system_config_search"); ?>
        <div>
            <input type="text" name="filter" id="configsearch" value="<?php echo escape($filter); ?>">
            <input type="submit" name="searching" value="<?php echo escape($lang["searchbutton"]); ?>">
            <?php if ($searching) { ?>
                <input type="button" name="clear_search" value="<?php echo escape($lang["clearbutton"]); ?>" onclick="jQuery('#configsearch').val(''); jQuery('#only_modified').prop('checked', false); CentralSpacePost(document.getElementById('SearchSystemPages'));">
            <?php } ?>
        </div>
        <div>
            <input type="checkbox" name="only_modified" id="only_modified" value="yes" <?php echo $only_modified ? 'checked="checked"' : ''; ?>>
            <label for="only_modified"><?php echo escape($lang["systemconfig_only_show_modified"]); ?></label>
        </div>
    </form>
    <?php
}

/**
 * Process values from config search fields, see render_config_filter_by_search()
 * Filter $page_def elements to show only those searched for.
 *
 * @param  array     $page_def        Array containing page definition, from functions config_add_ ...
 * @param  array     $config_type     Array representing the type of config to check:
 *                                    array() - empty array for system config
 *                                    array('user' => 1) - key 'user' and int representing user ref to check user preferences
 *                                    array('usergroup' => 2) - key 'usergroup' and int representing usergroup ref to check user group preferences
 * @param  string    $find            Value from getval("find", "")
 * @param  string    $only_modified   Value from getval("only_modified", "no")
 */
function config_filter_by_search(array $page_def, array $config_type, string $find, string $only_modified): array
{
    $only_modified = $only_modified == 'yes';
    $searching = $find != "" || $only_modified;

    if ($searching) {
        // Check for search phrase in config. description.
        if ($find !== '') {
            $search_matches = array();
            foreach ($page_def as $config_to_check) {
                if (isset($config_to_check[2]) && stripos($config_to_check[2], $find) !== false) {
                    $search_matches[] = $config_to_check;
                }
            }
            $page_def = $search_matches;
        }
        // Filter results to only config which has been changed previously i.e. exists in user_preferences with null in user column.
        if ($only_modified) {
            $search_matches = array();
            $returned_options = array();
            get_config_options($config_type, $returned_options);
            $returned_options = array_column($returned_options, 'parameter');
            foreach ($page_def as $config_to_check) {
                if (isset($config_to_check[1]) && in_array($config_to_check[1], $returned_options)) {
                    $search_matches[] = $config_to_check;
                }
            }
            $page_def = $search_matches;
        }
    }

    return $page_def;
}

/**
 * Remove user preferences by resetting to system preferences. Used to display system config or user group config
 * without the current user's preferences changing the values.
 *
 * @param  array   $page_def   Array containing page definition, from functions config_add_ ...
 */
function config_remove_user_preferences(array $page_def): void
{
    global $system_wide_config_options;

    // loop through every field about to be created
    foreach ($page_def as $page_item) {
        if (
            $page_item[0] !== 'html'
            && isset($system_wide_config_options[$page_item[1]])
            && $GLOBALS[$page_item[1]] !== $system_wide_config_options[$page_item[1]]
        ) {
            // Set the global variable back to the system value if it is different
            $GLOBALS[$page_item[1]] = $system_wide_config_options[$page_item[1]];
        }
    }
}

/**
 * Generate a percentage range input
 *
 * @param string    $name       The name of the configuration variable to be added.
 * @param string    $label      The user text displayed to label the text block. Usually a $lang string.
 * @param string    $current    The current value of the config variable being set.
 * @param int       $width      The width of the input field in pixels. Default: 420.
 * @param string    $title      The title attribute of the element
 * @param string    $help_link  Help link to be displayed alongside the label.
 */
function config_percent_range($name, $label, $current, $width = 420, $title = null, $help_link = "")
{
    global $lang;

    if (is_null($title)) {
        $title = str_replace('%cvn', $name, $lang['plugins-configvar']);
    }

    // Convert stored 0–1 value to percentage for display
    $percent = round($current * 100);
    $escaped_name = escape($name);
    ?>

    <div class="Question" id="question_<?php echo $escaped_name; ?>">
        <label for="<?php echo $escaped_name; ?>" title="<?php echo escape($title); ?>">
            <?php
            echo strip_tags_and_attributes($label, ['a'], ['href', 'target']);
            if ($help_link !== "") {
                render_help_link($help_link);
            }
            ?>
        </label>

        <!-- Range slider (dummy input for user interaction) -->
        <input
            type="range"
            id="<?php echo $escaped_name; ?>_range"
            min="1" max="100"
            value="<?php echo $percent; ?>"
            style="width:<?php echo (int) $width; ?>px"
            oninput="update_<?php echo $escaped_name; ?>(this.value)"
        />

        <!-- Live display of percentage -->
        <span id="<?php echo $escaped_name; ?>_display"><?php echo $percent; ?>%</span>

        <!-- Actual value (0–1) for form submission -->
        <input
            type="hidden"
            id="<?php echo $escaped_name; ?>"
            name="<?php echo $escaped_name; ?>"
            value="<?php echo escape((string) $current); ?>"
        />

        <script>
            function update_<?php echo $escaped_name; ?>(val) {
                // Update displayed percent
                document.getElementById('<?php echo $escaped_name; ?>_display').textContent = val + '%';
                // Update hidden 0–1 value
                document.getElementById('<?php echo $escaped_name; ?>').value = (val / 100).toFixed(2);
            }
        </script>

        <div class="clearerleft"></div>
    </div>
    <?php
}

/**
 * Return a data structure that will instruct the configuration page generator functions to
 * add a text entry configuration variable to the setup page.
 *
 * @param string    $config_var The name of the configuration variable to be added.
 * @param string    $label      The user text displayed to label the text block. Usually a $lang string.
 * @param bool      $password   Whether this is a "normal" text-entry field or a password-style field.
 * @param int       $width      The width of the input field in pixels. Default: 420.
 * @param bool      $textarea   Render as a HTML <textarea>
 * @param string    $title      The title attribute of the element
 * @param bool      $autosave   Enable auto-saving of changes when focus is lost
 * @param bool      $hidden     Whether field is hidden on the page
 * @param string    $help_link  Help link to be displayed alongside the label.
 */
function config_add_percent_range($config_var, $label, $width = 380, $title = null, string $help_link = "")
{
    return array('percent_range', $config_var, $label, $width, $title, $help_link);
}