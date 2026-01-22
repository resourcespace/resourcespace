<?php

declare(strict_types=1);

namespace SimpleSAML\Module\metarefresh;

/*
 * @package SimpleSAMLphp
 */
class ARP
{
    /** @var array */
    private array $metadata;

    /** @var array */
    private array $attributes = [];

    /** @var string */
    private string $prefix;

    /** @var string */
    private string $suffix;


    /**
     * Constructor
     *
     * @param array $metadata
     * @param string $attributemap_filename
     * @param string $prefix
     * @param string $suffix
     */
    public function __construct(array $metadata, string $attributemap_filename, string $prefix, string $suffix)
    {
        $this->metadata = $metadata;
        $this->prefix = $prefix;
        $this->suffix = $suffix;

        if (isset($attributemap_filename)) {
            $this->loadAttributeMap($attributemap_filename);
        }
    }


    /**
     * @param string $attributemap_filename
     *
     */
    private function loadAttributeMap(string $attributemap_filename): void
    {
        $config = \SimpleSAML\Configuration::getInstance();

        /** @psalm-suppress PossiblyNullOperand */
        include($config->getPathValue('attributemap', 'attributemap/') . $attributemap_filename . '.php');

        // Note that $attributemap is defined in the included attributemap-file!
        /** @psalm-var array $attributemap */
        $this->attributes = $attributemap;
    }


    /**
     * @param string $name
     *
     * @return string
     */
    private function surround(string $name): string
    {
        $ret = '';
        if (!empty($this->prefix)) {
            $ret .= $this->prefix;
        }
        $ret .= $name;
        if (!empty($this->suffix)) {
            $ret .= $this->suffix;
        }
        return $ret;
    }


    /**
     * @param string $name
     *
     * @return string
     */
    private function getAttributeID(string $name): string
    {
        if (empty($this->attributes)) {
            return $this->surround($name);
        }
        if (array_key_exists($name, $this->attributes)) {
            return $this->surround($this->attributes[$name]);
        }
        return $this->surround($name);
    }


    /**
     * @return string
     */
    public function getXML(): string
    {
        $xml = <<<MSG
        <?xml version="1.0" encoding="UTF-8"?>
        <AttributeFilterPolicyGroup id="urn:mace:funet.fi:haka:kalmar" xmlns="urn:mace:shibboleth:2.0:afp"
    xmlns:basic="urn:mace:shibboleth:2.0:afp:mf:basic" xmlns:saml="urn:mace:shibboleth:2.0:afp:mf:saml"
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:schemaLocation="urn:mace:shibboleth:2.0:afp classpath:/schema/shibboleth-2.0-afp.xsd
                        urn:mace:shibboleth:2.0:afp:mf:basic classpath:/schema/shibboleth-2.0-afp-mf-basic.xsd
                        urn:mace:shibboleth:2.0:afp:mf:saml classpath:/schema/shibboleth-2.0-afp-mf-saml.xsd">
MSG;

        foreach ($this->metadata as $metadata) {
            $xml .= $this->getEntryXML($metadata['metadata']);
        }

        $xml .= '</AttributeFilterPolicyGroup>';
        return $xml;
    }


    /**
     * @param array $entry
     *
     * @return string
     */
    private function getEntryXML(array $entry): string
    {
        $entityid = $entry['entityid'];
        return '    <AttributeFilterPolicy id="' . $entityid .
            '"><PolicyRequirementRule xsi:type="basic:AttributeRequesterString" value="' . $entityid .
            '" />' . $this->getEntryXMLcontent($entry) . '</AttributeFilterPolicy>';
    }


    /**
     * @param array $entry
     *
     * @return string
     */
    private function getEntryXMLcontent(array $entry): string
    {
        if (!array_key_exists('attributes', $entry)) {
            return '';
        }

        $ret = '';
        foreach ($entry['attributes'] as $a) {
            $ret .= '            <AttributeRule attributeID="' . $this->getAttributeID($a) .
                '"><PermitValueRule xsi:type="basic:ANY" /></AttributeRule>';
        }
        return $ret;
    }
}
