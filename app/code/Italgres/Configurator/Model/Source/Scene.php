<?php
declare(strict_types=1);

namespace Italgres\Configurator\Model\Source;

use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

class Scene extends AbstractSource
{
    public function getAllOptions(): array
    {
        return [
            ['value' => 'studio', 'label' => __('Warm studio')],
            ['value' => 'gallery', 'label' => __('Stone gallery')],
            ['value' => 'night', 'label' => __('Evening')],
            ['value' => 'white', 'label' => __('Light studio')],
        ];
    }
}
