<?php
declare(strict_types=1);

namespace Italgres\Configurator\Controller\Quote;

use Italgres\Configurator\Model\Config;
use Italgres\Configurator\Model\Quote\QuoteService;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Data\Form\FormKey\Validator as FormKeyValidator;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Exception\NoSuchEntityException;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * "Request a quote" form on the configurator page (AJAX, JSON response).
 */
class Submit implements HttpPostActionInterface
{
    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly FormKeyValidator $formKeyValidator,
        private readonly Config $config,
        private readonly QuoteService $quoteService,
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
        if (!$this->config->isQuoteEnabled()) {
            return $result->setData(['success' => false, 'message' => (string)__('Quote requests are not available.')]);
        }
        // Honeypot: real visitors never fill the hidden "website" field
        if (trim((string)$this->request->getParam('website')) !== '') {
            return $result->setData(['success' => true, 'reference' => '']);
        }
        try {
            $quote = $this->quoteService->submit((array)$this->request->getParams(), $this->storeManager->getStore());

            return $result->setData([
                'success' => true,
                'reference' => (string)$quote->getData('reference'),
            ]);
        } catch (NoSuchEntityException) {
            return $result->setData(['success' => false, 'message' => (string)__('This product is no longer available.')]);
        } catch (LocalizedException $e) {
            return $result->setData(['success' => false, 'message' => $e->getMessage()]);
        } catch (\Throwable $e) {
            $this->logger->critical($e);

            return $result->setData(['success' => false, 'message' => (string)__('We could not send your request. Please try again.')]);
        }
    }
}
