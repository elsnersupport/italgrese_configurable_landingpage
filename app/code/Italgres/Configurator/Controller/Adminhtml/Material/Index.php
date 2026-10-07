<?php
declare(strict_types=1);

namespace Italgres\Configurator\Controller\Adminhtml\Material;

use Magento\Backend\App\Action;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\View\Result\Page;

class Index extends Action implements HttpGetActionInterface
{
    public const ADMIN_RESOURCE = 'Italgres_Configurator::materials';

    public function execute(): Page
    {
        /** @var Page $page */
        $page = $this->resultFactory->create(ResultFactory::TYPE_PAGE);
        $page->setActiveMenu('Italgres_Configurator::materials');
        $page->getConfig()->getTitle()->prepend(__('Materials Library'));

        return $page;
    }
}
