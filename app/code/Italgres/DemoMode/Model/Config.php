<?php
declare(strict_types=1);

namespace Italgres\DemoMode\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class Config
{
    private const PATH = 'italgres_demo/general/';

    /** Routes the configurator page itself needs */
    private const ALWAYS_ALLOWED = [
        'italgres_cfg_cart_add',
        'italgres_cfg_cart_remove',
        'italgres_cfg_quote_submit',
        'customer_section_load',
        'page_cache_block_esi',
        'page_cache_block_render',
        'robots_index_index',
    ];

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function isLocked(): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'lock_enabled', ScopeInterface::SCOPE_STORE);
    }

    public function getLandingSku(): string
    {
        return trim((string)$this->scopeConfig->getValue(self::PATH . 'landing_sku', ScopeInterface::SCOPE_STORE));
    }

    /**
     * @return string[]
     */
    public function getAllowedActions(): array
    {
        $extra = preg_split('/[\s,]+/', (string)$this->scopeConfig->getValue(self::PATH . 'allowed_actions', ScopeInterface::SCOPE_STORE)) ?: [];

        return array_values(array_unique(array_merge(self::ALWAYS_ALLOWED, array_filter(array_map('trim', $extra)))));
    }

    public function getBrandName(): string
    {
        return (string)$this->scopeConfig->getValue(self::PATH . 'brand_name', ScopeInterface::SCOPE_STORE);
    }

    public function getBadge(): string
    {
        return (string)$this->scopeConfig->getValue(self::PATH . 'badge', ScopeInterface::SCOPE_STORE);
    }

    public function showNav(): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'show_nav', ScopeInterface::SCOPE_STORE);
    }
}
