<?php

include_once __DIR__ . '/../../../include/boot.php';
include __DIR__ . '/../../../include/authenticate.php';

try {
    consentmanager_external_require_manager();
    if (!checkperm('a')) {
        throw new RuntimeException('cm_external_denied');
    }
    $templates = consentmanager_external_templates(true);
} catch (Throwable $e) {
    http_response_code(403);
    exit(escape($lang[$e->getMessage()] ?? $lang['cm_external_failed']));
}
include __DIR__ . '/../../../include/header.php';
?>
<div class="BasicsBox">
    <h1><?php echo escape($lang['cm_external_templates']); ?></h1>
    <?php renderBreadcrumbs([
        ['title' => $lang['cm_external_admin'], 'href' => $baseurl_short . 'pages/admin/admin_home.php', 'menu' => true],
        ['title' => $lang['pluginmanager'], 'href' => $baseurl_short . 'pages/team/team_plugins.php'],
        ['title' => 'Consent Manager', 'href' => $baseurl_short . 'plugins/consentmanager/pages/setup.php'],
        ['title' => $lang['cm_external_templates']],
    ]); ?>
    <p><?php echo escape($lang['cm_external_templates_help']); ?></p>
    <p><a href="<?php echo escape($baseurl_short . 'plugins/consentmanager/pages/template_edit.php'); ?>"
        onclick="return CentralSpaceLoad(this, true);">
        <?php echo LINK_PLUS_CIRCLE; ?><?php echo escape($lang['cm_external_new_template']); ?>
    </a></p>
    <?php if ($templates) { ?>
        <div class="Listview">
            <table class="ListviewStyle">
                <tr class="ListviewTitleStyle">
                    <th><?php echo escape($lang['cm_external_title']); ?></th>
                    <th><?php echo escape($lang['cm_external_version']); ?></th>
                    <th><?php echo escape($lang['cm_external_status']); ?></th>
                    <th><div class="ListTools"><?php echo escape($lang['tools']); ?></div></th>
                </tr>
                <?php foreach ($templates as $template) { ?>
                    <tr>
                        <td><?php echo escape($template['title']); ?></td>
                        <td><?php echo (int) $template['version']; ?></td>
                        <td><?php echo escape($lang[$template['enabled'] ? 'cm_external_template_active' : 'cm_external_template_inactive']); ?></td>
                        <td>
                            <div class="ListTools">
                                <a href="<?php echo escape(generateURL(
                                    $baseurl_short . 'plugins/consentmanager/pages/template_edit.php',
                                    ['ref' => $template['ref']]
                                )); ?>" onclick="return CentralSpaceLoad(this, true);">
                                    <i class="icon-square-pen" aria-hidden="true"></i>&nbsp;<?php echo escape($lang['action-edit']); ?>
                                </a>
                                <a href="<?php echo escape(generateURL(
                                    $baseurl_short . 'plugins/consentmanager/pages/template_edit.php',
                                    ['ref' => $template['ref'], 'duplicate' => 'yes']
                                )); ?>" onclick="return CentralSpaceLoad(this, true);">
                                    <i class="icon-copy" aria-hidden="true"></i>&nbsp;<?php echo escape($lang['cm_external_duplicate']); ?>
                                </a>
                                <a href="<?php echo escape(generateURL(
                                    $baseurl_short . 'plugins/consentmanager/pages/template_delete.php',
                                    ['ref' => $template['ref']]
                                )); ?>" onclick="return CentralSpaceLoad(this, true);">
                                    <i class="icon-trash-2" aria-hidden="true"></i>&nbsp;<?php echo escape($lang['action-delete']); ?>
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
            </table>
        </div>
    <?php } else { ?>
        <p><?php echo escape($lang['cm_external_no_templates']); ?></p>
    <?php } ?>
</div>
<?php include __DIR__ . '/../../../include/footer.php'; ?>
