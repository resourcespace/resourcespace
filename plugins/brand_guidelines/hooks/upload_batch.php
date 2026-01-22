<?php

declare(strict_types=1);

/**
 * Ensure we always pass along the Brand Guidelines callback URL, if one was provided.
 * @global array $redirecturl
 */
function HookBrand_guidelinesUpload_batchModify_redirecturl()
{
    $guidelines_cb = getval(
        'guidelines_cb',
        '',
        false,
        'Montala\ResourceSpace\Plugins\BrandGuidelines\is_brand_guidelines_callback'
    );
    if ($guidelines_cb !== '') {
        return $GLOBALS['redirecturl'] . "&guidelines_cb=" . urlencode($guidelines_cb);
    } else {
        return false;
    }
}

/**
 * IMPORTANT: Directly within Javascript world on the upload_batch page!
 *
 * Complements the following hooks:
 * - HookBrand_guidelinesEditEditbeforeheader
 * - HookBrand_guidelinesEditRedirectaftersavetemplate
 * which are used in the edit then upload mode. Here the logic will modify the "redirurl" URL variable and inject the
 * newly uploaded resource ID required by the plugin.
 */
function HookBrand_guidelinesUpload_batchPostUploadActions_before_csl_redirurl_js(): void
{
    printf('<!-- Logic for %s -->%s', escape(__FUNCTION__), PHP_EOL);
    ?>
    if (redirurl.startsWith('<?php echo escape(BRAND_GUIDELINES_URL_MANAGE_CONTENT); ?>')) {
        console.debug('HookBrand_guidelinesAllPostUploadActions_before_csl_redirurl_js specific logic ...');
        redirurl += `&w_ref=${
            resource_keys
                .map((v) => parseInt(v, 10))
                .find((v) => v > 0)
        }`;
        CentralSpaceHideProcessing();
        rscompleted = [];
        processerrors = [];
        return ModalLoad(redirurl, true, true);
    }
    <?php
    printf('<!-- Logic (end) for %s -->%s', escape(__FUNCTION__), PHP_EOL);
}
