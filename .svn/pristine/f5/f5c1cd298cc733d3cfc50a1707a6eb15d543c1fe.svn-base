<?php

/**
 * Import users from uploaded CSV file or validate file for future use
 *
 * mandatory_columns in csv: 'username', 'email'
 * optional_columns in csv: 'password', 'fullname', 'account_expires', 'comments', 'ip_restrict', 'lang'
 *
 * @param  string   $csv_file       Filepath to input file
 * @param  string   $user_group_id  Usergroup reference to add new users to
 * @param  array    $messages       Array to store errors or warnings
 * @param  bool     $processcsv     if true process file else validate file only
 * @return bool     Return true if file processed of validated successfully, false if unable to process or validate file
 */
function csv_user_import_process($csv_file, $user_group_id, &$messages, $processcsv = false)
{
    global $defaultlanguage, $languages;

    $mandatory_columns = array('username', 'email');
    $possible_columns = ps_query("describe user", array());
    $possible_columns = array_column($possible_columns, "Field");

    $default_columns_values = array(
        'lang' => $defaultlanguage
    );

    $file = fopen($csv_file, 'r');

    // Manipulate headers
    $headers = fgetcsv($file);
    if (!$headers) {
        array_push($messages, 'No header found');
        fclose($file);

        return false;
    }

    for ($i = 0; $i < count($headers); $i++) {
        $headers[$i] = mb_strtolower(trim($headers[$i], "\xEF\xBB\xBF "));
    }

    // Check header columns
    $header_check_valid = true;
    $mandatory_columns_not_found = array_diff($mandatory_columns, $headers);
    foreach ($mandatory_columns_not_found as $column_header) {
        array_push($messages, 'Error: Could not find mandatory column "' . $column_header . '"');
        $header_check_valid = false;
    }

    // Validation of column headers in csv - only allow those matching table column names.
    $unknown_columns = array_diff($headers, $possible_columns);
    foreach ($unknown_columns as $column_header) {
        array_push($messages, 'Info: ResourceSpace has no use (ie. unknown) for column "' . $column_header . '" and as such it will not be taken into account');
    }

    // No point to continue since headers are not right
    if (!$header_check_valid) {
        fclose($file);
        return false;
    }

    $line_count      = 0;
    $error_count     = 0;
    $max_error_count = 100;

    array_push($messages, '### Processing ' . count($headers) . ' columns ###');

    while (( false !== ($line = fgetcsv($file)) ) && $error_count < $max_error_count) {
        $line_count++;
        if (array_filter(array_map('trim', $line)) == []) {
            continue;
        }
        // Check that the current row has the correct number of columns
        if (!$processcsv && count($line) !== count($headers)) {
            array_push($messages, 'Error: Incorrect number of columns( ' . count($line) . ') found on line ' . $line_count . '. It should be ' . count($headers));
            $error_count++;

            continue;
        }

        $sql_update_col_val_pair = "`usergroup` = ?";
        $parameters = array("i",$user_group_id);
        $cell_count = -1;
        $user_creation_data = array();

        foreach ($headers as $header) {
            $cell_count++;
            $cell_value = trim($line[$cell_count]);

            if (in_array($header, $unknown_columns)) {
                // Ignore this column;
                continue;
            }

            // Make sure mandatory fields have a value
            if (in_array($header, $mandatory_columns) && '' === $cell_value) {
                array_push($messages, 'Error: Mandatory column "' . $header . '" cannot be empty on line ' . $line_count);
                $error_count++;

                continue;
            }

            if ('username' === $header || 'email' === $header) {
                $check = ps_value("SELECT count(*) AS value FROM user WHERE `{$header}` = ?", array("s",$cell_value), 0);
                if (0 < $check) {
                    array_push($messages, ucfirst($header) . ' "' . $cell_value . '" exists already in ResourceSpace');
                    $error_count++;

                    continue;
                }
            }

            if ('password' === $header && '' != $cell_value) {
                $password_message = check_password($cell_value);
                if ($password_message !== true) {
                    array_push($messages, 'Line ' . ($line_count + 1) . ': ' . $password_message);
                    $error_count++;

                    continue;
                }
            }

            if ('account_expires' === $header && '' != $cell_value) {
                $expires = $cell_value;
                if ($expires != "" && (preg_match("/^\d{4}-\d{2}-\d{2}$/", $expires) === 0 || strtotime($expires) === false)) {
                    array_push($messages, ucfirst($header) .  ' "' .$expires . '" is not a valid date');
                    $error_count++;
                }
            }

            if('ip_restrict' === $header && '' != $cell_value) {
                $ip_restrict = $cell_value;
                $ip_valid = false;

                if (strpos($ip_restrict, '.') !== false) {
                    if (!preg_match('/[0-9\*]\*|\*[0-9\*.]/', $ip_restrict)) {
                        if (substr_count($ip_restrict, '.') === 1) {
                            $ip_append = '0.0.0';
                        } elseif (substr_count($ip_restrict, '.') === 2) {
                            $ip_append = '0.0';
                        } else {
                            $ip_append = '0';
                        }
                        $ip_check = str_replace('*', $ip_append, $ip_restrict);
                        if (filter_var($ip_check, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                            $ip_valid = true;
                        }
                    }
                }
                if (strpos($ip_restrict, ':') !== false) {
                    if (!preg_match('/[0-9a-fA-F\*]\*|\*[0-9a-fA-F\*:]/', $ip_restrict)) {
                        if ((substr_count($ip_restrict, '::') === 1) || (substr_count($ip_restrict, ':') === 7)) {
                            $ip_append = '0';
                        } else {
                            $ip_append = ':0';
                        }
                        $ip_check = str_replace('*', $ip_append, $ip_restrict);
                        if (filter_var($ip_check, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) {
                            $ip_valid = true;
                        }
                    }
                }
                if (!$ip_valid) {
                    array_push($messages, ucfirst($header) . ' "' .$ip_restrict . '" is not a valid IP');
                    $error_count++;
                }
            }

            if ('lang' === $header && '' != $cell_value) {
                $lang = $cell_value;
                if (!array_key_exists($lang, $languages)) {
                    array_push($messages, ucfirst($header) . ' "' .$lang . '" is not a valid language ID');
                    $error_count++;
                }
            }
            $user_creation_data[$header] = $cell_value;
        }

        if ($processcsv && 0 === $error_count) {
            // Create new user if we can process it and don't have any errors
            $new_user_id = new_user($user_creation_data['username']);

            if ($new_user_id === -2) {
                array_push($messages, 'Error: User(s) could not be imported: user limit exceeded');
                return false;
            }
            
            array_push($messages, 'Info: Created new user "' . $user_creation_data['username'] . '" with ID "' . $new_user_id . '"');

            foreach ($user_creation_data as $key => $value) {
                $sql_update_col_val_pair .= ", `" . $key . "` = ";
                if ($value === '' && array_key_exists($key, $default_columns_values)) {
                    $sql_update_col_val_pair .= "?";
                    $parameters = array_merge($parameters, array("s",$default_columns_values[$key]));
                } elseif ($key === 'password' && $value != '') {
                    $sql_update_col_val_pair .= "'" . hash('sha256', md5('RS' . $user_creation_data['username'] . $value)) . "'";
                } elseif ($value === '') {
                    $sql_update_col_val_pair .= 'NULL';
                } else {
                    $sql_update_col_val_pair .= "?";
                    $parameters = array_merge($parameters, array("s",$value));
                }
            }

            $reset_password_email_required = false;
            if (!isset($user_creation_data['password']) || $user_creation_data['password'] === '') {
                $sql_update_col_val_pair .= ", password = '" . hash('sha256', md5('RS' . $user_creation_data['username'] . make_password())) . "'";
                $reset_password_email_required = true;
            }

            // Update record
            $update_query = "UPDATE `user` SET {$sql_update_col_val_pair} WHERE `ref` = ?";
            $parameters = array_merge($parameters, array("i",$new_user_id));
            ps_query($update_query, $parameters);
            if ($reset_password_email_required === true) {
                email_reset_link($user_creation_data['email'], true);
            }
        }
    } /* end of reading each line found */

    fclose($file);

    if (!$processcsv && $line_count === 0) {
        array_push($messages, 'Error: No lines of data found in the uploaded file');

        return false;
    }

    // Consider removing if not much is going on through each line
    if (!$processcsv && 0 < $error_count) {
        array_push($messages, 'Warning: Script has found ' . $error_count . ' error(s)!');

        return false;
    }

    if (!$processcsv) {
        array_push($messages, 'Info: data successfully validated!');
    } else {
        array_push($messages, 'Info: data successfully processed!');
    }

    return true;
}
