<?php

include_once __DIR__ . '/../../../include/boot.php';
include __DIR__ . '/../../../include/authenticate.php';

$error = '';
$notice = '';
$manual_link = '';
$collection = (int) getval('collection', 0);
try {
    consentmanager_external_require_manager();
    $context = $collection > 0 ? consentmanager_external_collection_context($collection) : null;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && enforcePostRequest(false)) {
        $action = getval('cm_action', '');
        $ref = (int) getval('request', 0);
        switch ($action) {
            case 'create':
                $selected = $_POST['resources'] ?? [];
                if (!is_array($selected)) {
                    throw new InvalidArgumentException('cm_external_invalid');
                }
                $ref = consentmanager_external_create($collection, $_POST, $selected);
                $notice = $lang[consentmanager_external_send($ref) ? 'cm_external_sent' : 'cm_external_mail_failed'];
                $collection = 0;
                $context = null;
                break;
            case 'send':
                $notice = $lang[consentmanager_external_send($ref) ? 'cm_external_sent' : 'cm_external_mail_failed'];
                break;
            case 'link':
                $manual_link = consentmanager_external_issue_link($ref)['url'];
                $notice = $lang['cm_external_manual_link_ready'];
                break;
            case 'revoke':
                consentmanager_external_revoke($ref);
                $notice = $lang['cm_external_revoked'];
                break;
            case 'withdraw':
                consentmanager_external_revoke($ref, true);
                break;
            default:
                throw new InvalidArgumentException('cm_external_invalid');
        }
    }
    $request_find = getval('request_find', '');
    $request_status = getval('request_status', 'all');
    $request_list = consentmanager_external_request_page(
        $request_find,
        $request_status,
        (int) getval('offset', 0),
        (int) $default_perpage_list
    );
    $requests = $request_list['rows'];
    $consent_list_params = ['request_find' => $request_find, 'request_status' => $request_status];
    $request_detail = getval('audit', '') !== ''
        ? consentmanager_external_manage_request((int) getval('audit', 0)) : null;
    if ($request_detail) {
        $request_list['rows'] = [$request_detail];
        $request_list['total'] = 1;
    }
    $templates = consentmanager_external_templates();
    $events = getval('audit', '') !== '' ? consentmanager_external_events((int) getval('audit', 0)) : [];
} catch (Throwable $e) {
    $error = $lang[$e->getMessage()] ?? $lang['cm_external_failed'];
}
include __DIR__ . '/../../../include/header.php';
?>
<div class="BasicsBox">
    <h1><?php echo escape($lang[$collection > 0 ? 'cm_external_request' : 'cm_external_requests']); ?></h1>
    <?php if ($error !== '') {
        ?><p role="alert"><?php echo escape($error); ?></p><?php
    }
    if ($notice !== '') {
        ?><p role="status"><?php echo escape($notice); ?></p><?php
    }
    if ($manual_link !== '') { ?>
        <div class="Question">
            <label for="cm-manual-link"><?php echo escape($lang['cm_external_recipient_link']); ?></label>
            <input class="stdwidth" id="cm-manual-link" type="url" readonly
                value="<?php echo escape($manual_link); ?>" onclick="this.select();">
            <a href="#" onclick="const input=document.getElementById('cm-manual-link'); input.select(); if(navigator.clipboard){navigator.clipboard.writeText(input.value).catch(function(){});} return false;">
                <i class="icon-copy" aria-hidden="true"></i> <?php echo escape($lang['cm_external_copy_link']); ?>
            </a>
            <div class="clearerleft"></div>
        </div>
    <?php }
    if (isset($requests)) { ?>
        <?php
        $trail = [
            ['title' => $lang['cm_external_admin'], 'href' => $baseurl_short . 'pages/admin/admin_home.php', 'menu' => true],
            ['title' => $lang['manageconsents'], 'href' => $baseurl_short . 'plugins/consentmanager/pages/list.php'],
            ['title' => $request_detail ? $lang['cm_external_request_reference'] . ' #' . $request_detail['ref']
                : $lang[$context ? 'cm_external_request' : 'cm_external_requests']],
        ];
        renderBreadcrumbs($trail);
        ?>
        <?php if ($context) { ?>
            <h2><?php echo escape($context['collection']['name']); ?></h2>
            <p><?php echo escape($lang['cm_external_owner'] . ': ' . $userfullname); ?></p>
        <?php } ?>

        <?php if ($collection === 0) {
            include __DIR__ . '/../include/external_request_list.php';
        } ?>
        <?php if ($events) { ?>
            <h2><?php echo escape($lang['cm_external_audit']); ?></h2>
            <ul>
                <?php foreach ($events as $event) { ?>
                    <li><?php echo escape($event['created'] . ' / ' . $event['event'] . ' / '
                        . $event['revision'] . ' / ' . $event['document_hash']); ?></li>
                <?php } ?>
            </ul>
        <?php }
        if ($context && !$context['resources']) { ?>
            <p><?php echo escape($lang['cm_external_empty']); ?></p>
        <?php } elseif ($context && !$templates) { ?>
            <p><?php echo escape($lang['cm_external_no_templates']); ?></p>
        <?php } elseif ($context) { ?>
            <h2><?php echo escape($lang['cm_external_new']); ?></h2>
            <form method="post" onsubmit="return CentralSpacePost(this, true);">
                <?php generateFormToken('cm_external_create'); ?>
                <input type="hidden" name="cm_action" value="create">
                <?php foreach (['recipient_name', 'recipient_email', 'guardian', 'personal_message'] as $field) { ?>
                    <div class="Question">
                        <label for="cm-<?php echo escape($field); ?>"><?php echo escape($lang['cm_external_' . $field]); ?></label>
                        <?php if (in_array($field, ['usage_text', 'personal_message'], true)) { ?>
                            <textarea class="stdwidth" rows="4" id="cm-<?php echo escape($field); ?>"
                                name="<?php echo escape($field); ?>" maxlength="5000"
                                <?php echo $field === 'usage_text' ? 'required' : ''; ?>></textarea>
                        <?php } else { ?>
                        <input class="stdwidth" id="cm-<?php echo escape($field); ?>" name="<?php echo escape($field); ?>"
                            type="<?php echo $field === 'recipient_email' ? 'email' : 'text'; ?>"
                            maxlength="<?php echo in_array($field, ['usage_text', 'personal_message'], true) ? 5000 : 200; ?>"
                            <?php echo in_array($field, ['recipient_name', 'recipient_email', 'usage_text'], true) ? 'required' : ''; ?>>
                        <?php } ?>
                        <div class="clearerleft"></div>
                    </div>
                <?php } ?>
                <div class="Question">
                    <label><?php echo escape($lang['usage']); ?></label>
                    <fieldset class="tickset">
                        <legend class="accessibility-hidden"><?php echo escape($lang['usage']); ?></legend>
                        <?php foreach ($consent_usage_mediums as $index => $medium) { ?>
                            <div class="Inline">
                                <input type="checkbox" name="consent_usage[]" id="cm-usage-<?php echo (int) $index; ?>"
                                    value="<?php echo escape($medium); ?>">
                                <label for="cm-usage-<?php echo (int) $index; ?>">
                                    <?php echo escape(lang_or_i18n_get_translated($medium, 'consent_usage-')); ?>
                                </label>
                            </div>
                        <?php } ?>
                    </fieldset>
                    <div class="clearerleft"></div>
                </div>
                <div class="Question">
                    <label for="cm-template"><?php echo escape($lang['cm_external_template']); ?></label>
                    <select class="stdwidth" id="cm-template" name="template" required>
                        <?php foreach ($templates as $template) { ?>
                            <option value="<?php echo (int) $template['ref']; ?>"
                                <?php echo $template['ref'] == $consentmanager_external_default_template ? 'selected' : ''; ?>>
                                <?php echo escape($template['title']); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <div class="clearerleft"></div>
                </div>
                <div class="Question">
                    <label for="cm-expires"><?php echo escape($lang['cm_external_expires']); ?></label>
                    <input class="stdwidth" id="cm-expires" name="expires" type="date" required min="<?php echo date('Y-m-d'); ?>"
                        value="<?php echo date('Y-m-d', strtotime('+' . max(1, (int) $consentmanager_external_validity_days) . ' days')); ?>">
                    <div class="clearerleft"></div>
                </div>
                <div class="Question">
                    <label for="cm-consent-expires"><?php echo escape($lang['cm_external_consent_expires']); ?></label>
                    <input class="stdwidth" id="cm-consent-expires" name="consent_expires" type="date" min="<?php echo date('Y-m-d'); ?>">
                    <div class="clearerleft"></div>
                </div>
                <fieldset>
                    <legend><?php echo escape($lang['cm_external_assets']); ?></legend>
                    <div class="consent-asset-picker">
                        <?php foreach ($context['assets'] as $asset) { ?>
                            <label class="consent-asset-option">
                                <span class="consent-asset-preview">
                                    <?php if ($asset['preview'] !== '') { ?>
                                        <img loading="lazy" src="<?php echo escape($asset['preview']); ?>"
                                            alt="<?php echo escape('#' . $asset['ref']); ?>">
                                    <?php } else {
                                        echo $asset['fallback'];
                                    } ?>
                                </span>
                                <span>
                                    <input name="resources[]" type="checkbox" checked value="<?php echo (int) $asset['ref']; ?>">
                                    <?php echo escape('#' . $asset['ref']); ?>
                                </span>
                            </label>
                        <?php } ?>
                    </div>
                </fieldset>
                <p><?php echo escape($lang['cm_external_snapshot_notice']); ?></p>
                <div class="QuestionSubmit">
                    <input type="submit" value="<?php echo escape($lang['cm_external_create_draft']); ?>">
                </div>
            </form>
        <?php }
    } ?>
</div>
<?php include __DIR__ . '/../../../include/footer.php'; ?>
