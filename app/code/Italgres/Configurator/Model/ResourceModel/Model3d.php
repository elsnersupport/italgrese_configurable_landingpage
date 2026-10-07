<?php
declare(strict_types=1);

namespace Italgres\Configurator\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Model3d extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('italgres_cfg_model', 'model_id');
    }
}
