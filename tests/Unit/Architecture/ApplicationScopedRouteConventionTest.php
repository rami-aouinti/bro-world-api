<?php

declare(strict_types=1);

namespace App\Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

final class ApplicationScopedRouteConventionTest extends TestCase
{
    private const MODULES = [
        'crm',
        'recruit',
        'shop',
        'school',
        'calendar',
        'chat',
        'quiz',
        'blog',
    ];

    public function testV1RoutesFollowModuleScopedConvention(): void
    {
        $violations = [];

        foreach (self::MODULES as $module) {
            $moduleDir = \dirname(__DIR__, 3) . '/src/' . ucfirst($module) . '/Transport/Controller/Api/V1';

            if (!\is_dir($moduleDir)) {
                continue;
            }

            /** @var \SplFileInfo $file */
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($moduleDir)) as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $content = (string) \file_get_contents($file->getPathname());
                if (!\preg_match_all("/Route\\((?:[^'\\n]*?)'([^']+)'/", $content, $matches)) {
                    continue;
                }

                foreach ($matches[1] as $path) {
                    if (!\str_starts_with($path, '/v1/')) {
                        continue;
                    }

                    $expectedModulePrefix = '/v1/' . $module . '/';

                    if (!\str_starts_with($path, $expectedModulePrefix)) {
                        $violations[] = $file->getPathname() . ' -> ' . $path . ' (expected prefix: ' . $expectedModulePrefix . '...)';
                    }

                    if (\str_contains($path, '/applications/{applicationSlug}')) {
                        $violations[] = $file->getPathname() . ' -> ' . $path . ' (forbidden segment: /applications/{applicationSlug})';
                    }

                    if (\str_starts_with($path, '/v1/private/')) {
                        $violations[] = $file->getPathname() . ' -> ' . $path . ' (legacy private prefix: use /v1/' . $module . '/private/...)';
                    }
                }
            }
        }

        self::assertSame(
            [],
            $violations,
            "Routes V1 hors convention module-scoped. "
            . "Utiliser /v1/{module}/... sans /applications/{applicationSlug}; "
            . "passer le slug applicatif via query/header/body avec fallback sur 'general'.\n"
            . \implode("\n", $violations),
        );
    }
}
