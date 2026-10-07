<?php
declare(strict_types=1);

namespace Italgres\Configurator\Ui\Component\Listing\Column;

use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;

/**
 * Edit / Delete row actions. Configure with data/config/routePath (e.g. italgres_cfg/material) and idField.
 */
class Actions extends Column
{
    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly UrlInterface $urlBuilder,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource): array
    {
        $route = (string)$this->getData('config/routePath');
        $idField = (string)$this->getData('config/idField');
        $editAction = (string)($this->getData('config/editAction') ?: 'edit');
        $editLabel = (string)($this->getData('config/editLabel') ?: 'Edit');
        if (!isset($dataSource['data']['items'])) {
            return $dataSource;
        }
        foreach ($dataSource['data']['items'] as &$item) {
            if (!isset($item[$idField])) {
                continue;
            }
            $item[$this->getData('name')] = [
                'edit' => [
                    'href' => $this->urlBuilder->getUrl($route . '/' . $editAction, ['id' => $item[$idField]]),
                    'label' => __($editLabel),
                ],
                'delete' => [
                    'href' => $this->urlBuilder->getUrl($route . '/delete', ['id' => $item[$idField]]),
                    'label' => __('Delete'),
                    'confirm' => [
                        'title' => __('Delete "%1"', $item['name'] ?? $item['reference'] ?? ''),
                        'message' => __('Are you sure? Option values that use it will lose the link.'),
                    ],
                    'post' => true,
                ],
            ];
        }

        unset($item);

        return $dataSource;
    }
}
