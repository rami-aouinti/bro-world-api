<?php

declare(strict_types=1);

namespace App\Tool\Transport\Command\Crm;

use Override;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

#[AsCommand(
    name: 'qa:crm:swagger',
    description: 'Validate CRM routes are documented in OpenAPI and contain examples for body endpoints.',
)]
final class CrmSwaggerQaCommand extends Command
{
    private const string CRM_CONTROLLER_DIR = 'src/Crm/Transport/Controller/Api/V1';

    public function __construct(private readonly string $projectDir)
    {
        parent::__construct();
    }

    #[Override]
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $controllers = $this->collectControllers();

        if ($controllers === []) {
            $output->writeln('<error>No CRM controllers found to validate.</error>');

            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>CRM controllers with #[Route(...)] (%d):</info>', count($controllers)));
        foreach ($controllers as $controller) {
            $output->writeln(sprintf('- %s (%d route%s)', $controller['file'], count($controller['routes']), count($controller['routes']) > 1 ? 's' : ''));
        }

        $missingOperations = [];
        $missingRequestExamples = [];
        $missingResponseExamples = [];

        foreach ($controllers as $controller) {
            foreach ($controller['routes'] as $route) {
                if (!$route['hasOperation']) {
                    $missingOperations[] = $this->formatIssue($controller['file'], $route);

                    continue;
                }

                if (!$route['hasBody']) {
                    continue;
                }

                if (!$route['hasRequestExample']) {
                    $missingRequestExamples[] = $this->formatIssue($controller['file'], $route);
                }

                if (!$route['hasResponseExample']) {
                    $missingResponseExamples[] = $this->formatIssue($controller['file'], $route);
                }
            }
        }

        $hasViolation = false;

        if ($missingOperations !== []) {
            $hasViolation = true;
            $output->writeln('');
            $output->writeln('<error>Missing OpenAPI operation for CRM routes:</error>');
            foreach ($missingOperations as $issue) {
                $output->writeln('- ' . $issue);
            }
        }

        if ($missingRequestExamples !== []) {
            $hasViolation = true;
            $output->writeln('');
            $output->writeln('<error>Missing request examples for CRM body endpoints:</error>');
            foreach ($missingRequestExamples as $issue) {
                $output->writeln('- ' . $issue);
            }
        }

        if ($missingResponseExamples !== []) {
            $hasViolation = true;
            $output->writeln('');
            $output->writeln('<error>Missing response examples for CRM body endpoints:</error>');
            foreach ($missingResponseExamples as $issue) {
                $output->writeln('- ' . $issue);
            }
        }

        if ($hasViolation) {
            $output->writeln('');
            $output->writeln('<error>CRM Swagger QA failed. Please document all missing endpoints before merging.</error>');

            return Command::FAILURE;
        }

        $output->writeln('');
        $output->writeln('<info>CRM Swagger QA passed: all routes are documented with the required examples.</info>');

        return Command::SUCCESS;
    }

    /**
     * @return list<array{file: string, routes: list<array{path: string, methods: list<string>, hasOperation: bool, hasBody: bool, hasRequestExample: bool, hasResponseExample: bool}>}>
     */
    private function collectControllers(): array
    {
        $finder = new Finder();
        $finder
            ->files()
            ->in($this->projectDir . '/' . self::CRM_CONTROLLER_DIR)
            ->name('*.php')
            ->sortByName();

        $controllers = [];

        foreach ($finder as $file) {
            $content = (string)file_get_contents($file->getPathname());
            $routes = $this->extractRoutesFromContent($content);
            if ($routes === []) {
                continue;
            }

            $controllers[] = [
                'file' => str_replace($this->projectDir . '/', '', $file->getPathname()),
                'routes' => $routes,
            ];
        }

        return $controllers;
    }

    /**
     * @return list<array{path: string, methods: list<string>, hasOperation: bool, hasBody: bool, hasRequestExample: bool, hasResponseExample: bool}>
     */
    private function extractRoutesFromContent(string $content): array
    {
        $lines = preg_split('/\R/', $content) ?: [];
        $pendingAttributes = '';
        $collectingAttribute = false;
        $squareBracketDepth = 0;
        $routes = [];

        foreach ($lines as $line) {
            $trimmedLine = trim($line);

            if ($collectingAttribute || str_contains($line, '#[')) {
                if (!$collectingAttribute) {
                    $collectingAttribute = true;
                    $squareBracketDepth = 0;
                }

                $pendingAttributes .= $line . "\n";
                $squareBracketDepth += substr_count($line, '[') - substr_count($line, ']');
                if ($squareBracketDepth <= 0) {
                    $collectingAttribute = false;
                }

                continue;
            }

            if (preg_match('/public\s+function\s+[a-zA-Z0-9_]+\s*\(/', $line) === 1) {
                if ($pendingAttributes !== '') {
                    foreach ($this->extractRouteAttributes($pendingAttributes) as $routeAttribute) {
                        $methods = $this->extractHttpMethods($routeAttribute);
                        $hasOperation = preg_match('/#\[OA\\(?:Get|Post|Put|Patch|Delete)\(/', $pendingAttributes) === 1;
                        $hasBody = $this->hasBody($methods, $pendingAttributes);

                        $routes[] = [
                            'path' => $this->extractRoutePath($routeAttribute),
                            'methods' => $methods,
                            'hasOperation' => $hasOperation,
                            'hasBody' => $hasBody,
                            'hasRequestExample' => $hasBody ? $this->hasRequestExample($pendingAttributes) : false,
                            'hasResponseExample' => $hasBody ? $this->hasResponseExample($pendingAttributes) : false,
                        ];
                    }
                }

                $pendingAttributes = '';

                continue;
            }

            if (
                $trimmedLine !== ''
                && !str_starts_with($trimmedLine, '//')
                && !str_starts_with($trimmedLine, '/*')
                && !str_starts_with($trimmedLine, '*')
                && !str_starts_with($trimmedLine, '*/')
            ) {
                $pendingAttributes = '';
            }
        }

        return $routes;
    }

    /**
     * @return list<string>
     */
    private function extractRouteAttributes(string $attributes): array
    {
        preg_match_all('/#\[Route\((.*?)\)\]/s', $attributes, $matches);

        return $matches[1] ?? [];
    }

    /**
     * @return list<string>
     */
    private function extractHttpMethods(string $routeAttribute): array
    {
        if (preg_match('/methods\s*:\s*\[(.*?)\]/s', $routeAttribute, $matches) !== 1) {
            return [];
        }

        preg_match_all('/Request::METHOD_([A-Z]+)/', $matches[1], $constantMatches);
        $methods = array_map(static fn (string $method): string => strtoupper($method), $constantMatches[1] ?? []);

        if ($methods !== []) {
            return array_values(array_unique($methods));
        }

        preg_match_all('/[\"\']([A-Z]+)[\"\']/', $matches[1], $stringMatches);

        return array_values(array_unique(array_map(static fn (string $method): string => strtoupper($method), $stringMatches[1] ?? [])));
    }

    private function extractRoutePath(string $routeAttribute): string
    {
        if (preg_match('/^[\s\n\r\t]*[\"\']([^\"\']+)[\"\']/', $routeAttribute, $matches) === 1) {
            return $matches[1];
        }

        return '[unable to parse route path]';
    }

    /**
     * @param list<string> $methods
     */
    private function hasBody(array $methods, string $attributes): bool
    {
        foreach ($methods as $method) {
            if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
                return true;
            }
        }

        return preg_match('/#\[OA\\(?:Post|Put|Patch)\(/', $attributes) === 1;
    }

    private function hasRequestExample(string $attributes): bool
    {
        if (preg_match('/requestBody\s*:/', $attributes) !== 1) {
            return false;
        }

        return preg_match('/requestBody\s*:.*?\bexamples?\s*:/s', $attributes) === 1;
    }

    private function hasResponseExample(string $attributes): bool
    {
        if (preg_match('/responses\s*:/', $attributes) !== 1) {
            return false;
        }

        return preg_match('/responses\s*:.*?\bexamples?\s*:/s', $attributes) === 1;
    }

    /**
     * @param array{path: string, methods: list<string>} $route
     */
    private function formatIssue(string $file, array $route): string
    {
        $methods = $route['methods'] !== [] ? implode('|', $route['methods']) : 'ANY';

        return sprintf('%s :: [%s] %s', $file, $methods, $route['path']);
    }
}
