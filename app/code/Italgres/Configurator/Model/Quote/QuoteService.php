<?php
declare(strict_types=1);

namespace Italgres\Configurator\Model\Quote;

use Italgres\Configurator\Model\Config;
use Italgres\Configurator\Model\QuoteRequest;
use Italgres\Configurator\Model\QuoteRequestFactory;
use Italgres\Configurator\Model\ResourceModel\QuoteRequest as QuoteRequestResource;
use Italgres\Configurator\Model\Rules;
use Italgres\Configurator\Model\Source\CustomerType;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product;
use Magento\Framework\App\Area;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Escaper;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Filesystem;
use Magento\Framework\Mail\Template\TransportBuilder;
use Magento\Framework\Pricing\PriceCurrencyInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\Translate\Inline\StateInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

/**
 * Turns a "Request a quote" submission into a stored request plus notification emails.
 * Prices, option labels and dependency rules are recomputed here; only the option ids come from the browser.
 */
class QuoteService
{
    public const MEDIA_PATH = 'italgres/quotes';
    private const SELECT_TYPES = ['drop_down', 'radio'];
    private const CONTACT_PREFERENCES = ['email', 'phone', 'whatsapp'];
    private const MAX_SNAPSHOT_BYTES = 3 * 1024 * 1024;

    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly Rules $rules,
        private readonly Config $config,
        private readonly CustomerType $customerTypes,
        private readonly QuoteRequestFactory $requestFactory,
        private readonly QuoteRequestResource $requestResource,
        private readonly Filesystem $filesystem,
        private readonly TransportBuilder $transportBuilder,
        private readonly StateInterface $inlineTranslation,
        private readonly PriceCurrencyInterface $priceCurrency,
        private readonly Escaper $escaper,
        private readonly Json $json,
        private readonly DeploymentConfig $deploymentConfig,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array<string, mixed> $input request params
     * @throws LocalizedException
     */
    public function submit(array $input, StoreInterface $store): QuoteRequest
    {
        $customer = $this->validateCustomer($input);
        $product = $this->productRepository->getById((int)($input['product'] ?? 0), false, (int)$store->getId());
        if (!$product->getData('ig_cfg_enabled')) {
            throw new LocalizedException(__('This product cannot be configured.'));
        }
        $selected = array_map('intval', array_filter((array)($input['options'] ?? []), 'is_numeric'));
        $this->rules->validate($product, $selected);
        $qty = max(1, min(99, (int)($input['qty'] ?? 1)));

        [$rows, $unitPrice, $skus] = $this->priceConfiguration($product, $selected);

        $request = $this->requestFactory->create();
        $request->addData($customer + [
            'status' => 'new',
            'store_id' => (int)$store->getId(),
            'product_id' => (int)$product->getId(),
            'product_sku' => (string)$product->getSku(),
            'product_name' => (string)$product->getName(),
            'config_code' => implode('-', array_merge([(string)$product->getSku()], $skus)),
            'configuration' => $this->json->serialize($rows),
            'selected_options' => $this->json->serialize($selected),
            'dimensions' => $this->cleanDimensions((string)($input['dimensions'] ?? '')),
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice * $qty,
            'currency' => (string)$store->getCurrentCurrencyCode(),
            'share_url' => $this->cleanShareUrl((string)($input['share_url'] ?? ''), $store),
            'snapshot' => $this->saveSnapshot((string)($input['snapshot'] ?? '')),
        ]);
        $this->requestResource->save($request);
        $request->setData('reference', sprintf('IGQ-%05d', (int)$request->getId()));
        $this->requestResource->save($request);

        if ($this->sendNotifications($request, $store)) {
            $request->setData('email_sent', 1);
            $this->requestResource->save($request);
        }

        return $request;
    }

    /**
     * @return array<string, string|null>
     * @throws LocalizedException
     */
    private function validateCustomer(array $input): array
    {
        $clean = static fn($value, int $max) => mb_substr(trim(strip_tags((string)$value)), 0, $max);
        $data = [
            'customer_name' => $clean($input['name'] ?? '', 255),
            'email' => $clean($input['email'] ?? '', 255),
            'phone' => $clean($input['phone'] ?? '', 64),
            'company' => $clean($input['company'] ?? '', 255),
            'city' => $clean($input['city'] ?? '', 128),
            'country' => $clean($input['country'] ?? '', 64),
            'message' => $clean($input['message'] ?? '', 3000),
            'customer_type' => (string)($input['customer_type'] ?? ''),
            'contact_preference' => (string)($input['contact_preference'] ?? 'email'),
        ];
        if ($data['customer_name'] === '') {
            throw new LocalizedException(__('Please enter your name.'));
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            throw new LocalizedException(__('Please enter a valid email address.'));
        }
        if (empty($input['consent'])) {
            throw new LocalizedException(__('Please agree to be contacted about this request.'));
        }
        $types = array_column($this->customerTypes->toOptionArray(), 'value');
        $data['customer_type'] = in_array($data['customer_type'], $types, true) ? $data['customer_type'] : null;
        $data['contact_preference'] = in_array($data['contact_preference'], self::CONTACT_PREFERENCES, true)
            ? $data['contact_preference'] : 'email';
        if ($data['contact_preference'] !== 'email' && $data['phone'] === '') {
            throw new LocalizedException(__('Please add a phone number so we can call you back.'));
        }

        return array_map(static fn($v) => $v === '' ? null : $v, $data);
    }

    /**
     * @param array<int, int> $selected
     * @return array{0: array<int, array<string, mixed>>, 1: float, 2: string[]}
     */
    private function priceConfiguration(Product $product, array $selected): array
    {
        $base = (float)$product->getPriceInfo()->getPrice('final_price')->getAmount()->getValue();
        $options = array_values(array_filter(
            $product->getOptions() ?: [],
            static fn($o) => in_array($o->getType(), self::SELECT_TYPES, true)
        ));
        usort($options, static fn($a, $b) => (int)$a->getSortOrder() <=> (int)$b->getSortOrder());

        $rows = [];
        $skus = [];
        $total = $base;
        foreach ($options as $option) {
            $valueId = $selected[(int)$option->getId()] ?? null;
            $value = $valueId ? $option->getValueById($valueId) : null;
            if (!$value) {
                continue;
            }
            $price = $value->getPriceType() === 'percent'
                ? $base * (float)$value->getPrice(false) / 100
                : (float)$value->getPrice(false);
            $total += $price;
            if ((string)$value->getSku() !== '') {
                $skus[] = (string)$value->getSku();
            }
            $rows[] = [
                'step' => (string)$option->getTitle(),
                'value' => (string)$value->getTitle(),
                'sku' => (string)$value->getSku(),
                'price' => round($price, 2),
            ];
        }

        return [$rows, round($total, 2), $skus];
    }

    private function cleanDimensions(string $value): ?string
    {
        $value = trim($value);

        return preg_match('/^[\d\s×x.,cm]{1,64}$/u', $value) ? $value : null;
    }

    private function cleanShareUrl(string $url, StoreInterface $store): ?string
    {
        $base = (string)$store->getBaseUrl(UrlInterface::URL_TYPE_WEB);

        return $url !== '' && str_starts_with($url, $base) && strlen($url) < 2000 ? $url : null;
    }

    /**
     * Saves the browser's render (data:image/jpeg;base64,...) under pub/media/italgres/quotes.
     */
    private function saveSnapshot(string $dataUrl): ?string
    {
        if (!preg_match('#^data:image/(jpeg|png);base64,([A-Za-z0-9+/=]+)$#', $dataUrl, $m)) {
            return null;
        }
        $binary = base64_decode($m[2], true);
        if ($binary === false || strlen($binary) > self::MAX_SNAPSHOT_BYTES) {
            return null;
        }
        $info = @getimagesizefromstring($binary);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)) {
            return null;
        }
        $file = sprintf('%s/%s.%s', date('Y/m'), bin2hex(random_bytes(12)), $info[2] === IMAGETYPE_PNG ? 'png' : 'jpg');
        $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA)->writeFile(self::MEDIA_PATH . '/' . $file, $binary);

        return $file;
    }

    private function sendNotifications(QuoteRequest $request, StoreInterface $store): bool
    {
        $recipients = $this->config->getQuoteRecipients();
        $vars = $this->getTemplateVars($request, $store);
        $sent = false;
        $this->inlineTranslation->suspend();
        try {
            if ($recipients) {
                $this->transportBuilder
                    ->setTemplateIdentifier($this->config->getAdminTemplate())
                    ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => (int)$store->getId()])
                    ->setTemplateVars($vars)
                    ->setFromByScope($this->config->getQuoteSender(), (int)$store->getId())
                    ->addTo($recipients)
                    ->setReplyTo((string)$request->getEmail(), (string)$request->getCustomerName())
                    ->getTransport()
                    ->sendMessage();
                $sent = true;
            }
            if ($this->config->sendCustomerCopy()) {
                $this->transportBuilder
                    ->setTemplateIdentifier($this->config->getCustomerTemplate())
                    ->setTemplateOptions(['area' => Area::AREA_FRONTEND, 'store' => (int)$store->getId()])
                    ->setTemplateVars($vars)
                    ->setFromByScope($this->config->getQuoteSender(), (int)$store->getId())
                    ->addTo((string)$request->getEmail(), (string)$request->getCustomerName())
                    ->getTransport()
                    ->sendMessage();
            }
        } catch (\Throwable $e) {
            // The request is stored either way; the admin grid shows whether the notification went out.
            $this->logger->error('Italgres quote request email failed: ' . $e->getMessage(), ['reference' => $request->getReference()]);
        } finally {
            $this->inlineTranslation->resume();
        }

        return $sent;
    }

    /**
     * @return array<string, string>
     */
    private function getTemplateVars(QuoteRequest $request, StoreInterface $store): array
    {
        $currency = (string)$request->getCurrency();
        $format = fn(float $amount) => $this->priceCurrency->format($amount, false, 0, $store, $currency);
        $rows = '';
        foreach ($request->getConfigurationRows() as $row) {
            $rows .= sprintf(
                '<tr><td style="padding:9px 0;border-bottom:1px solid #e9e2d8;color:#7d7469;font-size:13px;">%s</td>'
                . '<td style="padding:9px 0;border-bottom:1px solid #e9e2d8;font-size:13px;text-align:right;">%s%s</td></tr>',
                $this->escaper->escapeHtml($row['step']),
                $this->escaper->escapeHtml($row['value']),
                $row['price'] ? ' <span style="color:#9a7a4c;">(' . ($row['price'] > 0 ? '+' : '−') . $format(abs((float)$row['price'])) . ')</span>' : ''
            );
        }
        $snapshot = $request->getSnapshot()
            ? $store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA) . self::MEDIA_PATH . '/' . $request->getSnapshot()
            : '';

        return [
            'reference' => (string)$request->getReference(),
            'product_name' => (string)$request->getProductName(),
            'config_code' => (string)$request->getConfigCode(),
            'config_rows' => $rows,
            'unit_price' => $format((float)$request->getUnitPrice()),
            'total_price' => $format((float)$request->getTotalPrice()),
            'qty' => (string)$request->getQty(),
            'dimensions' => (string)$request->getDimensions(),
            'snapshot_html' => $snapshot
                ? '<img src="' . $this->escaper->escapeUrl($snapshot) . '" alt="" width="520" style="display:block;width:100%;max-width:520px;height:auto;border:0;">'
                : '',
            'share_url' => (string)$request->getShareUrl(),
            'customer_name' => (string)$request->getCustomerName(),
            'email' => (string)$request->getEmail(),
            'phone' => (string)$request->getPhone() ?: '—',
            'customer_type' => $this->customerTypes->getLabel($request->getCustomerType()) ?: '—',
            'company' => (string)$request->getCompany() ?: '—',
            'location' => trim(implode(', ', array_filter([(string)$request->getCity(), (string)$request->getCountry()]))) ?: '—',
            'contact_preference' => ucfirst((string)$request->getContactPreference()),
            'message' => (string)$request->getMessage() ?: '—',
            'admin_url' => $store->getBaseUrl(UrlInterface::URL_TYPE_WEB)
                . trim((string)$this->deploymentConfig->get('backend/frontName', 'admin'), '/') . '/',
            'store_name' => (string)$store->getFrontendName(),
        ];
    }
}
