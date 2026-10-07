<?php
declare(strict_types=1);

namespace Italgres\Configurator\Ui\DataProvider;

use Italgres\Configurator\Model\Media;
use Italgres\Configurator\Model\ResourceModel\Model3d\CollectionFactory;
use Magento\Framework\Escaper;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Ui\DataProvider\AbstractDataProvider;

class ModelFormDataProvider extends AbstractDataProvider
{
    private ?array $loadedData = null;

    public function __construct(
        $name,
        $primaryFieldName,
        $requestFieldName,
        CollectionFactory $collectionFactory,
        private readonly Media $media,
        private readonly Json $json,
        private readonly Escaper $escaper,
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
            foreach ($this->collection->getItems() as $model) {
                $data = $model->getData();
                $data['file'] = $this->media->toUploaderValue($model->getFile());
                $data['slots'] = $this->formatSlots((string)$model->getSlots());
                $this->loadedData[$model->getId()] = $data;
            }
        }

        return $this->loadedData;
    }

    private function formatSlots(string $slots): string
    {
        if ($slots === '') {
            return '';
        }
        try {
            $data = $this->json->unserialize($slots);
        } catch (\InvalidArgumentException) {
            return '';
        }
        $lines = [];
        foreach (['materials' => 'Materials', 'meshes' => 'Meshes', 'nodes' => 'Nodes'] as $key => $label) {
            $lines[] = $label . ': ' . implode(', ', (array)($data[$key] ?? []));
        }

        return implode("\n", $lines);
    }
}
