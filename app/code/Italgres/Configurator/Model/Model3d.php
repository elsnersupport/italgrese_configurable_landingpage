<?php
declare(strict_types=1);

namespace Italgres\Configurator\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * GLB model in the 3D library.
 */
class Model3d extends AbstractModel
{
    public const CACHE_TAG = 'italgres_cfg_model';

    protected $_eventPrefix = 'italgres_cfg_model';

    protected function _construct(): void
    {
        $this->_init(ResourceModel\Model3d::class);
    }
}
