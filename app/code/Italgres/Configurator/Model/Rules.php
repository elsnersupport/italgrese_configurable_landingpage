<?php
declare(strict_types=1);

namespace Italgres\Configurator\Model;

use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Option;
use Magento\Framework\Exception\LocalizedException;

/**
 * Dependency rules between configurator steps.
 *
 * A value's "Requires" field lists value SKUs from earlier steps. The value is available when,
 * for every step those SKUs belong to, the selected value is one of them. A step with no
 * available value is skipped; every other select step must have a selection.
 * The storefront runs the same logic in JS; this class is the server-side guard.
 */
class Rules
{
    private const SELECT_TYPES = ['drop_down', 'radio'];

    /**
     * @param array<int|string, mixed> $selected option_id => option_type_id
     * @throws LocalizedException
     */
    public function validate(Product $product, array $selected): void
    {
        $options = $this->getSelectOptions($product);
        $skuToOption = [];
        $valueById = [];
        foreach ($options as $option) {
            foreach ($option->getValues() ?: [] as $value) {
                $valueById[(int)$value->getOptionTypeId()] = $value;
                if ((string)$value->getSku() !== '') {
                    $skuToOption[(string)$value->getSku()] = (int)$option->getId();
                }
            }
        }
        $selectedSku = [];
        foreach ($options as $option) {
            $valueId = (int)($selected[$option->getId()] ?? 0);
            if ($valueId && isset($valueById[$valueId])) {
                $selectedSku[(int)$option->getId()] = (string)$valueById[$valueId]->getSku();
            }
        }

        foreach ($options as $option) {
            $optionId = (int)$option->getId();
            $available = array_filter(
                $option->getValues() ?: [],
                fn($value) => $this->isAvailable((string)$value->getData('ig_requires'), $skuToOption, $selectedSku)
            );
            $valueId = (int)($selected[$optionId] ?? 0);
            if (!$available) {
                if ($valueId) {
                    throw new LocalizedException(
                        __('"%1" is not available with the selected configuration.', $option->getTitle())
                    );
                }
                continue;
            }
            if (!$valueId) {
                throw new LocalizedException(__('Please choose a value for "%1".', $option->getTitle()));
            }
            $ids = array_map(static fn($value) => (int)$value->getOptionTypeId(), $available);
            if (!in_array($valueId, $ids, true)) {
                throw new LocalizedException(
                    __('The selected "%1" is not available with the rest of the configuration.', $option->getTitle())
                );
            }
        }
    }

    /**
     * @param array<string, int> $skuToOption
     * @param array<int, string> $selectedSku
     */
    public function isAvailable(string $requires, array $skuToOption, array $selectedSku): bool
    {
        $groups = [];
        foreach ($this->parseRequires($requires) as $sku) {
            if (isset($skuToOption[$sku])) {
                $groups[$skuToOption[$sku]][] = $sku;
            }
        }
        foreach ($groups as $optionId => $skus) {
            if (!in_array($selectedSku[$optionId] ?? null, $skus, true)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return string[]
     */
    public function parseRequires(?string $requires): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\s,]+/', (string)$requires) ?: [])));
    }

    /**
     * @return Option[]
     */
    private function getSelectOptions(Product $product): array
    {
        $options = $product->getOptions() ?: $product->getProductOptionsCollection()->getItems();

        return array_values(array_filter(
            $options,
            static fn($option) => in_array($option->getType(), self::SELECT_TYPES, true)
        ));
    }
}
