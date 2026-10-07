<?php
declare(strict_types=1);

namespace Italgres\Configurator\Controller\Adminhtml\Quote;

use Italgres\Configurator\Model\QuoteRequestFactory;
use Italgres\Configurator\Model\ResourceModel\QuoteRequest as QuoteRequestResource;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Registry;

class View extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Italgres_Configurator::quotes';
    public const REGISTRY_KEY = 'italgres_quote_request';

    public function __construct(
        Context $context,
        private readonly QuoteRequestFactory $factory,
        private readonly QuoteRequestResource $resource,
        private readonly Registry $registry
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $request = $this->factory->create();
        $this->resource->load($request, (int)$this->getRequest()->getParam('id'));
        if (!$request->getId()) {
            $this->messageManager->addErrorMessage(__('This quote request no longer exists.'));

            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        $this->registry->register(self::REGISTRY_KEY, $request);
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('Italgres_Configurator::quotes');
        $page->getConfig()->getTitle()->prepend(__('Quote Request %1', $request->getData('reference')));

        return $page;
    }
}
