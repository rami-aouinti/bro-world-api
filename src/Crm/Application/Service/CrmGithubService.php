<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Application\Exception\CrmGithubApiException;
use App\Crm\Domain\Entity\Project;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_values;
use function count;
use function is_array;
use function is_int;
use function is_string;
use function parse_str;
use function preg_match;
use function rawurlencode;
use function sprintf;
use function str_contains;
use function strtolower;
use function trim;
use function urldecode;

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
        $stats = [
            'open' => 0,
            'closed' => 0,
            'merged' => 0,
        ];

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

    public function getRepository(Project $project, string $repoFullName): array
    {
        $repository = $this->request($project, 'GET', sprintf('/repos/%s', trim($repoFullName)));

        return [
            'id' => (int)($repository['id'] ?? 0),
            'name' => (string)($repository['name'] ?? ''),
            'fullName' => (string)($repository['full_name'] ?? ''),
            'private' => (bool)($repository['private'] ?? false),
            'defaultBranch' => isset($repository['default_branch']) && is_string($repository['default_branch']) && $repository['default_branch'] !== ''
                ? $repository['default_branch']
                : null,
            'description' => isset($repository['description']) ? (string)$repository['description'] : null,
            'owner' => (string)($repository['owner']['login'] ?? ''),
            'htmlUrl' => (string)($repository['html_url'] ?? ''),
        ];
    }

    public function listAccountRepositories(Project $project, int $page = 1, int $perPage = 30, string $search = ''): array
    {
        $response = $this->requestWithMeta($project, 'GET', '/user/repos', [
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
        }, $response['data'])));

        if ($search !== '') {
            $normalizedSearch = strtolower($search);
            $items = array_values(array_filter($items, static fn (array $item): bool => str_contains(strtolower((string)$item['name']), $normalizedSearch)
                || str_contains(strtolower((string)$item['fullName']), $normalizedSearch)));
        }

        return [
            'items' => $items,
            'pagination' => $this->buildPagination($response['meta']['link'], $page, $perPage, count($items)),
        ];
    }

    /**
     * @return array{fullName:string,defaultBranch:string|null}
     */
    public function attachRepository(Project $project, string $fullName): array
    {
        $normalizedFullName = trim($fullName);
        if ($normalizedFullName === '') {
            throw new CrmGithubApiException('Repository full name cannot be empty.', 422);
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
        $response = $this->requestWithMeta($project, 'GET', sprintf('/repos/%s/branches', $repoFullName), [
            'query' => [
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);

        $items = array_map(static fn (array $branch): array => [
            'name' => $branch['name'] ?? '',
            'protected' => (bool)($branch['protected'] ?? false),
            'sha' => $branch['commit']['sha'] ?? null,
        ], $response['data']);

        if ($search !== '') {
            $items = array_values(array_filter($items, static fn (array $item): bool => str_contains(strtolower((string)$item['name']), strtolower($search))));
        }

        return [
            'items' => $items,
            'pagination' => $this->buildPagination($response['meta']['link'], $page, $perPage, count($items)),
        ];
    }

    public function createBranch(Project $project, string $repoFullName, string $name, ?string $sourceBranch = null): array
    {
        $normalizedRepo = trim($repoFullName);
        $normalizedName = trim($name);
        if ($normalizedRepo === '' || $normalizedName === '') {
            throw new CrmGithubApiException('Repository and branch name are required.', 422);
        }

        $repository = $this->request($project, 'GET', sprintf('/repos/%s', $normalizedRepo));
        $baseBranch = trim((string)($sourceBranch ?? ''));
        if ($baseBranch === '') {
            $baseBranch = (string)($repository['default_branch'] ?? '');
        }

        if ($baseBranch === '') {
            throw new CrmGithubApiException('Unable to resolve source branch for this repository.', 422);
        }

        $baseRef = $this->request(
            $project,
            'GET',
            sprintf('/repos/%s/git/ref/heads/%s', $normalizedRepo, $this->encodeGitRefPath($baseBranch)),
        );
        $sha = (string)($baseRef['object']['sha'] ?? '');
        if ($sha === '') {
            throw new CrmGithubApiException('Unable to resolve source branch SHA.', 422);
        }

        $createdRef = $this->request($project, 'POST', sprintf('/repos/%s/git/refs', $normalizedRepo), [
            'json' => [
                'ref' => 'refs/heads/' . $normalizedName,
                'sha' => $sha,
            ],
        ]);

        return [
            'name' => $normalizedName,
            'sha' => (string)($createdRef['object']['sha'] ?? ''),
            'ref' => (string)($createdRef['ref'] ?? ''),
            'url' => (string)($createdRef['url'] ?? ''),
        ];
    }

    public function deleteBranch(Project $project, string $repoFullName, string $name): void
    {
        $normalizedRepo = trim($repoFullName);
        $normalizedName = trim($name);
        if ($normalizedRepo === '' || $normalizedName === '') {
            throw new CrmGithubApiException('Repository and branch name are required.', 422);
        }

        $this->request(
            $project,
            'DELETE',
            sprintf('/repos/%s/git/refs/heads/%s', $normalizedRepo, $this->encodeGitRefPath($normalizedName)),
        );
    }

    public function listPullRequests(Project $project, string $repoFullName, string $state = 'open', ?string $author = null, string $search = '', int $page = 1, int $perPage = 30): array
    {
        $response = $this->requestWithMeta($project, 'GET', sprintf('/repos/%s/pulls', $repoFullName), [
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
        }, $response['data'])));

        if (is_string($author) && $author !== '') {
            $items = array_values(array_filter($items, static fn (array $item): bool => strtolower((string)$item['author']) === strtolower($author)));
        }

        if ($search !== '') {
            $items = array_values(array_filter($items, static fn (array $item): bool => str_contains(strtolower((string)$item['title']), strtolower($search))));
        }

        return [
            'items' => $items,
            'pagination' => $this->buildPagination($response['meta']['link'], $page, $perPage, count($items)),
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

    public function listIssues(Project $project, string $repoFullName, string $state = 'open', int $page = 1, int $perPage = 30): array
    {
        $response = $this->requestWithMeta($project, 'GET', sprintf('/repos/%s/issues', $repoFullName), [
            'query' => [
                'state' => $state,
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);

        $items = array_values(array_filter(array_map(static function (array $issue): ?array {
            if (array_key_exists('pull_request', $issue)) {
                return null;
            }

            return [
                'number' => (int)($issue['number'] ?? 0),
                'title' => (string)($issue['title'] ?? ''),
                'state' => (string)($issue['state'] ?? ''),
                'author' => (string)($issue['user']['login'] ?? ''),
                'comments' => (int)($issue['comments'] ?? 0),
                'htmlUrl' => (string)($issue['html_url'] ?? ''),
                'createdAt' => (string)($issue['created_at'] ?? ''),
                'updatedAt' => (string)($issue['updated_at'] ?? ''),
            ];
        }, $response['data'])));

        return [
            'items' => $items,
            'pagination' => $this->buildPagination($response['meta']['link'], $page, $perPage, count($items)),
        ];
    }

    public function listCommits(Project $project, string $repoFullName, int $page = 1, int $perPage = 30, ?string $branch = null): array
    {
        $query = [
            'page' => $page,
            'per_page' => $perPage,
        ];
        if (is_string($branch) && trim($branch) !== '') {
            $query['sha'] = trim($branch);
        }

        $response = $this->requestWithMeta($project, 'GET', sprintf('/repos/%s/commits', $repoFullName), [
            'query' => $query,
        ]);

        $items = array_values(array_map(static fn (array $commit): array => [
            'sha' => (string)($commit['sha'] ?? ''),
            'message' => (string)($commit['commit']['message'] ?? ''),
            'author' => (string)($commit['author']['login'] ?? $commit['commit']['author']['name'] ?? ''),
            'date' => (string)($commit['commit']['author']['date'] ?? ''),
            'htmlUrl' => (string)($commit['html_url'] ?? ''),
        ], $response['data']));

        return [
            'items' => $items,
            'pagination' => $this->buildPagination($response['meta']['link'], $page, $perPage, count($items)),
        ];
    }

    public function getCommit(Project $project, string $repoFullName, string $sha): array
    {
        $commit = $this->request($project, 'GET', sprintf('/repos/%s/commits/%s', $repoFullName, trim($sha)));

        return [
            'sha' => (string)($commit['sha'] ?? ''),
            'message' => (string)($commit['commit']['message'] ?? ''),
            'author' => (string)($commit['author']['login'] ?? $commit['commit']['author']['name'] ?? ''),
            'date' => (string)($commit['commit']['author']['date'] ?? ''),
            'htmlUrl' => (string)($commit['html_url'] ?? ''),
            'files' => array_values(array_map(static fn (array $file): array => [
                'filename' => (string)($file['filename'] ?? ''),
                'status' => (string)($file['status'] ?? ''),
                'additions' => (int)($file['additions'] ?? 0),
                'deletions' => (int)($file['deletions'] ?? 0),
                'changes' => (int)($file['changes'] ?? 0),
            ], is_array($commit['files'] ?? null) ? $commit['files'] : [])),
        ];
    }

    public function listCollaborators(Project $project, string $repoFullName, int $page = 1, int $perPage = 30): array
    {
        $response = $this->requestWithMeta($project, 'GET', sprintf('/repos/%s/collaborators', $repoFullName), [
            'query' => [
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);

        $items = array_values(array_map(static fn (array $collaborator): array => [
            'login' => (string)($collaborator['login'] ?? ''),
            'type' => (string)($collaborator['type'] ?? ''),
            'htmlUrl' => (string)($collaborator['html_url'] ?? ''),
            'permissions' => is_array($collaborator['permissions'] ?? null) ? $collaborator['permissions'] : [],
        ], $response['data']));

        return [
            'items' => $items,
            'pagination' => $this->buildPagination($response['meta']['link'], $page, $perPage, count($items)),
        ];
    }

    public function listWorkflows(Project $project, string $repoFullName, int $page = 1, int $perPage = 30): array
    {
        $response = $this->request($project, 'GET', sprintf('/repos/%s/actions/workflows', $repoFullName), [
            'query' => [
                'page' => $page,
                'per_page' => $perPage,
            ],
        ]);
        $workflows = is_array($response['workflows'] ?? null) ? $response['workflows'] : [];

        return [
            'items' => array_values(array_map(static fn (array $workflow): array => [
                'id' => (int)($workflow['id'] ?? 0),
                'name' => (string)($workflow['name'] ?? ''),
                'state' => (string)($workflow['state'] ?? ''),
                'path' => (string)($workflow['path'] ?? ''),
                'htmlUrl' => (string)($workflow['html_url'] ?? ''),
                'createdAt' => (string)($workflow['created_at'] ?? ''),
                'updatedAt' => (string)($workflow['updated_at'] ?? ''),
            ], $workflows)),
            'pagination' => [
                'page' => $page,
                'limit' => $perPage,
                'totalItems' => (int)($response['total_count'] ?? count($workflows)),
                'totalPages' => (int)max(1, (int)ceil(((int)($response['total_count'] ?? count($workflows))) / $perPage)),
            ],
        ];
    }

    public function listWorkflowRuns(Project $project, string $repoFullName, ?int $workflowId = null, int $page = 1, int $perPage = 30, ?string $status = null): array
    {
        $path = is_int($workflowId)
            ? sprintf('/repos/%s/actions/workflows/%d/runs', $repoFullName, $workflowId)
            : sprintf('/repos/%s/actions/runs', $repoFullName);
        $query = [
            'page' => $page,
            'per_page' => $perPage,
        ];
        if (is_string($status) && trim($status) !== '') {
            $query['status'] = trim($status);
        }

        $response = $this->request($project, 'GET', $path, [
            'query' => $query,
        ]);

        $runs = is_array($response['workflow_runs'] ?? null) ? $response['workflow_runs'] : [];

        return [
            'items' => array_values(array_map(static fn (array $run): array => [
                'id' => (int)($run['id'] ?? 0),
                'name' => (string)($run['name'] ?? ''),
                'status' => (string)($run['status'] ?? ''),
                'conclusion' => isset($run['conclusion']) ? (string)$run['conclusion'] : null,
                'event' => (string)($run['event'] ?? ''),
                'htmlUrl' => (string)($run['html_url'] ?? ''),
                'createdAt' => (string)($run['created_at'] ?? ''),
                'updatedAt' => (string)($run['updated_at'] ?? ''),
            ], $runs)),
            'pagination' => [
                'page' => $page,
                'limit' => $perPage,
                'totalItems' => (int)($response['total_count'] ?? count($runs)),
                'totalPages' => (int)max(1, (int)ceil(((int)($response['total_count'] ?? count($runs))) / $perPage)),
            ],
        ];
    }

    public function getIssue(Project $project, string $repoFullName, int $number): array
    {
        $issue = $this->request($project, 'GET', sprintf('/repos/%s/issues/%d', $repoFullName, $number));

        return [
            'number' => (int)($issue['number'] ?? 0),
            'title' => (string)($issue['title'] ?? ''),
            'state' => (string)($issue['state'] ?? ''),
            'body' => isset($issue['body']) ? (string)$issue['body'] : null,
            'author' => (string)($issue['user']['login'] ?? ''),
            'comments' => (int)($issue['comments'] ?? 0),
            'htmlUrl' => (string)($issue['html_url'] ?? ''),
            'createdAt' => (string)($issue['created_at'] ?? ''),
            'updatedAt' => (string)($issue['updated_at'] ?? ''),
            'state_reason' => isset($issue['state_reason']) ? (string)$issue['state_reason'] : null,
            'labels' => is_array($issue['labels'] ?? null) ? $issue['labels'] : [],
        ];
    }

    public function listRepositoryProjects(Project $project, string $repoFullName, int $page = 1, int $perPage = 20): array
    {
        $repository = $this->request($project, 'GET', sprintf('/repos/%s', $repoFullName));
        $owner = (string)($repository['owner']['login'] ?? '');
        $ownerType = strtolower((string)($repository['owner']['type'] ?? ''));
        $ownerField = $ownerType === 'organization' ? 'organization' : 'user';

        $graphql = $this->graphql($project, sprintf(<<<'GRAPHQL'
query($owner:String!, $perPage:Int!) {
  %s(login: $owner) {
    projectsV2(first: $perPage, orderBy: {field: UPDATED_AT, direction: DESC}) {
      nodes { id title number url closed updatedAt }
      pageInfo { hasNextPage endCursor }
      totalCount
    }
  }
}
GRAPHQL, $ownerField), [
            'owner' => $owner,
            'perPage' => $perPage,
        ]);

        $projectBlock = $graphql['data'][$ownerField]['projectsV2'] ?? null;
        $nodes = is_array($projectBlock['nodes'] ?? null) ? $projectBlock['nodes'] : [];
        $totalCount = (int)($projectBlock['totalCount'] ?? count($nodes));

        return [
            'items' => array_values(array_map(static fn (array $item): array => [
                'id' => (string)($item['id'] ?? ''),
                'title' => (string)($item['title'] ?? ''),
                'number' => (int)($item['number'] ?? 0),
                'url' => (string)($item['url'] ?? ''),
                'closed' => (bool)($item['closed'] ?? false),
                'updatedAt' => (string)($item['updatedAt'] ?? ''),
            ], $nodes)),
            'pagination' => [
                'page' => $page,
                'limit' => $perPage,
                'totalItems' => $totalCount,
                'totalPages' => (int)max(1, (int)ceil($totalCount / $perPage)),
            ],
        ];
    }

    public function getProjectItems(Project $project, string $projectId, int $page = 1, int $perPage = 20): array
    {
        $graphql = $this->graphql($project, <<<'GRAPHQL'
query($projectId:ID!, $perPage:Int!, $after:String) {
  node(id: $projectId) {
    ... on ProjectV2 {
      items(first: $perPage, after: $after) {
        nodes {
          id
          content {
            ... on Issue { id number title url state }
          }
        }
        totalCount
        pageInfo { hasNextPage endCursor }
      }
    }
  }
}
GRAPHQL, [
            'projectId' => $projectId,
            'perPage' => $perPage,
            'after' => null,
        ]);

        $itemsBlock = $graphql['data']['node']['items'] ?? [];
        $nodes = is_array($itemsBlock['nodes'] ?? null) ? $itemsBlock['nodes'] : [];
        $totalCount = (int)($itemsBlock['totalCount'] ?? count($nodes));

        return [
            'items' => array_values(array_map(static fn (array $item): array => [
                'id' => (string)($item['id'] ?? ''),
                'issue' => [
                    'id' => (string)($item['content']['id'] ?? ''),
                    'number' => (int)($item['content']['number'] ?? 0),
                    'title' => (string)($item['content']['title'] ?? ''),
                    'url' => (string)($item['content']['url'] ?? ''),
                    'state' => (string)($item['content']['state'] ?? ''),
                ],
            ], $nodes)),
            'pagination' => [
                'page' => $page,
                'limit' => $perPage,
                'totalItems' => $totalCount,
                'totalPages' => (int)max(1, (int)ceil($totalCount / $perPage)),
            ],
        ];
    }

    public function deleteRepository(Project $project, string $repoFullName): void
    {
        $this->request($project, 'DELETE', sprintf('/repos/%s', trim($repoFullName)));
    }

    public function createRepository(Project $project, string $name, ?string $description = null, bool $private = true, ?string $owner = null): array
    {
        $payload = [
            'name' => trim($name),
            'private' => $private,
        ];

        if (is_string($description) && trim($description) !== '') {
            $payload['description'] = trim($description);
        }

        $owner = trim((string)$owner);
        $path = $owner !== '' ? sprintf('/orgs/%s/repos', $owner) : '/user/repos';

        return $this->request($project, 'POST', $path, [
            'json' => $payload,
        ]);
    }

    public function createIssue(Project $project, string $repoFullName, string $title, ?string $body = null): array
    {
        $payload = [
            'title' => trim($title),
        ];
        if (is_string($body) && trim($body) !== '') {
            $payload['body'] = trim($body);
        }

        return $this->request($project, 'POST', sprintf('/repos/%s/issues', $repoFullName), [
            'json' => $payload,
        ]);
    }

    public function updateIssueState(Project $project, string $repoFullName, int $number, string $state): array
    {
        return $this->request($project, 'PATCH', sprintf('/repos/%s/issues/%d', $repoFullName, $number), [
            'json' => [
                'state' => strtolower(trim($state)),
            ],
        ]);
    }

    public function addIssueComment(Project $project, string $repoFullName, int $number, string $body): array
    {
        return $this->request($project, 'POST', sprintf('/repos/%s/issues/%d/comments', $repoFullName, $number), [
            'json' => [
                'body' => trim($body),
            ],
        ]);
    }

    public function createProjectBoard(Project $project, string $ownerLogin, string $title): array
    {
        $graphql = $this->graphql($project, <<<'GRAPHQL'
mutation($owner:String!, $title:String!) {
  createProjectV2(input:{ownerId:$owner, title:$title}) {
    projectV2 { id title number url }
  }
}
GRAPHQL, [
            'owner' => $ownerLogin,
            'title' => $title,
        ]);

        return $graphql['data']['createProjectV2']['projectV2'] ?? [];
    }

    public function moveIssueToProjectColumn(Project $project, string $projectId, string $itemId, ?string $afterItemId = null): array
    {
        $graphql = $this->graphql($project, <<<'GRAPHQL'
mutation($projectId:ID!, $itemId:ID!, $afterId:ID) {
  updateProjectV2ItemPosition(input:{projectId:$projectId, itemId:$itemId, afterId:$afterId}) {
    items { totalCount }
  }
}
GRAPHQL, [
            'projectId' => $projectId,
            'itemId' => $itemId,
            'afterId' => $afterItemId,
        ]);

        return $graphql['data']['updateProjectV2ItemPosition']['items'] ?? [];
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
            'json' => [
                'state' => 'closed',
            ],
        ]);
    }

    /**
     * @param array<string,mixed> $options
     * @return array{data:array<mixed>,meta:array<string,mixed>}
     */
    private function requestWithMeta(Project $project, string $method, string $path, array $options = []): array
    {
        $token = $project->getGithubToken();
        if (!is_string($token) || $token === '') {
            throw new CrmGithubApiException('GitHub token is not configured on this project.', 400);
        }

        try {
            $response = $this->httpClient->request($method, self::BASE_URL . $path, $options + [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $data = $response->toArray(false);

            if ($statusCode >= 400) {
                throw $this->mapToCrmException($statusCode, is_array($data) ? $data : []);
            }

            return [
                'data' => is_array($data) ? $data : [],
                'meta' => [
                    'link' => $response->getHeaders(false)['link'][0] ?? null,
                ],
            ];
        } catch (CrmGithubApiException $exception) {
            throw $exception;
        } catch (ExceptionInterface $exception) {
            throw new CrmGithubApiException('Unable to reach GitHub API.', 502, previous: $exception);
        }
    }

    /**
     * @param array<string,mixed> $options
     * @return array<mixed>
     */
    private function request(Project $project, string $method, string $path, array $options = []): array
    {
        $response = $this->requestWithMeta($project, $method, $path, $options);

        return $response['data'];
    }

    /**
     * @param array<string,mixed> $variables
     */
    private function graphql(Project $project, string $query, array $variables = []): array
    {
        $data = $this->request($project, 'POST', '/graphql', [
            'json' => [
                'query' => $query,
                'variables' => $variables,
            ],
        ]);
        if (is_array($data['errors'] ?? null) && $data['errors'] !== []) {
            throw new CrmGithubApiException('GitHub project operation failed.', 422, $data['errors']);
        }

        return $data;
    }

    /**
     * @param array<string,mixed> $payload
     */
    private function mapToCrmException(int $statusCode, array $payload): CrmGithubApiException
    {
        $message = (string)($payload['message'] ?? 'GitHub API request failed.');
        $errors = is_array($payload['errors'] ?? null) ? $payload['errors'] : [];

        return match ($statusCode) {
            401, 403 => new CrmGithubApiException('GitHub authentication failed for this project token.', 401, $errors),
            404 => new CrmGithubApiException('GitHub resource not found or inaccessible.', 404, $errors),
            422 => new CrmGithubApiException('GitHub validation failed for this request.', 422, $errors),
            default => new CrmGithubApiException($message, 502, $errors),
        };
    }

    private function buildPagination(?string $linkHeader, int $page, int $limit, int $itemsCount): array
    {
        $parsed = $this->parseLinkHeader($linkHeader);
        $lastPage = $parsed['last'] ?? null;
        $nextPage = $parsed['next'] ?? null;

        return [
            'page' => $page,
            'limit' => $limit,
            'totalItems' => $lastPage !== null ? $lastPage * $limit : $itemsCount,
            'totalPages' => $lastPage ?? ($nextPage !== null ? $page + 1 : max(1, $page)),
            'hasNextPage' => $nextPage !== null,
        ];
    }

    /**
     * @return array{next?:int,last?:int}
     */
    private function parseLinkHeader(?string $linkHeader): array
    {
        if (!is_string($linkHeader) || trim($linkHeader) === '') {
            return [];
        }

        $pages = [];
        foreach (explode(',', $linkHeader) as $linkPart) {
            if (!preg_match('/<([^>]+)>;\s*rel="([a-z]+)"/i', trim($linkPart), $matches)) {
                continue;
            }

            $url = $matches[1];
            $rel = strtolower($matches[2]);

            $query = [];
            parse_str((string)parse_url($url, PHP_URL_QUERY), $query);
            $page = $query['page'] ?? null;
            if (is_string($page)) {
                $page = (int)urldecode($page);
            }

            if (is_int($page) && $page > 0) {
                $pages[$rel] = $page;
            }
        }

        return $pages;
    }

    private function encodeGitRefPath(string $ref): string
    {
        return str_replace('%2F', '/', rawurlencode(trim($ref)));
    }
}
