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
     * Override formatRoute to add query parameters
     */
    protected function formatRoute(RouteInfoDto $route): array
    {
        // Call parent to get the base formatted route
        $formatted = parent::formatRoute($route);

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
