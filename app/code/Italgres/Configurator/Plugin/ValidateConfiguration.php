<?php
declare(strict_types=1);

namespace Italgres\Configurator\Plugin;

use Italgres\Configurator\Model\Rules;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Type\AbstractType;
use Magento\Framework\DataObject;

/**
 * Rejects add-to-cart requests whose option combination breaks the configurator rules
 * (e.g. a leather colour posted together with a velvet upholstery).
 */
class ValidateConfiguration
{
    public function __construct(private readonly Rules $rules)
    {
    }

    /**
     * @param string|null $processMode
     */
    public function beforePrepareForCartAdvanced(
        AbstractType $subject,
        DataObject $buyRequest,
        $product,
        $processMode = null
    ): void {
        if (!$product instanceof Product
            || !$product->getData('ig_cfg_enabled')
            || ($processMode ?? AbstractType::PROCESS_MODE_FULL) !== AbstractType::PROCESS_MODE_FULL
        ) {
            return;
        }
        $this->rules->validate($product, (array)$buyRequest->getData('options'));
    }
}
