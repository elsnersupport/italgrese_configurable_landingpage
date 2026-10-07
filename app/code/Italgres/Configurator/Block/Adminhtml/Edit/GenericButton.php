<?php
declare(strict_types=1);

namespace Italgres\Configurator\Block\Adminhtml\Edit;

use Magento\Backend\Block\Widget\Context;

/**
 * Shared by the material and model edit forms; URLs are relative to the current controller.
 */
class GenericButton
{
    public function __construct(protected readonly Context $context)
    {
    }

    public function getId(): int
    {
        return (int)$this->context->getRequest()->getParam('id');
    }

    public function getUrl(string $route = '', array $params = []): string
    {
        return $this->context->getUrlBuilder()->getUrl($route, $params);
    }
}
