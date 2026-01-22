<?php

declare(strict_types=1);

namespace ImageBanks;

use SplFileInfo;

/**
 * Helper type used to indicate the absence of a (selected) Provider
 */
class NoProvider extends Provider
    {
    function __construct(array $lang, string $temp_dir_path)
            {
            $this->id = 999;
            $this->name = $lang['unknown'];
            $this->download_endpoint = generateSecureKey(16);
            $this->configs = [];
            $this->warning = '';
            $this->lang = $lang;
            $this->temp_dir_path = $temp_dir_path;
            }

    /** @inheritdoc */
    public function checkDependencies(): array
        {
        return [];
        }

    /** @inheritdoc */
    public function buildConfigPageDefinition(array $page_def): array
        {
        return $page_def;
        }

    /** @inheritdoc */
    public function runSearch($keywords, $per_page = 24, $page = 1, array $ctx = []): ProviderSearchResults
        {
        return new ProviderSearchResults();
        }

    /** @inheritdoc */
    public function getDownloadFileInfo(string $file): SplFileInfo
        {
        return new SplFileInfo('');
        }
    }
