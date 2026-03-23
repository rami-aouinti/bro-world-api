<?php

declare(strict_types=1);

namespace App\Tests\Unit\Architecture;

use PHPUnit\Framework\TestCase;

final class CrmSwaggerDocumentationConventionTest extends TestCase
{
    private const array TARGET_DIRECTORIES = [
        'src/Crm/Transport/Controller/Api/V1/Contact',
        'src/Crm/Transport/Controller/Api/V1/Company',
        'src/Crm/Transport/Controller/Api/V1/Billing',
        'src/Crm/Transport/Controller/Api/V1/Employee',
        'src/Crm/Transport/Controller/Api/V1/Project',
        'src/Crm/Transport/Controller/Api/V1/Sprint',
        'src/Crm/Transport/Controller/Api/V1/Task',
        'src/Crm/Transport/Controller/Api/V1/TaskRequest',
    ];

    private const array SUMMARY_VERBS = [
        'Add',
        'Attach',
        'Create',
        'Delete',
        'Detach',
        'Get',
        'Handle',
        'List',
        'Move',
        'Patch',
        'Put',
        'Remove',
        'Replace',
        'Run',
        'Update',
        'Upload',
    ];

    public function testCrmSwaggerOperationsFollowConvention(): void
    {
        $violations = [];

        foreach ($this->controllerFiles() as $relativePath) {
            $absolutePath = \dirname(__DIR__, 3) . '/' . $relativePath;
            $content = (string)\file_get_contents($absolutePath);

            $expectedTag = 'Crm';
            if (\str_contains($relativePath, '/Project/Github/')) {
                $expectedTag = 'Crm Github';
            } elseif (\str_contains($relativePath, '/TaskRequest/')) {
                $expectedTag = 'Crm TaskRequest';
            }

            if (!\str_contains($content, "#[OA\\Tag(name: '{$expectedTag}')]")) {
                $violations[] = $relativePath . ' -> tag attendu: ' . $expectedTag;
            }

            if (\preg_match('/#\\[OA\\\\(?:Get|Post|Put|Patch|Delete)\\(/', $content) === 1 && !\str_contains($content, 'responses: [')) {
                $violations[] = $relativePath . ' -> responses: [ ... ] manquant dans l\'opération OpenAPI.';
            }

            if (!\preg_match_all('/summary:\\s*\\\'([^\\\']+)\\\'/', $content, $summaryMatches)) {
                $violations[] = $relativePath . ' -> summary manquant.';

                continue;
            }

            foreach ($summaryMatches[1] as $summary) {
                if (\str_starts_with($summary, 'Exemple ')) {
                    continue;
                }

                if ($summary === 'JSON invalide' || $summary === 'Date invalide') {
                    continue;
                }

                $firstWord = \explode(' ', $summary)[0] ?? '';
                if (!\in_array($firstWord, self::SUMMARY_VERBS, true)) {
                    $violations[] = $relativePath . ' -> summary invalide (verbe inconnu): ' . $summary;
                }

                if (\str_contains($summary, '/v1/') || \str_contains($summary, 'dans le CRM')) {
                    $violations[] = $relativePath . ' -> summary invalide (format interdit): ' . $summary;
                }
            }
        }

        self::assertSame([], $violations, "Violations convention Swagger CRM:\n" . \implode("\n", $violations));
    }

    /**
     * @return list<string>
     */
    private function controllerFiles(): array
    {
        $files = [];

        foreach (self::TARGET_DIRECTORIES as $directory) {
            $absoluteDirectory = \dirname(__DIR__, 3) . '/' . $directory;
            if (!\is_dir($absoluteDirectory)) {
                continue;
            }

            /** @var \SplFileInfo $file */
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($absoluteDirectory)) as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $relative = \str_replace(\dirname(__DIR__, 3) . '/', '', $file->getPathname());
                $files[] = \str_replace('\\', '/', $relative);
            }
        }

        \sort($files);

        return $files;
    }
}
