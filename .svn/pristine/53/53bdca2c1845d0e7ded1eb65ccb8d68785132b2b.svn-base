<?php

declare(strict_types=1);

namespace SimpleSAML\Module\statistics\Statistics\Rulesets;

use SimpleSAML\Configuration;
use SimpleSAML\Error;
use SimpleSAML\Module\statistics\RatioDataset;
use SimpleSAML\Module\statistics\StatDataset;

/**
 * @package SimpleSAMLphp
 */
class Ratio extends BaseRule
{
    /** @var \SimpleSAML\Module\statistics\Statistics\Rulesets\BaseRule $refrule1 */
    protected BaseRule $refrule1;

    /** @var \SimpleSAML\Module\statistics\Statistics\Rulesets\BaseRule $refrule2 */
    protected BaseRule $refrule2;


    /**
     * Constructor
     *
     * @param \SimpleSAML\Configuration $statconfig
     * @param \SimpleSAML\Configuration $ruleconfig
     * @param string $ruleid
     * @param array $available
     */
    public function __construct(Configuration $statconfig, Configuration $ruleconfig, string $ruleid, array $available)
    {
        parent::__construct($statconfig, $ruleconfig, $ruleid, $available);

        $refNames = $this->ruleconfig->getArray('ref');

        $statrulesConfig = $this->statconfig->getConfigItem('statrules');
        if ($statrulesConfig === null) {
            throw new Error\ConfigurationError('Missing \'statrules\' in module configuration.');
        }

        $statruleConfig1 = $statrulesConfig->getConfigItem($refNames[0]);
        $statruleConfig2 = $statrulesConfig->getConfigItem($refNames[1]);

        if ($statruleConfig1 === null || $statruleConfig2 === null) {
            throw new Error\ConfigurationError();
        }

        $this->refrule1 = new BaseRule($this->statconfig, $statruleConfig1, $refNames[0], $available);
        $this->refrule2 = new BaseRule($this->statconfig, $statruleConfig2, $refNames[1], $available);
    }


    /**
     * @return array
     */
    public function availableTimeRes(): array
    {
        return $this->refrule1->availableTimeRes();
    }


    /**
     * @param string $timeres
     * @return array
     */
    public function availableFileSlots(string $timeres): array
    {
        return $this->refrule1->availableFileSlots($timeres);
    }


    /**
     * @param string $preferTimeRes
     * @return string
     */
    protected function resolveTimeRes(string $preferTimeRes): string
    {
        return $this->refrule1->resolveTimeRes($preferTimeRes);
    }


    /**
     * @param string $timeres
     * @param string $preferTime
     * @return string
     */
    protected function resolveFileSlot($timeres, $preferTime): string
    {
        return $this->refrule1->resolveFileSlot($timeres, $preferTime);
    }


    /**
     * @param string $timeres
     * @param string $preferTime
     * @return array
     */
    public function getTimeNavigation(string $timeres, string $preferTime): array
    {
        return $this->refrule1->getTimeNavigation($timeres, $preferTime);
    }


    /**
     * @param string $preferTimeRes
     * @param string $preferTime
     * @return \SimpleSAML\Module\statistics\RatioDataset
     */
    public function getDataSet(string $preferTimeRes, string $preferTime): StatDataset
    {
        $timeres = $this->resolveTimeRes($preferTimeRes);
        $fileslot = $this->resolveFileSlot($timeres, $preferTime);

        $refNames = $this->ruleconfig->getArray('ref');

        $dataset = new RatioDataset(
            $this->statconfig,
            $this->ruleconfig,
            $refNames,
            $timeres,
            $fileslot
        );
        return $dataset;
    }
}
