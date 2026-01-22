<?php
namespace ImageBanks;

use SplFileInfo;

class Pixabay extends Provider
    {
    function __construct(array $lang, string $temp_dir_path)
        {
        $this->id                = 1;
        $this->name              = "Pixabay";
        $this->download_endpoint = "https://pixabay.com/get/";
        $this->configs = array("pixabay_api_key" => "9664540-83e27f5c4cefd1aeb14fd8009");
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
        $page_def[] = \config_add_text_input('pixabay_api_key', $this->lang["image_banks_pixabay_api_key"]);

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

        $search_hash = md5("{$this->configs["pixabay_api_key"]}--{$keywords}--{$per_page}--{$page}");
        $api_cached_results = $this->getCache($search_hash, 24);
        if(!$api_cached_results)
            {
            $api_results = $this->searchPixabay($keywords, $per_page, $page);

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

        if (!isset($search_results["hits"]))
            {
            $provider_results->setError($this->lang['image_banks_error_unexpected_response']);
            debug(sprintf('[image_banks][%s] Unexpected response: %s', __METHOD__, json_encode($search_results)));
            return $provider_results;
            }

        foreach($search_results["hits"] as $result)
            {
            // As per https://pixabay.com/api/docs/ , imageURL key/value pair is only available if the account has been 
            // approved for full API access
            $original_file_url = $result['largeImageURL'];
            if(isset($result['imageURL']) && $result['imageURL'] !== '')
                {
                $original_file_url = $result['imageURL'];
                }

            $provider_result = new \ImageBanks\ProviderResult($result["id"], $this);
            $provider_result
                ->setTitle($result['tags'])
                ->setOriginalFileUrl($original_file_url)
                ->setProviderUrl($result['pageURL'])
                ->setPreviewUrl($result['previewURL'])
                ->setPreviewWidth($result['previewWidth'])
                ->setPreviewHeight($result['previewHeight']);

            $provider_results[] = $provider_result;
            }

        if($this->warning != "")
            {
            $provider_results->setWarning($this->warning);

            $this->warning = "";
            }

        $provider_results->total = count($provider_results);
        if(isset($search_results["totalHits"]))
            {
            $provider_results->total = $search_results["totalHits"];
            }

        return $provider_results;
        }

    /** @inheritdoc */
    public function getDownloadFileInfo(string $file): SplFileInfo
        {
        return new SplFileInfo($file);
        }


    private function searchPixabay($keywords, $per_page = 24, $page = 1)
        {
        $pixabay_api_url = generateURL(
            "https://pixabay.com/api/",
            array(
                "key"      => $this->configs["pixabay_api_key"],
                "q"        => $keywords,
                "per_page" => $per_page,
                "page"     => $page,
            )
        );

        $curl_handle = curl_init();
        $curl_response_headers = array();

        curl_setopt($curl_handle, CURLOPT_URL, $pixabay_api_url);
        curl_setopt($curl_handle, CURLOPT_HEADER, false);
        curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
        curl_setopt(
            $curl_handle,
            CURLOPT_HEADERFUNCTION,
            function($curl, $header) use (&$curl_response_headers)
                {
                $length = strlen($header);
                $header = explode(':', $header, 2);

                // Invalid header
                if(count($header) < 2)
                    {
                    return $length;
                    }

                $name = strtolower(trim($header[0]));

                if(!array_key_exists($name, $curl_response_headers))
                    {
                    $curl_response_headers[$name] = array(trim($header[1]));
                    }
                else
                    {
                    $curl_response_headers[$name][] = trim($header[1]);
                    }

                return $length;
                }
        );

        $result = curl_exec($curl_handle);
        $response_status_code = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
        curl_close($curl_handle);

        $result_json_decoded = json_decode($result, true);

        if(
            $response_status_code != 200
            || ($response_status_code == 200 && $result_json_decoded["totalHits"] == 0)
        )
            {
            switch($response_status_code)
                {
                case 200:
                    $message = $this->lang["image_banks_try_something_else"];
                    break;

                case 429:
                    $message = $this->lang["image_banks_try_again_later"];
                    break;

                default:
                    $message = $result;
                    break;
                }

            $error_data = array(
                "error" => array(
                    "message"  => $message
                )
            );

            return json_encode($error_data);
            }

        if(isset($curl_response_headers["x-ratelimit-remaining"][0]) && $curl_response_headers["x-ratelimit-remaining"][0] <= 20)
            {
            $warning_message = str_replace(
                array(
                    "%PROVIDER",
                    "%RATE-LIMIT-REMAINING",
                    "%TIME"
                ),
                array(
                    $this->name,
                    $curl_response_headers["x-ratelimit-remaining"][0],
                    date("i:s", $curl_response_headers["x-ratelimit-reset"][0])
                ),
                $this->lang["image_banks_warning_rate_limit_almost_reached"]
            );

            $this->warning = $warning_message;
            }

        return $result;
        }
    }