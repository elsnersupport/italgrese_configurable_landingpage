<?php
declare(strict_types=1);

namespace Italgres\Configurator\Model\Source;

use Italgres\Configurator\Model\ResourceModel\Material\CollectionFactory;
use Magento\Framework\Data\OptionSourceInterface;

/**
 * Library materials for the "Material" column of custom option values.
 */
class Material implements OptionSourceInterface
{
    private ?array $options = null;

    public function __construct(private readonly CollectionFactory $collectionFactory)
    {
    }

    public function toOptionArray(): array
    {
        if ($this->options === null) {
            $this->options = [['value' => '', 'label' => __('-- none --')]];
            $collection = $this->collectionFactory->create()
                ->setOrder('family', 'ASC')
                ->setOrder('sort_order', 'ASC')
                ->setOrder('name', 'ASC');
            foreach ($collection as $material) {
                $this->options[] = [
                    'value' => (string)$material->getId(),
                    'label' => sprintf('%s · %s', ucfirst((string)$material->getFamily()), $material->getName()),
                ];
            }
        }

        return $this->options;
    }
}
