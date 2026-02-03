<?php

include "../../include/boot.php";
include "../../include/api_functions.php";
include "../../include/authenticate.php";

$system = getval("system", "");
$remote_system = API_ISSUE_VALID_DESTINATIONS[$system] ?? false;

if ($remote_system) {
    $state = getval($remote_system["stateparam"], "");
    if (isset($_POST['submit']) && enforcePostRequest(false)) {
        // Send session key to remote system with the passed state string
        $postdata = http_build_query([
            $remote_system["stateparam"] => $state,
            "username" => $username,
            "sessionkey" => get_session_api_key($userref),
        ]);

        $curl = curl_init($remote_system["url"]);
        curl_setopt($curl, CURLOPT_HEADER, "Content-Type: application/x-www-form-urlencoded");
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $curl_response = curl_exec($curl);
        $cerror = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        unset($curl);

        $response_json = json_decode($curl_response, true);
        if ($cerror == 200) {
            $message = $lang["user_api_session_grant_success"] . " " . ($response_json["message"] ?? $curl_response);
        } else {
            $message = $lang["user_api_session_grant_error"] . " " . ($response_json["message"] ?? $curl_response) . " HTTP code: " . $cerror;
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

        <form
            method="post" 
            action="<?php echo generateURL("{$baseurl_short}pages/user/user_api_session.php", ['system' => $system]); ?>"
            onsubmit="return CentralSpacePost(this,true);">        
            <input type="hidden" name="state" value="<?php echo escape($state); ?>">
            <input type="hidden" name="system" value="<?php echo escape($system); ?>">
            <input type="hidden" name="submit" value="true">
            <?php
            generateFormToken("user_api_session");
            ?>
            <div class="QuestionSubmit">
                <input name="save"
                    type="submit" 
                    value="<?php echo escape($lang["user_api_session_grant_access"]); ?>"
                    />
                <div class="clearerleft"> </div>
            </div>
        </form>
        <?php
    }
    ?>
</div>

<?php
include "../../include/footer.php";
