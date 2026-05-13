<?php

namespace App\Services\Mcp;

use App\Contracts\Mcp\AiToolHandlerContract;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use SplFileInfo;

trait DiscoversAiToolHandlers
{
    /**
     * @param array<int, string> $toolDirectories
     * @param array<string, mixed> $parameterOverrides
     * @param array<int, string> $preferredRelativePrefixes
     * @return array<string, AiToolHandlerContract>
     */
    private function discoverHandlers(
        array $toolDirectories,
        array $parameterOverrides = [],
        array $preferredRelativePrefixes = [],
    ): array {
        $toolFiles = [];

        foreach ($toolDirectories as $toolDirectory) {
            if (!File::isDirectory($toolDirectory)) {
                continue;
            }

            foreach (File::allFiles($toolDirectory) as $toolFile) {
                $toolFiles[] = $toolFile;
            }
        }

        usort(
            $toolFiles,
            fn (SplFileInfo $left, SplFileInfo $right): int => strcmp(
                $this->toolDiscoverySortKey($left, $preferredRelativePrefixes),
                $this->toolDiscoverySortKey($right, $preferredRelativePrefixes),
            ),
        );

        $handlers = [];

        foreach ($toolFiles as $toolFile) {
            $className = $this->classNameFromToolFile($toolFile);

            if ($className === null || !class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);

            if ($reflection->isAbstract() || !$reflection->implementsInterface(AiToolHandlerContract::class)) {
                continue;
            }

            $handler = app()->makeWith($className, $parameterOverrides);
            $handlerName = $handler->name();

            if (!isset($handlers[$handlerName])) {
                $handlers[$handlerName] = $handler;
            }
        }

        return $handlers;
    }

    /**
     * @param array<int, string> $preferredRelativePrefixes
     */
    private function toolDiscoverySortKey(SplFileInfo $toolFile, array $preferredRelativePrefixes): string
    {
        $relativePath = $this->relativeToolPath($toolFile);
        $normalizedPath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);
        $priority = count($preferredRelativePrefixes);

        foreach ($preferredRelativePrefixes as $index => $prefix) {
            $normalizedPrefix = trim(str_replace(DIRECTORY_SEPARATOR, '/', $prefix), '/');

            if (str_starts_with($normalizedPath, $normalizedPrefix . '/')) {
                $priority = $index;

                break;
            }
        }

        return sprintf('%03d:%s', $priority, $normalizedPath);
    }

    private function classNameFromToolFile(SplFileInfo $toolFile): ?string
    {
        $relativePath = $this->relativeToolPath($toolFile);

        if ($relativePath === '') {
            return null;
        }

        return 'App\\' . str_replace(
            [DIRECTORY_SEPARATOR, '.php'],
            ['\\', ''],
            $relativePath,
        );
    }

    private function relativeToolPath(SplFileInfo $toolFile): string
    {
        $appRoot = app_path() . DIRECTORY_SEPARATOR;
        $pathName = $toolFile->getPathname();

        if (!str_starts_with($pathName, $appRoot)) {
            return '';
        }

        return substr($pathName, strlen($appRoot));
    }
}
