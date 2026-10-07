<?php
declare(strict_types=1);

namespace Italgres\Configurator\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * A "Request a quote" submission from the configurator page.
 */
class QuoteRequest extends AbstractModel
{
    protected $_eventPrefix = 'italgres_cfg_quote_request';

    protected function _construct(): void
    {
        $this->_init(ResourceModel\QuoteRequest::class);
    }

    /**
     * @return array<int, array{step: string, value: string, sku: string, price: float}>
     */
    public function getConfigurationRows(): array
    {
        $rows = json_decode((string)$this->getData('configuration'), true);

        return is_array($rows) ? $rows : [];
    }
}
