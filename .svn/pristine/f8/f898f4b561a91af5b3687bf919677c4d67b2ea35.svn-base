<?php

include "../include/boot.php";
include "../include/authenticate.php";
include_once "../include/image_processing.php";
include_once "../include/api_functions.php";
include_once "../include/api_bindings.php";
include_once "../include/login_functions.php";
include_once "../include/dash_functions.php";
include_once "../include/ajax_functions.php";
include "../include/header.php";

if (!$enable_remote_apis) {
    exit("API not enabled.");
}

if (!checkperm("a")) {
    exit("Access denied");
}

$api_function = getval("api_function", "");
$api_functions = array_filter(get_defined_functions()['user'], function ($e) {
    return strpos($e, 'api_') === 0;
});

if ($api_function != "") {
    $index = array_search('api_' . $api_function, $api_functions);
    if ($index !== false) {
        $fct = new ReflectionFunction($api_functions[$index]);
        $paramcount = $fct->getNumberOfParameters();
        $rparamcount = $fct->getNumberOfRequiredParameters();
        $fct_params = $fct->getParameters();
    }
}

$output = "";

if (getval("submitting", "") != "" && $api_function != "") {
    $output = "";

    $query = "function=" . $api_function;
    foreach ($fct_params as $fparam) {
        $param_name = $fparam->getName();
        $param_val = trim(getval($param_name, ""));

        if ($fparam->isOptional() && $param_val == "") {
            continue;
        }

        $query .= "&{$param_name}=" . urlencode($param_val);
    }
    $output .= "Query: " . $query . "\n\n";
    $output .= "Response:\n";
    $output .= execute_api_call($query, true);
}
?>

<div class="BasicsBox">
    <h1><?php echo escape($lang['api-test-tool']); ?></h1>

    <?php
    renderBreadcrumbs([
        ['title' => $lang["systemsetup"], 'href'  => $baseurl_short . "pages/admin/admin_home.php", 'menu' => true],
        ['title' => $lang['api-test-tool']]
    ]);
    ?>

    <p>
        <?php
        echo strip_tags_and_attributes($lang["api-help"]);
        render_help_link("api/");
        ?>
    </p>

    <form id="api-form" method="post" action="<?php echo $baseurl_short?>pages/api_test.php" onSubmit="return CentralSpacePost(this);">
        <?php generateFormToken("api-form"); ?>

        <div class="Question">
            <label><?php echo escape($lang["api-function"]); ?></label>
            <select class="stdwidth" name="api_function" onChange="CentralSpacePost(document.getElementById('api-form'));">
                <option value=""><?php echo escape($lang["select"]); ?></option>
                <?php
                # Allow selection from built in functions
                asort($api_functions);
                foreach ($api_functions as $function) {
                    ?>
                    <option <?php echo ($function == "api_" . $api_function) ? " selected" : ''; ?>>
                        <?php echo substr($function, 4); ?>
                    </option>
                    <?php
                }
                ?>
            </select>
            <?php if ($api_function != "") { ?>
                &nbsp;&nbsp;
                <a target="_blank" href="https://www.resourcespace.com/knowledge-base/api/<?php echo escape($api_function) ?>">
                    <?php echo escape($lang["api-view-documentation"]); ?>
                </a>
            <?php } ?>
        </div>

        <?php
        if ($api_function != "") {
            foreach ($fct_params as $fparam) {
                $param_name = $fparam->getName();

                if ($fparam->isOptional()) {
                    $required = '';
                    $required_attr = '';

                    $send_param = getval("send_{$param_name}", '') === 'yes';
                    $send_param_input = sprintf(
                        '<input type="checkbox" name="send_%s" value="yes" %s onchange="ToggleSendParam(this);">',
                        $param_name,
                        ($send_param ? 'checked' : '')
                    );
                    $disabled_attr = ($send_param ? '' : 'disabled');
                } else {
                    $required = ' *';
                    $required_attr = 'required';
                    $send_param_input = '';
                    $disabled_attr = '';
                }
                ?>
                <div class="Question">
                    <label><?php echo $send_param_input . $param_name . $required; ?></label>
                    <input
                        type="text"
                        name="<?php echo $param_name; ?>"
                        class="stdwidth"
                        value="<?php echo escape($_POST[$param_name] ?? ''); ?>"
                        <?php echo "{$required_attr} {$disabled_attr}"; ?>
                    >
                </div>
                <?php
            }
        }
        ?>
        <div class="QuestionSubmit">
            <input type="hidden" name="submitting" value="" id="submitting" />
            <input type="submit" name="submit" value="<?php echo escape($lang["call-function"]) ?>" onclick="document.getElementById('submitting').value='true';" />
        </div>
    </form>

    <?php if ($output != "") {
        //rebuild params for output to include encoding if needed
        $original_query = $query;
        $query = "function=" . $api_function;

        foreach ($fct_params as $fparam) {
            $param_name = $fparam->getName();
            $param_val = trim(getval($param_name, ""));

            if ($fparam->isOptional() && $param_val == "") {
                continue;
            }

            if (strpos(urlencode($param_val), '%') === false) {
                $query .= '&' . $param_name . '=' . $param_val;
            } else {
                $query .= '&' . $param_name . '=" . urlencode("' . addslashes($param_val) . '") . "';
            }
        }
        ?>
        <pre class="codeoutput"><?php echo escape($output); ?></pre>
        <br />
        <br />
        <h2><?php echo escape($lang["api-php-code"]); ?></h2>
        <p><?php echo escape($lang["api-php-help"]); ?></p>

        <pre class="codeexample">
&lt;?php

<span class="codecomment">// Set the private API key for the user (from the user account page) and the user we're accessing the system as.</span>
$private_key = "<?php echo get_api_key($userref) ?>";
$user = <?php echo strpos(urlencode($username), '%') === false ? '"' . $username . '"' : 'urlencode("' . $username . '")'; ?>;

<span class="codecomment">// Formulate the query</span>
$query = "user=" . $user . "&amp;<?php echo substr($query, -4) != ' . "' ? escape($query) . '"' : substr(escape($query), 0, -9); ?>;

<span class="codecomment">// Sign the query using the private key</span>
$sign = hash("sha256", $private_key . $query);

<span class="codecomment">// Make the request and output the JSON results.</span>
$results = json_decode(file_get_contents("<?php echo escape($baseurl) ?>/api/?" . $query . "&sign=" . $sign));
print_r($results);</pre>

        <h2><?php echo escape($lang["api-curl-example"]); ?></h2>
        <p><?php echo escape($lang["api-curl-help"]); ?></p>

        <pre class="codeexample">
private_key="<?php echo get_api_key($userref) ?>"; user=<?php echo escape(escapeshellarg($username)); ?>; query=<?php echo escape(escapeshellarg("user=" . $username . "&" . $original_query)); ?>; sign=$(echo -n "${private_key}${query}" | openssl dgst -sha256); curl -X POST "<?php echo $baseurl ?>/api/?${query}&sign=$(echo ${sign} | sed 's/^.* //')"</pre>

    <?php } ?>
</div>

<script>
    function ToggleSendParam(el) {
        console.debug('ToggleSendParam(%o)', el);

        var send_param = jQuery(el);
        var param_name = send_param.attr('name').replace('send_', '');
        var param_input = jQuery('input[name="' + param_name + '"]').not('[required]');

        console.debug('param_name = %o', param_name);
        console.debug('param_input = %o', param_input);

        if (param_input.length == 0) {
            console.error('Unable to find an input with name %o', param_name);
            return false;
        }

        if (send_param.is(':checked')) {
            param_input.prop('disabled', false);
        } else {
            param_input.prop('disabled', true);
        }

        return true;
    }
</script>

<?php
include "../include/footer.php";
