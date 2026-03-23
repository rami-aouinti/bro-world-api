<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Domain\Entity\CrmRepository;
use App\Crm\Domain\Entity\Project;
use App\Crm\Domain\Entity\Task;
use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Domain\Entity\TaskRequestGithubIssue;
use App\Crm\Domain\Enum\ProjectStatus;
use App\Crm\Domain\Enum\TaskStatus;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Infrastructure\Repository\CrmRepository as CrmRootRepository;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Infrastructure\Repository\TaskRequestRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_filter;
use function array_key_exists;
use function array_map;
use function array_merge;
use function array_values;
use function array_walk;
use function count;
use function explode;
use function is_array;
use function is_int;
use function mb_strtolower;
use function str_contains;
use function sprintf;
use function strtolower;
use function trim;

final readonly class CrmGithubBootstrapSyncService
{
    private const string BASE_URL = 'https://api.github.com';

    public function __construct(
        private CrmRootRepository $crmRepository,
        private CompanyRepository $companyRepository,
        private ProjectRepository $projectRepository,
        private TaskRequestRepository $taskRequestRepository,
        private EntityManagerInterface $entityManager,
        private HttpClientInterface $httpClient,
        private IssueToCrmMapper $issueToCrmMapper,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function sync(
        string $applicationSlug,
        string $token,
        string $owner,
        string $issueTarget,
        bool $createPublicProject,
        bool $dryRun,
    ): array {
        $report = [
            'projects' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
            'repositories' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
            'issues' => ['created' => 0, 'updated' => 0, 'skipped' => 0],
            'errors' => [],
        ];

        $owner = trim($owner);
        if ($owner === '') {
            $report['errors'][] = 'Owner cannot be empty.';

            return $report;
        }

        $crm = $this->crmRepository->findOneByApplicationSlug($applicationSlug);
        if ($crm === null) {
            $report['errors'][] = sprintf('CRM root not found for applicationSlug "%s".', $applicationSlug);

            return $report;
        }

        $companies = $this->companyRepository->findScoped($crm->getId(), 1, 0);
        $company = $companies[0] ?? null;
        if ($company === null) {
            $report['errors'][] = sprintf('No CRM company found for applicationSlug "%s".', $applicationSlug);

            return $report;
        }

        $ownerMeta = $this->verifyTokenAndOwner($token, $owner);
        if (($ownerMeta['valid'] ?? false) !== true) {
            $report['errors'] = array_merge($report['errors'], $ownerMeta['errors'] ?? []);

            return $report;
        }

        $githubProjects = $this->listOwnerProjects($token, $owner, (string)($ownerMeta['ownerType'] ?? ''));
        $crmProjects = $this->projectRepository->findScoped($crm->getId(), 5000, 0);

        $projectByGithubIdentity = [];
        foreach ($crmProjects as $crmProject) {
            $githubIds = $crmProject->getGithubResourceIds();
            $projectKey = $this->buildProjectIdentityKey(
                (string)($githubIds['project']['externalId'] ?? ''),
                (string)($githubIds['project']['nodeId'] ?? ''),
                (string)($githubIds['project']['url'] ?? ''),
            );

            if ($projectKey !== null) {
                $projectByGithubIdentity[$projectKey] = $crmProject;
            }
        }

        foreach ($githubProjects as $githubProject) {
            $key = $this->buildProjectIdentityKey(
                (string)($githubProject['externalId'] ?? ''),
                (string)($githubProject['nodeId'] ?? ''),
                (string)($githubProject['url'] ?? ''),
            );

            if ($key === null) {
                $report['projects']['skipped']++;
                continue;
            }

            $existing = $projectByGithubIdentity[$key] ?? null;
            if (!$existing instanceof Project) {
                $created = (new Project())
                    ->setCompany($company)
                    ->setName((string)($githubProject['title'] ?? 'GitHub Project'))
                    ->setStatus(ProjectStatus::ACTIVE)
                    ->setProvisioningStatus('ready')
                    ->setGithubToken($token)
                    ->setGithubResourceIds([
                        'project' => [
                            'externalId' => (string)($githubProject['externalId'] ?? ''),
                            'nodeId' => (string)($githubProject['nodeId'] ?? ''),
                            'url' => (string)($githubProject['url'] ?? ''),
                        ],
                    ]);

                if (!$dryRun) {
                    $this->entityManager->persist($created);
                }

                $projectByGithubIdentity[$key] = $created;
                $report['projects']['created']++;
                continue;
            }

            $existing->setName((string)($githubProject['title'] ?? $existing->getName()))
                ->setStatus(ProjectStatus::ACTIVE)
                ->setProvisioningStatus('ready')
                ->setGithubToken($token)
                ->setGithubResourceIds([
                    'project' => [
                        'externalId' => (string)($githubProject['externalId'] ?? ''),
                        'nodeId' => (string)($githubProject['nodeId'] ?? ''),
                        'url' => (string)($githubProject['url'] ?? ''),
                    ],
                ]);

            if (!$dryRun) {
                $this->entityManager->persist($existing);
            }

            $report['projects']['updated']++;
        }

        $publicProject = $this->resolvePublicProject($crmProjects, $applicationSlug, $report);
        if ($report['errors'] !== []) {
            return $report;
        }

        if ($publicProject === null) {
            $publicProject = (new Project())
                ->setCompany($company)
                ->setName('Public')
                ->setCode('PUBLIC')
                ->setStatus(ProjectStatus::ACTIVE)
                ->setProvisioningStatus('ready')
                ->setGithubToken($token);

            if (!$dryRun) {
                $this->entityManager->persist($publicProject);
            }

            $report['projects']['created']++;
        }

        $repositories = $this->listOwnerRepositories($token, $owner);
        $repositoryMapByIdentity = $this->buildRepositoryMap($crmProjects);

        foreach ($repositories as $repositoryPayload) {
            $targetProject = $this->matchProjectForRepository($repositoryPayload, $projectByGithubIdentity, $publicProject);
            if (!$targetProject instanceof Project) {
                $report['repositories']['skipped']++;
                continue;
            }

            $repoIdentity = $this->buildRepositoryIdentityKey($repositoryPayload);
            $existingRepository = $repoIdentity !== null ? ($repositoryMapByIdentity[$repoIdentity] ?? null) : null;

            if (!$existingRepository instanceof CrmRepository) {
                $entity = (new CrmRepository())
                    ->setProject($targetProject)
                    ->setProvider('github')
                    ->setOwner((string)($repositoryPayload['owner'] ?? $owner))
                    ->setName((string)($repositoryPayload['name'] ?? ''))
                    ->setFullName((string)($repositoryPayload['fullName'] ?? ''))
                    ->setDefaultBranch(isset($repositoryPayload['defaultBranch']) ? (string)$repositoryPayload['defaultBranch'] : null)
                    ->setIsPrivate((bool)($repositoryPayload['private'] ?? false))
                    ->setHtmlUrl((string)($repositoryPayload['htmlUrl'] ?? ''))
                    ->setExternalId(isset($repositoryPayload['externalId']) ? (string)$repositoryPayload['externalId'] : null)
                    ->setSyncStatus('synced')
                    ->setLastSyncedAt(new DateTimeImmutable())
                    ->setPayload($this->buildRepositoryImportPayload($repositoryPayload, null));

                if (!$dryRun) {
                    $this->entityManager->persist($entity);
                }

                if ($repoIdentity !== null) {
                    $repositoryMapByIdentity[$repoIdentity] = $entity;
                }

                $report['repositories']['created']++;
                $existingRepository = $entity;
            } else {
                $existingRepository
                    ->setProject($targetProject)
                    ->setOwner((string)($repositoryPayload['owner'] ?? $owner))
                    ->setName((string)($repositoryPayload['name'] ?? $existingRepository->getName()))
                    ->setFullName((string)($repositoryPayload['fullName'] ?? $existingRepository->getFullName()))
                    ->setDefaultBranch(isset($repositoryPayload['defaultBranch']) ? (string)$repositoryPayload['defaultBranch'] : null)
                    ->setIsPrivate((bool)($repositoryPayload['private'] ?? $existingRepository->isPrivate()))
                    ->setHtmlUrl((string)($repositoryPayload['htmlUrl'] ?? $existingRepository->getHtmlUrl()))
                    ->setExternalId(isset($repositoryPayload['externalId']) ? (string)$repositoryPayload['externalId'] : $existingRepository->getExternalId())
                    ->setSyncStatus('synced')
                    ->setLastSyncedAt(new DateTimeImmutable())
                    ->setPayload($this->buildRepositoryImportPayload($repositoryPayload, $existingRepository->getPayload()));

                if (!$dryRun) {
                    $this->entityManager->persist($existingRepository);
                }

                $report['repositories']['updated']++;
            }

            foreach (['open', 'closed'] as $state) {
                $issues = $this->listRepositoryIssues($token, (string)($repositoryPayload['fullName'] ?? ''), $state);
                foreach ($issues as $issue) {
                    $issue['repositoryFullName'] = (string)($repositoryPayload['fullName'] ?? '');
                    $this->importIssueByTarget($issueTarget, $crm->getId(), $targetProject, $existingRepository, $issue, $report, $dryRun);
                }
            }
        }

        if (!$dryRun) {
            $this->entityManager->flush();
        }

        return $report;
    }

    /**
     * @return array{valid:bool,ownerType?:string,errors?:list<string>}
     */
    private function verifyTokenAndOwner(string $token, string $owner): array
    {
        try {
            $viewer = $this->githubRequest($token, 'GET', '/user');
            $ownerMeta = $this->githubRequest($token, 'GET', '/users/' . $owner);

            $viewerLogin = strtolower((string)($viewer['login'] ?? ''));
            $ownerLogin = strtolower((string)($ownerMeta['login'] ?? ''));
            $ownerType = strtolower((string)($ownerMeta['type'] ?? 'user'));

            if ($ownerLogin === '') {
                return [
                    'valid' => false,
                    'errors' => ['Unable to resolve owner login from GitHub API.'],
                ];
            }

            if ($viewerLogin !== $ownerLogin && $ownerType === 'organization') {
                $orgs = $this->githubRequest($token, 'GET', '/user/orgs');
                $authorized = false;
                foreach ($orgs as $org) {
                    if (strtolower((string)($org['login'] ?? '')) === $ownerLogin) {
                        $authorized = true;
                        break;
                    }
                }

                if (!$authorized) {
                    return [
                        'valid' => false,
                        'errors' => [sprintf('Token is not authorized for owner "%s".', $owner)],
                    ];
                }
            }

            return [
                'valid' => true,
                'ownerType' => $ownerType,
            ];
        } catch (\Throwable $exception) {
            return [
                'valid' => false,
                'errors' => [
                    sprintf('GitHub token/owner verification failed: %s', trim($exception->getMessage())),
                ],
            ];
        }
    }

    /**
     * @param array<string,mixed> $issue
     * @param array<string,mixed> $report
     */
    private function importIssueByTarget(
        string $issueTarget,
        string $crmId,
        Project $targetProject,
        CrmRepository $repository,
        array $issue,
        array &$report,
        bool $dryRun,
    ): void {
        if ($issueTarget === 'task_request') {
            $this->importIssueAsTaskRequest($crmId, $targetProject, $repository, $issue, $report, $dryRun);

            return;
        }

        $this->importIssueAsTask($crmId, $targetProject, $issue, $report, $dryRun);
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function listOwnerProjects(string $token, string $owner, string $ownerType): array
    {
        $ownerField = $ownerType === 'organization' ? 'organization' : 'user';
        $query = sprintf(<<<'GRAPHQL'
query($owner:String!, $perPage:Int!, $after:String) {
  %s(login: $owner) {
    projectsV2(first: $perPage, after: $after, orderBy: {field: UPDATED_AT, direction: DESC}) {
      nodes { id number title url }
      pageInfo { hasNextPage endCursor }
    }
  }
}
GRAPHQL, $ownerField);

        $after = null;
        $projects = [];

        do {
            $graphql = $this->githubRequest($token, 'POST', '/graphql', [
                'json' => [
                    'query' => $query,
                    'variables' => [
                        'owner' => $owner,
                        'perPage' => 50,
                        'after' => $after,
                    ],
                ],
            ]);

            $block = $graphql['data'][$ownerField]['projectsV2'] ?? [];
            $nodes = is_array($block['nodes'] ?? null) ? $block['nodes'] : [];

            foreach ($nodes as $project) {
                $projects[] = [
                    'externalId' => isset($project['number']) ? (string)$project['number'] : '',
                    'nodeId' => (string)($project['id'] ?? ''),
                    'title' => (string)($project['title'] ?? ''),
                    'url' => (string)($project['url'] ?? ''),
                ];
            }

            $pageInfo = $block['pageInfo'] ?? [];
            $hasNext = (bool)($pageInfo['hasNextPage'] ?? false);
            $after = $hasNext ? (string)($pageInfo['endCursor'] ?? '') : null;
            if ($after === '') {
                $after = null;
            }
        } while ($after !== null);

        return $projects;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function listOwnerRepositories(string $token, string $owner): array
    {
        $page = 1;
        $repositories = [];

        while (true) {
            $rows = $this->githubRequest($token, 'GET', '/users/' . $owner . '/repos', [
                'query' => [
                    'per_page' => 100,
                    'page' => $page,
                    'sort' => 'updated',
                    'direction' => 'desc',
                ],
            ]);

            if (!is_array($rows) || $rows === []) {
                break;
            }

            foreach ($rows as $repository) {
                if (!is_array($repository)) {
                    continue;
                }

                $repositories[] = [
                    'externalId' => isset($repository['id']) ? (string)$repository['id'] : null,
                    'nodeId' => (string)($repository['node_id'] ?? ''),
                    'name' => (string)($repository['name'] ?? ''),
                    'fullName' => (string)($repository['full_name'] ?? ''),
                    'defaultBranch' => (string)($repository['default_branch'] ?? ''),
                    'private' => (bool)($repository['private'] ?? false),
                    'htmlUrl' => (string)($repository['html_url'] ?? ''),
                    'owner' => (string)($repository['owner']['login'] ?? $owner),
                    'projectNodeId' => '',
                    'projectUrl' => '',
                ];
            }

            if (count($rows) < 100) {
                break;
            }

            $page++;
        }

        return $repositories;
    }

    /**
     * @return list<array<string,mixed>>
     */
    private function listRepositoryIssues(string $token, string $fullName, string $state): array
    {
        if (trim($fullName) === '') {
            return [];
        }

        $page = 1;
        $issues = [];

        while (true) {
            $rows = $this->githubRequest($token, 'GET', '/repos/' . $fullName . '/issues', [
                'query' => [
                    'state' => $state,
                    'per_page' => 100,
                    'page' => $page,
                ],
            ]);

            if (!is_array($rows) || $rows === []) {
                break;
            }

            foreach ($rows as $issue) {
                if (!is_array($issue) || array_key_exists('pull_request', $issue)) {
                    continue;
                }

                $issues[] = [
                    'number' => (int)($issue['number'] ?? 0),
                    'nodeId' => (string)($issue['node_id'] ?? ''),
                    'title' => (string)($issue['title'] ?? ''),
                    'body' => isset($issue['body']) ? (string)$issue['body'] : null,
                    'state' => (string)($issue['state'] ?? 'open'),
                    'stateReason' => isset($issue['state_reason']) ? (string)$issue['state_reason'] : null,
                    'htmlUrl' => (string)($issue['html_url'] ?? ''),
                    'labels' => $this->normalizeIssueLabels($issue['labels'] ?? []),
                ];
            }

            if (count($rows) < 100) {
                break;
            }

            $page++;
        }

        return $issues;
    }

    /**
     * @param array<string,mixed> $issue
     * @param array<string,mixed> $report
     */
    private function importIssueAsTaskRequest(
        string $crmId,
        Project $targetProject,
        CrmRepository $repository,
        array $issue,
        array &$report,
        bool $dryRun,
    ): void {
        $repositoryFullName = $repository->getFullName();
        $issueNumber = (int)($issue['number'] ?? 0);
        if ($repositoryFullName === '' || $issueNumber <= 0) {
            $report['issues']['skipped']++;

            return;
        }

        $existing = $this->taskRequestRepository->findOneByGithubIssueMapping($repositoryFullName, $issueNumber);
        $taskRequestMapping = $this->issueToCrmMapper->mapIssueToTaskRequest($issue);
        if ($existing instanceof TaskRequest) {
            $existing
                ->setTitle($taskRequestMapping['title'] !== '' ? $taskRequestMapping['title'] : $existing->getTitle())
                ->setDescription($taskRequestMapping['description'] ?? $existing->getDescription())
                ->setRepository($repository)
                ->setStatus($taskRequestMapping['status'])
                ->setResolvedAt($taskRequestMapping['resolvedAt']);

            if (!$dryRun) {
                $this->entityManager->persist($existing);
            }

            $report['issues']['updated']++;

            return;
        }

        $task = $this->findAnyTaskForProject($crmId, $targetProject);
        if (!$task instanceof Task) {
            $task = (new Task())
                ->setProject($targetProject)
                ->setTitle(sprintf('Backlog %s', $targetProject->getName()))
                ->setDescription('Auto-created by GitHub bootstrap sync.')
                ->setStatus(TaskStatus::TODO);

            if (!$dryRun) {
                $this->entityManager->persist($task);
            }
        }

        $taskRequest = (new TaskRequest())
            ->setTask($task)
            ->setRepository($repository)
            ->setTitle($taskRequestMapping['title'])
            ->setDescription($taskRequestMapping['description'])
            ->setStatus($taskRequestMapping['status'])
            ->setResolvedAt($taskRequestMapping['resolvedAt']);

        $mapping = (new TaskRequestGithubIssue())
            ->setTaskRequest($taskRequest)
            ->setProvider('github')
            ->setRepositoryFullName($repositoryFullName)
            ->setIssueNumber($issueNumber)
            ->setIssueNodeId((string)($issue['nodeId'] ?? ''))
            ->setIssueUrl((string)($issue['htmlUrl'] ?? ''))
            ->setSyncStatus('synced')
            ->setLastSyncedAt(new DateTimeImmutable())
            ->setMetadata([]);

        $taskRequest->setGithubIssue($mapping);

        if (!$dryRun) {
            $this->entityManager->persist($taskRequest);
        }

        $report['issues']['created']++;
    }

    /**
     * @param array<string,mixed> $issue
     * @param array<string,mixed> $report
     */
    private function importIssueAsTask(string $crmId, Project $targetProject, array $issue, array &$report, bool $dryRun): void
    {
        $issueNumber = (int)($issue['number'] ?? 0);
        if ($issueNumber <= 0) {
            $report['issues']['skipped']++;

            return;
        }

        $prefix = '[' . (string)($issue['repositoryFullName'] ?? '') . '#' . $issueNumber . ']';
        $mapping = $this->issueToCrmMapper->mapIssueToTask($issue);
        $qb = $this->entityManager->createQueryBuilder()
            ->select('task')
            ->from(Task::class, 'task')
            ->leftJoin('task.project', 'project')
            ->leftJoin('project.company', 'company')
            ->andWhere('company.crm = :crmId')
            ->andWhere('project.id = :projectId')
            ->andWhere('task.title LIKE :prefix')
            ->setParameter('crmId', $crmId)
            ->setParameter('projectId', $targetProject->getId())
            ->setParameter('prefix', $prefix . '%')
            ->setMaxResults(1);

        $existing = $qb->getQuery()->getOneOrNullResult();
        if ($existing instanceof Task) {
            $existing
                ->setTitle($prefix . ' ' . ($mapping['title'] !== '' ? $mapping['title'] : $existing->getTitle()))
                ->setDescription($mapping['description'] ?? $existing->getDescription())
                ->setStatus($mapping['status'])
                ->setPriority($mapping['priority'])
                ->setGithubIssue([
                    'provider' => 'github',
                    'repositoryFullName' => (string)($issue['repositoryFullName'] ?? ''),
                    'issueNumber' => $issueNumber,
                    'issueNodeId' => (string)($issue['nodeId'] ?? ''),
                    'issueUrl' => (string)($issue['htmlUrl'] ?? ''),
                    'lastSyncedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
                ]);

            if (!$dryRun) {
                $this->entityManager->persist($existing);
            }

            $report['issues']['updated']++;

            return;
        }

        $task = (new Task())
            ->setProject($targetProject)
            ->setTitle($prefix . ' ' . $mapping['title'])
            ->setDescription($mapping['description'])
            ->setStatus($mapping['status'])
            ->setPriority($mapping['priority'])
            ->setGithubIssue([
                'provider' => 'github',
                'repositoryFullName' => (string)($issue['repositoryFullName'] ?? ''),
                'issueNumber' => $issueNumber,
                'issueNodeId' => (string)($issue['nodeId'] ?? ''),
                'issueUrl' => (string)($issue['htmlUrl'] ?? ''),
                'lastSyncedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            ]);

        if (!$dryRun) {
            $this->entityManager->persist($task);
        }

        $report['issues']['created']++;
    }

    /**
     * @param list<Project> $projects
     */
    private function resolvePublicProject(array $projects, string $applicationSlug, array &$report): ?Project
    {
        $publicProject = null;
        $publicProjectCount = 0;

        foreach ($projects as $project) {
            if (mb_strtolower(trim($project->getName())) === 'public') {
                $publicProjectCount++;
                if ($publicProject === null) {
                    $publicProject = $project;
                }
            }
        }

        if ($publicProjectCount > 1) {
            $report['errors'][] = sprintf(
                'Multiple "Public" projects detected for applicationSlug "%s". Bootstrap is aborted to avoid duplicate mappings.',
                $applicationSlug,
            );
        }

        return $publicProject;
    }

    /**
     * @param list<Project> $projects
     * @return array<string,CrmRepository>
     */
    private function buildRepositoryMap(array $projects): array
    {
        $map = [];
        foreach ($projects as $project) {
            foreach ($project->getRepositories() as $repository) {
                if (!$repository instanceof CrmRepository) {
                    continue;
                }

                $key = $this->buildRepositoryIdentityKey([
                    'fullName' => $repository->getFullName(),
                    'externalId' => $repository->getExternalId(),
                ]);

                if ($key !== null) {
                    $map[$key] = $repository;
                }
            }
        }

        return $map;
    }

    /**
     * @param array<string,mixed> $repositoryPayload
     */
    private function matchProjectForRepository(
        array $repositoryPayload,
        array $projectByGithubIdentity,
        ?Project $publicProject,
    ): ?Project {
        $projectKey = $this->buildProjectIdentityKey(
            (string)($repositoryPayload['projectExternalId'] ?? ''),
            (string)($repositoryPayload['projectNodeId'] ?? ''),
            (string)($repositoryPayload['projectUrl'] ?? ''),
        );

        if ($projectKey !== null && ($projectByGithubIdentity[$projectKey] ?? null) instanceof Project) {
            return $projectByGithubIdentity[$projectKey];
        }

        $name = trim((string)($repositoryPayload['name'] ?? ''));
        if ($name !== '') {
            foreach ($projectByGithubIdentity as $project) {
                if (!$project instanceof Project) {
                    continue;
                }

                if (mb_strtolower($project->getName()) === mb_strtolower($name)) {
                    return $project;
                }
            }
        }

        if ($publicProject instanceof Project) {
            return $publicProject;
        }

        return null;
    }

    /**
     * @param array<string,mixed> $repositoryPayload
     * @param array<string,mixed>|null $existingPayload
     * @return array<string,mixed>
     */
    private function buildRepositoryImportPayload(array $repositoryPayload, ?array $existingPayload): array
    {
        $payload = is_array($existingPayload) ? $existingPayload : [];
        $payload['nodeId'] = (string)($repositoryPayload['nodeId'] ?? '');
        $payload['projectNodeId'] = (string)($repositoryPayload['projectNodeId'] ?? '');
        $payload['projectUrl'] = (string)($repositoryPayload['projectUrl'] ?? '');
        $payload['importSource'] = 'github-bootstrap';
        $payload['importedAt'] = (new DateTimeImmutable())->format(DATE_ATOM);

        return $payload;
    }

    /**
     * @param array<string,mixed> $repositoryPayload
     */
    private function buildRepositoryIdentityKey(array $repositoryPayload): ?string
    {
        $fullName = mb_strtolower(trim((string)($repositoryPayload['fullName'] ?? '')));
        $externalId = trim((string)($repositoryPayload['externalId'] ?? ''));
        if ($fullName !== '' && $externalId !== '') {
            return 'full_name_external:' . $fullName . ':' . $externalId;
        }

        if ($externalId !== '') {
            return 'external:' . $externalId;
        }

        if ($fullName !== '') {
            return 'full_name:' . $fullName;
        }

        return null;
    }

    private function buildProjectIdentityKey(string $externalId, string $nodeId, string $url): ?string
    {
        $externalId = trim($externalId);
        if ($externalId !== '') {
            return 'external:' . $externalId;
        }

        $nodeId = trim($nodeId);
        if ($nodeId !== '') {
            return 'node:' . $nodeId;
        }

        $url = trim($url);
        if ($url !== '') {
            return 'url:' . strtolower($url);
        }

        return null;
    }

    /**
     * @param mixed $labels
     * @return list<array{name:string}>
     */
    private function normalizeIssueLabels(mixed $labels): array
    {
        if (!is_array($labels)) {
            return [];
        }

        $normalized = [];
        foreach ($labels as $label) {
            if (is_array($label)) {
                $name = trim((string)($label['name'] ?? ''));
                if ($name !== '') {
                    $normalized[] = ['name' => $name];
                }

                continue;
            }

            $raw = trim((string)$label);
            if ($raw === '') {
                continue;
            }

            if (str_contains($raw, ',')) {
                $parts = array_filter(array_map(static fn (string $part): string => trim($part), explode(',', $raw)));
                array_walk($parts, static function (string $part) use (&$normalized): void {
                    $normalized[] = ['name' => $part];
                });
                continue;
            }

            $normalized[] = ['name' => $raw];
        }

        return $normalized;
    }

    private function findAnyTaskForProject(string $crmId, Project $project): ?Task
    {
        $task = $this->entityManager->createQueryBuilder()
            ->select('task')
            ->from(Task::class, 'task')
            ->leftJoin('task.project', 'project')
            ->leftJoin('project.company', 'company')
            ->andWhere('company.crm = :crmId')
            ->andWhere('project.id = :projectId')
            ->setParameter('crmId', $crmId)
            ->setParameter('projectId', $project->getId())
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $task instanceof Task ? $task : null;
    }

    /**
     * @param array<string,mixed> $options
     * @return array<mixed>
     */
    private function githubRequest(string $token, string $method, string $path, array $options = []): array
    {
        try {
            $response = $this->httpClient->request($method, self::BASE_URL . $path, $options + [
                'headers' => [
                    'Authorization' => 'Bearer ' . trim($token),
                    'Accept' => 'application/vnd.github+json',
                    'X-GitHub-Api-Version' => '2022-11-28',
                ],
            ]);

            $status = $response->getStatusCode();
            $data = $response->toArray(false);

            if ($status >= 400) {
                $message = is_array($data) ? (string)($data['message'] ?? 'GitHub API request failed.') : 'GitHub API request failed.';
                throw new \RuntimeException($message);
            }

            if (!is_array($data)) {
                return [];
            }

            if ($this->isList($data)) {
                return array_values(array_filter(array_map(static fn ($item): ?array => is_array($item) ? $item : null, $data)));
            }

            return $data;
        } catch (ExceptionInterface $exception) {
            throw new \RuntimeException('GitHub API connection failed: ' . trim($exception->getMessage()), previous: $exception);
        }
    }

    /**
     * @param array<mixed> $data
     */
    private function isList(array $data): bool
    {
        if ($data === []) {
            return true;
        }

        $index = 0;
        foreach ($data as $key => $_value) {
            if (!is_int($key) || $key !== $index) {
                return false;
            }

            $index++;
        }

        return true;
    }
}
