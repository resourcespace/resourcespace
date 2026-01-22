<?php

declare(strict_types=1);

namespace SimpleSAML\Module\statistics;

use Exception;
use SimpleSAML\Configuration;
use SimpleSAML\Module;
use SimpleSAML\Module\statistics\Statistics\Rulesets\BaseRule;

/**
 * @package SimpleSAMLphp
 */
class Ruleset
{
    /** @var \SimpleSAML\Configuration */
    private Configuration $statconfig;

    /** @var array */
    private array $availrulenames;

    /** @var array */
    private array $availrules;

    /** @var array */
    private array $available;


    /**
     * Constructor
     *
     * @param \SimpleSAML\Configuration $statconfig
     */
    public function __construct(Configuration $statconfig)
    {
        $this->statconfig = $statconfig;
        $this->init();
    }


    /**
     */
    private function init(): void
    {
        $statdir = $this->statconfig->getValue('statdir');
        $statrules = $this->statconfig->getValue('statrules');
        $timeres = $this->statconfig->getValue('timeres');

        /*
         * Walk through file lists, and get available [rule][fileslot]...
         */
        if (!is_dir($statdir)) {
            throw new Exception('Statisics output directory [' . $statdir . '] does not exist.');
        }
        $filelist = scandir($statdir);
        $this->available = [];
        foreach ($filelist as $file) {
            if (preg_match('/([a-z0-9_]+)-([a-z0-9_]+)-([0-9]+)\.stat/', $file, $matches)) {
                if (array_key_exists($matches[1], $statrules)) {
                    if (array_key_exists($matches[2], $timeres)) {
                        $this->available[$matches[1]][$matches[2]][] = $matches[3];
                    }
                }
            }
        }
        if (empty($this->available)) {
            throw new Exception('No aggregated statistics files found in [' . $statdir . ']');
        }

        /**
         * Create array with information about available rules..
         */
        $this->availrules = array_keys($statrules);
        $available_rules = [];
        foreach ($this->availrules as $key) {
            $available_rules[$key] = ['name' => $statrules[$key]['name'], 'descr' => $statrules[$key]['descr']];
        }
        $this->availrulenames = $available_rules;
    }


    /**
     * @return array
     */
    public function availableRules(): array
    {
        return $this->availrules;
    }


    /**
     * @return array
     */
    public function availableRulesNames(): array
    {
        return $this->availrulenames;
    }


    /**
     * Resolve which rule is selected. Taking user preference and checks if it exists.
     *
     * @param string|null $preferRule
     * @return string|null
     */
    private function resolveSelectedRule(string $preferRule = null): ?string
    {
        $rule = $this->statconfig->getOptionalString('default', $this->availrules[0]);
        if (!empty($preferRule)) {
            if (in_array($preferRule, $this->availrules, true)) {
                $rule = $preferRule;
            }
        }
        return $rule;
    }


    /**
     * @param string|null $preferRule
     * @return \SimpleSAML\Module\statistics\Statistics\Rulesets\BaseRule
     */
    public function getRule(string $preferRule = null): BaseRule
    {
        $rule = $this->resolveSelectedRule($preferRule);
        $statrulesConfig = $this->statconfig->getConfigItem('statrules');
        $statruleConfig = $statrulesConfig->getConfigItem($rule);

        $presenterClass = Module::resolveClass(
            $statruleConfig->getOptionalValue('presenter', 'statistics:BaseRule'),
            'Statistics\Rulesets'
        );

        /** @psalm-suppress InvalidStringClass */
        $statrule = new $presenterClass($this->statconfig, $statruleConfig, $rule, $this->available);

        /** @var \SimpleSAML\Module\statistics\Statistics\Rulesets\BaseRule $statrule */
        return $statrule;
    }
}
