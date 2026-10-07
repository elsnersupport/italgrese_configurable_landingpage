<?php
declare(strict_types=1);

namespace Italgres\Configurator\Setup\Patch\Data;

use Italgres\Configurator\Model\Source\View;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Eav\Attribute;
use Magento\Eav\Setup\EavSetupFactory;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * "Opening View" product attribute: the camera preset the configurator starts on.
 */
class AddDefaultViewAttribute implements DataPatchInterface
{
    public function __construct(
        private readonly ModuleDataSetupInterface $moduleDataSetup,
        private readonly EavSetupFactory $eavSetupFactory
    ) {
    }

    public function apply(): self
    {
        $this->eavSetupFactory->create(['setup' => $this->moduleDataSetup])->addAttribute(
            Product::ENTITY,
            'ig_cfg_view',
            [
                'group' => '3D Configurator',
                'type' => 'varchar',
                'label' => 'Opening View',
                'input' => 'select',
                'source' => View::class,
                'default' => 'angle',
                'global' => Attribute::SCOPE_GLOBAL,
                'required' => false,
                'user_defined' => true,
                'searchable' => false,
                'filterable' => false,
                'comparable' => false,
                'visible_on_front' => false,
                'used_in_product_listing' => false,
                'is_used_in_grid' => false,
                'is_visible_in_grid' => false,
                'is_filterable_in_grid' => false,
                'sort_order' => 35,
                'note' => 'Front suits sectionals and catalogue-style shots; the ¾ view turns slowly until the visitor drags.',
            ]
        );

        return $this;
    }

    public static function getDependencies(): array
    {
        return [AddConfiguratorAttributes::class];
    }

    public function getAliases(): array
    {
        return [];
    }
}
