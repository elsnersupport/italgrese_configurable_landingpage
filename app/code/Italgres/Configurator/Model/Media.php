<?php
declare(strict_types=1);

namespace Italgres\Configurator\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Where configurator assets live under pub/media, and their public URLs.
 */
class Media
{
    public const BASE_PATH = 'italgres/configurator';

    /** Upload folders per asset kind, relative to BASE_PATH */
    public const FOLDERS = [
        'texture' => 'textures',
        'swatch' => 'swatches',
        'model' => 'models',
    ];

    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly Filesystem $filesystem
    ) {
    }

    public function getUrl(?string $relativePath): ?string
    {
        if ($relativePath === null || $relativePath === '') {
            return null;
        }
        $base = $this->storeManager->getStore()->getBaseUrl(UrlInterface::URL_TYPE_MEDIA);

        return $base . self::BASE_PATH . '/' . ltrim($relativePath, '/');
    }

    public function getAbsolutePath(string $relativePath = ''): string
    {
        return $this->filesystem->getDirectoryRead(DirectoryList::MEDIA)
            ->getAbsolutePath(self::BASE_PATH . '/' . ltrim($relativePath, '/'));
    }

    public function getFileSize(?string $relativePath): int
    {
        if (!$relativePath) {
            return 0;
        }
        $media = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        $path = self::BASE_PATH . '/' . ltrim($relativePath, '/');

        return $media->isFile($path) ? (int)($media->stat($path)['size'] ?? 0) : 0;
    }

    /**
     * Value shape the admin fileUploader component expects.
     *
     * @return array<int, array<string, mixed>>
     */
    public function toUploaderValue(?string $relativePath): array
    {
        if (!$relativePath) {
            return [];
        }

        return [[
            'name' => basename($relativePath),
            'file' => $relativePath,
            'url' => $this->getUrl($relativePath),
            'size' => $this->getFileSize($relativePath),
            'type' => str_ends_with(strtolower($relativePath), '.glb') ? 'model/gltf-binary' : 'image',
        ]];
    }
}
