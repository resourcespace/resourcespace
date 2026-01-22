<?php

declare(strict_types=1);

namespace SimpleSAML\Module\metarefresh;

use Exception;
use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Metadata\MetaDataStorageSource;

class MetaRefresh
{
    /**
     * @var \SimpleSAML\Configuration
     */
    private Configuration $config;

    /**
     * @var \SimpleSAML\Configuration
     */
    private Configuration $modconfig;

    /**
     * @param \SimpleSAML\Configuration              $config The configuration to use by the module.
     * @param \SimpleSAML\Configuration              $modconfig The module-specific configuration to use by the module.
     */
    public function __construct(Configuration $config, Configuration $modconfig)
    {
        $this->config = $config;
        $this->modconfig = $modconfig;
    }

    /**
     * @param string $crontag Only refresh sets which allow this crontag
     */
    public function runRefresh(string $crontag = null): void
    {
        $sets = $this->modconfig->getArray('sets');
        /** @var string $datadir */
        $datadir = $this->config->getPathValue('datadir', 'data/');
        $stateFile = $datadir . 'metarefresh-state.php';

        foreach ($sets as $setkey => $set) {
            $set = Configuration::loadFromArray($set);

            // Only process sets where cron matches the current cron tag
            $cronTags = $set->getArray('cron');
            if ($crontag !== null && !in_array($crontag, $cronTags, true)) {
                Logger::debug('[metarefresh]: Skipping set [' . $setkey . '], not allowed for cron tag ' . $crontag);
                continue;
            }

            Logger::info('[metarefresh]: Executing set [' . $setkey . ']');

            $expireAfter = $set->getOptionalInteger('expireAfter', null);
            if ($expireAfter !== null) {
                $expire = time() + $expireAfter;
            } else {
                $expire = null;
            }

            $outputDir = $set->getString('outputDir');
            $outputDir = $this->config->resolvePath($outputDir);
            if ($outputDir === null) {
                throw new Exception("Invalid outputDir specified.");
            }

            $outputFormat = $set->getValueValidate('outputFormat', ['flatfile', 'serialize', 'pdo'], 'flatfile');

            $oldMetadataSrc = MetaDataStorageSource::getSource([
                'type' => $outputFormat,
                'directory' => $outputDir,
            ]);

            $metaloader = new MetaLoader($expire, $stateFile, $oldMetadataSrc);

            // Get global blacklist, whitelist, attributewhitelist and caching info
            $blacklist = $this->modconfig->getOptionalArray('blacklist', []);
            $whitelist = $this->modconfig->getOptionalArray('whitelist', []);
            $attributewhitelist = $this->modconfig->getOptionalArray('attributewhitelist', []);
            $conditionalGET = $this->modconfig->getOptionalBoolean('conditionalGET', false);

            // get global type filters
            $available_types = [
                'saml20-idp-remote',
                'saml20-sp-remote',
                'attributeauthority-remote',
            ];
            $set_types = $set->getOptionalArray('types', $available_types);

            foreach ($set->getArray('sources') as $source) {
                // filter metadata by type of entity
                if (isset($source['types'])) {
                    $metaloader->setTypes($source['types']);
                } else {
                    $metaloader->setTypes($set_types);
                }

                // Merge global and src specific blacklists
                if (isset($source['blacklist'])) {
                    $source['blacklist'] = array_unique(array_merge($source['blacklist'], $blacklist));
                } else {
                    $source['blacklist'] = $blacklist;
                }

                // Merge global and src specific whitelists
                if (isset($source['whitelist'])) {
                    $source['whitelist'] = array_unique(array_merge($source['whitelist'], $whitelist));
                } else {
                    $source['whitelist'] = $whitelist;
                }

                # Merge global and src specific attributewhitelists: cannot use array_unique for multi-dim.
                if (isset($source['attributewhitelist'])) {
                    $source['attributewhitelist'] = array_merge($source['attributewhitelist'], $attributewhitelist);
                } else {
                    $source['attributewhitelist'] = $attributewhitelist;
                }

                // Let src specific conditionalGET override global one
                if (!isset($source['conditionalGET'])) {
                    $source['conditionalGET'] = $conditionalGET;
                }

                Logger::debug('[metarefresh]: In set [' . $setkey . '] loading source [' . $source['src'] . ']');
                $metaloader->loadSource($source);
            }

            // Write state information back to disk
            $metaloader->writeState();

            switch ($outputFormat) {
                case 'flatfile':
                    $metaloader->writeMetadataFiles($outputDir);
                    break;
                case 'serialize':
                    $metaloader->writeMetadataSerialize($outputDir);
                    break;
                case 'pdo':
                    $metaloader->writeMetadataPdo($this->config);
                    break;
            }

            if ($set->hasValue('arp')) {
                $arpconfig = Configuration::loadFromArray($set->getValue('arp'));
                $metaloader->writeARPfile($arpconfig);
            }
        }
    }
}
