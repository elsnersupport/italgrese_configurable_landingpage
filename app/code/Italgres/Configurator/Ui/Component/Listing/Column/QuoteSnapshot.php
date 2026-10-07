<?php
declare(strict_types=1);

namespace Italgres\Configurator\Ui\Component\Listing\Column;

use Italgres\Configurator\Model\Quote\QuoteService;
use Magento\Framework\Escaper;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Thumbnail of the render the customer saw when requesting the quote.
 */
class QuoteSnapshot extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Escaper $escaper,
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
        $base = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . QuoteService::MEDIA_PATH . '/';
        $name = $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            $item[$name] = empty($item[$name]) ? '' : sprintf(
                '<img src="%s" alt="" style="width:96px;height:72px;object-fit:cover;background:#f4efe8;border:1px solid #e3ddd3;">',
                $this->escaper->escapeUrl($base . $item[$name])
            );
        }
        unset($item);

        return $dataSource;
    }
}
