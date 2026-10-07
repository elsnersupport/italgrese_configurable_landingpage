<?php
declare(strict_types=1);

namespace Italgres\Configurator\Controller\Adminhtml\Material;

use Italgres\Configurator\Controller\Adminhtml\AbstractUpload;

class Upload extends AbstractUpload
{
    public const ADMIN_RESOURCE = 'Italgres_Configurator::materials';

    protected function getRule(string $field): array
    {
        return [
            'folder' => $field === 'swatch' ? 'swatches' : 'textures',
            'extensions' => ['jpg', 'jpeg', 'png', 'webp'],
        ];
    }
}
