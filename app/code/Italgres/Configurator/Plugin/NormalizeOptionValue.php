<?php
declare(strict_types=1);

namespace Italgres\Configurator\Plugin;

use Magento\Catalog\Model\Product\Option\Value;

/**
 * The product form posts empty configurator fields as '' (and an empty material select as '' or '0').
 * Store them as NULL so the material foreign key and "no action" checks stay clean.
 */
class NormalizeOptionValue
{
    private const FIELDS = ['ig_action', 'ig_target', 'ig_material_id', 'ig_color', 'ig_param', 'ig_requires'];

    public function beforeBeforeSave(Value $subject): void
    {
        foreach (self::FIELDS as $field) {
            if (!$subject->hasData($field)) {
                continue;
            }
            $value = $subject->getData($field);
            $value = is_string($value) ? trim($value) : $value;
            if ($value === '' || ($field === 'ig_material_id' && !(int)$value)) {
                $value = null;
            } elseif ($field === 'ig_material_id') {
                $value = (int)$value;
            }
            $subject->setData($field, $value);
        }
    }
}
