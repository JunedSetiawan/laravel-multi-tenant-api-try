<?php

namespace App\Services;

use YasinTgh\LaravelPostman\Collections\RouteGrouper;
use YasinTgh\LaravelPostman\DataTransferObjects\RouteInfoDto;
use YasinTgh\LaravelPostman\Services\NameGenerator;
use YasinTgh\LaravelPostman\Services\RequestBodyGenerator;

class ExtendedRouteGrouper extends RouteGrouper
{
    public function __construct(
        protected string $strategy,
        protected array $config,
        protected NameGenerator $name_generator,
        protected RequestBodyGenerator $bodyGenerator,
        protected QueryParameterExtractor $queryExtractor
    ) {
        parent::__construct($strategy, $config, $name_generator, $bodyGenerator);
    }

    /**
     * Override formatRoute to add query parameters and dynamic headers
     */
    protected function formatRoute(RouteInfoDto $route): array
    {
        // Call parent to get the base formatted route
        $formatted = parent::formatRoute($route);

        // Override headers with conditional logic based on URI
        $formatted['request']['header'] = $this->buildConditionalHeaders($route);

        // Only add query parameters for GET requests
        if (in_array('GET', $route->methods)) {
            $queryParams = $this->queryExtractor->extract($route->controller, $route->action);

            if (!empty($queryParams)) {
                // Add query parameters to URL structure
                $formatted['request']['url']['query'] = $queryParams;

                // Update raw URL with query string
                $queryString = $this->buildQueryString($queryParams);

                if (!empty($queryString)) {
                    $formatted['request']['url']['raw'] .= '?' . $queryString;
                }
            }
        }

        return $formatted;
    }

    /**
     * Build headers conditionally based on route URI
     *
     * @param RouteInfoDto $route
     * @return array
     */
    protected function buildConditionalHeaders(RouteInfoDto $route): array
    {
        $headers = [
            [
                'key' => 'Accept',
                'value' => 'application/json',
                'type' => 'text'
            ],
            [
                'key' => 'Content-Type',
                'value' => 'application/json',
                'type' => 'text'
            ],
        ];

        $uri = $route->uri;

        // Central routes - require X-Master-API-Key only
        if (str_starts_with($uri, 'api/central')) {
            $headers[] = [
                'key' => 'X-Master-API-Key',
                'value' => '{{master_api_key}}',
                'type' => 'text',
                'description' => 'Master API key for central management'
            ];
        } 
        // Tenant routes - require X-Tenant-API-Key
        else if (str_starts_with($uri, 'api/')) {
            $headers[] = [
                'key' => 'X-Tenant-API-Key',
                'value' => '{{tenant_api_key}}',
                'type' => 'text',
                'description' => 'Tenant-specific API key'
            ];
        }

        return $headers;
    }

    /**
     * Build query string from parameters
     *
     * @param array $params
     * @return string
     */
    protected function buildQueryString(array $params): string
    {
        $parts = [];

        foreach ($params as $param) {
            if (!empty($param['value'])) {
                $parts[] = $param['key'] . '=' . $param['value'];
            }
        }

        return implode('&', $parts);
    }
}
