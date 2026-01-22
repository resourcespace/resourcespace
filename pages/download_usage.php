<?php
include "../include/boot.php";

# External access support (authenticate only if no key provided, or if invalid access key provided)
$k           = getval('k', '');
$ref         = getval('ref', '', true);
$col         = getval('collection', getval('col', -1, true), true);
$size        = getval('size', '');
$ext         = getval('ext', '');
$alternative = getval('alternative', -1);
$iaccept     = getval('iaccept', 'off');
$url         = getval('url', '');

$email       = getval('email', '');
$usage       = getval("usage", '', true);
$usagecomment = getval("usagecomment", '');

$error = array();

if (-1 != $col) {
    $need_to_authenticate = !check_access_key_collection($col, $k);
} else {
    $need_to_authenticate = !check_access_key($ref, $k);
}

if ('' == $k || $need_to_authenticate) {
    include '../include/authenticate.php';
}

hook("pageevaluation");

$download_url_suffix = hook("addtodownloadquerystring");

if (getval("save", '') != '' && enforcePostRequest(false)) {
    $fields["usage"] = $usage;
    $fields["usagecomment"] = $usagecomment;
    $fields["email"] = $email;

    // validate input fields
    $error = validate_input_download_usage($fields);

    if (count($error) === 0) {
        $download_url_suffix_params = [];
        $download_url_suffix .= ($download_url_suffix == '') ? '?' : '&';
        if ($download_usage && -1 != $col) {
            $download_url_suffix_params["collection"] = $col;
            $redirect_url = "pages/collection_download.php";
            $download_url_suffix_params = array_merge($download_url_suffix_params, array("email" => $email));
        } else {
            $download_url_suffix_params["ref"] = $ref;
            $redirect_url = "pages/download_progress.php";
        }
        $download_url_suffix_params = array_merge(
            $download_url_suffix_params,
            [
                "size"          => $size,
                "ext"           => $ext,
                "k"             => $k,
                "alternative"   => $alternative,
                "iaccept"       => $iaccept,
                "usage"         => $usage,
                "usagecomment"  => $usagecomment,
                "offset"        => getval("saved_offset", getval("offset", 0, true)),
                "order_by"      => getval("saved_order_by", getval("order_by", '')),
                "sort"          => getval("saved_sort", getval("sort", '')),
                "archive"       => getval("saved_archive", getval("archive", '')),
                "email"         => $email
            ]
        );

        $url_parts = [];
        if (strpos($url, '?') !== false) {
            parse_str(explode('?', $url)[1], $url_parts);
        }

        if (strpos($url, 'download.php') !== false && count($url_parts) > 0 && isset($url_parts['noattach']) && $url_parts['noattach'] == true) {
            $redirect_url = $url;
        } elseif (strpos($url, 'download.php') !== false && (strpos($url, $baseurl_short) !== false || strpos($url, $baseurl) !== false)) {
            $download_url_suffix_params['url'] = $url;
        }
        redirect(generateURL($redirect_url, $download_url_suffix_params, $url_parts));
    }
}

include "../include/header.php";

if (isset($download_usage_prevent_options)) { ?>
    <script>
        function checkvalidusage() {
            validoptions = new Array(<?php echo "'" . implode("','", $download_usage_prevent_options) . "'" ?>);
            if (jQuery.inArray(jQuery('#usage').find(":selected").text(), validoptions ) !=- 1) {
                jQuery('#submit').prop('disabled', true).css("filter", "opacity(0.25)");
                alert("<?php echo escape($lang["download_usage_option_blocked"]); ?>");
            } else {
                jQuery('#submit').prop('disabled', false).css("filter", "opacity(1)");
            }
        }
    </script>   
    <?php
} ?>

<div class="BasicsBox">
    <form method="post" action="<?php echo $baseurl_short?>pages/download_usage.php<?php echo $download_url_suffix ?>">
        <?php
        generateFormToken("download_usage");

        if ($download_usage) { ?>
            <input type="hidden" name="url" value="<?php echo escape($url); ?>" />
            <?php if ($col != -1) { ?>
                <input type="hidden" name="col" value="<?php echo escape($col); ?>" />
            <?php }
        } ?>
        <input type="hidden" name="ref" value="<?php echo escape($ref) ?>" />
        <input type="hidden" name="size" value="<?php echo escape($size) ?>" />
        <input type="hidden" name="ext" value="<?php echo escape($ext) ?>" />
        <input type="hidden" name="alternative" value="<?php echo escape($alternative) ?>" />
        <input type="hidden" name="k" value="<?php echo escape($k) ?>" />
        <input type="hidden" name="save" value="true" />
        <input type="hidden" name="iaccept" value="<?php echo escape($iaccept) ?>" />
        <h1><?php echo escape($lang["usage"]); ?></h1>
        <p><?php echo strip_tags_and_attributes($lang["indicateusage"], array('a'), array('href', 'target')); ?></p>

        <?php if ($download_usage_email) { ?>
            <div class="Question">
                <label><?php echo escape($lang["emailaddress"]); ?></label>
                <input name="email" type="text" class="stdwidth" value="<?php echo escape($email); ?>">
                <span class="error"><?php echo isset($error['email']) ? $error["email"] : "" ?></span>
                <div class="clearerleft"></div>
            </div>
        <?php }

        if (!$remove_usage_textbox && !$usage_textbox_below) {
            echo html_usagecomments($usagecomment, $error);
        } ?>

        <div class="Question">
            <label for="usage"><?php echo escape($lang["indicateusagemedium"]); ?></label>
            <select class="stdwidth" name="usage" id="usage" <?php echo (isset($download_usage_prevent_options)) ? 'onchange="checkvalidusage();"' : ''; ?>>
                <option value=""><?php echo escape($lang["select"]); ?></option>
                <?php
                for ($n = 0; $n < count($download_usage_options); $n++) {
                    $selected = ($n === $usage) ? "selected" : "";
                    ?>
                    <option <?php echo $selected; ?> value="<?php echo $n; ?>">
                        <?php echo escape(i18n_get_translated($download_usage_options[$n])); ?>
                    </option>
                    <?php
                } ?>
            </select>
            <span class="error"><?php echo isset($error['usage']) ? $error["usage"] : "" ?></span>
            <div class="clearerleft"></div>
        </div>

        <?php if ($usage_textbox_below && !$remove_usage_textbox) {
            echo html_usagecomments($usagecomment, $error);
        } ?>

        <div class="QuestionSubmit">        
            <input name="submit" type="submit" id="submit" value="<?php echo escape($lang["action-download"]); ?>" />
        </div>
    </form>
</div>

<?php
include "../include/footer.php";

/**
 * HTML for usage comments input field
 *
 * @param string $usagecomment  - submitted value for field
 * @param array $error          - array of form field validation error messages
 *
 * @return string $html         - HTML string to display
 */

function html_usagecomments($usagecomment, $error)
{
    global $lang;

    $html = '<div class="Question"><label>{label}</label>
            <textarea rows="5" name="usagecomment" id="usagecomment" type="text" class="stdwidth">{value}</textarea>
            <span class="error">{error}</span>
            <div class="clearerleft"></div></div>';

    $replace = array(
        "{label}"   => $lang["usagecomments"],
        "{error}"   => isset($error["usagecomment"]) ?  $error["usagecomment"] : "",
        "{value}"   => escape($usagecomment)
    );

    $html = str_replace(array_keys($replace), array_values($replace), $html);

    return $html;
}

/**
 * Validate download usage form field values. Uses config var $usage_comment_blank to determine whether to validate usagecomment
 *
 * @param array $fields - list of fields to validate
 *
 * @return array $error - list of fields with error messages
 */

function validate_input_download_usage($fields)
{
    global $lang, $usage_comment_blank, $download_usage_email, $remove_usage_textbox;
    $error = array();
    $error["usage"] = $fields["usage"] == "" ? $lang["usageincorrect"] : "";

    if (!$remove_usage_textbox) {
        $error["usagecomment"] = $fields["usagecomment"] == "" && !$usage_comment_blank ? $lang["usageincorrect"] : "";
    }

    if ($download_usage_email) {
        $error["email"] = !filter_var($fields["email"], FILTER_VALIDATE_EMAIL) ? $lang["error_invalid_email"] : "";
    }

    $error = array_filter($error);

    return $error;
}

?>
