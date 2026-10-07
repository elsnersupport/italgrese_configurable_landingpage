<?php
declare(strict_types=1);

namespace Italgres\Configurator\Controller\Cart;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\DataObject;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Quote\Api\CartRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * AJAX add-to-cart for a configured product. Returns JSON so the configurator can stay on the page.
 */
class Add implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CheckoutSession $checkoutSession,
        private readonly CartRepositoryInterface $cartRepository,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute(): Json
    {
        $result = $this->jsonFactory->create();
        if (!$this->formKeyValidator->validate($this->request)) {
            return $result->setHttpResponseCode(403)
                ->setData(['success' => false, 'message' => (string)__('Your session expired. Please reload the page.')]);
        }

        try {
            $productId = (int)$this->request->getParam('product');
            $qty = max(1, min(99, (int)$this->request->getParam('qty', 1)));
            $options = array_map('intval', array_filter((array)$this->request->getParam('options', []), 'is_numeric'));
            $product = $this->productRepository->getById($productId, false, (int)$this->storeManager->getStore()->getId(), true);
            if (!$product->getData('ig_cfg_enabled')) {
                throw new LocalizedException(__('This product cannot be configured.'));
            }

            $quote = $this->checkoutSession->getQuote();
            $item = $quote->addProduct($product, new DataObject([
                'product' => $productId,
                'qty' => $qty,
                'options' => $options,
            ]));
            if (is_string($item)) {
                throw new LocalizedException(__($item));
            }
            $quote->getShippingAddress()->setCollectShippingRates(true);
            $quote->collectTotals();
            $this->cartRepository->save($quote);
            $this->checkoutSession->setQuoteId($quote->getId());

            return $result->setData([
                'success' => true,
                'itemId' => (int)($item->getParentItem() ? $item->getParentItem()->getId() : $item->getId()),
                'itemsQty' => (float)$quote->getItemsQty(),
                'message' => (string)__('%1 was added to your selection.', $product->getName()),
            ]);
        } catch (NoSuchEntityException) {
            return $result->setData(['success' => false, 'message' => (string)__('This product is no longer available.')]);
        } catch (LocalizedException $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->logger->critical($e);

            return $result->setData(['success' => false, 'message' => (string)__('We could not add this configuration to the cart.')]);
        }
    }
}
