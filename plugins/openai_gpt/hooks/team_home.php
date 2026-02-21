<?php

function HookOpenai_gptTeam_homeCustomteamfunctionadmin()
{
    global $lang, $openai_gpt_token_limit, $openai_gpt_token_limit_days;

    if ($openai_gpt_token_limit > 0 && $openai_gpt_token_limit_days > 0) {

        $tokens_used = openai_gpt_get_tokens_used($openai_gpt_token_limit_days);

        if ($tokens_used > $openai_gpt_token_limit) {
            $tokens_alert = "<p>" . escape($lang["openai_gpt_limit_warning_short"]) . "</p>";
            echo $tokens_alert;
        }
    }

    return false;
}