<?php
declare(strict_types=1);

namespace Italgres\DemoMode\ViewModel;

use Italgres\Configurator\Model\Config as ConfiguratorConfig;
use Italgres\DemoMode\Model\Config;
use Italgres\DemoMode\Model\ConfiguratorProducts;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Framework\View\Element\Block\ArgumentInterface;

class DemoNav implements ArgumentInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly ConfiguratorProducts $products,
        private readonly CatalogHelper $catalogHelper,
        private readonly ConfiguratorConfig $configuratorConfig
    ) {
    }

    public function getBrandName(): string
    {
        return $this->config->getBrandName() ?: 'Italgres';
    }

    public function getBadge(): string
    {
        return $this->config->getBadge();
    }

    public function isCartEnabled(): bool
    {
        return $this->configuratorConfig->isCartEnabled();
    }

    public function getHomeUrl(): string
    {
        return $this->products->getLandingUrl() ?: '/';
    }

    /**
     * @return array<int, array{id: int, sku: string, name: string, url: string, active: bool}>
     */
    public function getProducts(): array
    {
        if (!$this->config->showNav()) {
            return [];
        }
        $currentId = (int)$this->catalogHelper->getProduct()?->getId();

        return array_values(array_map(
            static fn(array $p) => $p + ['active' => $p['id'] === $currentId],
            $this->products->getAll()
        ));
    }
}
