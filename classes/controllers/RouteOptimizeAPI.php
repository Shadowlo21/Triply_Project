<?php

class RouteOptimizeAPI
{
    public string $apiKey;
    private array $destinations = [];

    public function __construct(string $apiKey = '')
    {
        $this->apiKey = $apiKey;
    }

    public function sendDestinations(array $locations): void
    {
        $this->destinations = $locations;
    }

    public function calculateOptimalRoute(): array
    {
        if (count($this->destinations) <= 1) return $this->destinations;
        $remaining = $this->destinations;
        $route     = [array_shift($remaining)];
        while ($remaining) {
            $last = end($route);
            usort($remaining, function ($a, $b) use ($last) {
                return $this->dist($last, $a) <=> $this->dist($last, $b);
            });
            $route[] = array_shift($remaining);
        }
        return $route;
    }

    private function dist(array $a, array $b): float
    {
        $dx = ($a['lat'] ?? 0) - ($b['lat'] ?? 0);
        $dy = ($a['lng'] ?? 0) - ($b['lng'] ?? 0);
        return sqrt($dx * $dx + $dy * $dy);
    }
}
