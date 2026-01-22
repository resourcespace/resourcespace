<?php

declare(strict_types=1);

namespace ImageBanks;

use RuntimeException;
use SplFileInfo;

class ResourceSpace extends Provider implements MultipleInstanceProviderInterface
    {
    /**
     * Only valid instances
     * @var list<ResourceSpaceProviderInstance>
     */
    private array $instances = [];
    private int $selected_instance_id;

    function __construct(array $lang, string $temp_dir_path)
            {
            $this->id = 3;
            $this->name = "ResourceSpace";
            $this->configs = [
                'resourcespace_instances_cfg' => '',
                // Loaded from global ResourceSpace configs
                'default_sort' => 'relevance',
                'popularity_sort' => true,
                'orderbyrating' => false,
                'date_column' => false,
                'colour_sort' => true,
                'order_by_resource_id' => true,

            ];
            $this->warning = "";
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
        $page_def[] = config_add_text_input(
            'resourcespace_instances_cfg',
            $this->lang['image_banks_label_resourcespace_instances_cfg'],
            false,
            800,
            true
        );
        return $page_def;
        }

    /** @inheritdoc */
    public function runSearch(string $keywords, int $per_page = 24, int $page = 1, array $ctx = []): ProviderSearchResults
        {
        $per_page = $per_page > 0 ? $per_page : $GLOBALS['default_perpage'];
        $page = $page > 0 ? $page : 1;
        $offset = ($page - 1) * $per_page;

        try
            {
            $instance = $this->getSelectedSystemInstance()->toArray();
            $instance_cfg = $instance['configuration'];
            $cache_id = md5("{$instance['baseURL']}--{$keywords}--{$ctx['order_by']}--{$per_page}--{$page}");
            $api_cached_results = $this->getCache($cache_id, 1);
            if($api_cached_results)
                {
                $api_results = json_decode($api_cached_results, true);
                }
            else
                {
                $api_results = $this->callApi(
                    'do_search',
                    [
                        'search' => $keywords,
                        'fetchrows' => "{$offset},{$per_page}",
                        'order_by' => $ctx['order_by'],
                    ]
                );
                $this->setCache($cache_id, json_encode($api_results));
                }
            }
        catch (RuntimeException $r)
            {
            $search_results = new ProviderSearchResults();
            $search_results->setError($r->getMessage());
            return $search_results;
            }

        $view_title_field = $instance_cfg['view_title_field'] ?? $GLOBALS['view_title_field'];
        $results = new ProviderSearchResults();
        $results->total = $api_results['total'];

        foreach($api_results['data'] as $row)
            {
            $item = (new ProviderResult($row['ref'], $this))
                ->setTitle((string) $row["field{$view_title_field}"])
                ->setProviderUrl(generateURL($instance['baseURL'], ['r' => $row['ref']]));

            try
                {
                $cache_id = md5("{$instance['baseURL']}--get_resource_all_image_sizes--{$row['ref']}");
                $api_cached_results = $this->getCache($cache_id, 10);
                if($api_cached_results)
                    {
                    $resource_sizes = json_decode($api_cached_results, true);
                    }
                else
                    {
                    $resource_sizes = $this->callApi('get_resource_all_image_sizes', ['resource' => $row['ref']]);
                    $this->setCache($cache_id, json_encode($resource_sizes));
                    }
                }
            catch (RuntimeException $r)
                {
                $resource_sizes = [];
                }

            foreach ($resource_sizes as $rsize)
                {
                // Select the original file (if allowed), otherwise go for the next available high resolution version
                if (in_array($rsize['size_code'], ['original', 'hpr', 'lpr']) && $item->getOriginalFileUrl() === null)
                    {
                    $item = $item->setOriginalFileUrl($rsize['url']);
                    }
                elseif ($rsize['size_code'] === 'thm')
                    {
                    $item = $item
                        ->setPreviewUrl($rsize['url'])
                        ->setPreviewWidth($row['thumb_width'])
                        ->setPreviewHeight($row['thumb_height']);
                    }
                }

            // When we don't have a way to get the preview information (e.g API fail, or returned nothing/too litle), use the nopreview
            if ($item->getPreviewUrl() === null)
                {
                $item = $item
                    ->setPreviewUrl(sprintf(
                        '%s/gfx/%s',
                        $GLOBALS['baseurl'],
                        "no_preview/default.png"
                    ))
                    ->setPreviewWidth(128)
                    ->setPreviewHeight(128);
                }

            $results[] = $item;
            }

        return $results;
        }

    /** @inheritdoc */
    public function getDownloadFileInfo(string $file): SplFileInfo
        {
        $file_url_path = parse_url($file, PHP_URL_PATH);
        if (basename($file_url_path) === 'download.php')
            {
            parse_str(parse_url($file, PHP_URL_QUERY), $qs_params);
            $ref = $qs_params['ref'] ?? '';
            $ext = $qs_params['ext'] ?? '';
            }
        elseif (preg_match('/\/filestore(?:\/\d+)*\w+\/(\d+)\w+\.([a-zA-Z0-9]{1,10})/', $file_url_path, $matches))
            {
            [, $ref, $ext] = $matches;
            }
        return new SplFileInfo("$ref.$ext");
        }

    /**
     * Helper method to use ResourceSpace API
     *
     * @param string $function API binding function name
     * @param array $data Request payload
     * @return array|int|string JSON decoded data, as received from the API binding
     */
    function callApi(string $function, array $data)
        {
        $instance = $this->getSelectedSystemInstance();
        $err_msg_prefix = sprintf('%s - %s: ', $this->name, $instance->getName());
        $api = $instance->toArray();

        // Build request & send
        $query = http_build_query(
            array_merge(
                [
                    'user' => $api['username'],
                    'language' => $GLOBALS['language'] ?? $GLOBALS['defaultlanguage'],
                    'function' => $function,
                ],
                $data
            )
        );
        $sign = hash('sha256', $api['key'] . $query);
        $request = file_get_contents(
            "{$api['baseURL']}/api/?$query&sign=$sign",
            false,
            stream_context_create([
                'http' => [
                    'ignore_errors' => true,
                    'user_agent' => sprintf('ResourceSpace-Plugin-ImageBanks/1.0 (%s)', $GLOBALS['baseurl']),
                ],
            ])
        );
        $status_code = preg_match('/\d{3}/', $http_response_header[0], $match) ? (int) $match[0] : 0;
        $results = json_decode($request, true);

        // Handle generic fails (simple string responses)
        if ($status_code !== 200 && JSON_ERROR_NONE !== json_last_error())
            {
            throw new RuntimeException($err_msg_prefix . $request);
            }
        // Handle generic (structured) errors (usually done using ajax_functions.php)
        elseif ($status_code !== 200 && isset($results['error']['detail']))
            {
            throw new RuntimeException($err_msg_prefix . $results['error']['detail']);
            }
        elseif ($status_code === 200 && JSON_ERROR_NONE !== json_last_error())
            {
            throw new RuntimeException("$err_msg_prefix (JSON) " . json_last_error_msg());
            }

        return $results;
        }

    /** @inheritdoc */
    public function parseInstancesConfiguration(): array
        {
        $errs = [];
        $raw_instances = array_values(array_filter(explode(PHP_EOL, trim($this->configs['resourcespace_instances_cfg']))));
        $create_instance_id_from = createProviderInstanceId($this);
        foreach ($raw_instances as $id => $data)
            {
            $parsed = ResourceSpaceProviderInstance::parseRaw($data);
            if ($parsed instanceof ResourceSpaceProviderInstance)
                {
                $this->instances[$create_instance_id_from($id)] = $parsed;
                continue;
                }
            $errs[] = $this->lang[$parsed] ?? "%PROVIDER - $parsed";
            }
        return $errs;
        }

    /** @inheritdoc */
    public function getAllInstances(): array
        {
        return $this->instances;
        }

    /** @inheritdoc */
    public function selectSystemInstance(int $id): Provider
        {
        $this->selected_instance_id = $id;
        $this->download_endpoint = ($this->getSelectedSystemInstance()->toArray())['baseURL'];
        return $this;
        }

    /** @inheritdoc */
    public function getSelectedSystemInstance(): ResourceSpaceProviderInstance
        {
        return $this->instances[$this->selected_instance_id];
        }

    /** @inheritdoc */
    public function allowViewPage(): bool
        {
        return true;
        }

    /**
     * @inheritdoc
     * @param int $id Resource ref
     */
    public function findById($id): ProviderResult
        {
        try
            {
            $instance = $this->getSelectedSystemInstance()->toArray();
            $instance_cfg = $instance['configuration'];

            $view_title_field = $instance_cfg['view_title_field'] ?? $GLOBALS['view_title_field'];
            $title = $this->callApi(
                'get_data_by_field',
                [
                    'ref' => $id,
                    'field' => $view_title_field
                ]
            );

            $resource_data = $this->callApi('get_resource_data', ['resource' => $id]);
            $preview_sizes = $this->callApi('get_resource_all_image_sizes', ['resource' => $id]);
            }
        catch (RuntimeException $e)
            {
            debug(sprintf('[image_banks][%s] %s', __METHOD__, $e->getMessage()));
            $title ??= '';
            $preview_sizes ??= [];
            }

        $item = (new ProviderResult($id, $this))
            ->setTitle($title)
            ->setProviderUrl(generateURL($instance['baseURL'], ['r' => $id]));

        foreach ($preview_sizes as $size)
            {
            // Select the original file (if allowed), otherwise go for the next available high resolution version
            if (in_array($size['size_code'], ['original', 'hpr', 'lpr']) && $item->getOriginalFileUrl() === null)
                {
                $item = $item->setOriginalFileUrl($size['url']);
                }
            elseif ($size['size_code'] === 'pre')
                {
                $item = $item->setPreviewUrl($size['url']);
                }
            }

        if ($item->getPreviewUrl() === null)
            {
            $item = $item
                ->setPreviewUrl(sprintf(
                    '%s/gfx/%s',
                    $GLOBALS['baseurl'],
                    "no_preview/default.png"
                ))
                ->setPreviewWidth(128)
                ->setPreviewHeight(128);
            }

        return $item;
        }

    /**
     * @inheritdoc
     * @param int $id Resource ref
     */
    public function getImageNonMetadataProperties($id): array
        {
        try
            {
            $resource_data = $this->callApi('get_resource_data', ['resource' => $id]);
            $resource_types = array_column($this->callApi('get_resource_types', []), 'name', 'ref');
            $users = array_column($this->callApi('get_users', []), 'fullname', 'ref');
            }
        catch (RuntimeException $e)
            {
            debug(sprintf('[image_banks][%s] %s', __METHOD__, $e->getMessage()));
            $resource_data ??= [];
            }
        $props = [];

        if (isset($resource_data['resource_type'], $resource_types[$resource_data['resource_type']]))
            {
            $props[$this->lang['property-resource_type']] = $resource_types[$resource_data['resource_type']];
            }

        if (isset($resource_data['created_by'], $users[$resource_data['created_by']]))
            {
            $props[$this->lang['contributedby']] = $users[$resource_data['created_by']];
            }

        return $props;
        }

    /**
     * @inheritdoc
     * @param int $id Resource ref
     */
    public function getImageMetadata($id): array
        {
        try
            {
            $instance = $this->getSelectedSystemInstance()->toArray();
            $instance_cfg = $instance['configuration'];
            $view_title_field = $instance_cfg['view_title_field'] ?? $GLOBALS['view_title_field'];

            $meta = [];
            $resource_metadata = $this->callApi('get_resource_field_data', ['resource' => $id]);
            foreach ($resource_metadata as $metadata)
                {
                if ($metadata['ref'] === $view_title_field)
                    {
                    continue;
                    }

                if ($metadata['value'] !== '')
                    {
                    $meta[$metadata['title']] = $metadata['value'];
                    }
                }
            }
        catch (RuntimeException $e)
            {
            debug(sprintf('[image_banks][%s] %s', __METHOD__, $e->getMessage()));
            $meta = [];
            }

        return $meta;
        }

    /**
     * @inheritdoc
     * @param int $id Resource ref
     */
    public function getResourceDownloadOptionsTable($id): array
        {
        try
            {
            $preview_sizes = $this->callApi('get_resource_all_image_sizes', ['resource' => $id]);
            }
        catch (RuntimeException $e)
            {
            debug(sprintf('[image_banks][%s] %s', __METHOD__, $e->getMessage()));
            $preview_sizes ??= [];
            }

        foreach ($preview_sizes as $size)
            {
            if (in_array($size['size_code'], ['original', 'hpr', 'lpr']))
                {
                return [
                    'header' => [
                        $this->lang['filedimensions'],
                        $this->lang['filesize'],
                    ],
                    'data' => [
                        str_replace(
                            '%SIZE_CODE',
                            $size['size_code'],
                            $this->lang['image_banks_resourcespace_file_information_description']
                        ),
                        sprintf('<td class="DownloadFileDimensions">%s</td>', get_size_info($size)),
                        sprintf('<td class="DownloadFileSize">%s</td>', $size['filesize']),
                    ],
                ];
                }
            }

        return parent::getResourceDownloadOptionsTable($id);
        }

    /** @inheritdoc */
    public function getSortOptions(): array
        {
        $sort_options = [
            $this->configs['default_sort'] => $this->lang[$this->configs['default_sort']] ?? $this->lang['relevance'],
        ];
        if ($this->configs['popularity_sort']) {
            $sort_options['popularity'] = $this->lang['popularity'];
        }
        if ($this->configs['orderbyrating']) {
            $sort_options['rating'] = $this->lang['rating'];
        }
        if ($this->configs['date_column']) {
            $sort_options['date'] = $this->lang['date'];
        }
        if ($this->configs['colour_sort']) {
            $sort_options['colour'] = $this->lang['colour'];
        }
        if ($this->configs['order_by_resource_id']) {
            $sort_options['resourceid'] = $this->lang['resourceid'];
        }
        $sort_options['resourcetype'] = $this->lang['type'];
        $sort_options['modified'] = $this->lang['modified'];
        return $sort_options;
        }
    }
