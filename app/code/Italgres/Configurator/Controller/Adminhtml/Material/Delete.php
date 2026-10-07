<?php
declare(strict_types=1);

namespace Italgres\Configurator\Controller\Adminhtml\Material;

use Italgres\Configurator\Model\Material as Entity;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;

class Delete extends Action implements HttpPostActionInterface, HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Italgres_Configurator::materials';

    public function __construct(Context $context, private readonly \Italgres\Configurator\Model\MaterialFactory $factory)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create()->setPath('*/*/');
        /** @var Entity $entity */
        $entity = $this->factory->create();
        $entity->load((int)$this->getRequest()->getParam('id'));
        if (!$entity->getId()) {
            $this->messageManager->addErrorMessage(__('This material no longer exists.'));

            return $redirect;
        }
        try {
            $entity->delete();
            $this->messageManager->addSuccessMessage(__('The material was deleted.'));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $redirect;
    }
}
