<?php
declare(strict_types=1);

namespace Italgres\Configurator\Controller\Adminhtml\Model;

use Italgres\Configurator\Controller\Adminhtml\UploaderFieldsTrait;
use Italgres\Configurator\Model\GlbInspector;
use Italgres\Configurator\Model\Media;
use Italgres\Configurator\Model\Model3dFactory;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\Serialize\Serializer\Json;

class Save extends Action implements HttpPostActionInterface
{
    use UploaderFieldsTrait;

    public const ADMIN_RESOURCE = 'Italgres_Configurator::models';

    public function __construct(
        Context $context,
        private readonly Model3dFactory $factory,
        private readonly GlbInspector $inspector,
        private readonly Media $media,
        private readonly Json $json
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $redirect = $this->resultRedirectFactory->create();
        $data = (array)$this->getRequest()->getPostValue();
        if (!$data) {
            return $redirect->setPath('*/*/');
        }
        $id = (int)($data['model_id'] ?? 0);
        $model = $this->factory->create();
        if ($id) {
            $model->load($id);
            if (!$model->getId()) {
                $this->messageManager->addErrorMessage(__('This 3D model no longer exists.'));

                return $redirect->setPath('*/*/');
            }
        }
        unset($data['model_id'], $data['form_key'], $data['created_at'], $data['updated_at'], $data['slots'], $data['preview']);
        $data = $this->flattenUploads($data, ['file']);
        $data['code'] = strtolower(trim((string)preg_replace('/[^A-Za-z0-9_-]+/', '-', (string)($data['code'] ?? ''))), '-');
        $slots = $data['file'] ? $this->inspector->inspect($this->media->getAbsolutePath($data['file'])) : null;
        $data['slots'] = $slots ? $this->json->serialize($slots) : null;

        try {
            $model->addData($data)->save();
            $this->messageManager->addSuccessMessage(__('The 3D model "%1" was saved.', $model->getName()));
            if ($data['file'] && !$slots) {
                $this->messageManager->addWarningMessage(__('The uploaded file could not be read as a GLB model.'));
            }
            if ($this->getRequest()->getParam('back')) {
                return $redirect->setPath('*/*/edit', ['id' => $model->getId()]);
            }

            return $redirect->setPath('*/*/');
        } catch (\Throwable $e) {
            $this->messageManager->addErrorMessage($e->getMessage());

            return $redirect->setPath('*/*/edit', $id ? ['id' => $id] : []);
        }
    }
}
