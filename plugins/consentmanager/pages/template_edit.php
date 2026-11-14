<?php

include_once __DIR__ . '/../../../include/boot.php';
include __DIR__ . '/../../../include/authenticate.php';

$error = '';
try {
    consentmanager_external_require_manager();
    if (!checkperm('a')) {
        throw new RuntimeException('cm_external_denied');
    }
    $ref = max(0, (int) getval('ref', 0));
    $template = $ref > 0 ? consentmanager_external_template($ref) : ['enabled' => 1, 'configuration' => '{}'];
    $config = consentmanager_external_json($template['configuration']);
    if (getval('duplicate', '') === 'yes') {
        $ref = 0;
        $config['title'] = ($config['title'] ?? '') . ' - ' . $lang['cm_external_copy'];
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && enforcePostRequest(false)) {
        consentmanager_external_save_template($ref, $_POST);
        redirect($baseurl_short . 'plugins/consentmanager/pages/templates.php');
    }
} catch (Throwable $e) {
    $error = $lang[$e->getMessage()] ?? $lang['cm_external_failed'];
}
include __DIR__ . '/../../../include/header.php';
?>
<div class="BasicsBox">
    <h1><?php echo escape($lang[empty($ref) ? 'cm_external_new_template' : 'cm_external_edit_template']); ?></h1>
    <?php renderBreadcrumbs([
        ['title' => $lang['cm_external_admin'], 'href' => $baseurl_short . 'pages/admin/admin_home.php', 'menu' => true],
        ['title' => $lang['pluginmanager'], 'href' => $baseurl_short . 'pages/team/team_plugins.php'],
        ['title' => 'Consent Manager', 'href' => $baseurl_short . 'plugins/consentmanager/pages/setup.php'],
        ['title' => $lang['cm_external_templates'], 'href' => $baseurl_short . 'plugins/consentmanager/pages/templates.php'],
        ['title' => $lang[empty($ref) ? 'cm_external_new_template' : 'cm_external_edit_template']],
    ]); ?>
    <?php if ($error !== '') { ?>
        <p role="alert"><?php echo escape($error); ?></p>
    <?php }
    if (isset($config)) { ?>
        <p><?php echo escape($lang['cm_external_template_snapshot_help']); ?></p>
        <form method="post" action="<?php echo escape($baseurl_short . 'plugins/consentmanager/pages/template_edit.php'); ?>"
            onsubmit="CentralSpacePost(this, true); return false;">
            <?php generateFormToken('cm_external_template'); ?>
            <input name="ref" type="hidden" value="<?php echo (int) $ref; ?>">
            <?php foreach (['title', 'organisation', 'wording', 'declarations', 'privacy', 'duration', 'options', 'fields'] as $field) {
                $value = $config[$field] ?? (in_array($field, ['options', 'fields'], true) ? [] : '');
                ?>
                <div class="Question">
                    <label for="cm-template-<?php echo escape($field); ?>"><?php echo escape($lang['cm_external_' . $field]); ?></label>
                    <div class="consent-template-field">
                        <?php if (in_array($field, ['options', 'fields'], true)) { ?>
                            <select class="stdwidth consent-template-tags" multiple
                                id="cm-template-<?php echo escape($field); ?>" name="<?php echo escape($field); ?>[]"
                                aria-describedby="cm-help-<?php echo escape($field); ?>">
                                <?php foreach ((array) $value as $item) { ?>
                                    <option value="<?php echo escape($item); ?>" selected><?php echo escape($item); ?></option>
                                <?php } ?>
                            </select>
                        <?php } elseif (in_array($field, ['title', 'organisation'], true)) { ?>
                            <input class="stdwidth" id="cm-template-<?php echo escape($field); ?>" name="<?php echo escape($field); ?>"
                                maxlength="200" required value="<?php echo escape($value); ?>"
                                aria-describedby="cm-help-<?php echo escape($field); ?>">
                        <?php } else { ?>
                            <textarea class="stdwidth" rows="<?php echo $field === 'wording' ? 8 : 4; ?>"
                                id="cm-template-<?php echo escape($field); ?>" name="<?php echo escape($field); ?>"
                                aria-describedby="cm-help-<?php echo escape($field); ?>"
                                <?php echo in_array($field, ['options', 'fields'], true) ? '' : 'required'; ?>><?php echo escape($value); ?></textarea>
                        <?php } ?>
                        <p class="FormHelp" id="cm-help-<?php echo escape($field); ?>"><?php echo escape($lang['cm_external_help_' . $field]); ?></p>
                    </div>
                    <div class="clearerleft"></div>
                </div>
            <?php } ?>
            <div class="Question">
                <label for="cm-template-enabled"><?php echo escape($lang['cm_external_template_enabled']); ?></label>
                <input id="cm-template-enabled" name="enabled" type="checkbox" value="yes" <?php echo $template['enabled'] ? 'checked' : ''; ?>>
                <div class="clearerleft"></div>
            </div>
            <div class="QuestionSubmit"><input type="submit" value="<?php echo escape($lang['save']); ?>"></div>
        </form>
    <?php } ?>
</div>
<script>
jQuery(function () {
    jQuery('.consent-template-tags').select2({
        tags: true,
        width: '420px',
        maximumSelectionLength: 20,
        maximumInputLength: 200,
        placeholder: <?php echo encode_js_value($lang['cm_external_add_item_help']); ?>,
        createTag: function (params) {
            const text = params.term.trim();
            return text === '' ? null : {id: text, text: text, newTag: true};
        },
        templateResult: function (item) {
            return item.newTag ? <?php echo encode_js_value($lang['cm_external_add_item']); ?> + ': ' + item.text : item.text;
        }
    });
});
</script>
<?php include __DIR__ . '/../../../include/footer.php'; ?>
