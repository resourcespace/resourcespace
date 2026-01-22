<?php

/**
 * Return total successful image edits for the past 30 days.
 *
 * @return  array   Array of data for processing in get_system_status().
 */
function HookOpenai_image_editAllExtra_checks() : array
    {
    $message['openai_image_edit'] = [
        'status' => 'OK',
        'info' => daily_stat_past_month_by_activity('OpenAI Image Edit')
        ];
    return $message;
    }