<?php
declare(strict_types=1);

namespace Italgres\Configurator\Controller\Adminhtml\Model;

use Italgres\Configurator\Controller\Adminhtml\AbstractUpload;

class Upload extends AbstractUpload
{
    public const ADMIN_RESOURCE = 'Italgres_Configurator::models';

    protected function getRule(string $field): array
    {
        return ['folder' => 'models', 'extensions' => ['glb']];
    }
}
