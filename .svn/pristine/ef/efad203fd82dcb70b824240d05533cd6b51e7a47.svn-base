<?php

// Exception to the rule on autoloading (which should be handled mostly by Composer) because this library has its own
// approach to loading its files.
require_once dirname(__DIR__, 3) . '/vendor/axiell/imu-api-php/src/IMu.php';
require_once IMu::$lib . '/Session.php';
require_once IMu::$lib . '/Module.php';
require_once IMu::$lib . '/Terms.php';

/**
* EMuAPI is a wrapper for IMu API
*
* EMuAPI wrapps aroung IMu APi by providing an easy way of searching
* and retrieving objects from an KE EMu database. Current functionality
* allows getting objects mainly based on IRN
*
* Example usage:
* $emu_api = new EMuAPI($emu_api_server, $emu_api_server_port, 'epublic');
* $emu_api->setColumns(array('ClaInstitution', 'ObjTitle', 'ObjectNumber', 'AffOriCountry_tab', 'OriginSummaryData_tab'));
* echo '<pre>';print_r($emu_api->getObjectByIrn(362024));echo '</pre>';
*
* @package EMuAPI
* @author  Alin Cota
* @version 1.1
* @access  public
*/
class EMuAPI
{
    protected $session;
    protected $module;
    protected $terms;
    protected $columns = array();

    private const MIME_TYPES = ['application', 'audio', 'image', 'text', 'video'];


    /**
    * Initialises a connection to an EMu server
    *
    * Creates a sessions for a given server address and port
    *
    * @param string  $server_address KE EMu server address
    * @param integer $server_port    KE EMu server port to connect to
    *
    * @return void
    */
    public function __construct($server_address, $server_port)
    {
        $this->session = new IMuSession($server_address, $server_port);

        // IMPORTANT: connecting now will throw an exception if connection is refused. Calling code should deal with it
        // as appropriate for the context
        $this->session->connect();
    }


    /**
    * Set module
    *
    * @param string $name
    *
    * @return boolean
    */
    public function setModule($name)
    {
        if ('' == $name) {
            return false;
        }

        $this->module  = new IMuModule($name, $this->session);
        $this->terms   = null;
        $this->columns = array();

        return true;
    }


    /**
    * Set terms
    *
    * @param IMuTerms $terms
    *
    * @return void
    */
    public function setTerms(IMuTerms $terms)
    {
        $this->terms = $terms;
    }


    /**
    * Search an EMu database using existing session in the current
    * module using current search terms
    *
    * @return integer An estimate of number of records found
    */
    public function runSearch()
    {
        // We require a module and terms, at least to run a search
        if (is_null($this->module) || is_null($this->terms)) {
            trigger_error('Could not run a search in EMu because the EMuAPI object does not have a module or search terms set!');
        }

        return $this->module->findTerms($this->terms);
    }


    /**
    * Get search results
    *
    * @uses EMuAPI::getResults()
    *
    * @param integer $offset
    * @param integer $limit  Use default value (-1) to get all results back
    *
    * @return array
    */
    public function getSearchResults($offset = 0, $limit = -1)
    {
        return $this->getResults('object_fields', $offset, $limit);
    }


    /**
    * Set IRN column
    *
    * @return void
    */
    public function setIrnColumn()
    {
        $this->columns[] = 'irn';
        $this->module->addFetchSet('object_fields', $this->columns);
    }


    /**
    * Set columns property
    *
    * @param array $names Column name(s) to set
    */
    public function setColumns(array $names): void
    {
        $this->columns = array_merge($this->columns, $names);

        if (!empty($this->columns)) {
            try {
                $this->module->addFetchSet('object_fields', $this->columns);
            } catch (Throwable $t) {
                debug(sprintf(
                    'Unable to set module columns (%s). %s',
                    implode(', ', $this->columns),
                    json_encode(['trace' => $t->getTraceAsString()])
                ));
            }
        }
    }


    /**
    * Get all columns names or just one of them
    *
    * @param array $name Column name to individually select/ list
    *
    * @return void
    */
    public function getColumns($name = '')
    {
        $return = $this->columns;

        if ('' !== trim($name)) {
            $return = $this->columns[$name];
        }

        return $return;
    }


    /**
    * Get EMu object based on IRN
    *
    * @param integer $irn Unique KE EMu object identifier
    *
    * @return array
    */
    public function getObjectByIrn($irn)
    {
        $this->module->findKey($irn);

        return $this->getResults('object_fields');
    }


    /**
    * Get EMu Multimedia resource object based on a multimedia IRN
    *
    * @param integer $irn     Multimedia resource IRN
    * @param array   $columns Optional, columns to fetch from EMu
    *
    * @return array
    */
    public function getObjectMultimediaByIrn($irn, array $columns = array('resource'))
    {
        $multimedia_module = new IMuModule('emultimedia', $this->session);

        $hits = $multimedia_module->findKey($irn);

        if (0 == $hits) {
            return array();
        }

        return $multimedia_module->fetch('start', 0, 1, $columns)->rows[0];
    }


    /**
    * Get EMu objects based on an array of IRNs
    *
    * @param array $irns Array of Unique KE EMu object identifiers
    */
    public function getObjectsByIrns(array $irns): array
    {
        $count_irns = count($irns);
        if (0 === $count_irns) {
            return array();
        }

        try {
            $this->module->findKeys($irns);
            return $this->getResults('object_fields', 0, $count_irns);
        } catch (Throwable $t) {
            debug(sprintf(
                'Unable to find records by keys (%s). %s',
                implode(', ', $irns),
                json_encode(['trace' => $t->getTraceAsString()])
            ));
            return [];
        }
    }


    /**
    * Get results based on search done before calling this function
    *
    * @param string|array $columns The columns to retrieve for a record. You can use either
    *                              an array of columns or an alias (which is defined by calling
    *                              $this->module->addFetchSet('[alias]', array(columns assigned to alias));)
    *                              Note: using aliases is faster when requesting the same columns
    * @param integer      $offset
    * @param integer      $count
    *
    * @return array
    */
    public function getResults($columns, $offset = 0, $count = 1)
    {
        return $this->module->fetch('start', $offset, $count, $columns)->rows;
    }


    /**
    * Utility function used to check whether a particular MIME type
    * is EMu valid
    *
    * @param string $mime
    *
    * @return boolean
    */
    public static function validMime($mime)
    {
        $mime = trim($mime);

        if ('' == $mime) {
            return false;
        }

        return in_array($mime, EMuAPI::MIME_TYPES);
    }


    /**
    * Download media file from EMu
    *
    * @param array   $multimedia
    * @param string  $to          Path where TO save this file
    * @param integer $length      Up to length number of bytes read
    *
    * @return boolean
    */
    public static function getMediaFile(array $multimedia, $to, $length = 4096)
    {
        if (
            0 === count($multimedia)
            || (!isset($multimedia['resource']['file']) || (isset($multimedia['resource']['file']) && 'stream' != get_resource_type($multimedia['resource']['file'])))
            || '' == $to
        ) {
            return false;
        }

        $file = $multimedia['resource']['file'];
        $copy = fopen($to, 'wb');

        if (false === $copy) {
            return false;
        }

        while ($copy) {
            $data = fread($file, $length);

            if (false === $data || 0 == strlen($data)) {
                break;
            }

            fwrite($copy, $data);
        }

        fclose($copy);

        return true;
    }
}
