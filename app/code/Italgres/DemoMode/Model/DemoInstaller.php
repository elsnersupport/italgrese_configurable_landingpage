<?php
declare(strict_types=1);

namespace Italgres\DemoMode\Model;

use Italgres\Configurator\Model\GlbInspector;
use Italgres\Configurator\Model\Media;
use Italgres\Configurator\Model\MaterialFactory;
use Italgres\Configurator\Model\Model3dFactory;
use Italgres\Configurator\Model\ResourceModel\Material as MaterialResource;
use Italgres\Configurator\Model\ResourceModel\Model3d as Model3dResource;
use Magento\Catalog\Api\Data\ProductCustomOptionInterfaceFactory;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Type;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ProductFactory;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Framework\Filesystem;
use Magento\Framework\Module\Dir;
use Magento\Framework\Module\Dir\Reader as ModuleReader;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Theme\Model\ResourceModel\Theme\CollectionFactory as ThemeCollectionFactory;

/**
 * Installs the demo: copies fixture assets to pub/media, upserts the material and model libraries,
 * creates the configurator products and points the store at the Italgres theme. Safe to re-run.
 */
class DemoInstaller
{
    private const THEME_PATH = 'frontend/Italgres/configurator';

    /** @var callable */
    private $log;

    public function __construct(
        private readonly ModuleReader $moduleReader,
        private readonly Filesystem $filesystem,
        private readonly Json $json,
        private readonly MaterialFactory $materialFactory,
        private readonly MaterialResource $materialResource,
        private readonly Model3dFactory $modelFactory,
        private readonly Model3dResource $modelResource,
        private readonly GlbInspector $glbInspector,
        private readonly Media $media,
        private readonly ProductFactory $productFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ProductCustomOptionInterfaceFactory $optionFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly ThemeCollectionFactory $themeCollectionFactory,
        private readonly WriterInterface $configWriter
    ) {
        $this->log = static fn(string $m) => null;
    }

    public function install(?callable $log = null): void
    {
        if ($log) {
            $this->log = $log;
        }
        $this->storeManager->setCurrentStore(0);
        $fixtures = $this->moduleReader->getModuleDir(Dir::MODULE_ETC_DIR, 'Italgres_DemoMode') . '/../fixtures';
        $data = $this->json->unserialize((string)file_get_contents($fixtures . '/demo.json'));

        $this->copyAssets($fixtures);
        $materials = $this->upsertMaterials($data['materials']);
        $models = $this->upsertModels($data['models']);
        foreach ($data['products'] as $productData) {
            $this->upsertProduct($productData, $materials, $models);
        }
        $this->configureStore($data['products'][0]['sku']);
    }

    private function copyAssets(string $fixtures): void
    {
        $media = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $count = 0;
        foreach (Media::FOLDERS as $folder) {
            $source = $fixtures . '/' . $folder;
            if (!is_dir($source)) {
                continue;
            }
            $target = Media::BASE_PATH . '/' . $folder;
            $media->create($target);
            foreach (glob($source . '/*') ?: [] as $file) {
                if (is_file($file)) {
                    $media->writeFile($target . '/' . basename($file), (string)file_get_contents($file));
                    $count++;
                }
            }
        }
        ($this->log)(sprintf('Copied %d asset files to pub/media/%s', $count, Media::BASE_PATH));
    }

    /**
     * @return array<string, int> code => id
     */
    private function upsertMaterials(array $rows): array
    {
        $ids = [];
        foreach ($rows as $i => $row) {
            $material = $this->materialFactory->create();
            $this->materialResource->load($material, $row['code'], 'code');
            $material->addData($row + [
                'family' => 'other',
                'repeat_scale' => 1,
                'roughness' => 0.8,
                'metalness' => 0,
                'sheen' => 0,
                'clearcoat' => 0,
                'normal_scale' => 1,
                'is_active' => 1,
                'sort_order' => ($i + 1) * 10,
            ]);
            $this->materialResource->save($material);
            $ids[$row['code']] = (int)$material->getId();
        }
        ($this->log)(sprintf('Material library: %d materials', count($ids)));

        return $ids;
    }

    /**
     * @return array<string, int> code => id
     */
    private function upsertModels(array $rows): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $model = $this->modelFactory->create();
            $this->modelResource->load($model, $row['code'], 'code');
            $slots = $this->glbInspector->inspect($this->media->getAbsolutePath($row['file']));
            $model->addData($row + ['is_active' => 1]);
            $model->setData('slots', $slots ? $this->json->serialize($slots) : null);
            $this->modelResource->save($model);
            $ids[$row['code']] = (int)$model->getId();
        }
        ($this->log)(sprintf('3D models: %s', implode(', ', array_keys($ids))));

        return $ids;
    }

    /**
     * @param array<string, int> $materials
     * @param array<string, int> $models
     */
    private function upsertProduct(array $row, array $materials, array $models): void
    {
        try {
            /** @var Product $product */
            $product = $this->productRepository->get($row['sku'], true, 0, true);
        } catch (NoSuchEntityException) {
            $product = $this->productFactory->create();
            $product->setSku($row['sku'])
                ->setTypeId(Type::TYPE_SIMPLE)
                ->setAttributeSetId($product->getDefaultAttributeSetId());
        }
        $product->setStoreId(0)
            ->setName($row['name'])
            ->setUrlKey($row['url_key'])
            ->setPrice($row['price'])
            ->setWeight($row['weight'] ?? 1)
            ->setStatus(Status::STATUS_ENABLED)
            ->setVisibility(Visibility::VISIBILITY_BOTH)
            ->setWebsiteIds([(int)$this->storeManager->getDefaultStoreView()->getWebsiteId()])
            ->setTaxClassId(2)
            ->setDescription($row['description'])
            ->setShortDescription($row['tagline'])
            ->setStockData(['use_config_manage_stock' => 0, 'manage_stock' => 0, 'is_in_stock' => 1, 'qty' => 100])
            ->setData('ig_cfg_enabled', 1)
            ->setData('ig_cfg_model', $models[$row['model']] ?? null)
            ->setData('ig_cfg_scene', $row['scene'] ?? 'studio')
            ->setData('ig_cfg_view', $row['view'] ?? 'angle')
            ->setData('ig_cfg_tagline', $row['tagline'])
            ->setData('ig_cfg_lead_time', $row['lead_time'])
            ->setData('ig_cfg_features', implode("\n", $row['features']))
            ->setData('meta_title', $row['name'] . ' · Configure in 3D');

        $options = [];
        foreach ($row['options'] as $sort => $optionRow) {
            $values = [];
            foreach ($optionRow['values'] as $valueSort => $value) {
                $values[] = [
                    'title' => $value['title'],
                    'price' => (float)($value['price'] ?? 0),
                    'price_type' => 'fixed',
                    'sku' => $value['sku'],
                    'sort_order' => ($valueSort + 1) * 10,
                    'ig_action' => $value['action'] ?? '',
                    'ig_target' => $value['target'] ?? null,
                    'ig_material_id' => isset($value['material']) ? ($materials[$value['material']] ?? null) : null,
                    'ig_color' => $value['color'] ?? null,
                    'ig_param' => $value['param'] ?? null,
                    'ig_requires' => $value['requires'] ?? null,
                ];
            }
            // A step whose every value depends on another step may be skipped entirely,
            // so leave "required" to the configurator rules instead of Magento.
            $dependent = !array_filter($optionRow['values'], static fn($v) => empty($v['requires']));
            $option = $this->optionFactory->create();
            $option->setProductSku($row['sku'])
                ->setTitle($optionRow['title'])
                ->setType('radio')
                ->setIsRequire(!$dependent)
                ->setSortOrder(($sort + 1) * 10)
                ->setData('ig_display', $optionRow['display'] ?? 'tile')
                ->setData('ig_note', $optionRow['note'] ?? null)
                ->setData('values', $values);
            $options[] = $option;
        }
        $product->setOptions($options);
        $product->setCanSaveCustomOptions(true);
        $product->setHasOptions(true);
        $product->setRequiredOptions(true);
        $this->productRepository->save($product);
        ($this->log)(sprintf('Product %s: %d configurator steps', $row['sku'], count($options)));
    }

    private function configureStore(string $landingSku): void
    {
        $theme = $this->themeCollectionFactory->create()->getThemeByFullPath(self::THEME_PATH);
        if ($theme && $theme->getId()) {
            $this->configWriter->save('design/theme/theme_id', $theme->getId());
            ($this->log)(sprintf('Storefront theme: %s (id %d)', self::THEME_PATH, $theme->getId()));
        } else {
            ($this->log)('Theme ' . self::THEME_PATH . ' is not registered yet: run setup:upgrade, then re-run this command.');
        }
        $this->configWriter->save('italgres_demo/general/landing_sku', $landingSku);
        $this->configWriter->save('italgres_demo/general/lock_enabled', 1);
        $this->configWriter->save('general/store_information/name', 'Italgres');
        $this->configWriter->save('design/head/default_title', 'Italgres');
        $this->configWriter->save('design/head/title_suffix', ' · Italgres');
        $this->configWriter->save('design/search_engine_robots/default_robots', 'NOINDEX,NOFOLLOW');
        $this->configWriter->save('catalog/recently_products/recently_viewed_enabled', 0);
        $this->configWriter->save('checkout/sidebar/display', 0);
        $this->configWriter->save('web/cookie/cookie_restriction', 0);
    }
}
