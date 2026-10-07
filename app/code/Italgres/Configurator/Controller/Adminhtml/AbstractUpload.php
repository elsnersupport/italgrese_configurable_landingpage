<?php
declare(strict_types=1);

namespace Italgres\Configurator\Controller\Adminhtml;

use Italgres\Configurator\Model\Media;
use Magento\Backend\App\Action;
use Magento\Backend\App\Action\Context;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Controller\Result\Json;
use Magento\Framework\Controller\ResultFactory;
use Magento\Framework\Filesystem;
use Magento\MediaStorage\Model\File\UploaderFactory;

/**
 * fileUploader endpoint shared by the material and model forms. Files go straight to
 * pub/media/italgres/configurator/<folder>/ and the relative path is stored on save.
 */
abstract class AbstractUpload extends Action implements HttpPostActionInterface
{
    public function __construct(
        Context $context,
        private readonly UploaderFactory $uploaderFactory,
        private readonly Filesystem $filesystem,
        private readonly Media $media
    ) {
        parent::__construct($context);
    }

    /**
     * @return array{folder: string, extensions: string[]}
     */
    abstract protected function getRule(string $field): array;

    public function execute(): Json
    {
        /** @var Json $result */
        $result = $this->resultFactory->create(ResultFactory::TYPE_JSON);
        $field = (string)$this->getRequest()->getParam('param_name');
        try {
            $rule = $this->getRule($field);
            $uploader = $this->uploaderFactory->create(['fileId' => $field]);
            $uploader->setAllowedExtensions($rule['extensions']);
            $uploader->setAllowRenameFiles(true);
            $uploader->setFilesDispersion(false);
            $folder = Media::BASE_PATH . '/' . $rule['folder'];
            $saved = $uploader->save(
                $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA)->getAbsolutePath($folder)
            );
            if (!$saved) {
                throw new \RuntimeException((string)__('File could not be saved.'));
            }
            $relative = $rule['folder'] . '/' . ltrim((string)$saved['file'], '/');
            $value = $this->media->toUploaderValue($relative)[0];
            $value['size'] = (int)$saved['size'];

            return $result->setData($value);
        } catch (\Throwable $e) {
            return $result->setData(['error' => $e->getMessage(), 'errorcode' => $e->getCode()]);
        }
    }
}
