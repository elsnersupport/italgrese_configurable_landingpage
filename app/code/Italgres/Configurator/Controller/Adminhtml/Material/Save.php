<?php
declare(strict_types=1);

namespace Italgres\Configurator\Controller\Adminhtml\Material;

use Italgres\Configurator\Controller\Adminhtml\UploaderFieldsTrait;
use Italgres\Configurator\Model\MaterialFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;

class Save extends Action implements HttpPostActionInterface
{
    use UploaderFieldsTrait;

    public const ADMIN_RESOURCE = 'Italgres_Configurator::materials';

    private const FILE_FIELDS = ['map', 'normal_map', 'roughness_map', 'swatch'];

    public function __construct(Context $context, private readonly MaterialFactory $factory)
    {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $data = (array)$this->getRequest()->getPostValue();
        if (!$data) {
            return $redirect->setPath('*/*/');
        }
        $id = (int)($data['material_id'] ?? 0);
        $material = $this->factory->create();
        if ($id) {
            $material->load($id);
            if (!$material->getId()) {
                $this->messageManager->addErrorMessage(__('This material no longer exists.'));

                return $redirect->setPath('*/*/');
            }
        }
        unset($data['material_id'], $data['form_key'], $data['created_at'], $data['updated_at']);
        $data = $this->flattenUploads($data, self::FILE_FIELDS);
        $data['code'] = strtolower(trim((string)preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)($data['code'] ?? ''))), '-');
        foreach (['color', 'sheen_color'] as $hex) {
            $data[$hex] = $this->normalizeHex($data[$hex] ?? null);
        }

        try {
            $material->addData($data)->save();
            $this->messageManager->addSuccessMessage(__('The material "%1" was saved.', $material->getName()));
            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['id' => $material->getId()]);
            }

            return $redirect->setPath('*/*/');
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $redirect->setPath('*/*/edit', $id ? ['id' => $id] : []);
        }
    }

    private function normalizeHex(?string $value): ?string
    {
        $value = trim((string)$value);
        if ($value === '') {
            return null;
        }
        if (preg_match('/^#?([0-9a-f]{6})$/i', $value, $m)) {
            return '#' . strtolower($m[1]);
        }
        if (preg_match('/^rgba?\((\d+),\s*(\d+),\s*(\d+)/i', $value, $m)) {
            return sprintf('#%02x%02x%02x', $m[1], $m[2], $m[3]);
        }

        return null;
    }
}
