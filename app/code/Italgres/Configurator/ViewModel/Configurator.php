<?php
declare(strict_types=1);

namespace Italgres\Configurator\ViewModel;

use Italgres\Configurator\Model\Config;
use Italgres\Configurator\Model\Media;
use Italgres\Configurator\Model\Rules;
use Italgres\Configurator\Model\Source\Action;
use Italgres\Configurator\Model\ResourceModel\Material\CollectionFactory as MaterialCollectionFactory;
use Italgres\Configurator\Model\ResourceModel\Model3d\CollectionFactory as ModelCollectionFactory;
use Magento\Catalog\Helper\Data as CatalogHelper;
use Magento\Catalog\Helper\Output as OutputHelper;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Option;
use Magento\Framework\Locale\ResolverInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Builds the JSON the storefront configurator runs on: steps (custom options), what each value does
 * to the 3D model, the library materials and models it needs, and pricing.
 */
class Configurator implements ArgumentInterface
{
    private const SELECT_TYPES = ['drop_down', 'radio'];

    private ?array $config = null;

    public function __construct(
        private readonly CatalogHelper $catalogHelper,
        private readonly OutputHelper $outputHelper,
        private readonly Media $media,
        private readonly MaterialCollectionFactory $materialCollectionFactory,
        private readonly ModelCollectionFactory $modelCollectionFactory,
        private readonly Rules $rules,
        private readonly StoreManagerInterface $storeManager,
        private readonly UrlInterface $urlBuilder,
        private readonly ResolverInterface $localeResolver,
        private readonly Json $json,
        private readonly Config $settings
    ) {
    }

    public function getProduct(): ?Product
    {
        return $this->catalogHelper->getProduct();
    }

    public function getConfigJson(): string
    {
        return $this->json->serialize($this->getConfig());
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array
    {
        if ($this->config !== null) {
            return $this->config;
        }
        $product = $this->getProduct();
        if (!$product) {
            return $this->config = [];
        }
        $store = $this->storeManager->getStore();
        $basePrice = (float)$product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();

        $materialIds = [];
        $modelCodes = [];
        $options = [];
        foreach ($this->getSelectOptions($product) as $option) {
            $values = [];
            foreach ($this->sortValues($option->getValues() ?: []) as $value) {
                $action = (string)$value->getData('ig_action');
                $materialId = (int)$value->getData('ig_material_id');
                if ($materialId) {
                    $materialIds[$materialId] = true;
                }
                if ($action === Action::MODEL && $value->getData('ig_param')) {
                    $modelCodes[(string)$value->getData('ig_param')] = true;
                }
                $values[] = [
                    'id' => (int)$value->getOptionTypeId(),
                    'title' => (string)$value->getTitle(),
                    'sku' => (string)$value->getSku(),
                    'price' => (float)$value->getPrice(false),
                    'priceType' => (string)$value->getPriceType() ?: 'fixed',
                    'action' => $action,
                    'target' => (string)$value->getData('ig_target'),
                    'materialId' => $materialId ?: null,
                    'color' => (string)$value->getData('ig_color') ?: null,
                    'param' => (string)$value->getData('ig_param'),
                    'requires' => $this->rules->parseRequires((string)$value->getData('ig_requires')),
                ];
            }
            if (!$values) {
                continue;
            }
            $options[] = [
                'id' => (int)$option->getId(),
                'title' => (string)$option->getTitle(),
                'display' => (string)$option->getData('ig_display') ?: 'tile',
                'note' => (string)$option->getData('ig_note'),
                'values' => $values,
            ];
        }

        $materials = $this->getMaterials(array_keys($materialIds));
        foreach ($options as &$option) {
            foreach ($option['values'] as &$value) {
                $value['swatch'] = $this->getSwatch($value, $materials);
            }
        }
        unset($option, $value);

        $models = $this->getModels((int)$product->getData('ig_cfg_model'), array_keys($modelCodes));

        return $this->config = [
            'product' => [
                'id' => (int)$product->getId(),
                'sku' => (string)$product->getSku(),
                'name' => (string)$product->getName(),
            ],
            'price' => [
                'base' => $basePrice,
                'currency' => (string)$store->getCurrentCurrencyCode(),
                'locale' => str_replace('_', '-', (string)$this->localeResolver->getLocale()),
            ],
            'scene' => (string)$product->getData('ig_cfg_scene') ?: 'studio',
            'view' => (string)$product->getData('ig_cfg_view') ?: 'angle',
            'model' => $models['default'],
            'models' => $models['byCode'],
            'materials' => $materials,
            'options' => $options,
            'purchase' => [
                'cart' => $this->settings->isCartEnabled(),
                'quote' => $this->settings->isQuoteEnabled(),
            ],
            'urls' => [
                'add' => $this->urlBuilder->getUrl('italgres_cfg/cart/add'),
                'remove' => $this->urlBuilder->getUrl('italgres_cfg/cart/remove'),
                'quote' => $this->urlBuilder->getUrl('italgres_cfg/quote/submit'),
                'product' => $product->getProductUrl(),
            ],
        ];
    }

    public function isCartEnabled(): bool
    {
        return $this->settings->isCartEnabled();
    }

    public function isQuoteEnabled(): bool
    {
        return $this->settings->isQuoteEnabled();
    }

    public function getTagline(): string
    {
        return (string)$this->getProduct()?->getData('ig_cfg_tagline');
    }

    public function getLeadTime(): string
    {
        return (string)$this->getProduct()?->getData('ig_cfg_lead_time');
    }

    public function getDescriptionHtml(): string
    {
        $product = $this->getProduct();
        if (!$product || !$product->getDescription()) {
            return '';
        }

        return (string)$this->outputHelper->productAttribute($product, $product->getDescription(), 'description');
    }

    /**
     * @return array<int, array{title: string, text: string}>
     */
    public function getFeatures(): array
    {
        $lines = preg_split('/\R/', (string)$this->getProduct()?->getData('ig_cfg_features')) ?: [];
        $features = [];
        foreach ($lines as $line) {
            if (trim($line) === '') {
                continue;
            }
            [$title, $text] = array_pad(array_map('trim', explode('|', $line, 2)), 2, '');
            $features[] = ['title' => $title, 'text' => $text];
        }

        return $features;
    }

    public function getCredits(): string
    {
        return (string)($this->getConfig()['model']['credits'] ?? '');
    }

    /**
     * @return Option[]
     */
    private function getSelectOptions(Product $product): array
    {
        $options = array_values(array_filter(
            $product->getOptions() ?: [],
            static fn($option) => in_array($option->getType(), self::SELECT_TYPES, true)
        ));
        usort($options, static fn($a, $b) => (int)$a->getSortOrder() <=> (int)$b->getSortOrder());

        return $options;
    }

    private function sortValues(array $values): array
    {
        $values = array_values($values);
        usort($values, static fn($a, $b) => (int)$a->getSortOrder() <=> (int)$b->getSortOrder());

        return $values;
    }

    /**
     * @param int[] $ids
     * @return array<int, array<string, mixed>>
     */
    private function getMaterials(array $ids): array
    {
        if (!$ids) {
            return [];
        }
        $result = [];
        $collection = $this->materialCollectionFactory->create()->addFieldToFilter('material_id', ['in' => $ids]);
        foreach ($collection as $material) {
            $result[(int)$material->getId()] = [
                'id' => (int)$material->getId(),
                'code' => (string)$material->getCode(),
                'name' => (string)$material->getName(),
                'family' => (string)$material->getFamily(),
                'color' => (string)$material->getColor() ?: '#ffffff',
                'map' => $this->media->getUrl($material->getMap()),
                'normalMap' => $this->media->getUrl($material->getNormalMap()),
                'roughnessMap' => $this->media->getUrl($material->getRoughnessMap()),
                'swatch' => $this->media->getUrl($material->getSwatch()),
                'repeat' => (float)$material->getRepeatScale(),
                'roughness' => (float)$material->getRoughness(),
                'metalness' => (float)$material->getMetalness(),
                'sheen' => (float)$material->getSheen(),
                'sheenColor' => (string)$material->getSheenColor() ?: null,
                'clearcoat' => (float)$material->getClearcoat(),
                'normalScale' => (float)$material->getNormalScale(),
                'description' => (string)$material->getDescription(),
            ];
        }

        return $result;
    }

    /**
     * @param string[] $codes
     * @return array{default: array<string, mixed>|null, byCode: array<string, array<string, mixed>>}
     */
    private function getModels(int $defaultId, array $codes): array
    {
        $collection = $this->modelCollectionFactory->create()->addFieldToFilter('is_active', 1);
        $connection = $collection->getConnection();
        $collection->getSelect()->where(
            $connection->quoteInto('model_id = ?', $defaultId) . ' OR ' . $connection->quoteInto('code IN (?)', $codes ?: [''])
        );
        $default = null;
        $byCode = [];
        foreach ($collection as $model) {
            $data = [
                'code' => (string)$model->getCode(),
                'name' => (string)$model->getName(),
                'url' => $this->versioned($model->getFile()),
                'baseWidthCm' => (float)$model->getBaseWidthCm(),
                'stretchZone' => (float)$model->getStretchZone(),
                'rigidParts' => $this->rules->parseRequires(str_replace(',', ' ', (string)$model->getRigidParts())),
                'uvScale' => (float)$model->getUvScale() ?: 1.0,
                'rotationY' => (float)$model->getRotationY(),
                'credits' => (string)$model->getCredits(),
            ];
            $byCode[$data['code']] = $data;
            if ((int)$model->getId() === $defaultId) {
                $default = $data;
            }
        }

        return ['default' => $default, 'byCode' => $byCode];
    }

    /**
     * Media URL plus the file time, so a replaced GLB is not served from the browser cache.
     */
    private function versioned(?string $file): ?string
    {
        $url = $this->media->getUrl($file);
        $path = $file ? $this->media->getAbsolutePath($file) : '';

        return $url && is_file($path) ? $url . '?v=' . filemtime($path) : $url;
    }

    /**
     * @param array<string, mixed> $value
     * @param array<int, array<string, mixed>> $materials
     * @return array{type: string, value: string, image?: string}|null
     */
    private function getSwatch(array $value, array $materials): ?array
    {
        $material = $value['materialId'] ? ($materials[$value['materialId']] ?? null) : null;
        if ($value['color']) {
            // A colourway of a library material: its swatch photo tinted with the colour
            return ['type' => 'color', 'value' => $value['color']]
                + ($material && $material['swatch'] ? ['image' => $material['swatch']] : []);
        }
        if ($material && $material['swatch']) {
            return ['type' => 'image', 'value' => $material['swatch']];
        }
        if ($material) {
            return ['type' => 'color', 'value' => $material['color']];
        }

        return null;
    }
}
