<?php

use Google\Service\YouTube;
use Google\Service\YouTube\Video;
use Google\Service\YouTube\VideoSnippet;
use Google\Service\YouTube\VideoStatus;

function youtube_publish_initialize()
{
    global $ref, $userref, $baseurl, $youtube_publish_client_id, $youtube_publish_client_secret,$language, $client,$youtube;

    $client = new Google_Client();
    $client->setClientId($youtube_publish_client_id);
    $client->setClientSecret($youtube_publish_client_secret);

    // Scope description: "See, edit, and permanently delete your YouTube videos, ratings, comments and captions"
    $client->setScopes(Google_Service_YouTube::YOUTUBE_FORCE_SSL);
    $client->setRedirectUri("{$baseurl}/plugins/youtube_publish/pages/youtube_upload.php");

    // Offline access will give you both an access and refresh token so that your app can refresh the access token
    // without user interaction.
    $client->setAccessType('offline');
    $client->setApprovalPrompt('force');

    $access_tokens = ps_query("SELECT youtube_access_token,youtube_refresh_token FROM user WHERE ref = ?", array("i", $userref));
    $access_token = (string) $access_tokens[0]["youtube_access_token"];
    $refresh_token = (string) $access_tokens[0]["youtube_refresh_token"];
    $GLOBALS["use_error_exception"] = true;

    if (trim($access_token) == "" ||  trim($refresh_token) == "") {
        if (getval("code", "") == "") {
            get_youtube_authorization_code();
            exit();
        } else {
            global $youtube_publish_client_id, $youtube_publish_client_secret;

            $authresponse = $client->fetchAccessTokenWithAuthCode(getval("code", ""));

            $access_token = $authresponse['access_token'];
            if (isset($authresponse['refresh_token'])) {
                $refresh_token = $authresponse['refresh_token'];
                debug("YouTube plugin: Refresh token: " . $refresh_token);
                ps_query("UPDATE user SET youtube_refresh_token = ? WHERE ref = ?", array("s", $refresh_token, "i", $userref));
            } else {
                delete_youtube_tokens();
                get_youtube_authorization_code();
                exit();
            }
            debug("YouTube plugin: Retrieved access token: " . $access_token);
            ps_query("UPDATE user SET youtube_access_token = ? WHERE ref = ?", array("s", $access_token, "i", $userref));
        }
    }

    try {
        $client->setAccessToken(json_encode(array("access_token" => $access_token)));
    } catch (Google_Service_Exception $e) {
        $errortext = sprintf(
            '<p>A service error occurred: <code>%s</code></p>',
            escape($e->getMessage())
        );
    } catch (Google_Exception $e) {
        $errortext = sprintf(
            '<p>A client error occurred: <code>%s</code></p>',
            escape($e->getMessage())
        );
    }

    if ($client->isAccessTokenExpired()) {
        try {
            $client->refreshToken($refresh_token);
        } catch (Google_Service_Exception $e) {
            delete_youtube_tokens();
            get_youtube_authorization_code();
            exit();
        } catch (Google_Exception $e) {
            delete_youtube_tokens();
            get_youtube_authorization_code();
            exit();
        }
    }

    // Define an object that will be used to make all API requests.
    try {
        $youtube = new YouTube($client);
        # Get user account details and store these so we can tell which account they will be uploading to

        // Call the API's channels.list method with mine parameter to fetch authorized user's channel.
        $listResponse = $youtube->channels->listChannels('snippet', array(
                 'mine' => 'true',
              ));
        if (isset($listResponse[0]['snippet']['title'])) {
            $youtube_username = $listResponse[0]['snippet']['title'];
            ps_query("UPDATE user SET youtube_username = ? WHERE ref = ?", array("s", $youtube_username, "i", $userref));
        } else {
            throw new Exception('No Youtube user found for account.');
        }
    } catch (Google_Service_Exception $e) {
        $errortext = sprintf(
            '<p>A service error occurred: <code>%s</code></p>',
            escape($e->getMessage())
        );
    } catch (Google_Exception $e) {
        $errortext = sprintf(
            '<p>A client error occurred: <code>%s</code></p>',
            escape($e->getMessage())
        );
    } catch (Exception $e) {
        $errortext = sprintf(
            '<p>An error occurred: <code>%s</code></p>',
            escape($e->getMessage())
        );
    }

    unset($GLOBALS["use_error_exception"]);

    if (isset($errortext)) {
        return array(false,$errortext);
    }
    return array(true,"");
}

function get_youtube_authorization_code()
{
    global $ref, $baseurl, $youtube_publish_client_id, $youtube_publish_client_secret,$language, $client,$youtube;

    // If the user hasn't authorized the app, initiate the OAuth flow
    $state = $ref;
    $client->setState($state);
    $_SESSION['state'] = $state;
    $authUrl = $client->createAuthUrl();

    header("Location: " . $authUrl);
}

function delete_youtube_tokens()
{
    global $userref;
    ps_query("UPDATE user SET youtube_access_token = '', youtube_refresh_token = '' WHERE ref = ?", array("i", $userref));
}

function upload_video()
{
    global $lang, $video_title, $video_description, $video_keywords, $video_category, $filename, $ref, $video_status, $youtube_video_url, $youtube_publish_developer_key,$youtube_chunk_size, $client,$youtube;
    debug("youtube_publish: uploading video resource ID:" . $ref);
    $errortext = "";
    try {
        # Get file info for upload
        $resource = get_resource_data($ref);
        $alternative = -1;
        $ext = $resource["file_extension"];

        $videoPath = get_resource_path($ref, true, "", false, $ext, -1, 1, false, "", $alternative);

        // Create a snippet with title, description, tags and category ID
        // Create an asset resource and set its snippet metadata and type.
        // This example sets the video's title, description, keyword tags, and
        // video category.
        $snippet = new VideoSnippet();
        $snippet->setTitle($video_title);
        $snippet->setDescription($video_description);
        $snippet->setTags(array($video_keywords));

        // Numeric video category. See
        // https://developers.google.com/youtube/v3/docs/videoCategories/list
        $snippet->setCategoryId($video_category);

        // Set the video's status to "public". Valid statuses are "public",
        // "private" and "unlisted".
        $status = new VideoStatus();
        $status->privacyStatus = $video_status;

        // Associate the snippet and status objects with a new video resource.
        $video = new Video();
        $video->setSnippet($snippet);
        $video->setStatus($status);

        // Specify the size of each chunk of data, in bytes. Set a higher value for
        // reliable connection as fewer chunks lead to faster uploads. Set a lower
        // value for better recovery on less reliable connections.
        if (!is_numeric($youtube_chunk_size)) {
            $youtube_chunk_size = 10;
        }

        $chunkSizeBytes = intval($youtube_chunk_size) * 1024 * 1024;

        // Setting the defer flag to true tells the client to return a request which can be called
        // with ->execute(); instead of making the API call immediately.
        $client->setDefer(true);

        // Create a request for the API's videos.insert method to create and upload the video.
        $insertRequest = $youtube->videos->insert("status,snippet", $video);

        // Create a MediaFileUpload object for resumable uploads.
        $media = new Google_Http_MediaFileUpload(
            $client,
            $insertRequest,
            'video/*',
            null,
            true,
            $chunkSizeBytes
        );
        $media->setFileSize(filesize($videoPath));

        // Read the media file and upload it chunk by chunk.
        $status = false;
        $handle = fopen($videoPath, "rb");
        while (!$status && !feof($handle)) {
            $chunk = fread($handle, $chunkSizeBytes);
            $status = $media->nextChunk($chunk);
        }

        fclose($handle);

        // If you want to make other calls after the file upload, set setDefer back to false
        $client->setDefer(true);

        $youtube_new_url = "https://www.youtube.com/watch?v=" . $status['id'];

        return array(true,$youtube_new_url);
    } catch (Google_Service_Exception $e) {
        $htmlBody = sprintf(
            '<p>A service error occurred: <code>%s</code></p>',
            escape($e->getMessage())
        );
        exit($htmlBody);
    } catch (Google_Exception $e) {
        $htmlBody = sprintf(
            '<p>A client error occurred: <code>%s</code></p>',
            escape($e->getMessage())
        );
        exit($htmlBody);
    }

    if (isset($errortext)) {
        return array(false,$errortext);
    }
}

function youtube_upload_get_categories()
{
    global $client,$youtube;

    try {
        $listResponse = $youtube->videoCategories->listVideoCategories('snippet', array(
                'regionCode' => 'GB',
            ));
    } catch (Google_Service_Exception $e) {
        $errortext = sprintf(
            '<p>A service error occurred: <code>%s</code></p>',
            escape($e->getMessage())
        );
    } catch (Google_Exception $e) {
        $errortext = sprintf(
            '<p>A client error occurred: <code>%s</code></p>',
            escape($e->getMessage())
        );
    }

    if (isset($errortext)) {
        return $errortext;
    }

    $categories = $listResponse['items'];
    $availablecategories = array();
    foreach ($categories as $category) {
        $availablecategories[$category["id"]] = $category["snippet"]["title"];
    }
    return $availablecategories;
}
