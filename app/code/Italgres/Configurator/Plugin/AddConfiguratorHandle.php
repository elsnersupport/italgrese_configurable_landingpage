<?php
declare(strict_types=1);

namespace Italgres\Configurator\Plugin;

use Magento\Catalog\Helper\Product\View;
use Magento\Catalog\Model\Product;
use Magento\Framework\View\Result\Page;

/**
 * Products with the configurator switched on get the italgres_configurator_product handle.
 * Added before initProductLayout() because the layout is loaded while that method runs.
 */
class AddConfiguratorHandle
{
    public const HANDLE = 'italgres_configurator_product';

    /**
     * @param mixed $product
     * @param mixed $params
     */
    public function beforeInitProductLayout(View $subject, Page $resultPage, $product, $params = null): void
    {
        if ($product instanceof Product && $product->getData('ig_cfg_enabled')) {
            $resultPage->addHandle(self::HANDLE);
            $resultPage->getConfig()->setPageLayout('1column');
        }
    }
}
