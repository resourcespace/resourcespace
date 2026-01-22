<?php

declare(strict_types=1);

namespace SimpleSAML\Module\statistics\Statistics\FieldPresentation;

use SimpleSAML\Locale\Translate;
use SimpleSAML\XHTML\Template;

class Base
{
    /** @var array */
    protected array $fields;

    /** @var \SimpleSAML\XHTML\Template */
    protected Template $template;

    /** @var \SimpleSAML\Locale\Translate */
    protected Translate $translator;

    /** @var string */
    protected string $config;


    /**
     * @param array $fields
     * @param string $config
     * @param \SimpleSAML\XHTML\Template $template
     */
    public function __construct(array $fields, string $config, Template $template)
    {
        $this->config = $config;
        $this->fields = $fields;
        $this->template = $template;
        $this->translator = $template->getTranslator();
    }


    /**
     * @return array
     */
    public function getPresentation(): array
    {
        return ['_' => 'Total'];
    }
}
