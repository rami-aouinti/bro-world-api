<?php

declare(strict_types=1);

namespace App\Tool\Transport\Command\Crm;

use App\Crm\Application\Exception\CrmGithubApiException;
use App\Crm\Application\Service\CrmGithubService;
use App\Crm\Application\Service\CrmTaskRequestGithubStatusMapper;
use App\Crm\Domain\Enum\TaskRequestStatus;
use App\Crm\Infrastructure\Repository\CrmProjectRepositoryRepository;
use App\Crm\Infrastructure\Repository\TaskRequestRepository;
use App\General\Transport\Command\Traits\SymfonyStyleTrait;
use DateTimeImmutable;
use JsonException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

use function hash;
use function json_encode;
use function sprintf;
use function strtolower;
use function trim;

#[AsCommand(
    name: self::NAME,
    description: 'Reconcile CRM repositories state with GitHub and fix drifts.',
)]
final class CrmGithubSyncCommand extends Command
{
    use SymfonyStyleTrait;

    final public const string NAME = 'crm:github:sync';

    public function __construct(
        private readonly CrmProjectRepositoryRepository $crmProjectRepositoryRepository,
        private readonly TaskRequestRepository $taskRequestRepository,
        private readonly CrmGithubService $crmGithubService,
        private readonly CrmTaskRequestGithubStatusMapper $statusMapper,
    ) {
        parent::__construct();
    }

    /**
     * @throws Throwable
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = $this->getSymfonyStyle($input, $output);

        $scanned = 0;
        $updated = 0;
        $failed = 0;
        $taskRequestsScanned = 0;
        $taskRequestsUpdated = 0;
        $taskRequestsFailed = 0;

        $repositories = $this->crmProjectRepositoryRepository->findBy([
            'provider' => 'github',
        ]);

        foreach ($repositories as $repository) {
            $scanned++;
            $project = $repository->getProject();
            if ($project === null) {
                continue;
            }

            try {
                $githubState = $this->crmGithubService->getRepository($project, $repository->getFullName());
                $checksum = hash('sha256', (string)json_encode([
                    'fullName' => (string)($githubState['fullName'] ?? ''),
                    'defaultBranch' => (string)($githubState['defaultBranch'] ?? ''),
                    'isPrivate' => (bool)($githubState['isPrivate'] ?? false),
                    'updatedAt' => (string)($githubState['updatedAt'] ?? ''),
                ], JSON_THROW_ON_ERROR));

                $payload = $repository->getPayload() ?? [];
                $knownChecksum = strtolower(trim((string)($payload['checksum'] ?? '')));
                $knownUpdatedAt = trim((string)($payload['updatedAt'] ?? ''));
                $remoteUpdatedAt = trim((string)($githubState['updatedAt'] ?? ''));

                if ($knownChecksum !== $checksum || $knownUpdatedAt !== $remoteUpdatedAt) {
                    $payload['checksum'] = $checksum;
                    $payload['updatedAt'] = $remoteUpdatedAt;
                    $payload['lastSyncSource'] = 'crm:github:sync';

                    $repository
                        ->setOwner((string)($githubState['owner'] ?? $repository->getOwner()))
                        ->setName((string)($githubState['name'] ?? $repository->getName()))
                        ->setFullName((string)($githubState['fullName'] ?? $repository->getFullName()))
                        ->setDefaultBranch(isset($githubState['defaultBranch']) ? (string)$githubState['defaultBranch'] : $repository->getDefaultBranch())
                        ->setIsPrivate((bool)($githubState['isPrivate'] ?? $repository->isPrivate()))
                        ->setHtmlUrl(isset($githubState['htmlUrl']) ? (string)$githubState['htmlUrl'] : $repository->getHtmlUrl())
                        ->setExternalId(isset($githubState['externalId']) ? (string)$githubState['externalId'] : $repository->getExternalId())
                        ->setLastSyncedAt(new DateTimeImmutable())
                        ->setSyncStatus('synced')
                        ->setPayload($payload);

                    $this->crmProjectRepositoryRepository->save($repository, true);
                    $updated++;

                    continue;
                }

                $repository
                    ->setLastSyncedAt(new DateTimeImmutable())
                    ->setSyncStatus('synced');
                $this->crmProjectRepositoryRepository->save($repository, true);
            } catch (CrmGithubApiException | JsonException) {
                $failed++;
                $repository->setSyncStatus('error');
                $this->crmProjectRepositoryRepository->save($repository, true);
            }
        }

        $taskRequests = $this->taskRequestRepository->findAllWithGithubIssueMapping();
        foreach ($taskRequests as $taskRequest) {
            $taskRequestsScanned++;
            $project = $taskRequest->getTask()?->getProject();
            $githubIssue = $taskRequest->getGithubIssue();
            if ($project === null || $githubIssue === null) {
                continue;
            }

            $repositoryFullName = $githubIssue->getRepositoryFullName();
            $issueNumber = $githubIssue->getIssueNumber();
            if ($repositoryFullName === '' || $issueNumber === null) {
                continue;
            }

            try {
                $issue = $this->crmGithubService->getIssue($project, $repositoryFullName, $issueNumber);
                $status = $this->statusMapper->resolveTaskRequestStatusFromIssuePayload([
                    'state' => (string)($issue['state'] ?? 'open'),
                    'labels' => $issue['labels'] ?? [],
                    'state_reason' => $issue['state_reason'] ?? null,
                ]);

                if ($taskRequest->getStatus() !== $status) {
                    $taskRequest->setStatus($status);
                    $taskRequest->setResolvedAt($status === TaskRequestStatus::PENDING ? null : new DateTimeImmutable());
                    $taskRequestsUpdated++;
                }

                $metadata = $githubIssue->getMetadata();
                $metadata['lastSyncSource'] = 'crm:github:sync';
                $metadata['lastSyncAt'] = (new DateTimeImmutable())->format(DATE_ATOM);
                $githubIssue
                    ->setIssueUrl(isset($issue['htmlUrl']) ? (string)$issue['htmlUrl'] : $githubIssue->getIssueUrl())
                    ->setSyncStatus('synced')
                    ->setLastSyncedAt(new DateTimeImmutable())
                    ->setMetadata($metadata);

                $this->taskRequestRepository->save($taskRequest, true);
            } catch (CrmGithubApiException) {
                $taskRequestsFailed++;
                if ($githubIssue !== null) {
                    $githubIssue->setSyncStatus('error');
                }
                $this->taskRequestRepository->save($taskRequest, true);
            }
        }

        if ($input->isInteractive()) {
            $io->success(sprintf(
                'CRM GitHub reconciliation done. repositories(scanned=%d updated=%d failed=%d) taskRequests(scanned=%d updated=%d failed=%d)',
                $scanned,
                $updated,
                $failed,
                $taskRequestsScanned,
                $taskRequestsUpdated,
                $taskRequestsFailed,
            ));
        }

        return ($failed + $taskRequestsFailed) > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
