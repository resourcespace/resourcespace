<?php

declare(strict_types=1);

use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use Webmozart\Assert\Assert;

/**
 * Hook to run a cron job.
 *
 * @param array &$croninfo  Output
 */
function metarefresh_hook_cron(array &$croninfo): void
{
    Assert::keyExists($croninfo, 'summary');
    Assert::keyExists($croninfo, 'tag');

    Logger::info('cron [metarefresh]: Running cron in cron tag [' . $croninfo['tag'] . '] ');

    $config = Configuration::getInstance();
    $mconfig = Configuration::getOptionalConfig('module_metarefresh.php');

    $mf = new \SimpleSAML\Module\metarefresh\MetaRefresh($config, $mconfig);

    try {
        $mf->runRefresh($croninfo['tag']);
    } catch (\Exception $e) {
        $croninfo['summary'][] = 'Error during metarefresh: ' . $e->getMessage();
    }
}
