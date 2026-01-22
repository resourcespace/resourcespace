<?php

/** Retrieve the current config for the feedback plugin, if the plugin is being used for the first time the default config file
 *  is loaded and added to the database
 *
 *  @param string $dir the directory for the default config file
 *
 *  @return array config array containing the promopt text and question data
 */
function get_feedback_config($dir = '')
{
    global $feedback_prompt_text, $feedback_questions;
    $config = get_plugin_config('feedback');

    if (empty($config)) {
        if (file_exists($dir)) {
            include $dir;
        }
        $config = ['prompt_text' => $feedback_prompt_text, 'questions' => $feedback_questions];
        set_plugin_config('feedback', $config);
        update_feedback_fields($feedback_questions);
    }

    return $config;
}

function make_new_results_file($storagedir)
{
    $filename = get_feedback_results_file($storagedir . '/feedback/', 'results', false);

    if (file_exists($storagedir . '/feedback/' . $filename) && filesize($storagedir . '/feedback/' . $filename) !== 0) {
        rename($storagedir . '/feedback/' . $filename, $storagedir . '/feedback/' . get_feedback_results_file($storagedir . '/feedback/', 'results'));
        touch($storagedir  . '/feedback/' . $filename);
        chmod($storagedir  . '/feedback/' . $filename, 0777);
    }
}

function get_feedback_results_file($dir, $basename, $create_new_file = true)
{
    global $scramble_key;

    srand((int)$scramble_key);
    $n = 0;
    $filename = md5(str_shuffle($basename) . $n);

    while (file_exists($dir . $filename . '.csv') && $create_new_file === true) {
        $n++;
        $filename = md5(str_shuffle($basename) . $n);
    }

    srand();

    return $filename . '.csv';
}

/** This function is used to save data from the feedback plugin back to the database
 *
 *  @param array $data an associative array of data that will be entered into the database where the key of each
 *  element is the field name for that element
 *
 */
function save_feedback_data(array $data)
{
    ['date' => $date, 'user' => $user] = array_intersect_key($data, ['user' => null, 'date' => date('Y-m-d')]);
    unset($data['user'], $data['date']);

    $offset = ps_value('SELECT MIN(id) `value` FROM feedback_fields WHERE version = (SELECT MAX(version) FROM feedback_fields) ORDER BY VERSION DESC LIMIT 1', array(), '1');
    $n      = 0;

    foreach ($data as $datum) {
        $type = ps_value('SELECT type AS value FROM feedback_fields WHERE id = ?', array("i",$offset + $n), '');
        while ($type == 4) {
            $n++;
            # This is to skip fields that are marked as lables so that the question ids match up correctly
            $type = ps_value('SELECT type AS value FROM feedback_fields WHERE id = ?', array("i",$offset + $n), '');
        }
        ps_query(
            'INSERT INTO feedback_data (field_id, value, date, user) VALUES(?, ?, ?, ?)',
            [
                'i',$offset + $n,
                's',$datum,
                's',$date,
                's',$user
            ]
        );
        $n++;
    }
}

/** Function used to save the fields configured to the database
 *
 *  @param array $fielddata the array of fields to save to the database
 */
function update_feedback_fields(array $fielddata)
{
    if (is_array($fielddata)) {
        $version = ps_value('SELECT (IFNULL(MAX(version), 0) + 1) as value FROM feedback_fields', array(), '');
        if ($version !== '') {
            foreach ($fielddata as $data) {
                ps_query('INSERT INTO feedback_fields (version, text, type, options) VALUES (?, ?, ?, ?)', ['i', $version, 's', $data['text'], 'i', $data['type'], 's', $data['options']]);
            }
        }
    }
}
