<?php

declare(strict_types=1);

namespace SimpleSAML\Module\statistics\Statistics\FieldPresentation;

use SimpleSAML\Metadata\MetaDataStorageHandler;

class Entity extends Base
{
    /**
     * @return array
     */
    public function getPresentation(): array
    {
        $mh = MetaDataStorageHandler::getMetadataHandler();
        $metadata = $mh->getList($this->config);

        $translation = ['_' => 'All services'];
        foreach ($this->fields as $field) {
            if (array_key_exists($field, $metadata)) {
                $translation[$field] = $this->template->getEntityDisplayName($metadata[$field]);
            }
        }
        return $translation;
    }
}
