<?php
declare(strict_types=1);

namespace Italgres\Configurator\Model\Source;

use Magento\Framework\Data\OptionSourceInterface;

/**
 * What selecting an option value does to the 3D model.
 */
class Action implements OptionSourceInterface
{
    public const NONE = '';
    public const MATERIAL = 'material';
    public const COLOR = 'color';
    public const PROPS = 'props';
    public const STRETCH = 'stretch';
    public const MODEL = 'model';
    public const VISIBILITY = 'visibility';
    public const REPEAT = 'repeat';
    public const COMPOSE = 'compose';

    public function toOptionArray(): array
    {
        return [
            ['value' => self::NONE, 'label' => __('No 3D change')],
            ['value' => self::MATERIAL, 'label' => __('Apply library material')],
            ['value' => self::COLOR, 'label' => __('Tint colour')],
            ['value' => self::PROPS, 'label' => __('Surface finish (props)')],
            ['value' => self::STRETCH, 'label' => __('Stretch width (cm)')],
            ['value' => self::MODEL, 'label' => __('Swap 3D model (code)')],
            ['value' => self::VISIBILITY, 'label' => __('Show / hide parts')],
            ['value' => self::REPEAT, 'label' => __('Repeat module (count)')],
            ['value' => self::COMPOSE, 'label' => __('Add element to the row (node)')],
        ];
    }
}
