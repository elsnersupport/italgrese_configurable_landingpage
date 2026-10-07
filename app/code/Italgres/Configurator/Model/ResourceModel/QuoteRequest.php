<?php
declare(strict_types=1);

namespace Italgres\Configurator\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class QuoteRequest extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('italgres_cfg_quote_request', 'request_id');
    }
}
