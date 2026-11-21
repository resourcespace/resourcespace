<?php

declare(strict_types=1);

namespace Montala\ResourceSpace\Tests\Support\Browser;

enum UiElementSelector: string
{
    case HeaderUploadButton = '#header-container .actions .UploadButton';
    case UppyActionUploadButton = '.uppy-StatusBar-actionBtn--upload';
    case CollectionBarToggleButton = '.collection-bar-toggle-button';
    case CollectionBarShowThumbsButton = '.show-thumbs-button';
}
