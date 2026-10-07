<?php
declare(strict_types=1);

namespace Italgres\Configurator\Controller\Adminhtml\Quote;

use Italgres\Configurator\Model\QuoteRequestFactory;
use Italgres\Configurator\Model\ResourceModel\QuoteRequest as QuoteRequestResource;
use Italgres\Configurator\Model\Source\QuoteStatus;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;

/**
 * Status and internal note from the request view page.
 */
class Save extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Italgres_Configurator::quotes';

    public function __construct(
        Context $context,
        private readonly QuoteRequestFactory $factory,
        private readonly QuoteRequestResource $resource,
        private readonly QuoteStatus $statuses
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $id = (int)$this->getRequest()->getParam('id');
        $request = $this->factory->create();
        $this->resource->load($request, $id);
        if (!$request->getId()) {
            $this->messageManager->addErrorMessage(__('This quote request no longer exists.'));

            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        $status = (string)$this->getRequest()->getParam('status');
        if (in_array($status, array_column($this->statuses->toOptionArray(), 'value'), true)) {
            $request->setData('status', $status);
        }
        $request->setData('admin_note', trim((string)$this->getRequest()->getParam('admin_note')) ?: null);
        $this->resource->save($request);
        $this->messageManager->addSuccessMessage(__('Quote request %1 was updated.', $request->getData('reference')));

        return $this->resultRedirectFactory->create()->setPath('*/*/view', ['id' => $id]);
    }
}
