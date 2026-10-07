<?php
declare(strict_types=1);

namespace Italgres\Configurator\Controller\Adminhtml\Model;

use Italgres\Configurator\Model\Model3d as Entity;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;

class Delete extends Action implements HttpPostActionInterface, HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Italgres_Configurator::models';

    public function __construct(Context $context, private readonly \Italgres\Configurator\Model\Model3dFactory $factory)
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
            $this->messageManager->addErrorMessage(__('This 3D model no longer exists.'));

            return $redirect;
        }
        try {
            $entity->delete();
            $this->messageManager->addSuccessMessage(__('The 3D model was deleted.'));
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());
        }

        return $redirect;
    }
}
