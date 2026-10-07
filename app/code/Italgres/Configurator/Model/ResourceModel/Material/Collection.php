<?php
declare(strict_types=1);

namespace Italgres\Configurator\Model\ResourceModel\Material;

use Italgres\Configurator\Model\Material;
use Italgres\Configurator\Model\ResourceModel\Material as MaterialResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'material_id';

    protected function _construct(): void
    {
        $this->_init(Material::class, MaterialResource::class);
    }
}
