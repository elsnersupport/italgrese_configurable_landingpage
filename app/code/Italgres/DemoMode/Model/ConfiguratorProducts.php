<?php
declare(strict_types=1);

namespace Italgres\DemoMode\Model;

use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * The storefront-visible products that have the configurator page switched on.
 */
class ConfiguratorProducts
{
    /** @var array<int, array{id: int, sku: string, name: string, url: string}>|null */
    private ?array $products = null;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Config $config
    ) {
    }

    /**
     * @return array<int, array{id: int, sku: string, name: string, url: string}>
     */
    public function getAll(): array
    {
        if ($this->products !== null) {
            return $this->products;
        }
        $collection = $this->collectionFactory->create();
        // Made-to-order pieces: don't hide them behind the stock index (it may be stale on a demo box without cron)
        $collection->setFlag('has_stock_status_filter', true);
        $collection->setStoreId((int)$this->storeManager->getStore()->getId())
            ->addAttributeToSelect(['name', 'url_key'])
            ->addAttributeToFilter('ig_cfg_enabled', 1)
            ->addAttributeToFilter('status', Status::STATUS_ENABLED)
            ->addAttributeToFilter('visibility', ['in' => [Visibility::VISIBILITY_IN_CATALOG, Visibility::VISIBILITY_BOTH]])
            ->addWebsiteFilter((int)$this->storeManager->getStore()->getWebsiteId())
            ->addUrlRewrite()
            ->setOrder('entity_id', 'ASC');

        $landing = $this->config->getLandingSku();
        $products = [];
        foreach ($collection as $product) {
            $products[(int)$product->getId()] = [
                'id' => (int)$product->getId(),
                'sku' => (string)$product->getSku(),
                'name' => (string)$product->getName(),
                'url' => (string)$product->getProductUrl(),
            ];
        }
        uasort($products, static fn($a, $b) => ($b['sku'] === $landing) <=> ($a['sku'] === $landing));

        return $this->products = $products;
    }

    public function isConfigurator(int $productId): bool
    {
        return isset($this->getAll()[$productId]);
    }

    public function getLandingUrl(): ?string
    {
        $products = $this->getAll();

        return $products ? reset($products)['url'] : null;
    }
}
