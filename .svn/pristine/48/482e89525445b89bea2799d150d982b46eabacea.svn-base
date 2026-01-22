<?php

function HookApi_webhooksEditExtra_edit_buttons()
{
    // Add configured buttons as appropriate.
    global $api_webhooks_urls,$multiple;
    if (!isset($api_webhooks_urls)) {
        return false;
    }
    if ($multiple) {
        return false;
    } // Not for batch edit
    ?>
    <input type="hidden" name="api_webhooks_submitted_button" id="api_webhooks_submitted_button" value="" />
    <?php
    $counter = 0;
    foreach ($api_webhooks_urls as $url) {
        ?>
        <input  name="save"
        class="editsave APIWebhooksEditButton"
        type="submit"
        value="&nbsp;&nbsp;&#8634;&nbsp;<?php echo escape(i18n_get_translated($url["buttontext"])); ?>&nbsp;&nbsp;"
        onclick="document.getElementById('api_webhooks_submitted_button').value=<?php echo $counter ?>;"
        />
        <?php
        $counter++;
    }
}

function HookApi_webhooksEditRedirectaftersave()
{
    // Process the saved form and access the remote script.
    global $api_webhooks_urls, $ref;
    if (!isset($api_webhooks_urls)) {
        return false;
    }

    // Fetch appropriate button
    $button_index = getval("api_webhooks_submitted_button", "");
    if ($button_index === "") {
        return false;
    } // No button pressed? Redirect to view page as normal.
    $button = $api_webhooks_urls[$button_index];

    // Perform API call.
    $url = $button["url"] . $ref;
    $options = array(
                'http' => array(
                        'header'  => "Content-type: application/x-www-form-urlencoded\r\n"
                                   . "Content-Length: " . strlen($ref) . "\r\n",
                        'content' => $ref,
                        'method'  => 'POST',
                        'ignore_errors' => true
                ),
    );

    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    // Handle any errors.
    if (strpos($http_response_header[0], "200 OK") === false) {
        ?>
        <script>alert('Error - POST to <?php echo escape($url) ?> returned <?php echo escape($http_response_header[0]) ?>');</script>
        <?php
    }

    // Clear posted values so user's changes don't overwrite the data coming back in.
    $_POST = array();

    return true;
}

function HookApi_webhooksEditUploadreviewabortnext()
{
    global $api_webhooks_urls;
    if (!isset($api_webhooks_urls)) {
        return false;
    }

    // Don't move to the next resource when an API button has been pressed.
    $button_index = getval("api_webhooks_submitted_button", "");
    if ($button_index !== "") {
        return true;
    }
}

function HookApi_webhooksEditRedirectaftersavetemplate()
{
    return HookApi_webhooksEditRedirectaftersave();
}
