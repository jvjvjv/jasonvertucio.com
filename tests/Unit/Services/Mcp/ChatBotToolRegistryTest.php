<?php

namespace Tests\Unit\Services\Mcp;

use App\Contracts\Mcp\AiToolHandlerContract;
use App\Contracts\ResumeDataServiceContract;
use App\Models\AiConversation;
use App\Services\AiMemoryService;
use App\Services\Mcp\ChatBotToolRegistry;
use App\Services\Mcp\TargetedResumeToolRegistry;
use App\Services\TargetedResumeService;
use Illuminate\Support\Facades\File;
use ReflectionClass;
use SplFileInfo;
use Tests\TestCase;

class ChatBotToolRegistryTest extends TestCase
{
    public function testItRegistersEveryToolInTheMcpToolsDirectory(): void
    {
        $resumeDataService = $this->createMock(ResumeDataServiceContract::class);
        $memoryService = $this->createMock(AiMemoryService::class);
        $targetedResumeService = $this->createMock(TargetedResumeService::class);
        $conversation = new AiConversation([
            'user_id' => 123,
            'context' => [],
        ]);

        $this->app->instance(ResumeDataServiceContract::class, $resumeDataService);
        $this->app->instance(AiMemoryService::class, $memoryService);
        $this->app->instance(TargetedResumeService::class, $targetedResumeService);

        $registry = new ChatBotToolRegistry(
            $conversation,
            $resumeDataService,
            $memoryService,
            $targetedResumeService,
        );

        $registeredToolNames = array_column($registry->toApiTools(), 'name');

        $expectedToolNames = $this->expectedToolNames([
            app_path('Services/Mcp/Tools'),
        ], [
            'conversation' => $conversation,
            'resumeDataService' => $resumeDataService,
            'memoryService' => $memoryService,
            'targetedResumeService' => $targetedResumeService,
            'userId' => $conversation->user_id,
        ], ['Services/Mcp/Tools/ChatBot']);

        $this->assertSame($expectedToolNames, $registeredToolNames);
    }

    public function testItRegistersEveryTargetedResumeToolInItsDirectory(): void
    {
        $resumeDataService = $this->createMock(ResumeDataServiceContract::class);
        $memoryService = $this->createMock(AiMemoryService::class);
        $targetedResumeService = $this->createMock(TargetedResumeService::class);
        $conversation = new AiConversation([
            'user_id' => 456,
            'context' => [],
        ]);

        $this->app->instance(ResumeDataServiceContract::class, $resumeDataService);
        $this->app->instance(AiMemoryService::class, $memoryService);
        $this->app->instance(TargetedResumeService::class, $targetedResumeService);

        $registry = new TargetedResumeToolRegistry(
            $conversation,
            $resumeDataService,
            $memoryService,
            $targetedResumeService,
        );

        $registeredToolNames = array_column($registry->toApiTools(), 'name');

        $expectedToolNames = $this->expectedToolNames([
            app_path('Services/Mcp/Tools/TargetedResume'),
        ], [
            'conversation' => $conversation,
            'resumeDataService' => $resumeDataService,
            'memoryService' => $memoryService,
            'targetedResumeService' => $targetedResumeService,
            'userId' => $conversation->user_id,
        ]);

        $this->assertSame($expectedToolNames, $registeredToolNames);
    }

    /**
     * @param array<int, string> $directories
     * @param array<string, mixed> $parameterOverrides
     * @param array<int, string> $preferredRelativePrefixes
     * @return array<int, string>
     */
    private function expectedToolNames(
        array $directories,
        array $parameterOverrides,
        array $preferredRelativePrefixes = [],
    ): array {
        $toolFiles = [];

        foreach ($directories as $directory) {
            foreach (File::allFiles($directory) as $toolFile) {
                $toolFiles[] = $toolFile;
            }
        }

        usort(
            $toolFiles,
            static fn (SplFileInfo $left, SplFileInfo $right): int => strcmp(
                self::sortKey($left, $preferredRelativePrefixes),
                self::sortKey($right, $preferredRelativePrefixes),
            ),
        );

        $expectedToolNames = [];

        foreach ($toolFiles as $toolFile) {
            $className = self::classNameFromToolFile($toolFile);

            if ($className === null || !class_exists($className)) {
                continue;
            }

            $reflection = new ReflectionClass($className);

            if ($reflection->isAbstract() || !$reflection->implementsInterface(AiToolHandlerContract::class)) {
                continue;
            }

            $handler = app()->makeWith($className, $parameterOverrides);

            $this->assertInstanceOf(AiToolHandlerContract::class, $handler);

            if (!in_array($handler->name(), $expectedToolNames, true)) {
                $expectedToolNames[] = $handler->name();
            }
        }

        return $expectedToolNames;
    }

    /**
     * @param array<int, string> $preferredRelativePrefixes
     */
    private static function sortKey(SplFileInfo $toolFile, array $preferredRelativePrefixes): string
    {
        $relativePath = self::relativeToolPath($toolFile);
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

    private static function classNameFromToolFile(SplFileInfo $toolFile): ?string
    {
        $relativePath = self::relativeToolPath($toolFile);

        if ($relativePath === '') {
            return null;
        }

        return 'App\\' . str_replace(
            [DIRECTORY_SEPARATOR, '.php'],
            ['\\', ''],
            $relativePath,
        );
    }

    private static function relativeToolPath(SplFileInfo $toolFile): string
    {
        $appRoot = app_path() . DIRECTORY_SEPARATOR;
        $pathName = $toolFile->getPathname();

        if (!str_starts_with($pathName, $appRoot)) {
            return '';
        }

        return substr($pathName, strlen($appRoot));
    }
}
