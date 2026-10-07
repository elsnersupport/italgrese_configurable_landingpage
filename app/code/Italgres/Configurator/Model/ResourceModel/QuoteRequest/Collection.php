<?php
declare(strict_types=1);

namespace Italgres\Configurator\Model\ResourceModel\QuoteRequest;

use Italgres\Configurator\Model\QuoteRequest;
use Italgres\Configurator\Model\ResourceModel\QuoteRequest as QuoteRequestResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'request_id';

    protected function _construct(): void
    {
        $this->_init(QuoteRequest::class, QuoteRequestResource::class);
    }
}
