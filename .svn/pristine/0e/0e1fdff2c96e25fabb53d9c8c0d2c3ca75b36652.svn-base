<?php

declare(strict_types=1);

use SimpleSAML\Locale\Translate;
use SimpleSAML\Module;
use SimpleSAML\XHTML\Template;

/**
 * Hook to add the consentAdmin module to the config page.
 *
 * @param \SimpleSAML\XHTML\Template $template The template that we should alter in this hook.
 * @return void
 */
function consentAdmin_hook_configpage(Template &$template): void
{
    $template->data['links'][] = [
        'href' => Module::getModuleURL('consentAdmin/'),
        'text' => Translate::noop('Consent administration'),
    ];

    $template->getLocalization()->addModuleDomain('consentAdmin');
}
