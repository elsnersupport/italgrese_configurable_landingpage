<?php
declare(strict_types=1);

namespace Italgres\Configurator\Controller\Cart;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Quote\Api\CartRepositoryInterface;
use Psr\Log\LoggerInterface;

class Remove implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setHttpResponseCode(403)->setData(['success' => false]);
        }
        try {
            $quote = $this->checkoutSession->getQuote();
            $itemId = (int)$this->request->getParam('item_id');
            if ($itemId && $quote->getItemById($itemId)) {
                $quote->removeItem($itemId);
                $quote->collectTotals();
                $this->cartRepository->save($quote);
            }

            return $result->setData(['success' => true, 'itemsQty' => (float)$quote->getItemsQty()]);
        } catch (\Throwable $e) {
            $this->logger->critical($e);

            return $result->setData(['success' => false]);
        }
    }
}
