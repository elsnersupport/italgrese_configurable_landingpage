<?php
declare(strict_types=1);

namespace Italgres\Configurator\Block\Adminhtml\Quote;

use Italgres\Configurator\Controller\Adminhtml\Quote\View as ViewController;
use Italgres\Configurator\Model\Quote\QuoteService;
use Italgres\Configurator\Model\QuoteRequest;
use Italgres\Configurator\Model\Source\CustomerType;
use Italgres\Configurator\Model\Source\QuoteStatus;
use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Registry;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

class View extends Template
{
    public function __construct(
        Context $context,
        private readonly Registry $registry,
        private readonly QuoteStatus $statuses,
        private readonly CustomerType $customerTypes,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly StoreManagerInterface $storeManager,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getRequestModel(): ?QuoteRequest
    {
        return $this->registry->registry(ViewController::REGISTRY_KEY);
    }

    public function formatMoney(float $amount): string
    {
        return (string)$this->priceCurrency->format($amount, false, 0, null, $this->getRequestModel()?->getData('currency'));
    }

    public function getSnapshotUrl(): ?string
    {
        $file = (string)$this->getRequestModel()?->getData('snapshot');
        if ($file === '') {
            return null;
        }
        $store = $this->storeManager->getStore((int)$this->getRequestModel()->getData('store_id') ?: null);

        return $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . QuoteService::MEDIA_PATH . '/' . $file;
    }

    public function getStatuses(): array
    {
        return $this->statuses->toOptionArray();
    }

    public function getStatusLabel(): string
    {
        return $this->statuses->getLabel((string)$this->getRequestModel()?->getData('status'));
    }

    public function getCustomerTypeLabel(): string
    {
        return $this->customerTypes->getLabel($this->getRequestModel()?->getData('customer_type'));
    }

    public function getSaveUrl(): string
    {
        return $this->getUrl('*/*/save', ['id' => $this->getRequestModel()?->getId()]);
    }

    public function getDeleteUrl(): string
    {
        return $this->getUrl('*/*/delete', ['id' => $this->getRequestModel()?->getId()]);
    }

    public function getBackUrl(): string
    {
        return $this->getUrl('*/*/');
    }
}
