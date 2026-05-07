<?php

class ExternalMapService
{
    public function getMapData(array $location): array
    {
        return [
            'lat'     => $location['lat'] ?? null,
            'lng'     => $location['lng'] ?? null,
            'name'    => $location['name'] ?? '',
            'preview' => null,
        ];
    }

    public function renderMap(array $route): string
    {
        $points = array_map(fn($p) => ($p['lat'] ?? 0) . ',' . ($p['lng'] ?? 0), $route);
        return 'about:blank?route=' . urlencode(implode('|', $points));
    }
}
