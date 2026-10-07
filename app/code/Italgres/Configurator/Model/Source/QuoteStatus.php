<?php
declare(strict_types=1);

namespace Italgres\Configurator\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

class QuoteStatus implements OptionSourceInterface
{
    public const NEW = 'new';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::NEW, 'label' => __('New')],
            ['value' => 'contacted', 'label' => __('Contacted')],
            ['value' => 'quoted', 'label' => __('Quote sent')],
            ['value' => 'won', 'label' => __('Won')],
            ['value' => 'lost', 'label' => __('Lost')],
        ];
    }

    public function getLabel(string $value): string
    {
        foreach ($this->toOptionArray() as $option) {
            if ($option['value'] === $value) {
                return (string)$option['label'];
            }
        }

        return $value;
    }
}
