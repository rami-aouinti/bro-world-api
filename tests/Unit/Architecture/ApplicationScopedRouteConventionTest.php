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

    private const APPLICATION_SCOPED_SEGMENT = '/applications/{applicationSlug}';

    public function testV1RoutesFollowModuleScopedConvention(): void
    {
        $invalidModulePrefixes = [];
        $legacyPrivatePrefixes = [];
        $applicationScopedRoutes = [];

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
                    $routeIdentity = $file->getPathname() . ' -> ' . $path;

                    if (!\str_starts_with($path, $expectedModulePrefix)) {
                        $invalidModulePrefixes[] = $routeIdentity . ' (expected prefix: ' . $expectedModulePrefix . '...)';
                    }

                    if (\str_starts_with($path, '/v1/private/')) {
                        $legacyPrivatePrefixes[] = $routeIdentity . ' (legacy private prefix: use /v1/' . $module . '/private/...)';
                    }

                    if (\str_contains($path, self::APPLICATION_SCOPED_SEGMENT)) {
                        $applicationScopedRoutes[] = $routeIdentity;
                    }
                }
            }
        }

        self::assertSame(
            [],
            $invalidModulePrefixes,
            "Routes V1 hors convention module-scoped: utiliser explicitement le préfixe /v1/{module}/...\n"
            . \implode("\n", $invalidModulePrefixes),
        );

        self::assertSame(
            [],
            $legacyPrivatePrefixes,
            "Routes V1 avec préfixe private legacy: utiliser /v1/{module}/private/...\n"
            . \implode("\n", $legacyPrivatePrefixes),
        );

        self::assertSame(
            [],
            $applicationScopedRoutes,
            "Routes V1 interdites avec segment applicatif " . self::APPLICATION_SCOPED_SEGMENT . ".\n"
            . "Nouvelle convention: rester en /v1/{module}/... et passer le slug applicatif via query/header/body (fallback: general).\n"
            . \implode("\n", $applicationScopedRoutes),
        );
    }
}
