<?php

class ItineraryController
{
    public array  $locations       = [];
    public array  $routeData       = [];
    public array  $alternativeData = [];
    public string $apiStatus       = 'idle';
    public ?int   $currentTripId   = null;

    private ?RouteOptimizeAPI   $routeApi;
    private ?ExternalMapService $maps;

    public function __construct(
        ?RouteOptimizeAPI   $routeApi = null,
        ?ExternalMapService $maps     = null
    ) {
        $this->routeApi = $routeApi ?? new RouteOptimizeAPI();
        $this->maps     = $maps     ?? new ExternalMapService();
    }

    public function requestOptimization(array $locations): void
    {
        $this->locations = $locations;
        $this->apiStatus = 'requesting';
        try {
            $this->routeApi->sendDestinations($locations);
            $this->routeData = $this->routeApi->calculateOptimalRoute();
            $this->apiStatus = 'ok';
        } catch (\Throwable $e) {
            $this->showConnectionError();
        }
    }

    public function showConnectionError(): void
    {
        $this->apiStatus = 'error';
    }
}
