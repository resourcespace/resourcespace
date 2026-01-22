<?php
include '../../../include/boot.php';
include '../../../include/authenticate.php';

if (!checkperm('a')) {
    header('HTTP/1.1 401 Unauthorized');
    exit($lang['error-permissiondenied']);
}

$script   = getval('script', 0, true);
$ajax     = ('true' == getval('ajax', '') ? true : false);
$run_mode = ('true' == getval('run_mode', '') ? true : false);

// Process this script now
if ($ajax && $run_mode && enforcePostRequest($ajax)) {
    $command = "\"{$php_path}" . ($config_windows ? '/php.exe" ' : '/php" ') . "{$SCRIPTS[$script]['file']} --emu_test_mode=false --emu_userref={$userref}";
    run_external($command);

    exit();
}

if (file_exists($SCRIPTS[$script]['file'])) {
    $command = "\"{$php_path}" . ($config_windows ? '/php.exe" ' : '/php" ') . "{$SCRIPTS[$script]['file']} --emu_test_mode=true --emu_userref={$userref}";
    $output  = run_external($command);
} else {
    $error = "EMu script '{$SCRIPTS[$script]['file']}' not found!";
}

include '../../../include/header.php';
?>

<div class="BasicsBox">
    <h1><?php echo escape($lang['emu_test_script_title'] . ' - ' . $SCRIPTS[$script]['name']); ?></h1>
    <?php
    if (isset($error)) {
        echo "<div class=\"PageInformal\">{$error}</div>";
    }
    ?>
    <div class="Question">
        <label for="emu_test_script_log"><?php echo escape($lang["log"]); ?></label>
        <textarea id="emu_test_script_log" disabled><?php echo implode(PHP_EOL, $output); ?></textarea>
    </div>
    <div class="QuestionSubmit">
        <button type="button" class="RSButton" onclick="ModalClose();"><?php echo escape($lang['close']); ?></button>
        <button type="button" class="RSButton" onclick="runScript(<?php echo $script; ?>);"><?php echo escape($lang['emu_run_script']); ?></button>
        <div class="clearerleft"></div>
    </div>
    <script>
        function runScript(script) {
            if (script <= 0) {
                return false;
            }

            jQuery.post('<?php echo $baseurl; ?>/plugins/emu/pages/emu_test_script.php', {
                ajax     : true,
                script   : script,
                run_mode : true,
                <?php echo generateAjaxToken("runScript"); ?>
                },
                function(response) {
                    console.log(response);
                },
                'json'
            );

            ModalClose();

            return true;
        }
    </script>
</div>

<?php
include '../../../include/footer.php';