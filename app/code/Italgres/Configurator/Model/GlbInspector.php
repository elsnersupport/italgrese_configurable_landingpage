<?php
declare(strict_types=1);

namespace Italgres\Configurator\Model;

/**
 * Reads the JSON chunk of a .glb file so the admin can see which material slots and
 * nodes a model exposes (those names are what option values target).
 */
class GlbInspector
{
    /**
     * @return array{materials: string[], nodes: string[], meshes: string[]}|null
     */
    public function inspect(string $absolutePath): ?array
    {
        if (!is_file($absolutePath) || !is_readable($absolutePath)) {
            return null;
        }
        $handle = fopen($absolutePath, 'rb');
        if ($handle === false) {
            return null;
        }
        try {
            $header = fread($handle, 20);
            if ($header === false || strlen($header) < 20 || substr($header, 0, 4) !== 'glTF') {
                return null;
            }
            $chunk = unpack('Vlength/a4type', substr($header, 12, 8));
            if (!$chunk || $chunk['type'] !== 'JSON' || $chunk['length'] <= 0 || $chunk['length'] > 64 * 1024 * 1024) {
                return null;
            }
            $json = json_decode((string)fread($handle, $chunk['length']), true);
        } finally {
            fclose($handle);
        }
        if (!is_array($json)) {
            return null;
        }
        $names = static fn(array $items): array => array_values(array_unique(array_filter(
            array_map(static fn($item) => is_array($item) ? (string)($item['name'] ?? '') : '', $items)
        )));

        return [
            'materials' => $names($json['materials'] ?? []),
            'nodes' => $names($json['nodes'] ?? []),
            'meshes' => $names($json['meshes'] ?? []),
        ];
    }
}
