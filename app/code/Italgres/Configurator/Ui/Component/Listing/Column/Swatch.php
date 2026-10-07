<?php
declare(strict_types=1);

namespace Italgres\Configurator\Ui\Component\Listing\Column;

use Italgres\Configurator\Model\Media;
use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Renders the swatch image, or the base colour when no image is set.
 */
class Swatch extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly Media $media,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        $name = $this->getData('name');
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        foreach ($dataSource['data']['items'] as &$item) {
            $style = 'display:inline-block;width:44px;height:44px;border-radius:50%;border:1px solid #ccc;';
            $url = $this->media->getUrl($item['swatch'] ?? null) ?? $this->media->getUrl($item['map'] ?? null);
            $style .= $url
                ? sprintf("background:url('%s') center/cover;", $this->escaper->escapeUrl($url))
                : sprintf('background:%s;', $this->escaper->escapeHtmlAttr((string)($item['color'] ?? '#eee')));
            $item[$name] = sprintf('<span style="%s"></span>', $style);
        }

        unset($item);

        return $dataSource;
    }
}
