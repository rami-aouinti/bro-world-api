<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Application\Exception\CrmGithubApiException;
use App\Crm\Domain\Entity\Project;
use DateTimeImmutable;

use function explode;
use function is_array;
use function is_numeric;
use function is_string;
use function str_contains;
use function preg_replace;
use function strtolower;
use function trim;

final readonly class ProjectGithubProvisioningService
{
    private const string DEFAULT_REPOSITORY_OWNER = 'rami-aouinti';

    public function __construct(private CrmGithubService $crmGithubService)
    {
    }

    /**
     * @return array{provisioningStatus:string,githubResourceIds:array<string,mixed>}
     */
    public function provision(Project $project, string $repositoryName): array
    {
        $provisionedRepository = null;
        $normalizedRepositoryName = $this->normalizeRepositoryName($repositoryName);

        try {
            $repository = $this->crmGithubService->createRepository(
                $project,
                $normalizedRepositoryName,
                $project->getDescription(),
                true,
                self::DEFAULT_REPOSITORY_OWNER,
            );
            $provisionedRepository = $repository;

            $board = $this->crmGithubService->createProjectBoard($project, (string)($repository['owner']['node_id'] ?? ''), $project->getName());

            $repositoryRecord = [
                'provider' => 'github',
                'owner' => (string)($repository['owner']['login'] ?? ''),
                'name' => (string)($repository['name'] ?? $normalizedRepositoryName),
                'fullName' => (string)($repository['full_name'] ?? ''),
                'defaultBranch' => is_string($repository['default_branch'] ?? null) ? $repository['default_branch'] : null,
                'isPrivate' => (bool)($repository['private'] ?? true),
                'htmlUrl' => is_string($repository['html_url'] ?? null) ? $repository['html_url'] : null,
                'externalId' => is_numeric($repository['id'] ?? null) ? (string)$repository['id'] : null,
                'syncStatus' => 'synced',
                'payload' => [
                    'nodeId' => is_string($repository['node_id'] ?? null) ? $repository['node_id'] : null,
                    'url' => is_string($repository['url'] ?? null) ? $repository['url'] : null,
                    'projectBoard' => [
                        'id' => (string)($board['id'] ?? ''),
                        'url' => (string)($board['url'] ?? ''),
                    ],
                ],
            ];

            $project->setGithubRepositories([$repositoryRecord]);
            $project->setGithubResourceIds($this->buildGithubResourceIds($repository, is_array($board) ? $board : []));
            $project->setProvisioningStatus('provisioned');

            foreach ($project->getRepositories() as $storedRepository) {
                $storedRepository->setLastSyncedAt(new DateTimeImmutable());
            }

            return [
                'provisioningStatus' => $project->getProvisioningStatus(),
                'githubResourceIds' => $project->getGithubResourceIds(),
            ];
        } catch (CrmGithubApiException $exception) {
            $this->rollbackProvisioning($project, $provisionedRepository);

            return [
                'provisioningStatus' => $project->getProvisioningStatus(),
                'githubResourceIds' => $project->getGithubResourceIds(),
            ];
        }
    }

    /**
     * @param array<string,mixed>|null $provisionedRepository
     */
    private function rollbackProvisioning(Project $project, ?array $provisionedRepository): void
    {
        if (is_array($provisionedRepository) && is_string($provisionedRepository['full_name'] ?? null) && $provisionedRepository['full_name'] !== '') {
            try {
                $this->crmGithubService->deleteRepository($project, (string)$provisionedRepository['full_name']);
            } catch (CrmGithubApiException) {
                // Best-effort compensating action.
            }
        }

        $project->setGithubRepositories([]);
        $project->setProvisioningStatus('failed');
        $project->setGithubResourceIds([]);
    }

    /**
     * @param array<string,mixed> $repository
     * @param array<string,mixed> $board
     * @return array<string,mixed>
     */
    private function buildGithubResourceIds(array $repository, array $board): array
    {
        return [
            'repository' => [
                'externalId' => is_numeric($repository['id'] ?? null) ? (string)$repository['id'] : null,
                'nodeId' => is_string($repository['node_id'] ?? null) ? $repository['node_id'] : null,
                'url' => is_string($repository['html_url'] ?? null) ? $repository['html_url'] : null,
            ],
            'project' => [
                'externalId' => $this->extractProjectExternalId((string)($board['url'] ?? '')),
                'nodeId' => (string)($board['id'] ?? ''),
                'url' => (string)($board['url'] ?? ''),
            ],
        ];
    }

    private function normalizeRepositoryName(string $repositoryName): string
    {
        $normalized = strtolower(trim((string)preg_replace('/[^a-z0-9._-]+/i', '-', $repositoryName), '-'));

        return $normalized !== '' ? $normalized : 'project';
    }

    private function extractProjectExternalId(string $boardUrl): ?string
    {
        if (!str_contains($boardUrl, '/projects/')) {
            return null;
        }

        $parts = explode('/projects/', trim($boardUrl, '/'));
        if (!isset($parts[1])) {
            return null;
        }

        $id = trim($parts[1]);

        return $id !== '' ? $id : null;
    }
}
