<?php

declare(strict_types=1);

namespace SimpleSAML\Module\metarefresh\Controller;

use Exception;
use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\Logger;
use SimpleSAML\Utils;
use SimpleSAML\XHTML\Template;

/**
 * Controller class for the metarefresh module.
 *
 * This class serves the different views available in the module.
 *
 * @package SimpleSAML\Module\metarefresh
 */

class MetaRefresh
{
    /** @var \SimpleSAML\Configuration */
    protected Configuration $config;

    /** @var \SimpleSAML\Configuration */
    protected Configuration $module_config;

    /**
     * @var \SimpleSAML\Utils\Auth
     */
    protected $authUtils;


    /**
     * Controller constructor.
     *
     * It initializes the global configuration and auth source configuration for the controllers implemented here.
     *
     * @param \SimpleSAML\Configuration              $config The configuration to use by the controllers.
     *
     * @throws \Exception
     */
    public function __construct(
        Configuration $config,
    ) {
        $this->config = $config;
        $this->module_config = Configuration::getConfig('module_metarefresh.php');
        $this->authUtils = new Utils\Auth();
    }


    /**
     * Inject the \SimpleSAML\Utils\Auth dependency.
     *
     * @param \SimpleSAML\Utils\Auth $authUtils
     */
    public function setAuthUtils(Utils\Auth $authUtils): void
    {
        $this->authUtils = $authUtils;
    }

    /**
     * Inject the \SimpleSAML\Configuration dependency.
     *
     * @param \SimpleSAML\Configuration $module_config
     */
    public function setModuleConfig(Configuration $module_config): void
    {
        $this->module_config = $module_config;
    }

    /**
     * @return \SimpleSAML\XHTML\Template
     */
    public function main(): Template
    {
        $this->authUtils->requireAdmin();

        Logger::setCaptureLog(true);

        $mf = new \SimpleSAML\Module\metarefresh\MetaRefresh($this->config, $this->module_config);

        try {
            $mf->runRefresh();
        } catch (Exception $e) {
            $e = Error\Exception::fromException($e);
            $e->logWarning();
        }

        $logentries = Logger::getCapturedLog();

        $t = new Template($this->config, 'metarefresh:fetch.twig');
        $t->data['logentries'] = $logentries;
        return $t;
    }
}
