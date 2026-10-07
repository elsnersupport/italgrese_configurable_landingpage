<?php
declare(strict_types=1);

namespace Italgres\Configurator\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Family implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'fabric', 'label' => __('Fabric')],
            ['value' => 'leather', 'label' => __('Leather')],
            ['value' => 'wood', 'label' => __('Wood')],
            ['value' => 'metal', 'label' => __('Metal')],
            ['value' => 'porcelain', 'label' => __('Porcelain / stone')],
            ['value' => 'other', 'label' => __('Other')],
        ];
    }
}
