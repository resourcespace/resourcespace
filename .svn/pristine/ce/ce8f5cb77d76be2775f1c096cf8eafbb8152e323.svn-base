<?php

declare(strict_types=1);

namespace ImageBanks;

final class ResourceSpaceProviderInstance implements ProviderInstanceInterface
    {
    private string $name;
    private string $baseURL;
    private string $username;
    private string $key;
    private array $configuration;

    private function __construct(string $name, array $details)
        {
        $this->name = i18n_get_translated($name);
        $this->baseURL = $details['baseURL'];
        $this->username = $details['username'];
        $this->key = $details['key'];
        $this->configuration = $details['configuration'];
        }

    public static function parseRaw(string $data)
        {
        $data_parts = array_values(array_filter(array_map('trim', explode('|', $data))));
        $data_parts_count = count($data_parts);
        if ($data_parts_count > 0 && $data_parts_count < 5)
            {
            return 'image_banks_error_resourcespace_invalid_instance_cfg';
            }
        [$name, $baseURL, $username, $key, $json_cfg] = $data_parts;

        if ('http' !== mb_strtolower(mb_substr($baseURL, 0, 4)))
            {
            return 'image_banks_error_bad_url_scheme';
            }

        $configuration = json_decode($json_cfg, true);
        if (json_last_error() !== JSON_ERROR_NONE)
            {
            return '(JSON) ' . json_last_error_msg();
            }

        return new self(
            $name,
            [
                'baseURL' => $baseURL,
                'username' => $username,
                'key' => $key,
                'configuration' => $configuration,
            ]
        );
        }

    public function getName(): string
        {
        return $this->name;
        }

    public function toArray(): array
        {
        return [
            'baseURL' => $this->baseURL,
            'username' => $this->username,
            'key' => $this->key,
            'configuration' => $this->configuration,
        ];
        }
    }
