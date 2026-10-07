<?php
declare(strict_types=1);

namespace Italgres\Configurator\Controller\Adminhtml;

/**
 * Turns fileUploader values ([{file, url, ...}]) back into the relative path stored in the table.
 */
trait UploaderFieldsTrait
{
    /**
     * @param array<string, mixed> $data
     * @param string[] $fields
     * @return array<string, mixed>
     */
    private function flattenUploads(array $data, array $fields): array
    {
        foreach ($fields as $field) {
            $value = $data[$field] ?? null;
            if (is_array($value)) {
                $data[$field] = isset($value[0]['file']) ? (string)$value[0]['file'] : null;
            } elseif (!array_key_exists($field, $data) || $value === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
