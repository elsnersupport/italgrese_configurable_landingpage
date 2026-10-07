<?php
declare(strict_types=1);

namespace Italgres\Configurator\Controller\Adminhtml\Quote;

use Italgres\Configurator\Model\QuoteRequestFactory;
use Italgres\Configurator\Model\ResourceModel\QuoteRequest as QuoteRequestResource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Delete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Italgres_Configurator::quotes';

    public function __construct(
        Context $context,
        private readonly QuoteRequestFactory $factory,
        private readonly QuoteRequestResource $resource
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $request = $this->factory->create();
        $this->resource->load($request, (int)$this->getRequest()->getParam('id'));
        if ($request->getId()) {
            $this->resource->delete($request);
            $this->messageManager->addSuccessMessage(__('Quote request %1 was deleted.', $request->getData('reference')));
        }

        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
