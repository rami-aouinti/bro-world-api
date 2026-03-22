<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Domain\Entity\Project;
use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_filter;
use function array_map;
use function array_values;
use function count;
use function is_array;
use function is_string;
use function sprintf;
use function str_contains;
use function strtolower;
use function trim;

readonly class CrmGithubService
{
    private const string BASE_URL = 'https://api.github.com';

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    public function getDashboard(Project $project): array
    {
        $repositories = $this->listRepositories($project);
        $stats = ['open' => 0, 'closed' => 0, 'merged' => 0];

        foreach ($repositories as $repository) {
            $pulls = $this->listPullRequests($project, $repository['fullName'], state: 'all', page: 1, perPage: 100);
            foreach ($pulls['items'] as $pullRequest) {
                $state = (string)($pullRequest['state'] ?? '');
                if ($state === 'open') {
                    $stats['open']++;
                    continue;
                }

                $mergedAt = $pullRequest['mergedAt'] ?? null;
                if (is_string($mergedAt) && $mergedAt !== '') {
                    $stats['merged']++;
                    continue;
                }

                $stats['closed']++;
            }
        }

        return [
            'repositories' => $repositories,
            'pullRequests' => $stats,
        ];
    }

    /**
     * @return list<array{fullName:string,defaultBranch:string|null}>
     */
    public function listRepositories(Project $project): array
    {
        $configured = $project->getGithubRepositories();

        return array_values(array_filter(array_map(static function (array $repository): ?array {
            $fullName = $repository['fullName'] ?? null;
            if (!is_string($fullName) || $fullName === '') {
                return null;
            }

            $defaultBranch = $repository['defaultBranch'] ?? null;

            return [
                'fullName' => $fullName,
                'defaultBranch' => is_string($defaultBranch) && $defaultBranch !== '' ? $defaultBranch : null,
            ];
        }, $configured)));
    }

    public function listAccountRepositories(Project $project, int $page = 1, int $perPage = 30, string $search = ''): array
    {
        $response = $this->request($project, 'GET', '/user/repos', [
            'query' => [
                'sort' => 'updated',
                'direction' => 'desc',
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);

        $items = array_values(array_filter(array_map(static function (array $repository): ?array {
            $fullName = $repository['full_name'] ?? null;
            if (!is_string($fullName) || $fullName === '') {
                return null;
            }

            return [
                'name' => (string)($repository['name'] ?? ''),
                'fullName' => $fullName,
                'private' => (bool)($repository['private'] ?? false),
                'defaultBranch' => isset($repository['default_branch']) && is_string($repository['default_branch']) && $repository['default_branch'] !== ''
                    ? $repository['default_branch']
                    : null,
                'htmlUrl' => (string)($repository['html_url'] ?? ''),
                'owner' => (string)($repository['owner']['login'] ?? ''),
            ];
        }, $response)));

        if ($search !== '') {
            $normalizedSearch = strtolower($search);
            $items = array_values(array_filter($items, static fn (array $item): bool => str_contains(strtolower((string)$item['name']), $normalizedSearch)
                || str_contains(strtolower((string)$item['fullName']), $normalizedSearch)));
        }

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $perPage,
                'totalItems' => count($items),
                'totalPages' => 1,
            ],
        ];
    }

    /**
     * @return array{fullName:string,defaultBranch:string|null}
     */
    public function attachRepository(Project $project, string $fullName): array
    {
        $normalizedFullName = trim($fullName);
        if ($normalizedFullName === '') {
            throw new RuntimeException('Repository full name cannot be empty.');
        }

        $repository = $this->request($project, 'GET', sprintf('/repos/%s', $normalizedFullName));
        $normalizedRepository = [
            'fullName' => (string)($repository['full_name'] ?? $normalizedFullName),
            'defaultBranch' => isset($repository['default_branch']) && is_string($repository['default_branch']) && $repository['default_branch'] !== ''
                ? $repository['default_branch']
                : null,
        ];

        $repositories = $this->listRepositories($project);
        foreach ($repositories as $configuredRepository) {
            if (strtolower($configuredRepository['fullName']) === strtolower($normalizedRepository['fullName'])) {
                return $configuredRepository;
            }
        }

        $repositories[] = $normalizedRepository;
        $project->setGithubRepositories($repositories);

        return $normalizedRepository;
    }

    public function listBranches(Project $project, string $repoFullName, int $page = 1, int $perPage = 30, string $search = ''): array
    {
        $response = $this->request($project, 'GET', sprintf('/repos/%s/branches', $repoFullName), [
            'query' => ['page' => $page, 'per_page' => $perPage],
        ]);

        $items = array_map(static fn (array $branch): array => [
            'name' => $branch['name'] ?? '',
            'protected' => (bool)($branch['protected'] ?? false),
            'sha' => $branch['commit']['sha'] ?? null,
        ], $response);

        if ($search !== '') {
            $items = array_values(array_filter($items, static fn (array $item): bool => str_contains(strtolower((string)$item['name']), strtolower($search))));
        }

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $perPage,
                'totalItems' => count($items),
                'totalPages' => 1,
            ],
        ];
    }

    public function listPullRequests(Project $project, string $repoFullName, string $state = 'open', ?string $author = null, string $search = '', int $page = 1, int $perPage = 30): array
    {
        $response = $this->request($project, 'GET', sprintf('/repos/%s/pulls', $repoFullName), [
            'query' => [
                'state' => $state,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);

        $items = array_values(array_filter(array_map(static function (array $pull): ?array {
            $user = $pull['user'] ?? [];
            $head = $pull['head'] ?? [];
            $base = $pull['base'] ?? [];

            return [
                'number' => (int)($pull['number'] ?? 0),
                'title' => (string)($pull['title'] ?? ''),
                'state' => (string)($pull['state'] ?? ''),
                'mergedAt' => $pull['merged_at'] ?? null,
                'author' => (string)($user['login'] ?? ''),
                'head' => (string)($head['ref'] ?? ''),
                'base' => (string)($base['ref'] ?? ''),
                'draft' => (bool)($pull['draft'] ?? false),
                'htmlUrl' => (string)($pull['html_url'] ?? ''),
            ];
        }, $response)));

        if (is_string($author) && $author !== '') {
            $items = array_values(array_filter($items, static fn (array $item): bool => strtolower((string)$item['author']) === strtolower($author)));
        }

        if ($search !== '') {
            $items = array_values(array_filter($items, static fn (array $item): bool => str_contains(strtolower((string)$item['title']), strtolower($search))));
        }

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $perPage,
                'totalItems' => count($items),
                'totalPages' => 1,
            ],
        ];
    }

    public function getPullRequest(Project $project, string $repoFullName, int $number): array
    {
        $pull = $this->request($project, 'GET', sprintf('/repos/%s/pulls/%d', $repoFullName, $number));

        return [
            'number' => (int)($pull['number'] ?? 0),
            'title' => (string)($pull['title'] ?? ''),
            'state' => (string)($pull['state'] ?? ''),
            'author' => (string)($pull['user']['login'] ?? ''),
            'commits' => (int)($pull['commits'] ?? 0),
            'changedFiles' => (int)($pull['changed_files'] ?? 0),
            'additions' => (int)($pull['additions'] ?? 0),
            'deletions' => (int)($pull['deletions'] ?? 0),
            'mergedAt' => $pull['merged_at'] ?? null,
            'mergeable' => $pull['mergeable'] ?? null,
            'statusesUrl' => (string)($pull['statuses_url'] ?? ''),
            'head' => (string)($pull['head']['ref'] ?? ''),
            'base' => (string)($pull['base']['ref'] ?? ''),
            'htmlUrl' => (string)($pull['html_url'] ?? ''),
        ];
    }

    public function mergePullRequest(Project $project, string $repoFullName, int $number, string $method = 'merge'): array
    {
        return $this->request($project, 'PUT', sprintf('/repos/%s/pulls/%d/merge', $repoFullName, $number), [
            'json' => [
                'merge_method' => $method,
            ],
        ]);
    }

    public function closePullRequest(Project $project, string $repoFullName, int $number): array
    {
        return $this->request($project, 'PATCH', sprintf('/repos/%s/pulls/%d', $repoFullName, $number), [
            'json' => ['state' => 'closed'],
        ]);
    }

    /**
     * @param array<string,mixed> $options
     * @return array<mixed>
     */
    private function request(Project $project, string $method, string $path, array $options = []): array
    {
        $token = $project->getGithubToken();
        if (!is_string($token) || $token === '') {
            throw new RuntimeException('GitHub token is not configured on this project.');
        }
        try {
            $response = $this->httpClient->request($method, self::BASE_URL . $path, $options + [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                ],
            ]);

            return $response->toArray(false);
        } catch (ExceptionInterface $exception) {
            throw new RuntimeException('GitHub API request failed: ' . $exception->getMessage(), previous: $exception);
        }
    }
}
