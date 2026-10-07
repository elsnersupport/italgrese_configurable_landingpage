<?php
declare(strict_types=1);

namespace Italgres\Configurator\Ui\Component\Listing\Column;

use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Formats an amount in the row's own currency (column "currency").
 */
class Money extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly PriceCurrencyInterface $priceCurrency,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            if (isset($item[$name])) {
                $item[$name] = $this->priceCurrency->format((float)$item[$name], false, 0, null, $item['currency'] ?? null);
            }
        }
        unset($item);

        return $dataSource;
    }
}
