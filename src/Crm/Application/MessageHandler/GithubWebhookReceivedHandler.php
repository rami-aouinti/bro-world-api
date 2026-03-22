<?php

declare(strict_types=1);

namespace App\Crm\Application\MessageHandler;

use App\Crm\Application\Message\GithubWebhookReceived;
use App\Crm\Application\Service\CrmReadCacheInvalidator;
use App\Crm\Infrastructure\Repository\CrmGithubWebhookEventRepository;
use App\Crm\Infrastructure\Repository\CrmProjectRepositoryRepository;
use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use DateTimeImmutable;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

use function is_array;
use function is_string;

#[AsMessageHandler]
final readonly class GithubWebhookReceivedHandler
{
    private const string INDEX_NAME = 'crm_github_event';

    public function __construct(
        private CrmGithubWebhookEventRepository $webhookEventRepository,
        private CrmProjectRepositoryRepository $crmProjectRepositoryRepository,
        private CrmReadCacheInvalidator $crmReadCacheInvalidator,
        private ElasticsearchServiceInterface $elasticsearchService,
    ) {
    }

    public function __invoke(GithubWebhookReceived $message): void
    {
        $webhookEvent = $this->webhookEventRepository->find($message->webhookEventId);
        if ($webhookEvent === null) {
            return;
        }

        $repository = null;
        $applicationSlug = null;

        if ($message->repositoryFullName !== null && $message->repositoryFullName !== '') {
            $repository = $this->crmProjectRepositoryRepository->findOneByProviderAndFullName('github', $message->repositoryFullName);
        }

        if ($repository !== null) {
            $repositoryPayload = $repository->getPayload() ?? [];
            $repositoryPayload['lastWebhook'] = [
                'deliveryId' => $message->deliveryId,
                'event' => $message->eventName,
                'action' => $message->action,
                'checksum' => $message->checksum,
                'receivedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            ];

            if (isset($message->payload['repository']) && is_array($message->payload['repository'])) {
                $repoPayload = $message->payload['repository'];
                $repositoryPayload['updatedAt'] = is_string($repoPayload['updated_at'] ?? null) ? $repoPayload['updated_at'] : null;
            }

            $repository
                ->setLastSyncedAt(new DateTimeImmutable())
                ->setSyncStatus('synced')
                ->setPayload($repositoryPayload);

            $this->crmProjectRepositoryRepository->save($repository, true);

            $project = $repository->getProject();
            $applicationSlug = $project?->getCompany()?->getCrm()?->getApplication()?->getSlug();
            if ($applicationSlug !== null && $applicationSlug !== '') {
                $this->crmReadCacheInvalidator->invalidateProjectCaches($applicationSlug, $project?->getId());
            }
        }

        $this->elasticsearchService->index(self::INDEX_NAME, $webhookEvent->getId(), [
            'id' => $webhookEvent->getId(),
            'deliveryId' => $message->deliveryId,
            'event' => $message->eventName,
            'action' => $message->action,
            'repositoryFullName' => $message->repositoryFullName,
            'applicationSlug' => $applicationSlug,
            'checksum' => $message->checksum,
            'receivedAt' => $webhookEvent->getCreatedAt()?->format(DATE_ATOM),
            'processedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ]);

        $webhookEvent->setStatus('processed')->setProcessedAt(new DateTimeImmutable());
        $this->webhookEventRepository->save($webhookEvent, true);
    }
}
