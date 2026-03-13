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

    public function testApplicationScopedRoutesFollowOfficialConvention(): void
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

                $content = (string)\file_get_contents($file->getPathname());
                if (!\preg_match_all("/Route\\((?:[^'\\n]*?)'([^']+)'/", $content, $matches)) {
                    continue;
                }

                foreach ($matches[1] as $path) {
                    if (!\str_contains($path, '{applicationSlug}')) {
                        continue;
                    }

                    $expectedPrefix = '/v1/' . $module . '/applications/{applicationSlug}';
                    if (!\str_starts_with($path, $expectedPrefix)) {
                        $violations[] = $file->getPathname() . ' -> ' . $path;

                        continue;
                    }

                    if (\str_contains($path, '/private/applications/{applicationSlug}')) {
                        $violations[] = $file->getPathname() . ' -> ' . $path;
                    }
                }
            }
        }

        self::assertSame([], $violations, "Routes hors convention:\n" . \implode("\n", $violations));
    }
}
