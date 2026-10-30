<?php

include "../../include/boot.php";
include "../../include/api_functions.php";
include_once "../../include/user_api_session_functions.php";
if (!headers_sent()) {
    // Keep the same-origin consent POST's Origin header for the bootstrap CSRF check.
    header('Referrer-Policy: same-origin');
}
include "../../include/authenticate.php";

$system = getval("system", "");
$remote_system = get_api_session_destination($system, getval('url', ''));

if ($remote_system) {
    $callback_url = $remote_system['url'];
    $state = getval($remote_system["stateparam"], "");
    $code_challenge = getval('code_challenge', '');
    $code_challenge_method = getval('code_challenge_method', '');
    if ($remote_system['delivery'] === 'redirect_code') {
        // generateURL() expects query parameters separately from the URL in this revision.
        $callback_parts = explode('?', $callback_url, 2);
        $callback_params = [];
        parse_str($callback_parts[1] ?? '', $callback_params);
        unset($callback_params['code'], $callback_params['error']);
        if (
            !$enable_remote_apis
            || $state === '' || strlen($state) > 1024
            || $code_challenge_method !== 'S256'
            || !preg_match('/\A[A-Za-z0-9_-]{43}\z/', $code_challenge)
        ) {
            $message = $lang['user_api_session_invalid_request'];
        } elseif (isset($_POST['submit']) || isset($_POST['cancel'])) {
            if (!enforcePostRequest(false) || !isValidCSRFToken(getval($CSRF_token_identifier, ''), $usersession)) {
                http_response_code(400);
                $message = $lang['error-csrf-verification-failed'];
            } elseif (isset($_POST['cancel'])) {
                header('Referrer-Policy: no-referrer');
                header('Location: ' . generateURL($callback_parts[0], $callback_params, [
                    'error' => 'access_denied', $remote_system['stateparam'] => $state,
                ]), true, 303);
                exit();
            } else {
                $code = create_api_session_code($userref, $system, $callback_url, $code_challenge, $usersession);
                if ($code === false) {
                    $message = $lang['user_api_session_invalid_request'];
                } else {
                    header('Referrer-Policy: no-referrer');
                    header('Location: ' . generateURL($callback_parts[0], $callback_params, [
                        'code' => $code, $remote_system['stateparam'] => $state,
                    ]), true, 303);
                    exit();
                }
            }
        }
    } elseif (isset($_POST['submit']) && enforcePostRequest(false)) {
        // Send session key to remote system with the passed state string
        $params = [
            $remote_system["stateparam"] => $state,
            "sessionkey" => get_session_api_key($userref),
        ];

        // Map potential query keys to their values
        $available = [
            "username" => $username,
            "email"    => $useremail,
            "fullname" => $userfullname,
        ];

        $querydata = $remote_system["querydata"];

        // Only include keys present in $querydata
        foreach ($querydata as $key) {
            if (isset($available[$key])) {
                $params[$key] = $available[$key];
            }
        }

        $postdata = http_build_query($params);

        $curl = curl_init($callback_url);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $curl_response = curl_exec($curl);
        $cerror = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        unset($curl);

        $response_json = json_decode($curl_response, true);
        if ($cerror == 200) {
            // Support redirect URL in JSON response
            if (isset($response_json["redirectUrl"])) {
                // Note: Cannot use redirect() function which is for redirecting to ResourceSpace URLs only.
                header("Location: " . $response_json["redirectUrl"], true, 303);
                exit();
            }

            $message = $lang["user_api_session_grant_success"] . " " . ($response_json["message"] ?? $curl_response);
        } else {
            $message = $lang["user_api_session_grant_error"] . " "
                . ($response_json["message"] ?? $curl_response) . " HTTP code: " . $cerror;
        }
    }
} else {
    $message = $lang["user_api_session_invalid_system"];
}

include "../../include/header.php";
?>
<div class="BasicsBox">

    <h1><?php echo escape($lang["user_api_session_title"]); ?></h1>
    <?php if (isset($message)) { ?>
        <div class='PageInformal'><?php echo escape($message); ?></div>
        <?php
    } elseif ($remote_system) { ?>
        <p>
            <?php echo escape(str_replace(
                ["[system]","[applicationname]"],
                [$remote_system["name"],$applicationname],
                $lang["user_api_session_text"]
            )); ?>
        </p>

        <ul>
            <?php foreach ($remote_system["querydata"] as $key) { ?>
                <li><?php echo escape($lang[$key] ?? $key); ?></li>
            <?php } ?>
        </ul>

        <form
            method="post" 
            action="<?php echo escape(generateURL(
                "{$baseurl_short}pages/user/user_api_session.php",
                ['system' => $system]
            )); ?>">
            <input type="hidden"
                name="<?php echo escape($remote_system['stateparam']); ?>"
                value="<?php echo escape($state); ?>">
            <input type="hidden" name="system" value="<?php echo escape($system); ?>">
            <input type="hidden" name="url" value="<?php echo escape($callback_url); ?>">
            <input type="hidden" name="submit" value="true">
            <?php if ($remote_system['delivery'] === 'redirect_code') { ?>
                <input type="hidden" name="code_challenge" value="<?php echo escape($code_challenge); ?>">
                <input type="hidden" name="code_challenge_method" value="S256">
            <?php } ?>
            <?php
            generateFormToken("user_api_session");
            ?>
            <div class="QuestionSubmit">
                <input name="save"
                    type="submit" 
                    value="<?php echo escape($lang["user_api_session_grant_access"]); ?>"
                    />
                <?php if ($remote_system['delivery'] === 'redirect_code') { ?>
                    <button type="submit" name="cancel" value="true"><?php echo escape($lang['cancel']); ?></button>
                <?php } ?>
                <div class="clearerleft"> </div>
            </div>
        </form>
        <?php
    }
    ?>
</div>

<?php
include "../../include/footer.php";
