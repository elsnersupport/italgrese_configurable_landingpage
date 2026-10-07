<?php
declare(strict_types=1);

namespace Italgres\Configurator\Controller\Adminhtml\Quote;

use Italgres\Configurator\Model\ResourceModel\QuoteRequest\CollectionFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Ui\Component\MassAction\Filter;

class MassDelete extends Action implements HttpPostActionInterface
{
    public const ADMIN_RESOURCE = 'Italgres_Configurator::quotes';

    public function __construct(
        Context $context,
        private readonly Filter $filter,
        private readonly CollectionFactory $collectionFactory
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $collection = $this->filter->getCollection($this->collectionFactory->create());
        $count = 0;
        foreach ($collection as $request) {
            $request->delete();
            $count++;
        }
        $this->messageManager->addSuccessMessage(__('%1 quote request(s) were deleted.', $count));

        return $this->resultRedirectFactory->create()->setPath('*/*/');
    }
}
