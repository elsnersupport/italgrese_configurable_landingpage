<?php
declare(strict_types=1);

namespace Italgres\Configurator\Model\Source;

use Italgres\Configurator\Model\ResourceModel\Model3d\CollectionFactory;
use Magento\Eav\Model\Entity\Attribute\Source\AbstractSource;

/**
 * 3D models for the product attribute "3D model".
 */
class Model3d extends AbstractSource
{
    public function __construct(private readonly CollectionFactory $collectionFactory)
    {
    }

    public function getAllOptions(): array
    {
        if ($this->_options === null) {
            $this->_options = [['value' => '', 'label' => __('-- Select a model --')]];
            foreach ($this->collectionFactory->create()->setOrder('name', 'ASC') as $model) {
                $this->_options[] = [
                    'value' => (string)$model->getId(),
                    'label' => sprintf('%s (%s)', $model->getName(), $model->getCode()),
                ];
            }
        }

        return $this->_options;
    }
}
