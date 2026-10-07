<?php
declare(strict_types=1);

namespace Italgres\Configurator\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Stores > Configuration > Italgres > 3D Configurator.
 */
class Config
{
    private const PATH = 'italgres_configurator/purchase/';

    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    public function isCartEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'cart_enabled', ScopeInterface::SCOPE_STORE);
    }

    public function isQuoteEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'quote_enabled', ScopeInterface::SCOPE_STORE);
    }

    /**
     * @return string[]
     */
    public function getQuoteRecipients(): array
    {
        $value = (string)$this->scopeConfig->getValue(self::PATH . 'quote_recipient', ScopeInterface::SCOPE_STORE);

        return array_values(array_filter(
            array_map('trim', preg_split('/[,;\s]+/', $value) ?: []),
            static fn(string $email) => (bool)filter_var($email, FILTER_VALIDATE_EMAIL)
        ));
    }

    public function getQuoteSender(): string
    {
        return (string)$this->scopeConfig->getValue(self::PATH . 'quote_sender', ScopeInterface::SCOPE_STORE) ?: 'general';
    }

    public function getAdminTemplate(): string
    {
        return (string)$this->scopeConfig->getValue(self::PATH . 'quote_admin_template', ScopeInterface::SCOPE_STORE);
    }

    public function getCustomerTemplate(): string
    {
        return (string)$this->scopeConfig->getValue(self::PATH . 'quote_customer_template', ScopeInterface::SCOPE_STORE);
    }

    public function sendCustomerCopy(): bool
    {
        return $this->scopeConfig->isSetFlag(self::PATH . 'quote_customer_copy', ScopeInterface::SCOPE_STORE);
    }
}
