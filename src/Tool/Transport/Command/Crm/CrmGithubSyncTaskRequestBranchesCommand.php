<?php

declare(strict_types=1);

namespace App\Tool\Transport\Command\Crm;

use App\Crm\Application\Exception\CrmGithubApiException;
use App\Crm\Application\Service\CrmGithubService;
use App\Crm\Application\Service\CrmReadCacheInvalidator;
use App\Crm\Domain\Entity\Project;
use App\Crm\Domain\Entity\TaskRequestGithubBranch;
use App\Crm\Infrastructure\Repository\TaskRequestGithubBranchRepository;
use App\General\Transport\Command\Traits\SymfonyStyleTrait;
use DateTimeImmutable;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

use function array_key_exists;
use function sprintf;
use function strtolower;
use function trim;

#[AsCommand(
    name: self::NAME,
    description: 'Recalculate CRM TaskRequest GitHub branches state to recover from missed webhooks.',
)]
final class CrmGithubSyncTaskRequestBranchesCommand extends Command
{
    use SymfonyStyleTrait;

    final public const string NAME = 'crm:github:sync-taskrequest-branches';

    public function __construct(
        private readonly TaskRequestGithubBranchRepository $taskRequestGithubBranchRepository,
        private readonly CrmGithubService $crmGithubService,
        private readonly CrmReadCacheInvalidator $cacheInvalidator,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getSymfonyStyle($input, $output);

        $scanned = 0;
        $updated = 0;
        $failed = 0;

        $branches = $this->taskRequestGithubBranchRepository->findAllWithProjectContext();
        $invalidatedTaskRequests = [];

        foreach ($this->groupBranchesByProjectAndRepository($branches) as $group) {
            $project = $group['project'];
            $repositoryFullName = $group['repositoryFullName'];
            $groupBranches = $group['branches'];

            try {
                $remoteBranchNames = $this->fetchRemoteBranchNames($project, $repositoryFullName);
                foreach ($groupBranches as $branch) {
                    $scanned++;
                    $remoteExists = array_key_exists(strtolower($branch->getBranchName()), $remoteBranchNames);
                    $nextStatus = $remoteExists ? 'synced' : 'deleted';
                    $metadata = $branch->getMetadata();
                    $metadata['lastInboundWebhook'] = [
                        'event' => 'reconcile',
                        'action' => 'sync-taskrequest-branches',
                        'status' => $nextStatus,
                        'receivedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
                        'source' => self::NAME,
                    ];

                    if ($branch->getSyncStatus() !== $nextStatus) {
                        $updated++;
                    }

                    $branch
                        ->setSyncStatus($nextStatus)
                        ->setLastSyncedAt(new DateTimeImmutable())
                        ->setMetadata($metadata);

                    if (!$remoteExists) {
                        $branch->setBranchSha(null);
                    }

                    $this->taskRequestGithubBranchRepository->save($branch, true);

                    $taskRequest = $branch->getTaskRequest();
                    $applicationSlug = $taskRequest?->getTask()?->getProject()?->getCompany()?->getCrm()?->getApplication()?->getSlug();
                    $taskRequestId = $taskRequest?->getId();
                    if ($applicationSlug === null || $applicationSlug === '' || $taskRequestId === null || $taskRequestId === '') {
                        continue;
                    }

                    $cacheKey = $applicationSlug . ':' . $taskRequestId;
                    if (isset($invalidatedTaskRequests[$cacheKey])) {
                        continue;
                    }

                    $this->cacheInvalidator->invalidateTaskRequest($applicationSlug, $taskRequestId);
                    $invalidatedTaskRequests[$cacheKey] = true;
                }
            } catch (CrmGithubApiException) {
                foreach ($groupBranches as $branch) {
                    $scanned++;
                    $failed++;
                    $branch->setSyncStatus('error')->setLastSyncedAt(new DateTimeImmutable());
                    $this->taskRequestGithubBranchRepository->save($branch, true);

                    $taskRequest = $branch->getTaskRequest();
                    $applicationSlug = $taskRequest?->getTask()?->getProject()?->getCompany()?->getCrm()?->getApplication()?->getSlug();
                    $taskRequestId = $taskRequest?->getId();
                    if ($applicationSlug === null || $applicationSlug === '' || $taskRequestId === null || $taskRequestId === '') {
                        continue;
                    }

                    $cacheKey = $applicationSlug . ':' . $taskRequestId;
                    if (isset($invalidatedTaskRequests[$cacheKey])) {
                        continue;
                    }

                    $this->cacheInvalidator->invalidateTaskRequest($applicationSlug, $taskRequestId);
                    $invalidatedTaskRequests[$cacheKey] = true;
                }
            }
        }

        if ($input->isInteractive()) {
            $io->success(sprintf(
                'CRM TaskRequest branch reconciliation done. scanned=%d updated=%d failed=%d',
                $scanned,
                $updated,
                $failed,
            ));
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    /**
     * @param list<TaskRequestGithubBranch> $branches
     *
     * @return list<array{project: Project, repositoryFullName: string, branches: list<TaskRequestGithubBranch>}>
     */
    private function groupBranchesByProjectAndRepository(array $branches): array
    {
        $groups = [];

        foreach ($branches as $branch) {
            $taskRequest = $branch->getTaskRequest();
            $project = $taskRequest?->getTask()?->getProject();
            $repositoryFullName = trim($branch->getRepositoryFullName());
            if ($project === null || $repositoryFullName === '') {
                continue;
            }

            $groupKey = $project->getId() . ':' . strtolower($repositoryFullName);
            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'project' => $project,
                    'repositoryFullName' => $repositoryFullName,
                    'branches' => [],
                ];
            }

            $groups[$groupKey]['branches'][] = $branch;
        }

        return array_values($groups);
    }

    /**
     * @return array<string,true>
     */
    private function fetchRemoteBranchNames(Project $project, string $repositoryFullName): array
    {
        $page = 1;
        $perPage = 100;
        $names = [];

        do {
            $response = $this->crmGithubService->listBranches($project, $repositoryFullName, $page, $perPage);
            foreach ($response['items'] ?? [] as $item) {
                $name = trim((string)($item['name'] ?? ''));
                if ($name === '') {
                    continue;
                }

                $names[strtolower($name)] = true;
            }

            $hasNextPage = (bool)($response['pagination']['hasNextPage'] ?? false);
            $page++;
        } while ($hasNextPage === true);

        return $names;
    }
}
