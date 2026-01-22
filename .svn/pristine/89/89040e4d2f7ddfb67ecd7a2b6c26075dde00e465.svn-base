<?php

declare(strict_types=1);

use SimpleSAML\Locale\Translate;
use SimpleSAML\Module;
use SimpleSAML\XHTML\Template;

/**
 * Hook to add the statistics module to the config page.
 *
 * @param \SimpleSAML\XHTML\Template &$template The template that we should alter in this hook.
 */
function statistics_hook_configpage(Template &$template): void
{
    $template->data['links'][] = [
        'href' => Module::getModuleURL('statistics/'),
        'text' => Translate::noop('Show statistics'),
    ];

    $template->data['links'][] = [
        'href' => Module::getModuleURL('statistics/metadata'),
        'text' => Translate::noop('Show statistics metadata'),
    ];

    $template->getLocalization()->addModuleDomain('statistics');
}
