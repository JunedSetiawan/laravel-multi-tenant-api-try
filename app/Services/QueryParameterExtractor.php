<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use ReflectionClass;
use ReflectionMethod;

class QueryParameterExtractor
{
    /**
     * Extract query parameters from controller method
     *
     * @param string|null $controllerClass
     * @param string|null $method
     * @return array
     */
    public function extract(?string $controllerClass, ?string $method): array
    {
        if (!$controllerClass || !$method) {
            return [];
        }

        try {
            // Check if method uses pagination
            if ($this->usesPagination($controllerClass, $method)) {
                return $this->getPaginationParams();
            }

            // Check for request->query() or request->get() calls
            $params = $this->extractFromMethodBody($controllerClass, $method);

            return $params;
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Check if controller method uses Laravel pagination
     *
     * @param string $controllerClass
     * @param string $method
     * @return bool
     */
    protected function usesPagination(string $controllerClass, string $method): bool
    {
        try {
            if (!class_exists($controllerClass)) {
                return false;
            }

            $reflection = new ReflectionClass($controllerClass);

            if (!$reflection->hasMethod($method)) {
                return false;
            }

            $methodReflection = $reflection->getMethod($method);
            $fileName = $methodReflection->getFileName();
            $startLine = $methodReflection->getStartLine();
            $endLine = $methodReflection->getEndLine();

            if (!$fileName || !$startLine || !$endLine) {
                return false;
            }

            $fileContent = file($fileName);
            $methodCode = implode('', array_slice($fileContent, $startLine - 1, $endLine - $startLine + 1));

            // Check for pagination methods
            return preg_match('/->paginate\s*\(|->simplePaginate\s*\(|->cursorPaginate\s*\(/', $methodCode) === 1;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get standard pagination parameters
     *
     * @return array
     */
    protected function getPaginationParams(): array
    {
        return [
            [
                'key' => 'page',
                'value' => '1',
                'description' => 'Page number for pagination',
                'disabled' => false
            ],
            [
                'key' => 'per_page',
                'value' => '10',
                'description' => 'Items per page',
                'disabled' => false
            ]
        ];
    }

    /**
     * Extract query parameters from method body (looking for $request->query() patterns)
     *
     * @param string $controllerClass
     * @param string $method
     * @return array
     */
    protected function extractFromMethodBody(string $controllerClass, string $method): array
    {
        try {
            if (!class_exists($controllerClass)) {
                return [];
            }

            $reflection = new ReflectionClass($controllerClass);

            if (!$reflection->hasMethod($method)) {
                return [];
            }

            $methodReflection = $reflection->getMethod($method);
            $fileName = $methodReflection->getFileName();
            $startLine = $methodReflection->getStartLine();
            $endLine = $methodReflection->getEndLine();

            if (!$fileName || !$startLine || !$endLine) {
                return [];
            }

            $fileContent = file($fileName);
            $methodCode = implode('', array_slice($fileContent, $startLine - 1, $endLine - $startLine + 1));

            $params = [];

            // Match $request->query('param') or $request->get('param') or $request->input('param')
            preg_match_all('/\$request->(query|get|input)\s*\(\s*[\'"]([a-zA-Z_][a-zA-Z0-9_]*)[\'"]/', $methodCode, $matches);

            if (!empty($matches[2])) {
                foreach (array_unique($matches[2]) as $param) {
                    $params[] = [
                        'key' => $param,
                        'value' => '',
                        'description' => ucfirst(str_replace('_', ' ', $param)),
                        'disabled' => false
                    ];
                }
            }

            return $params;
        } catch (\Exception $e) {
            return [];
        }
    }
}
