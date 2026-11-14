<?php
// Rendering only; request and response access is handled by the controller and plugin helpers.
$preview = $preview ?? false;
$portal_url = $preview
    ? $baseurl . '/plugins/consentmanager/pages/recipient_preview.php'
    : consentmanager_external_portal_url();
$portal_params = $preview ? ['request' => $request['ref'] ?? 0] : ['token' => $token];
$asset_url = $baseurl_short . 'plugins/consentmanager/';
?>
<!doctype html>
<html lang="<?php echo escape($language ?? 'en'); ?>"
    <?php if (!empty($GLOBALS['colour_theme'])) { ?>class="mode-<?php echo escape($GLOBALS['colour_theme']); ?>"<?php } ?>>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo escape($lang['cm_external_portal_title']); ?></title>
    <link rel="stylesheet" href="<?php echo escape($baseurl_short . 'css/global.css'); ?>">
    <link rel="stylesheet" href="<?php echo escape($baseurl_short . 'css/light.css'); ?>">
    <link rel="stylesheet" href="<?php echo escape(generateURL($baseurl_short . 'css/css_override.php', ['noauth' => 'true'])); ?>">
    <link rel="stylesheet" href="<?php echo escape($asset_url . 'css/external.css'); ?>">
    <script defer src="<?php echo escape($asset_url . 'js/draw.js'); ?>"></script>
    <script defer src="<?php echo escape($asset_url . 'js/signature.js'); ?>"></script>
</head>
<body>
<main>
    <img class="cm-logo" src="<?php echo escape(get_header_image(true)); ?>"
        alt="<?php echo escape($applicationname); ?>">
    <h1><?php echo escape($lang['cm_external_portal_title']); ?></h1>
    <?php if ($preview) { ?>
        <p class="cm-success"><?php echo escape($lang['cm_external_preview_notice']); ?></p>

    <?php } ?>
    <?php if ($error !== '') { ?>
        <p class="cm-error" role="alert"><?php echo escape($error); ?></p>
    <?php }
    if ($declined) { ?>
        <p><?php echo escape($lang['cm_external_decline_confirmed']); ?></p>
    <?php } elseif (isset($request)) {
        $terms = consentmanager_external_json($request['terms']);
        $response = $preview_response ?? consentmanager_external_response($request['ref']);
        $answers = $response['responses'] ? consentmanager_external_json($response['responses']) : [];
        $is_signed = $request['status'] === 'signed' || $request['status'] === 'completed';
        $document_url = generateURL($portal_url, $portal_params, [
            'action' => 'pdf',
            'signed' => $is_signed ? '1' : '0',
            'revision' => $response['revision'],
        ]);
        ?>
        <?php if ($preview && isset($response['signed_pdf'])) {
            $document_url = 'data:application/pdf;base64,' . base64_encode($response['signed_pdf']);
        } ?>
        <h2><?php echo escape($terms['organisation'] . ' / ' . $terms['title']); ?></h2>
        <p><?php echo escape('CM-' . $request['ref']); ?></p>
        <?php if ($request['status'] === 'completed') { ?>
            <p class="cm-success" role="status"><?php echo escape($lang['cm_external_submission_confirmed']); ?></p>
            <?php if ($consentmanager_external_recipient_copy || $preview) { ?>
                <a class="cm-link" href="<?php echo escape($document_url); ?>" target="_blank" rel="noopener"
                    <?php echo $preview_response !== null ? 'download="consent-preview.pdf"' : ''; ?>>
                    <?php echo escape($lang['cm_external_download_signed']); ?>
                </a>
            <?php }
        } else { ?>
            <?php if (!$is_signed) { ?>
            <h2><?php echo escape($lang['cm_external_assets']); ?></h2>
            <div class="cm-assets">
                <?php foreach (consentmanager_external_resources($request['ref']) as $resource) {
                    $media = consentmanager_external_media($request, (int) $resource['resource']);
                    $url = generateURL($portal_url, $portal_params, ['action' => 'media', 'resource' => $resource['resource']]);
                    ?>
                    <figure>
                        <?php if ($media && $media[1] === 'image/jpeg') { ?>
                            <img loading="lazy" src="<?php echo escape($url); ?>" alt="<?php echo escape($resource['identification']); ?>">
                        <?php } elseif ($media && str_starts_with($media[1], 'video/')) { ?>
                            <video controls preload="none" aria-label="<?php echo escape($resource['identification']); ?>" src="<?php echo escape($url); ?>"></video>
                        <?php } elseif ($media && $media[1] === 'audio/mpeg') { ?>
                            <audio controls preload="none" aria-label="<?php echo escape($resource['identification']); ?>" src="<?php echo escape($url); ?>"></audio>
                        <?php } else { ?>
                            <p><?php echo escape($lang['cm_external_no_preview']); ?></p>
                        <?php } ?>
                        <figcaption><?php echo escape($resource['identification']); ?></figcaption>
                        <?php if ($consentmanager_external_originals) { ?>
                            <a href="<?php echo escape(generateURL(
                                $portal_url,
                                $portal_params,
                                ['action' => 'media', 'resource' => $resource['resource'], 'original' => '1']
                            )); ?>">
                                <?php echo escape($lang['cm_external_original']); ?>
                            </a>
                        <?php } ?>
                    </figure>
                <?php } ?>
            </div>
            <?php }
            if (!$is_signed) { ?>
                <h2><?php echo escape($lang['cm_external_form']); ?></h2>
                <div class="cm-terms"><?php echo escape($terms['wording']); ?></div>
                <h3><?php echo escape($lang['cm_external_usage']); ?></h3>
                <p class="cm-terms"><?php echo escape($request['usage_text']); ?></p>
                <p class="cm-terms"><?php echo escape($terms['privacy']); ?></p>
                <p class="cm-terms"><?php echo escape($terms['duration']); ?></p>
                <p><?php echo escape($lang['cm_external_consent_expires'] . ': '
                    . ($request['consent_expires'] ?? $lang['cm_external_no_expiry'])); ?></p>
                <form method="post">
                    <?php generateFormToken('cm_external_form'); ?>
                    <input type="hidden" name="action" value="form">
                    <label for="cm-name"><?php echo escape($lang['cm_external_name']); ?></label>
                    <input id="cm-name" name="name" required maxlength="200" autocomplete="name"
                        value="<?php echo escape($answers['name'] ?? $request['recipient_name']); ?>">
                    <label for="cm-guardian"><?php echo escape($lang['cm_external_guardian']); ?></label>
                    <input id="cm-guardian" name="guardian" maxlength="200"
                        value="<?php echo escape($answers['guardian'] ?? $request['guardian']); ?>">
                    <fieldset>
                        <legend><?php echo escape($lang['cm_external_declarations']); ?></legend>
                        <p class="cm-terms"><?php echo escape($terms['declarations']); ?></p>
                        <label><input type="checkbox" name="declarations" value="yes" required>
                            <?php echo escape($lang['cm_external_accept_declarations']); ?>
                        </label>
                    </fieldset>
                    <?php if ($terms['options']) { ?>
                        <fieldset>
                            <legend><?php echo escape($lang['cm_external_options']); ?></legend>
                            <?php foreach ($terms['options'] as $index => $label) { ?>
                                <label>
                                    <input type="checkbox" name="options[<?php echo (int) $index; ?>]" value="yes"
                                        <?php echo !empty($answers['options'][$index]) ? 'checked' : ''; ?>>
                                    <?php echo escape($label); ?>
                                </label>
                            <?php } ?>
                        </fieldset>
                    <?php }
                    foreach ($terms['fields'] as $index => $label) { ?>
                        <label for="cm-field-<?php echo (int) $index; ?>"><?php echo escape($label); ?></label>
                        <input id="cm-field-<?php echo (int) $index; ?>" name="fields[<?php echo (int) $index; ?>]"
                            maxlength="1000" required value="<?php echo escape($answers['fields'][$index] ?? ''); ?>">
                    <?php } ?>
                    <h2><?php echo escape($lang['cm_external_signature_page']); ?></h2>
                    <?php include __DIR__ . '/external_signature_fields.php'; ?>
                    <div class="cm-step-actions">
                        <button type="submit"><?php echo escape($lang['cm_external_next']); ?></button>
                    </div>
                </form>
            <?php } else { ?>
                <h2><?php echo escape($lang['cm_external_review_signed']); ?></h2>
                <?php if ($is_signed) { ?>
                    <p role="status"><?php echo escape($lang['cm_external_not_submitted']); ?></p>
                <?php } ?>
                <p><?php echo escape($lang['cm_external_pdf_help']); ?></p>
                <a class="cm-link" href="<?php echo escape($document_url); ?>" target="_blank" rel="noopener"
                    <?php echo $preview_response !== null ? 'download="consent-preview.pdf"' : ''; ?>>
                    <?php echo escape($lang['cm_external_open_pdf']); ?>
                </a>
                <iframe class="cm-document" src="<?php echo escape($document_url); ?>" title="<?php echo escape($lang['cm_external_document']); ?>"></iframe>
                <form method="post">
                    <?php generateFormToken('cm_external_correct'); ?>
                    <input type="hidden" name="action" value="correct">
                    <?php if ($preview) { ?>
                        <input type="hidden" name="preview_responses" value="<?php echo escape($response['responses']); ?>">
                    <?php } ?>
                    <button type="submit"><?php echo escape($lang['cm_external_correct']); ?></button>
                </form>

                    <form method="post">
                        <?php generateFormToken('cm_external_submit'); ?>
                        <input type="hidden" name="action" value="submit">
                        <input type="hidden" name="revision" value="<?php echo (int) $response['revision']; ?>">
                        <input type="hidden" name="hash" value="<?php echo escape($response['signed_hash']); ?>">
                        <label><input type="checkbox" name="approve_signed" value="yes" required>
                            <?php echo escape($lang['cm_external_sign_confirm']); ?>
                        </label>
                        <button type="submit" <?php echo $preview ? 'disabled' : ''; ?>><?php echo escape($lang['cm_external_submit']); ?></button>
                    </form>
            <?php } ?>
            <form method="post">
                <?php generateFormToken('cm_external_decline'); ?>
                <input type="hidden" name="action" value="decline">
                <button class="cm-decline" type="submit" <?php echo $preview ? 'disabled' : ''; ?>>
                    <span aria-hidden="true">&#10005;</span> <?php echo escape($lang['cm_external_decline']); ?>
                </button>
            </form>
        <?php }
    }
    ?>
</main>
</body>
</html>
