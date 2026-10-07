<?php
declare(strict_types=1);

namespace Italgres\Configurator\Ui\DataProvider;

use Italgres\Configurator\Model\Media;
use Italgres\Configurator\Model\ResourceModel\Material\CollectionFactory;
use Magento\Ui\DataProvider\AbstractDataProvider;

class MaterialFormDataProvider extends AbstractDataProvider
{
    private ?array $loadedData = null;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly Media $media,
        array $meta = [],
        array $data = []
    ) {
        parent::__construct($name, $primaryFieldName, $requestFieldName, $meta, $data);
        $this->collection = $collectionFactory->create();
    }

    public function getData(): array
    {
        if ($this->loadedData === null) {
            $this->loadedData = [];
            foreach ($this->collection->getItems() as $material) {
                $data = $material->getData();
                foreach (['map', 'normal_map', 'roughness_map', 'swatch'] as $field) {
                    $data[$field] = $this->media->toUploaderValue($material->getData($field));
                }
                $this->loadedData[$material->getId()] = $data;
            }
        }

        return $this->loadedData;
    }
}
