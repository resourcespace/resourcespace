<?php
namespace ImageBanks;

use SplFileInfo;

abstract class Provider
    {
    protected array $lang;
    protected string $temp_dir_path = "";
    protected int $id;
    protected string $name;
    protected string $download_endpoint;
    protected array $configs;
    protected string $warning;

    /**
     * Allow each provider to register its own dependency checks.
     * Note: Image Banks implements the extra_warn_checks hook which will use these errors to notify sysadmins.
     *
     * @return array List of failed (system) dependencies.
     */
    abstract public function checkDependencies(): array;

    /**
     * Add configuration needs to the image_banks' setup page
     * @param array $page_def Setup page definitions {@see config_functions.php}
     */
    abstract public function buildConfigPageDefinition(array $page_def): array;

    /**
    * Search providers' database based on specified keywords
    *
    * @param  string   $keywords  Search keyword(s)
    * @param  integer  $per_page  Number of results per page
    * @param  integer  $page      Select the page number
    * @param  array    $ctx       Any other search related contextual information specific provider instances might need
    */
    abstract protected function runSearch(
        string $keywords,
        int $per_page = 24,
        int $page = 1,
        array $ctx = []
    ): ProviderSearchResults;

    /**
     * Get file information for download or when creating a new resource based on a Providers' source file.
     * 
     * @param string $file The source file URL 
     */
    abstract public function getDownloadFileInfo(string $file): SplFileInfo;

    /**
    * Register configuration options required by the Provider in the GLOBAL scope
    * 
    * @param  array $globals The globals variable - $GLOBALS
    * 
    * @return void  
    */
    final public function registerConfigurationNeeds(array $globals)
        {
        foreach($this->configs as $config => $value)
            {
            if(array_key_exists($config, $globals))
                {
                // GLOBALS have been set from the plugin configuration, no reason to set to the default value now
                $this->configs[$config] = $globals[$config];

                continue;
                }

            $GLOBALS[$config] = $value;
            }
        }

    /**
    * Search providers' database based on specified keywords
    * 
    * @param  string   $keywords  Search keyword(s)
    * @param  integer  $per_page  Number of results per page
    * @param  integer  $page      Select the page number
    * @param  array    $ctx       Any other search related contextual information specific provider instances might need
    */
    final public function search($keywords, $per_page = 24, $page = 1, array $ctx = []): ProviderSearchResults
        {
        return $this->runSearch($keywords, $per_page, $page, $ctx);
        }


    /**
    * Get Image Bank providers' temporary directory path
    * 
    * @return string
    */
    final public function getTempDirPath()
        {
        return $this->temp_dir_path;
        }

    /**
    * Get Cache from the providers' temporary directory
    * 
    * @param  string  $id   The cache ID. This is also the filename when saved on disk
    * @param  int     $ttl  The time to live (in hours) of the cache value. This is measured based on the last modified 
    *                       timestamp of the cache file
    * 
    * @return boolean|string  Returns FALSE if no cache found or the content of the file
    */
    final protected function getCache($id, $ttl)
        {
        $files = new \DirectoryIterator($this->temp_dir_path);

        foreach($files as $file)
            {
            if($file->isDot())
                {
                continue;
                }

            if($file->getFilename() != $id)
                {
                continue;
                }

            $interval = \DateTime::createFromFormat('U', $file->getMTime())->diff(new \DateTime());
            $hours = $interval->h + ($interval->days * 24);

            if($hours > $ttl)
                {
                return false;
                }

            return file_get_contents($file->getPathname());
            }

        return false;
        }

    /**
    * Set cache in the providers' temporary directory
    * 
    * @param  string  $id     The cache ID
    * @param  mixed   $value  The value to store in the file
    * 
    * @throws  Error if unable to open file
    * 
    * @return void
    */
    final protected function setCache($id, $value)
        {
        $file = $this->temp_dir_path . DIRECTORY_SEPARATOR . $id;

        $fh = fopen($file, "wb");

        if($fh === false)
            {
            trigger_error("Unable to open file '{$file}' to set cache for {$this->name}");
            }

        fwrite($fh, $value);
        fclose($fh);
        }

    public function getId()
        {
        return $this->id;
        }

    final public function getName()
        {
        return $this->name;
        }

    final public function getAllowedDownloadEndpoint()
        {
        return $this->download_endpoint;
        }

    /** Determine if the Provider should show the view page when clicking on the search result preview image */
    public function allowViewPage(): bool
        {
        return false;
        }

    /**
     * Find Image Bank record by ID.
     * @param int|string $id Record ID
     */
    public function findById($id): ProviderResult
        {
        return new ProviderResult($id, $this);
        }

    /**
     * Retrieve non-metadata properties from Image Bank Provider for image ID.
     * @param int|string $id Record ID
     * @return array<string, string> The returned key must be the user friendly name (label), the value is just that.
     */
    public function getImageNonMetadataProperties($id): array
        {
        return [];
        }

    /**
     * Retrieve image metadata from Image Bank Provider.
     * @param int|string $id Record ID
     * @return array<string, string> The returned key must be the user friendly name (label), the value is just that.
     */
    public function getImageMetadata($id): array
        {
        return [];
        }

    /**
     * Get table view information for rendering on the plugins' view page
     * @param int|string $id Record ID
     */
    public function getResourceDownloadOptionsTable($id): array
        {
        return [
            'header' => [],
            'data' => [$this->lang['collection_download_original']]
        ];
        }

    /**
     * Get the Providers' sort options which should be displayed on the search page
     */
    public function getSortOptions(): array
        {
        return [];
        }
    }
