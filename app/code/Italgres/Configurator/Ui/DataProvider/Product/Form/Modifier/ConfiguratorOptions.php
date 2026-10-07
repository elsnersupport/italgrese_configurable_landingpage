<?php
declare(strict_types=1);

namespace Italgres\Configurator\Ui\DataProvider\Product\Form\Modifier;

use Italgres\Configurator\Model\Source\Action;
use Italgres\Configurator\Model\Source\Display;
use Italgres\Configurator\Model\Source\Material;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\AbstractModifier;
use Magento\Catalog\Ui\DataProvider\Product\Form\Modifier\CustomOptions;
use Magento\Ui\Component\Form\Element\DataType\Text;
use Magento\Ui\Component\Form\Element\Input;
use Magento\Ui\Component\Form\Element\Select;
use Magento\Ui\Component\Form\Field;

/**
 * Adds the configurator columns to "Customizable Options":
 * per step: display style and help text; per value: 3D action, material, colour, target parts,
 * action parameter and dependency rule.
 */
class ConfiguratorOptions extends AbstractModifier
{
    public function __construct(
        private readonly Action $actionSource,
        private readonly Display $displaySource,
        private readonly Material $materialSource
    ) {
    }

    public function modifyData(array $data)
    {
        return $data;
    }

    public function modifyMeta(array $meta)
    {
        $optionRecord = &$meta[CustomOptions::GROUP_CUSTOM_OPTIONS_NAME]['children'][CustomOptions::GRID_OPTIONS_NAME]
            ['children']['record']['children'][CustomOptions::CONTAINER_OPTION]['children'];
        if (!isset($optionRecord[CustomOptions::CONTAINER_COMMON_NAME])) {
            unset($optionRecord);

            return $meta;
        }

        $optionRecord[CustomOptions::CONTAINER_COMMON_NAME]['children'] += [
            'ig_display' => $this->select('Configurator Style', 'ig_display', 50, $this->displaySource->toOptionArray()),
            'ig_note' => $this->input('Step Help Text', 'ig_note', 60),
        ];

        $valueRecord = &$optionRecord[CustomOptions::GRID_TYPE_SELECT_NAME]['children']['record']['children'];
        $valueRecord += [
            'ig_action' => $this->select('3D Action', 'ig_action', 42, $this->actionSource->toOptionArray(), [
                'tooltip' => ['description' => (string)__(
                    'What choosing this value does in the 3D viewer. Material/Tint/Finish use "3D Parts"; '
                    . 'Stretch uses the width in cm as parameter; Swap model uses a model code; '
                    . 'Show/hide uses part names in "3D Parts" (prefix ! to hide); '
                    . 'Repeat module copies the part in "3D Parts" side by side, Parameter = number of copies; '
                    . 'Add element puts the node in "3D Parts" into the row, left to right in step order '
                    . '(Parameter "mirror" for right-hand ends, empty "3D Parts" adds nothing).'
                )],
            ]),
            'ig_material_id' => $this->select('Material', 'ig_material_id', 43, $this->materialSource->toOptionArray()),
            'ig_color' => $this->input('Colour', 'ig_color', 44, ['placeholder' => '#RRGGBB']),
            'ig_target' => $this->input('3D Parts', 'ig_target', 45, [
                'placeholder' => 'fabric*, legs',
                'tooltip' => ['description' => (string)__(
                    'Material, mesh or node names from the GLB (see the 3D Models page). * is a wildcard.'
                )],
            ]),
            'ig_param' => $this->input('Parameter', 'ig_param', 46, [
                'placeholder' => '220 | roughness:0.1',
            ]),
            'ig_requires' => $this->input('Requires SKUs', 'ig_requires', 47, [
                'tooltip' => ['description' => (string)__(
                    'Only offer this value when one of these value SKUs is selected in that step (comma separated).'
                )],
            ]),
        ];
        unset($valueRecord, $optionRecord);

        return $meta;
    }

    private function input(string $label, string $scope, int $sortOrder, array $extra = []): array
    {
        return ['arguments' => ['data' => ['config' => $extra + [
            'label' => __($label),
            'componentType' => Field::NAME,
            'formElement' => Input::NAME,
            'dataScope' => $scope,
            'dataType' => Text::NAME,
            'sortOrder' => $sortOrder,
        ]]]];
    }

    private function select(string $label, string $scope, int $sortOrder, array $options, array $extra = []): array
    {
        return ['arguments' => ['data' => ['config' => $extra + [
            'label' => __($label),
            'componentType' => Field::NAME,
            'formElement' => Select::NAME,
            'dataScope' => $scope,
            'dataType' => Text::NAME,
            'sortOrder' => $sortOrder,
            'options' => $options,
        ]]]];
    }
}
