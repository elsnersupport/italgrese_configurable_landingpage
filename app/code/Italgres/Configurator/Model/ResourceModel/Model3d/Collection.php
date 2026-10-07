<?php
declare(strict_types=1);

namespace Italgres\Configurator\Model\ResourceModel\Model3d;

use Italgres\Configurator\Model\Model3d;
use Italgres\Configurator\Model\ResourceModel\Model3d as Model3dResource;
use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'model_id';

    protected function _construct(): void
    {
        $this->_init(Model3d::class, Model3dResource::class);
    }
}
