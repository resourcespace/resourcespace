<?php

namespace ImageBanks;

use SplFileInfo;

class Shutterstock extends Provider
    {
    function __construct(array $lang, string $temp_dir_path)
        {
        $this->id                = 2;
        $this->name              = "Shutterstock";
        $this->download_endpoint = "https://api.shutterstock.com/v2/images/";
        $this->configs = array(
            "shutterstock_token" => "ENTER_TOKEN_HERE",
            "shutterstock_result_limit" => "1000");
        $this->warning = "";
        $this->lang = $lang;
        $this->temp_dir_path = $temp_dir_path;
        }

    /** @inheritdoc */
    public function checkDependencies(): array
        {
        if (!function_exists('curl_version'))
            {
            return [$this->lang["image_banks_error_detail_curl"]];
            }
        return [];
        }

    /** @inheritdoc */
    public function buildConfigPageDefinition(array $page_def): array
        {
        $page_def[] = \config_add_text_input('shutterstock_token', $this->lang["image_banks_shutterstock_token"],false,800,true);
        $page_def[] = \config_add_text_input('shutterstock_result_limit', $this->lang["image_banks_shutterstock_result_limit"]);

        return $page_def;
        }

    /** @inheritdoc */
    public function runSearch(string $keywords, int $per_page = 24, int $page = 1, array $ctx = []): ProviderSearchResults
        {
        if($per_page < 3)
            {
            $per_page = 3;
            }
        elseif($per_page > 200)
            {
            $per_page = 200;
            }

        if($page < 1)
            {
            $page = 1;
            }

        $search_hash = md5("{$this->configs["shutterstock_token"]}--{$keywords}--{$per_page}--{$page}");
        $api_cached_results = $this->getCache($search_hash, 24);
        if(!$api_cached_results)
            {
            $api_results = $this->searchShutterstock($keywords, $per_page, $page);

            $search_results = json_decode($api_results, true);

            if(isset($search_results["error"]))
                {
                $provider_error = new ProviderSearchResults();
                $provider_error->setError($search_results["error"]["message"]);

                return $provider_error;
                }

            $this->setCache($search_hash, $api_results);
            }

        if(!isset($search_results))
            {
            $search_results = json_decode($api_cached_results, true);
            }

        $provider_results = new ProviderSearchResults();

        if (!isset($search_results["data"]))
            {
            $provider_results->setError($this->lang['image_banks_error_unexpected_response']);
            debug(sprintf('[image_banks][%s] Unexpected response: %s', __METHOD__, json_encode($search_results)));
            return $provider_results;
            }

        foreach($search_results["data"] as $result)
            {
            $width=$result['assets']['large_thumb']['width'];
            $height=$result['assets']['large_thumb']['height'];

            // Allow for the so-called "whitestrip" which is not included in the returned dimensions.
            $whitestrip_size=17;
            if ($width>$height) {$height+=$whitestrip_size;} else {$width+=$whitestrip_size;}

            global $baseurl_short;
            $provider_result = new \ImageBanks\ProviderResult($result["id"], $this);
            $provider_result
                ->setTitle($result['description'])
                ->setOriginalFileUrl("")
                ->setProviderUrl($baseurl_short . "plugins/image_banks/pages/shutterstock_license.php?id=" . urlencode($result['id']) 
                . "&preview=" . urlencode(isset($result['assets']['preview_1500']['url'])?$result['assets']['preview_1500']['url']:'')
                . "&description=" . urlencode(isset($result['description'])?$result['description']:'')
                . "&filename=" . urlencode(isset($result['original_filename'])?$result['original_filename']:'')
                )
                ->setPreviewUrl($result['assets']['large_thumb']['url'])
                ->setPreviewWidth($width)
                ->setPreviewHeight($height);

            $provider_results[] = $provider_result;
            }

        if($this->warning != "")
            {
            $provider_results->setWarning($this->warning);

            $this->warning = "";
            }

        $provider_results->total = count($provider_results);
        if(isset($search_results["total_count"]))
            {
            global $shutterstock_result_limit;
            // Cap at the configured total if the results are more than that.
            $provider_results->total = ($search_results["total_count"]>$shutterstock_result_limit?$shutterstock_result_limit:$search_results["total_count"]);
            }

        return $provider_results;
        }

    /** @inheritdoc */
    public function getDownloadFileInfo(string $file): SplFileInfo
        {
        return new SplFileInfo($file);
        }


    private function searchShutterstock($keywords, $per_page = 24, $page = 1)
        {
        $queryFields = [
            "query" => $keywords,
            "image_type" => "photo",
            "page" => $page,
            "per_page" => $per_page,
            "sort" => "popular",
            "view" => "minimal"
            ];
        
        $options = [
        CURLOPT_URL => "https://api.shutterstock.com/v2/images/search?" . http_build_query($queryFields),
        CURLOPT_USERAGENT => "php/curl",
        CURLOPT_HTTPHEADER => [
            "Authorization: Bearer v2/bHVCN2VtSkg1SnFoanJIbWNGWTAzSE5XSUwxMGlsa0YvMjAwMjA3MzM0L2N1c3RvbWVyLzQvcml1TWFIVk5xT2hkdnJTeWkxb2VoVzBIYWc0VGxJYWNJS3NBbXFicWVCeGZTWFY5OUZLcVdzbF9vQVE4OXJneDAwLVVncU92Zk9TNDE0UzdqRzNEeTBtNGNwa3dEZGpoalk5azAxVlZhMF9OM1gxNmFfbkZEN3pvbXhVeEx3bm9UMVZRUHh6T2lnRmlsUkprTmoyZWlyUElCVEdlQktwY3Jubmg3SldQVU1JOHRaN0VsQlhXbGcxYWR5a1BBTDh3Rk43MnNlSjlZVnhwUlNDQ21ZT2pGUS83ek1IQldZaldjWUtDRXdRU0R1VE9n"
        ],
        CURLOPT_RETURNTRANSFER => 1
        ];
        
        $handle = curl_init();
        curl_setopt_array($handle, $options);
        $response = curl_exec($handle);
        curl_close($handle);
        
        return $response;
        }

     }
