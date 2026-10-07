<?php
declare(strict_types=1);

namespace Italgres\DemoMode\Observer;

use Italgres\DemoMode\Model\Config;
use Italgres\DemoMode\Model\ConfiguratorProducts;
use Magento\Framework\App\Action\Forward;
use Magento\Framework\App\ActionFlag;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event\ObserverInterface;

/**
 * Demo lock: the storefront only serves configurator product pages (plus the AJAX they need).
 * Everything else, including the home page, redirects to the landing product.
 */
class LockStorefront implements ObserverInterface
{
    public function __construct(
        private readonly Config $config,
        private readonly ConfiguratorProducts $products,
        private readonly ActionFlag $actionFlag,
        private readonly HttpResponse $response
    ) {
    }

    public function execute(Observer $observer): void
    {
        /** @var HttpRequest $request */
        $request = $observer->getEvent()->getData('request');
        if (!$request instanceof HttpRequest || !$this->config->isLocked()) {
            return;
        }
        // URL rewrites first dispatch an internal Forward; the real route is checked on the next pass
        if ($observer->getEvent()->getData('controller_action') instanceof Forward || !$request->getRouteName()) {
            return;
        }
        $action = $request->getFullActionName();
        if (in_array($action, $this->config->getAllowedActions(), true)) {
            return;
        }
        if ($action === 'catalog_product_view' && $this->products->isConfigurator((int)$request->getParam('id'))) {
            return;
        }

        $this->actionFlag->set('', ActionInterface::FLAG_NO_DISPATCH, true);
        if ($request->isAjax() || !$request->isGet()) {
            $this->response->setHttpResponseCode(404)->setBody('');

            return;
        }
        $this->response->setNoCacheHeaders();
        $this->response->setRedirect($this->products->getLandingUrl() ?: '/', 302);
    }
}
