<?php
declare(strict_types=1);

namespace Italgres\Configurator\Model;

use Magento\Framework\Model\AbstractModel;

/**
 * Library material (fabric, leather, wood, metal, porcelain slab) applied to 3D model parts.
 */
class Material extends AbstractModel
{
    public const CACHE_TAG = 'italgres_cfg_material';

    protected $_eventPrefix = 'italgres_cfg_material';

    protected function _construct(): void
    {
        $this->_init(ResourceModel\Material::class);
    }
}
