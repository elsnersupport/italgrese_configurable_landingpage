<?php
declare(strict_types=1);

namespace Italgres\Configurator\Block\Adminhtml\Edit;

use Magento\Framework\View\Element\UiComponent\Control\ButtonProviderInterface;

class DeleteButton extends GenericButton implements ButtonProviderInterface
{
    public function getButtonData(): array
    {
        if (!$this->getId()) {
            return [];
        }

        return [
            'label' => __('Delete'),
            'class' => 'delete',
            'on_click' => sprintf(
                "deleteConfirm('%s', '%s', {data: {}})",
                __('Delete this entry? Option values that use it will lose the link.'),
                $this->getUrl('*/*/delete', ['id' => $this->getId()])
            ),
            'sort_order' => 20,
        ];
    }
}
