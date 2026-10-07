<?php
declare(strict_types=1);

namespace Italgres\Configurator\Setup\Patch\Data;

use Italgres\Configurator\Model\Source\Model3d;
use Italgres\Configurator\Model\Source\Scene;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Model\Entity\Attribute\Source\Boolean;
use Magento\Eav\Setup\EavSetup;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * Product attributes in a "3D Configurator" group: switch the configurator page on and pick the model.
 */
class AddConfiguratorAttributes implements DataPatchInterface
{
    private const GROUP = '3D Configurator';

    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        /** @var EavSetup $eavSetup */
        $eavSetup = $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup]);
        $common = [
            'group' => self::GROUP,
            'required' => false,
            'user_defined' => true,
            'searchable' => false,
            'filterable' => false,
            'comparable' => false,
            'visible_on_front' => false,
            'used_in_product_listing' => false,
            'unique' => false,
            'is_used_in_grid' => false,
            'is_visible_in_grid' => false,
            'is_filterable_in_grid' => false,
        ];

        $eavSetup->addAttribute(Product::ENTITY, 'ig_cfg_enabled', $common + [
            'type' => 'int',
            'label' => 'Enable 3D Configurator Page',
            'input' => 'boolean',
            'source' => Boolean::class,
            'default' => '0',
            'global' => Attribute::SCOPE_GLOBAL,
            'sort_order' => 10,
            'note' => 'Replaces the standard product page with the 3D configurator. Custom options become the configurator steps.',
        ]);
        $eavSetup->addAttribute(Product::ENTITY, 'ig_cfg_model', $common + [
            'type' => 'int',
            'label' => '3D Model',
            'input' => 'select',
            'source' => Model3d::class,
            'global' => Attribute::SCOPE_GLOBAL,
            'sort_order' => 20,
            'note' => 'Manage models under Catalog > 3D Configurator > 3D Models.',
        ]);
        $eavSetup->addAttribute(Product::ENTITY, 'ig_cfg_scene', $common + [
            'type' => 'varchar',
            'label' => 'Viewer Backdrop',
            'input' => 'select',
            'source' => Scene::class,
            'default' => 'studio',
            'global' => Attribute::SCOPE_GLOBAL,
            'sort_order' => 30,
        ]);
        $eavSetup->addAttribute(Product::ENTITY, 'ig_cfg_tagline', $common + [
            'type' => 'varchar',
            'label' => 'Configurator Tagline',
            'input' => 'text',
            'global' => Attribute::SCOPE_STORE,
            'sort_order' => 40,
        ]);
        $eavSetup->addAttribute(Product::ENTITY, 'ig_cfg_lead_time', $common + [
            'type' => 'varchar',
            'label' => 'Lead Time Message',
            'input' => 'text',
            'global' => Attribute::SCOPE_STORE,
            'sort_order' => 50,
        ]);
        $eavSetup->addAttribute(Product::ENTITY, 'ig_cfg_features', $common + [
            'type' => 'text',
            'label' => 'Feature Highlights',
            'input' => 'textarea',
            'global' => Attribute::SCOPE_STORE,
            'sort_order' => 60,
            'note' => 'One per line: Title | Description',
        ]);

        return $this;
    }

    public static function getDependencies(): array
    {
        return [];
    }

    public function getAliases(): array
    {
        return [];
    }
}
