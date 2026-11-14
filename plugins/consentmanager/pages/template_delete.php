<?php

include_once __DIR__ . '/../../../include/boot.php';
include __DIR__ . '/../../../include/authenticate.php';

try {
    $ref = (int) getval('ref', 0);
    $template = consentmanager_external_template($ref);
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && enforcePostRequest(false)) {
        consentmanager_external_delete_template($ref);
        redirect($baseurl_short . 'plugins/consentmanager/pages/templates.php');
    }
} catch (Throwable $e) {
    http_response_code(403);
    exit(escape($lang[$e->getMessage()] ?? $lang['cm_external_failed']));
}
include __DIR__ . '/../../../include/header.php';
?>
<div class="BasicsBox">
    <h1><?php echo escape($lang['cm_external_delete_template']); ?></h1>
    <?php renderBreadcrumbs([
        ['title' => $lang['cm_external_admin'], 'href' => $baseurl_short . 'pages/admin/admin_home.php', 'menu' => true],
        ['title' => $lang['pluginmanager'], 'href' => $baseurl_short . 'pages/team/team_plugins.php'],
        ['title' => 'Consent Manager', 'href' => $baseurl_short . 'plugins/consentmanager/pages/setup.php'],
        ['title' => $lang['cm_external_templates'], 'href' => $baseurl_short . 'plugins/consentmanager/pages/templates.php'],
        ['title' => $lang['cm_external_delete_template']],
    ]); ?>
    <p><?php echo escape($template['title']); ?></p>
    <p><?php echo escape($lang['cm_external_delete_template_help']); ?></p>
    <form method="post" onsubmit="CentralSpacePost(this, true); return false;">
        <?php generateFormToken('cm_external_delete_template'); ?>
        <input type="hidden" name="ref" value="<?php echo (int) $ref; ?>">
        <div class="QuestionSubmit"><input type="submit" value="<?php echo escape($lang['action-delete']); ?>"></div>
    </form>
</div>
<?php include __DIR__ . '/../../../include/footer.php'; ?>
