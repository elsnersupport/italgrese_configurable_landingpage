<?php
declare(strict_types=1);

namespace Italgres\Configurator\Controller\Adminhtml\Quote;

use Italgres\Configurator\Model\ResourceModel\QuoteRequest\CollectionFactory;
use Italgres\Configurator\Model\Source\QuoteStatus;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Ui\Component\MassAction\Filter;

class MassStatus extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Italgres_Configurator::quotes';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory,
        private readonly QuoteStatus $statuses
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $status = (string)$this->getRequest()->getParam('status');
        if (!in_array($status, array_column($this->statuses->toOptionArray(), 'value'), true)) {
            $this->messageManager->addErrorMessage(__('Unknown status.'));

            return $this->resultRedirectFactory->create()->setPath('*/*/');
        }
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $count = 0;
        foreach ($collection as $request) {
            $request->setData('status', $status)->save();
            $count++;
        }
        $this->messageManager->addSuccessMessage(__('%1 quote request(s) set to "%2".', $count, $this->statuses->getLabel($status)));

        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
