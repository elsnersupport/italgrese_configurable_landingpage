<?php
declare(strict_types=1);

namespace Italgres\Configurator\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class CustomerType implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'homeowner', 'label' => __('Homeowner')],
            ['value' => 'designer', 'label' => __('Interior designer / architect')],
            ['value' => 'trade', 'label' => __('Trade / retailer')],
        ];
    }

    public function getLabel(?string $value): string
    {
        foreach ($this->toOptionArray() as $option) {
            if ($option['value'] === $value) {
                return (string)$option['label'];
            }
        }

        return (string)$value;
    }
}
