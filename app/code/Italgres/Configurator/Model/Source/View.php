<?php
declare(strict_types=1);

namespace Italgres\Configurator\Model\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

/**
 * Camera preset the 3D viewer opens on.
 */
class View extends AbstractSource
{
    public function getAllOptions(): array
    {
        return [
            ['value' => 'angle', 'label' => __('¾ view, slowly turning')],
            ['value' => 'front', 'label' => __('Front, still (catalogue)')],
        ];
    }
}
