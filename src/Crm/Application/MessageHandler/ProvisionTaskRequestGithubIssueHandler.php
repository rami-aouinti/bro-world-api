<?php

declare(strict_types=1);

namespace App\Crm\Application\MessageHandler;

use App\Crm\Application\Message\ProvisionTaskRequestGithubIssue;
use App\Crm\Application\Service\CrmGithubService;
use App\Crm\Domain\Entity\TaskRequestGithubIssue;
use App\Crm\Infrastructure\Repository\TaskRequestRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use function is_int;
use function is_string;

#[AsMessageHandler]
final readonly class ProvisionTaskRequestGithubIssueHandler
{
    public function __construct(
        private TaskRequestRepository $taskRequestRepository,
        private CrmGithubService $crmGithubService,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(ProvisionTaskRequestGithubIssue $message): void
    {
        $taskRequest = $this->taskRequestRepository->find($message->taskRequestId);
        if ($taskRequest === null) {
            return;
        }

        $project = $taskRequest->getTask()?->getProject();
        $repository = $taskRequest->getRepository();

        if ($project === null || $repository === null) {
            return;
        }

        $githubIssue = $taskRequest->getGithubIssue() ?? (new TaskRequestGithubIssue())->setTaskRequest($taskRequest);
        if ($githubIssue->getIssueNumber() !== null && $githubIssue->getSyncStatus() === 'synced') {
            return;
        }

        $githubIssue
            ->setProvider($repository->getProvider())
            ->setRepositoryFullName($repository->getFullName())
            ->setSyncStatus('pending');

        $createdIssue = $this->crmGithubService->createIssue(
            $project,
            $repository->getFullName(),
            $taskRequest->getTitle(),
            $taskRequest->getDescription(),
        );

        $issueNumber = $createdIssue['number'] ?? null;

        $githubIssue
            ->setIssueNumber(is_int($issueNumber) ? $issueNumber : null)
            ->setIssueNodeId(is_string($createdIssue['node_id'] ?? null) ? $createdIssue['node_id'] : null)
            ->setIssueUrl(is_string($createdIssue['html_url'] ?? null) ? $createdIssue['html_url'] : null)
            ->setSyncStatus('synced')
            ->setLastSyncedAt(new DateTimeImmutable())
            ->setMetadata([
                'lastProvisionedBy' => 'crm',
                'lastProvisionedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            ]);

        $this->entityManager->persist($githubIssue);
        $this->entityManager->flush();
    }
}
