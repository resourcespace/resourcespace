<?php

declare(strict_types=1);

namespace SimpleSAML\Module\statistics\Controller;

use Exception;
use SimpleSAML\Configuration;
use SimpleSAML\Module;
use SimpleSAML\Module\statistics\AccessCheck;
use SimpleSAML\Module\statistics\Aggregator;
use SimpleSAML\Module\statistics\Graph;
use SimpleSAML\Module\statistics\Ruleset;
use SimpleSAML\Session;
use SimpleSAML\XHTML\Template;
use Symfony\Component\HttpFoundation\Request;

/**
 * Controller class for the statistics module.
 *
 * This class serves the statistics views available in the module.
 *
 * @package SimpleSAML\Module\statistics
 */
class Statistics
{
    /** @var \SimpleSAML\Configuration */
    protected $config;

    /** @var \SimpleSAML\Configuration */
    protected $moduleConfig;

    /** @var \SimpleSAML\Session */
    protected $session;


    /**
     * StatisticsController constructor.
     *
     * @param \SimpleSAML\Configuration $config The configuration to use.
     * @param \SimpleSAML\Session $session The current user session.
     */
    public function __construct(Configuration $config, Session $session)
    {
        $this->config = $config;
        $this->moduleConfig = Configuration::getConfig('module_statistics.php');
        $this->session = $session;
    }


    /**
     * Display statistics metadata.
     *
     * @param Request $request The current request.
     *
     * @return \SimpleSAML\XHTML\Template
     */
    public function metadata(Request $request): Template
    {
        AccessCheck::checkAccess($this->moduleConfig);

        $aggr = new Aggregator();
        $aggr->loadMetadata();
        $metadata = $aggr->getMetadata();
        if ($metadata !== null) {
            if (array_key_exists('memory', $metadata)) {
                $metadata['memory'] = number_format($metadata['memory'] / (1024 * 1024), 2);
            }
        }

        $t = new Template($this->config, 'statistics:statmeta.twig');
        $t->data = [
            'metadata' => $metadata,
        ];

        return $t;
    }


    /**
     * Display the main admin page.
     *
     * @return \SimpleSAML\XHTML\Template
     */
    public function main(Request $request): Template
    {
        AccessCheck::checkAccess($this->moduleConfig);

        /**
         * Check input parameters
         */
        $preferRule = $request->query->get('rule');
        $preferRule2 = $request->query->get('rule2');
        if ($preferRule2 === '_') {
            $preferRule2 = null;
        }

        $preferTime = strval($request->query->get('time'));
        $preferTimeRes = strval($request->query->get('res'));
        $delimiter = strval($request->query->get('delimiter'));

        /**
         * Create statistics data.
         */
        $ruleset = new Ruleset($this->moduleConfig);
        $statrule = $ruleset->getRule($preferRule);
        $rule = $statrule->getRuleID();

        /**
         * Prepare template.
         */
        $t = new Template($this->config, 'statistics:statistics.twig');
        $t->data = [
            'delimiter' => $delimiter,
            'pageid' => 'statistics',
            'header' => 'stat',
            'available_rules' => $ruleset->availableRulesNames(),
            'selected_rule' => $rule,
            'selected_rule2' => $preferRule2,
        ];

        $t->data['available_rules'] = $ruleset->availableRulesNames();
        $t->data['current_rule'] = $t->data['available_rules'][$rule];
        $t->data['post_rule'] = $this->getBaseURL($t, 'post', 'rule');
        $t->data['post_res'] = $this->getBaseURL($t, 'post', 'res');
        $t->data['post_time'] = $this->getBaseURL($t, 'post', 'time');
        $t->data['available_timeres'] = [];
        $t->data['available_times'] = [];

        if (isset($preferRule2)) {
            $statrule = $ruleset->getRule($preferRule2);
            $t->data['available_timeres'] = $statrule->availableTimeRes();
            $t->data['available_times'] = $statrule->availableFileSlots($timeres);
        }

        try {
            $dataset = $statrule->getDataset($preferTimeRes, $preferTime);
            $dataset->setDelimiter($delimiter);
            $dataset->aggregateSummary();
            $dataset->calculateMax();
        } catch (Exception $e) {
            $t->data['error'] = "No data available";
            return $t;
        }

        $delimiter = $dataset->getDelimiter();
        $timeres = $dataset->getTimeRes();
        $fileslot = $dataset->getFileslot();
        $timeNavigation = $statrule->getTimeNavigation($timeres, $preferTime);
        $piedata = $dataset->getPieData();
        $datasets = [$dataset->getPercentValues()];
        $axis = $dataset->getAxis();
        $maxes = [$dataset->getMax()];

        $t->data['results'] = $dataset->getResults();
        $t->data['summaryDataset'] = $dataset->getSummary();
        $t->data['debugdata'] = $dataset->getDebugData();
        $t->data['topdelimiters'] = $dataset->getTopDelimiters();
        $t->data['availdelimiters'] = $dataset->availDelimiters();
        $t->data['available_times_prev'] = $timeNavigation['prev'];
        $t->data['available_times_next'] = $timeNavigation['next'];
        $t->data['selected_rule'] = $rule;
        $t->data['selected_time'] = $fileslot;
        $t->data['selected_timeres'] = $timeres;
        $t->data['post_d'] = $this->getBaseURL($t, 'post', 'd');

        $dimx = $this->moduleConfig->getOptionalValue('dimension.x', 800);
        $dimy = $this->moduleConfig->getOptionalValue('dimension.y', 350);
        $grapher = new Graph\GoogleCharts($dimx, $dimy);
        $t->data['imgurl'] = $grapher->show($axis['axis'], $axis['axispos'], $datasets, $maxes);

        if (isset($preferRule2)) {
            try {
                $dataset2 = $statrule->getDataset($preferTimeRes, $preferTime);
                $dataset2->aggregateSummary();
                $dataset2->calculateMax();
                $datasets[] = $dataset2->getPercentValues();
                $maxes[] = $dataset2->getMax();

                if ($request->query->get('format') === 'csv') {
                    header('Content-type: text/csv');
                    header('Content-Disposition: attachment; filename="simplesamlphp-data.csv"');
                    $data = $dataset->getDebugData();
                    foreach ($data as $de) {
                        if (isset($de[1])) {
                            echo '"' . $de[0] . '",' . $de[1] . "\n";
                        }
                    }
                    exit;
                } else {
                    $t->data['error'] = 'Export format not supported';
                    return $t;
                }
            } catch (Exception $e) {
                $t->data['error'] = "No data available to compare";
                return $t;
            }
        }

        if (!empty($piedata)) {
            $t->data['pieimgurl'] = $grapher->showPie($dataset->getDelimiterPresentationPie(), $piedata);
        }

        $t->data['selected_rule2'] = $preferRule2;
        $t->data['selected_delimiter'] = $delimiter;
        $t->data['post_rule2'] = $this->getBaseURL($t, 'post', 'rule2');
        $t->data['get_times_prev'] = $this->getBaseURL($t, 'get', 'time', $t->data['available_times_prev']);
        $t->data['get_times_next'] = $this->getBaseURL($t, 'get', 'time', $t->data['available_times_next']);
        $t->data['delimiterPresentation'] = $dataset->getDelimiterPresentation();

        return $t;
    }


    /**
     * @param \SimpleSAML\XHTML\Template $t
     * @param string $type
     * @param string|null $key
     * @param string|null $value
     * @return string|array
     */
    private function getBaseURL(Template $t, string $type = 'get', string $key = null, string $value = null)
    {
        $vars = [
            'rule' => $t->data['selected_rule'],
            'time' => $t->data['selected_time'],
            'res' => $t->data['selected_timeres'],
        ];
        if (isset($t->data['selected_delimiter'])) {
            $vars['d'] = $t->data['selected_delimiter'];
        }
        if (!empty($t->data['selected_rule2']) && $t->data['selected_rule2'] !== '_') {
            $vars['rule2'] = $t->data['selected_rule2'];
        }
        if (isset($key)) {
            if (isset($vars[$key])) {
                unset($vars[$key]);
            }
            if (isset($value)) {
                $vars[$key] = $value;
            }
        }
        if ($type === 'get') {
            return Module::getModuleURL("statistics/") . '?' . http_build_query($vars, '', '&');
        }
        return $vars;
    }
}
