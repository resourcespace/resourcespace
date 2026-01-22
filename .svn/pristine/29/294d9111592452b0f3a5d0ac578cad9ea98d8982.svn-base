<?php
include_once "../include/boot.php";

# External access support (authenticate only if no key provided)
$k = getval("k", "");
$k_shares_collection = getval("collection", "");
$k_shares_ref = getval("ref", "");
$ref = getval("ref", "");
$upload = (getval("upload", "") != "");

# Check access key because we need to honor terms requirement at user group override level
if ($k != "") {
    if ($k_shares_collection != "") {
        if (!check_access_key_collection(getval("collection", "", true), $k)) {
            include "../include/authenticate.php";
        }
    } elseif ($k_shares_ref != "") {
        if (!check_access_key(getval("ref", ""), $k)) {
            include "../include/authenticate.php";
        }
    }
} else {
    include "../include/authenticate.php";
}

$url = getval("url", "pages/home.php?login=true");
$newurl = hook("beforeredirectchangeurl");

if (is_string($newurl)) {
    $url = $newurl;
}

$terms_save = getval('save', '');
$terms_url_accepted = "";

if ('' != $terms_save && enforcePostRequest(false)) {
    $terms_iaccept = getval('iaccept', '');
    if ('on' == $terms_iaccept) {
        ps_query("UPDATE user SET accepted_terms = 1 WHERE ref = ?", array("i",$userref));
        $terms_url_accepted = (strpos($url, "?") ? "&" : "?") . "iaccept=" . $terms_iaccept;
    }

    $url .= $terms_url_accepted;

    if (strpos($url, 'download_progress.php') !== false || strpos($url, 'download.php') !== false) {
        $temp_download_key = download_link_generate_key((isset($userref) ? $userref : $k), $ref);
        rs_setcookie("dl_key", $temp_download_key, 1, $baseurl_short, "", substr($baseurl, 0, 5) == "https", true);

        global $download_usage;
        if ($download_usage && strpos($url, 'download_usage.php') === false) {
            $params = array();
            if (($pos = strpos($url, '?')) !== false) {
                parse_str(substr($url, $pos + 1), $params);
            }
            $url = generateURL($baseurl_short . 'pages/download_usage.php', array_merge(['url' => $url], $params));
        }
    }

    if (strpos($url, 'upload_batch.php') !== false || strpos($url, 'edit.php') !== false) {
        rs_setcookie("acceptedterms", true, 1);
    }

    redirect($url);
}

if (!
    (
        (
            $terms_upload 
            && 
            (
                strpos($url, 'edit.php') !== false
                || strpos($url, 'upload_batch.php') !== false
            )
        )
        || 
        (
            $terms_download 
            && strpos($url, 'download') !== false
        )
        || getval("noredir", "") !== ""
    )
) {
    redirect($url);
}

global $useracceptedterms;
if ($useracceptedterms || trim($k) !== '') {
    $decline_link = "window.history.go(-1)";
} else {
    $decline_link = "window.location='" . generateURL($baseurl . '/login.php', ['logout' => true, 'nc' => time()]) . "'";
}

include "../include/header.php";
?>

<div class="BasicsBox"> 
    <h1><?php echo escape($lang["termsandconditions"]); ?></h1>
    <p><?php echo escape(text("introtext")); ?></p>
  
    <div class="Question">
        <label><?php echo escape($lang["termsandconditions"]); ?></label>
        <div class="Terms">
            <?php
            $termstext = text(($upload ? "upload_terms" : "terms"));
            $modified_termstext = hook('modified_termstext');

            if ($modified_termstext != '') {
                $termstext = $modified_termstext;
            }

            if (is_html($termstext)) {
                echo strip_tags_and_attributes($termstext);
            } else {
                echo escape($termstext);
            }
            ?>
        </div>
        <div class="clearerleft"></div>
    </div>
    
    <form method="post" action="<?php echo $baseurl_short?>pages/terms.php?k=<?php echo urlencode($k); ?>&collection=<?php echo urlencode($k_shares_collection); ?>" 
        onsubmit="if (!document.getElementById('iaccept').checked) {alert('<?php echo escape($lang["mustaccept"]); ?>');return false;}">
        <?php generateFormToken("terms"); ?>
        <input type=hidden name="url" value="<?php echo escape($url)?>">
        <input type=hidden name="ref" value="<?php echo escape($ref)?>">
        
        <div class="Question">
            <label for="iaccept"><?php echo escape($lang["iaccept"]); ?></label>
            <input type="checkbox" name="iaccept" id="iaccept" />
            <div class="clearerleft"></div>
        </div>
        
        <div class="QuestionSubmit">
            <input
                name="save"
                type="submit"
                value="<?php echo escape($lang["proceed"]); ?>"
            />
            <input
                type="button"
                name="decline"
                onclick="<?php echo $decline_link; ?>"
                value="<?php echo escape($lang["idecline"]); ?>"
            />
        </div>
    </form>
</div>

<?php
include "../include/footer.php";
?>
