<?php
declare(strict_types=1);

namespace Italgres\Configurator\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class Display implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'tile', 'label' => __('Tiles with swatch')],
            ['value' => 'swatch', 'label' => __('Round swatches')],
            ['value' => 'pill', 'label' => __('Buttons')],
            ['value' => 'stepper', 'label' => __('Plus / minus stepper')],
            ['value' => 'module', 'label' => __('Element tiles (3D preview)')],
            ['value' => 'sequence', 'label' => __('Element list (add / remove)')],
        ];
    }
}
