<?php

declare(strict_types=1);

namespace ImageBanks;

interface ProviderInstanceInterface
    {
    // Required (read-only) properties
    /**
     * @var string $name User friendly instance name. i18n strings MUST be supported
     */
    // private string $name;

    /**
     * @param string $data Raw data to be parsed to obtain the Providers' details
     * @return self|string Return a ProviderInstanceInterface or an error. The error can be either an actual error message,
     *                     or a language key (eg. image_banks_error_resourcespace_invalid_instance_cfg).
     */
    public static function parseRaw(string $data);
    
    /**
     * Get Providers' instance (user friendly) name
     */
    public function getName(): string;

    /**
     * Convert to array
     */
    public function toArray(): array;
    }
