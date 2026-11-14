<?php
include __DIR__ . '/../../../include/header.php';
?>
<div class="BasicsBox">
    <?php renderBreadcrumbs([
        ['title' => $lang['cm_external_admin'], 'href' => $baseurl_short . 'pages/admin/admin_home.php', 'menu' => true],
        ['title' => $lang['manageconsents'], 'href' => $baseurl_short . 'plugins/consentmanager/pages/list.php'],
        ['title' => $lang['cm_external_view_consent'] . ' #' . $ref],
    ]); ?>
    <h1><?php echo escape($lang['cm_external_view_consent'] . ' #' . $ref); ?></h1>
    <?php if ($evidence['withdrawn']) { ?>
        <p class="consent-withdrawn-notice" role="status"><?php echo escape(
            $lang['cm_external_withdrawn_record'] . ' ' . nicedate($evidence['withdrawn'], true)
        ); ?></p>
    <?php } ?>
    <p><?php echo escape($lang['cm_external_immutable']); ?></p>
    <p><?php echo escape($evidence['signatory'] . ' / ' . $evidence['signed_at']); ?></p>
    <p><?php echo escape($lang['cm_external_hash'] . ': ' . $evidence['signed_hash']); ?></p>
    <iframe class="consent-record-document"
        src="<?php echo escape(generateURL($baseurl_short . 'plugins/consentmanager/pages/download.php', ['ref' => $ref])); ?>"
        title="<?php echo escape($lang['cm_external_document']); ?>"></iframe>
    <a href="<?php echo escape(generateURL($baseurl_short . 'plugins/consentmanager/pages/download.php', ['ref' => $ref])); ?>">
        <?php echo escape($lang['cm_external_download_signed']); ?>
    </a>
    <?php if (consentmanager_external_enabled()) { ?>
        <p><a href="<?php echo escape(generateURL(
            $baseurl_short . 'plugins/consentmanager/pages/requests.php',
            ['audit' => $evidence['ref']]
        )); ?>">
            <?php echo escape($lang['cm_external_manage']); ?>
        </a></p>
    <?php } ?>
</div>
<?php include __DIR__ . '/../../../include/footer.php'; ?>
