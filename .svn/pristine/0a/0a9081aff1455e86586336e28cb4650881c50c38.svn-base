<?php

declare(strict_types=1);

namespace SimpleSAML\Module\metarefresh;

use Exception;
use SimpleSAML\Configuration;
use SimpleSAML\Logger;
use SimpleSAML\Metadata;
use SimpleSAML\Utils;
use SimpleSAML\XML\DOMDocumentFactory;
use Symfony\Component\VarExporter\VarExporter;

/**
 * @package SimpleSAMLphp
 */
class MetaLoader
{
    /** @var int|null */
    private ?int $expire;

    /** @var array */
    private array $metadata = [];

    /** @var object|null */
    private ?object $oldMetadataSrc;

    /** @var string|null */
    private ?string $stateFile = null;

    /** @var bool */
    private bool $changed = false;

    /** @var array */
    private array $state = [];

    /** @var array */
    private array $types = [
        'saml20-idp-remote',
        'saml20-sp-remote',
        'attributeauthority-remote',
    ];


    /**
     * Constructor
     *
     * @param int|null $expire
     * @param string|null  $stateFile
     * @param object|null  $oldMetadataSrc
     */
    public function __construct(int $expire = null, string $stateFile = null, object $oldMetadataSrc = null)
    {
        $this->expire = $expire;
        $this->oldMetadataSrc = $oldMetadataSrc;
        $this->stateFile = $stateFile;

        // Read file containing $state from disk
        /** @psalm-var array|null */
        $state = null;
        if (!is_null($stateFile) && is_readable($stateFile)) {
            include($stateFile);
        }

        if (!empty($state)) {
            $this->state = $state;
        }
    }


    /**
     * Get the types of entities that will be loaded.
     *
     * @return array The entity types allowed.
     */
    public function getTypes(): array
    {
        return $this->types;
    }


    /**
     * Set the types of entities that will be loaded.
     *
     * @param string|array $types Either a string with the name of one single type allowed, or an array with a list of
     * types. Pass an empty array to reset to all types of entities.
     */
    public function setTypes($types): void
    {
        if (!is_array($types)) {
            $types = [$types];
        }
        $this->types = $types;
    }


    /**
     * This function processes a SAML metadata file.
     *
     * @param array $source
     */
    public function loadSource(array $source): void
    {
        if (preg_match('@^https?://@i', $source['src'])) {
            // Build new HTTP context
            $context = $this->createContext($source);

            $httpUtils = new Utils\HTTP();
            // GET!
            try {
                /** @var array $response  We know this because we set the third parameter to `true` */
                $response = $httpUtils->fetch($source['src'], $context, true);
                list($data, $responseHeaders) = $response;
            } catch (Exception $e) {
                Logger::warning('metarefresh: ' . $e->getMessage());
            }

            // We have response headers, so the request succeeded
            if (!isset($responseHeaders)) {
                // No response headers, this means the request failed in some way, so re-use old data
                Logger::info('No response from ' . $source['src'] . ' - attempting to re-use cached metadata');
                $this->addCachedMetadata($source);
                return;
            } elseif (preg_match('@^HTTP/1\.[01]\s304\s@', $responseHeaders[0])) {
                // 304 response
                Logger::debug('Received HTTP 304 (Not Modified) - attempting to re-use cached metadata');
                $this->addCachedMetadata($source);
                return;
            } elseif (!preg_match('@^HTTP/1\.[01]\s200\s@', $responseHeaders[0])) {
                // Other error
                Logger::info('Error from ' . $source['src'] . ' - attempting to re-use cached metadata');
                $this->addCachedMetadata($source);
                return;
            }
        } else {
            // Local file.
            $data = file_get_contents($source['src']);
            $responseHeaders = null;
        }

        // Everything OK. Proceed.
        if (isset($source['conditionalGET']) && $source['conditionalGET']) {
            // Stale or no metadata, so a fresh copy
            Logger::debug('Downloaded fresh copy');
        }

        try {
            $entities = $this->loadXML($data, $source);
        } catch (Exception $e) {
            Logger::notice(
                'XML parser error when parsing ' . $source['src'] . ' - attempting to re-use cached metadata',
            );
            Logger::debug('XML parser returned: ' . $e->getMessage());
            $this->addCachedMetadata($source);
            return;
        }

        foreach ($entities as $entity) {
            if (!$this->processBlacklist($entity, $source)) {
                continue;
            }
            if (!$this->processWhitelist($entity, $source)) {
                continue;
            }
            if (!$this->processAttributeWhitelist($entity, $source)) {
                continue;
            }
            if (!$this->processCertificates($entity, $source)) {
                continue;
            }

            $template = null;
            if (array_key_exists('template', $source)) {
                $template = $source['template'];
            }

            if (array_key_exists('regex-template', $source)) {
                foreach ($source['regex-template'] as $e => $t) {
                    if (preg_match($e, $entity->getEntityID())) {
                        if (is_array($template)) {
                            $template = array_merge($template, $t);
                        } else {
                            $template = $t;
                        }
                    }
                }
            }

            if (in_array('saml20-sp-remote', $this->types, true)) {
                $this->addMetadata($source['src'], $entity->getMetadata20SP(), 'saml20-sp-remote', $template);
            }
            if (in_array('saml20-idp-remote', $this->types, true)) {
                $this->addMetadata($source['src'], $entity->getMetadata20IdP(), 'saml20-idp-remote', $template);
            }
            if (in_array('attributeauthority-remote', $this->types, true)) {
                $attributeAuthorities = $entity->getAttributeAuthorities();
                if (count($attributeAuthorities) && !empty($attributeAuthorities[0])) {
                    $this->addMetadata(
                        $source['src'],
                        $attributeAuthorities[0],
                        'attributeauthority-remote',
                        $template,
                    );
                }
            }
        }

        Logger::debug(sprintf('Found %d entities', count($entities)));
        $this->saveState($source, $responseHeaders);
    }


    /**
     * @param \SimpleSAML\Metadata\SAMLParser $entity
     * @param array $source
     * @bool
     */
    private function processCertificates(Metadata\SAMLParser $entity, array $source): bool
    {
        if (array_key_exists('certificates', $source) && ($source['certificates'] !== null)) {
            if (!$entity->validateSignature($source['certificates'])) {
                $entityId = $entity->getEntityId();
                Logger::notice(
                    'Skipping "' . $entityId . '" - could not verify signature using certificate.' . "\n",
                );
                return false;
            }
        }
        return true;
    }


    /**
     * @param \SimpleSAML\Metadata\SAMLParser $entity
     * @param array $source
     * @bool
     */
    private function processBlacklist(Metadata\SAMLParser $entity, array $source): bool
    {
        if (isset($source['blacklist'])) {
            if (!empty($source['blacklist']) && in_array($entity->getEntityId(), $source['blacklist'], true)) {
                Logger::info('Skipping "' . $entity->getEntityId() . '" - blacklisted.' . "\n");
                return false;
            }
        }
        return true;
    }


    /**
     * @param \SimpleSAML\Metadata\SAMLParser $entity
     * @param array $source
     * @bool
     */
    private function processWhitelist(Metadata\SAMLParser $entity, array $source): bool
    {
        if (isset($source['whitelist'])) {
            if (!empty($source['whitelist']) && !in_array($entity->getEntityId(), $source['whitelist'], true)) {
                Logger::info('Skipping "' . $entity->getEntityId() . '" - not in the whitelist.' . "\n");
                return false;
            }
        }
        return true;
    }


    /**
     * @param \SimpleSAML\Metadata\SAMLParser $entity
     * @param array $source
     * @bool
     */
    private function processAttributeWhitelist(Metadata\SAMLParser $entity, array $source): bool
    {
        /* Do we have an attribute whitelist? */
        if (isset($source['attributewhitelist']) && !empty($source['attributewhitelist'])) {
            $idpMetadata = $entity->getMetadata20IdP();
            if (!isset($idpMetadata)) {
                /* Skip non-IdPs */
                return false;
            }

            /**
             * Do a recursive comparison for each whitelist of the attributewhitelist with the idpMetadata for this
             * IdP. At least one of these whitelists should match
             */
            $match = false;
            foreach ($source['attributewhitelist'] as $whitelist) {
                if ($this->containsArray($whitelist, $idpMetadata)) {
                    $match = true;
                    break;
                }
            }

            if (!$match) {
                /* No match found -> next IdP */
                return false;
            }
            Logger::debug('Whitelisted entityID: ' . $entity->getEntityID());
        }
        return true;
    }


    /**
     * @param array|string $src
     * @param array|string $dst
     * @return bool
     *
     * Recursively checks whether array $dst contains array $src. If $src
     * is not an array, a literal comparison is being performed.
     */
    private function containsArray($src, $dst): bool
    {
        if (is_array($src)) {
            if (!is_array($dst)) {
                return false;
            }
            $dstKeys = array_keys($dst);

            /* Loop over all src keys */
            foreach ($src as $srcKey => $srcval) {
                if (is_int($srcKey)) {
                    /* key is number, check that the key appears as one
                     * of the destination keys: if not, then src has
                     * more keys than dst */
                    if (!array_key_exists($srcKey, $dst)) {
                        return false;
                    }

                    /* loop over dest keys, to find value: we don't know
                     * whether they are in the same order */
                    $submatch = false;
                    foreach ($dstKeys as $dstKey) {
                        if ($this->containsArray($srcval, $dst[$dstKey])) {
                            $submatch = true;
                            break;
                        }
                    }
                    if (!$submatch) {
                        return false;
                    }
                } else {
                    /* key is regexp: find matching keys */
                    /** @var array|false $matchingDstKeys */
                    $matchingDstKeys = preg_grep($srcKey, $dstKeys);
                    if (!is_array($matchingDstKeys)) {
                        return false;
                    }

                    $match = false;
                    foreach ($matchingDstKeys as $dstKey) {
                        if ($this->containsArray($srcval, $dst[$dstKey])) {
                            /* Found a match */
                            $match = true;
                            break;
                        }
                    }
                    if (!$match) {
                        /* none of the keys has a matching value */
                        return false;
                    }
                }
            }
            /* each src key/value matches */
            return true;
        } else {
            /* src is not an array, do a regexp match against dst */
            return (preg_match($src, strval($dst)) === 1);
        }
    }

    /**
     * Create HTTP context, with any available caches taken into account
     *
     * @param array $source
     * @return array
     */
    private function createContext(array $source): array
    {
        $config = Configuration::getInstance();
        $name = $config->getOptionalString('technicalcontact_name', null);
        $mail = $config->getOptionalString('technicalcontact_email', null);

        $rawheader = "User-Agent: SimpleSAMLphp metarefresh, run by $name <$mail>\r\n";

        if (isset($source['conditionalGET']) && $source['conditionalGET']) {
            if (array_key_exists($source['src'], $this->state)) {
                $sourceState = $this->state[$source['src']];

                if (isset($sourceState['last-modified'])) {
                    $rawheader .= 'If-Modified-Since: ' . $sourceState['last-modified'] . "\r\n";
                }

                if (isset($sourceState['etag'])) {
                    $rawheader .= 'If-None-Match: ' . $sourceState['etag'] . "\r\n";
                }
            }
        }

        return ['http' => ['header' => $rawheader]];
    }

    private function addCachedMetadata(array $source): void
    {
        if (!isset($this->oldMetadataSrc)) {
            Logger::info('No oldMetadataSrc, cannot re-use cached metadata');
            return;
        }

        foreach ($this->types as $type) {
            foreach ($this->oldMetadataSrc->getMetadataSet($type) as $entity) {
                if (array_key_exists('metarefresh:src', $entity)) {
                    if ($entity['metarefresh:src'] == $source['src']) {
                        $this->addMetadata($source['src'], $entity, $type);
                    }
                }
            }
        }
    }


    /**
     * Store caching state data for a source
     *
     * @param array $source
     * @param array|null $responseHeaders
     */
    private function saveState(array $source, ?array $responseHeaders): void
    {
        if (isset($source['conditionalGET']) && $source['conditionalGET']) {
            // Headers section
            if ($responseHeaders !== null) {
                $candidates = ['last-modified', 'etag'];

                foreach ($candidates as $candidate) {
                    if (array_key_exists($candidate, $responseHeaders)) {
                        $this->state[$source['src']][$candidate] = $responseHeaders[$candidate];
                    }
                }
            }

            if (!empty($this->state[$source['src']])) {
                // Timestamp when this src was requested.
                $this->state[$source['src']]['requested_at'] = $this->getTime();
                $this->changed = true;
            }
        }
    }


    /**
     * Parse XML metadata and return entities
     *
     * @param string $data
     * @param array $source
     * @return \SimpleSAML\Metadata\SAMLParser[]
     * @throws \Exception
     */
    private function loadXML(string $data, array $source): array
    {
        try {
            $doc = DOMDocumentFactory::fromString($data);
        } catch (Exception $e) {
            throw new Exception('Failed to read XML from ' . $source['src']);
        }
        return Metadata\SAMLParser::parseDescriptorsElement($doc->documentElement);
    }


    /**
     * This function writes the state array back to disk
     *
     */
    public function writeState(): void
    {
        if ($this->changed && !is_null($this->stateFile)) {
            Logger::debug('Writing: ' . $this->stateFile);
            $sysUtils = new Utils\System();
            $sysUtils->writeFile(
                $this->stateFile,
                "<?php\n/* This file was generated by the metarefresh module at " . $this->getTime() . ".\n" .
                " Do not update it manually as it will get overwritten. */\n" .
                '$state = ' . var_export($this->state, true) . ";\n",
                0644,
            );
        }
    }


    /**
     * This function writes the metadata to stdout.
     *
     */
    public function dumpMetadataStdOut(): void
    {
        foreach ($this->metadata as $category => $elements) {
            echo '/* The following data should be added to metadata/' . $category . '.php. */' . "\n";

            foreach ($elements as $m) {
                $filename = $m['filename'];
                $entityID = $m['metadata']['entityid'];
                $time = $this->getTime();
                echo "\n";
                echo '/* The following metadata was generated from ' . $filename . ' on ' . $time . '. */' . "\n";
                echo '$metadata[\'' . addslashes($entityID) . '\'] = ' . var_export($m['metadata'], true) . ';' . "\n";
            }

            echo "\n";
            echo '/* End of data which should be added to metadata/' . $category . '.php. */' . "\n";
            echo "\n";
        }
    }


    /**
     * This function adds metadata from the specified file to the list of metadata.
     * This function will return without making any changes if $metadata is NULL.
     *
     * @param string $filename The filename the metadata comes from.
     * @param \SAML2\XML\md\AttributeAuthorityDescriptor[]|null $metadata The metadata.
     * @param string $type The metadata type.
     * @param array|null $template The template.
     */
    private function addMetadata(string $filename, ?array $metadata, string $type, array $template = null): void
    {
        if ($metadata === null) {
            return;
        }

        if (isset($template)) {
            $metadata = array_merge($metadata, $template);
        }

        $metadata['metarefresh:src'] = $filename;
        if (!array_key_exists($type, $this->metadata)) {
            $this->metadata[$type] = [];
        }

        // If expire is defined in constructor...
        if (!empty($this->expire)) {
            // If expire is already in metadata
            if (array_key_exists('expire', $metadata)) {
                // Override metadata expire with more restrictive global config
                if ($this->expire < $metadata['expire']) {
                    $metadata['expire'] = $this->expire;
                }

                // If expire is not already in metadata use global config
            } else {
                $metadata['expire'] = $this->expire;
            }
        }
        $this->metadata[$type][] = ['filename' => $filename, 'metadata' => $metadata];
    }


    /**
     * This function writes the metadata to an ARP file
     *
     * @param \SimpleSAML\Configuration $config
     */
    public function writeARPfile(Configuration $config): void
    {
        $arpfile = $config->getString('arpfile');
        $types = ['saml20-sp-remote'];

        $md = [];
        foreach ($this->metadata as $category => $elements) {
            if (!in_array($category, $types, true)) {
                continue;
            }
            $md = array_merge($md, $elements);
        }

        // $metadata, $attributemap, $prefix, $suffix
        $arp = new ARP(
            $md,
            $config->getOptionalString('attributemap', ''),
            $config->getOptionalString('prefix', ''),
            $config->getOptionalString('suffix', ''),
        );


        $arpxml = $arp->getXML();

        Logger::info('Writing ARP file: ' . $arpfile . "\n");
        file_put_contents($arpfile, $arpxml);
    }


    /**
     * This function writes the metadata to to separate files in the output directory.
     *
     * @param string $outputDir
     */
    public function writeMetadataFiles(string $outputDir): void
    {
        while (strlen($outputDir) > 0 && $outputDir[strlen($outputDir) - 1] === '/') {
            $outputDir = substr($outputDir, 0, strlen($outputDir) - 1);
        }

        if (!file_exists($outputDir)) {
            Logger::info('Creating directory: ' . $outputDir . "\n");
            $res = @mkdir($outputDir, 0777, true);
            if ($res === false) {
                throw new Exception('Error creating directory: ' . $outputDir);
            }
        }

        foreach ($this->types as $type) {
            $filename = $outputDir . '/' . $type . '.php';

            if (array_key_exists($type, $this->metadata)) {
                $elements = $this->metadata[$type];
                Logger::debug('Writing: ' . $filename);

                $content  = '<?php' . "\n" . '/* This file was generated by the metarefresh module at ';
                $content .= $this->getTime() . "\nDo not update it manually as it will get overwritten\n" . '*/' . "\n";

                foreach ($elements as $m) {
                    $entityID = $m['metadata']['entityid'];
                    $content .= "\n" . '$metadata[\'';
                    $content .= addslashes($entityID) . '\'] = ' . VarExporter::export($m['metadata']) . ';' . "\n";
                }

                $sysUtils = new Utils\System();
                $sysUtils->writeFile($filename, $content, 0644);
            } elseif (is_file($filename)) {
                if (unlink($filename)) {
                    Logger::debug('Deleting stale metadata file: ' . $filename);
                } else {
                    Logger::warning('Could not delete stale metadata file: ' . $filename);
                }
            }
        }
    }


    /**
     * Save metadata for loading with the 'serialize' metadata loader.
     *
     * @param string $outputDir  The directory we should save the metadata to.
     */
    public function writeMetadataSerialize(string $outputDir): void
    {
        $metaHandler = new Metadata\MetaDataStorageHandlerSerialize(['directory' => $outputDir]);

        // First we add all the metadata entries to the metadata handler
        foreach ($this->metadata as $set => $elements) {
            foreach ($elements as $m) {
                $entityId = $m['metadata']['entityid'];

                Logger::debug(sprintf(
                    'metarefresh: Add metadata entry %s in set %s.',
                    var_export($entityId, true),
                    var_export($set, true),
                ));
                $metaHandler->saveMetadata($entityId, $set, $m['metadata']);
            }
        }
    }


    /**
     * This function uses the `PDO` metadata handler to upsert metadata in database.
     *
     * @param \SimpleSAML\Configuration $globalConfig
     * @param array $config An associative array with the configuration for `PDO` handler.
     *
     * @return void
     */
    public function writeMetadataPdo(Configuration $globalConfig, array $config = []): void
    {
        $metaHandler = new Metadata\MetaDataStorageHandlerPdo($globalConfig->toArray(), $config);

        foreach ($this->metadata as $set => $elements) {
            foreach ($elements as $m) {
                $entityId = $m['metadata']['entityid'];

                Logger::debug("PDO Metarefresh: Upsert metadata entry `{$entityId}` in set `{$set}`.");
                $metaHandler->addEntry($entityId, $set, $m['metadata']);
            }
        }
    }


    /**
     * @return string
     */
    private function getTime(): string
    {
        // The current date, as a string
        return gmdate('Y-m-d\\TH:i:s\\Z');
    }
}
