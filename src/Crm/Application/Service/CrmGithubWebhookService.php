<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Application\Message\GithubWebhookReceived;
use App\Crm\Domain\Entity\CrmGithubWebhookEvent;
use App\Crm\Infrastructure\Repository\CrmGithubWebhookEventRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\MessageBusInterface;

use function hash;
use function hash_equals;
use function hash_hmac;
use function in_array;
use function is_array;
use function is_string;
use function str_starts_with;
use function strlen;
use function strtolower;
use function substr;
use function trim;

final readonly class CrmGithubWebhookService
{
    private const array ALLOWED_EVENTS = [
        'repository',
        'issues',
        'pull_request',
        'project',
        'project_card',
        'projects_v2',
        'projects_v2_item',
        'issue_comment',
    ];

    public function __construct(
        private CrmGithubWebhookEventRepository $webhookEventRepository,
        private MessageBusInterface $messageBus,
        #[Autowire('%kernel.secret%')]
        private string $githubWebhookSecret,
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function handle(array $payload, string $rawPayload, ?string $deliveryId, ?string $eventName, ?string $signature): CrmGithubWebhookEvent
    {
        $normalizedDeliveryId = trim((string)$deliveryId);
        $normalizedEventName = trim((string)$eventName);
        $normalizedSignature = trim((string)$signature);

        if ($normalizedDeliveryId === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Missing x-github-delivery header.');
        }

        if ($normalizedEventName === '' || !in_array($normalizedEventName, self::ALLOWED_EVENTS, true)) {
            throw new HttpException(JsonResponse::HTTP_ACCEPTED, 'Event ignored (not supported).');
        }

        if ($this->webhookEventRepository->findOneBy(['deliveryId' => $normalizedDeliveryId]) instanceof CrmGithubWebhookEvent) {
            throw new HttpException(JsonResponse::HTTP_ACCEPTED, 'Event already received.');
        }

        if ($this->environment === 'prod' && $normalizedSignature === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Missing x-hub-signature-256 header.');
        }

        if ($normalizedSignature !== '' && !$this->isValidSignature($rawPayload, $normalizedSignature)) {
            throw new HttpException(JsonResponse::HTTP_UNAUTHORIZED, 'Invalid GitHub webhook signature.');
        }

        $action = isset($payload['action']) && is_string($payload['action']) ? trim($payload['action']) : null;
        $repositoryFullName = null;
        if (isset($payload['repository']) && is_array($payload['repository'])) {
            $repositoryFullName = is_string($payload['repository']['full_name'] ?? null) ? trim((string)$payload['repository']['full_name']) : null;
        }

        $checksum = hash('sha256', $rawPayload);

        $event = (new CrmGithubWebhookEvent())
            ->setDeliveryId($normalizedDeliveryId)
            ->setEventName($normalizedEventName)
            ->setEventAction($action)
            ->setRepositoryFullName($repositoryFullName)
            ->setSignature($normalizedSignature !== '' ? $normalizedSignature : null)
            ->setChecksum($checksum)
            ->setStatus('queued')
            ->setPayload($payload);

        $this->webhookEventRepository->save($event, true);

        $this->messageBus->dispatch(new GithubWebhookReceived(
            $event->getId(),
            $normalizedDeliveryId,
            $normalizedEventName,
            $action,
            $repositoryFullName,
            $payload,
            $checksum,
        ));

        return $event;
    }

    private function isValidSignature(string $rawPayload, string $provided): bool
    {
        if (!str_starts_with(strtolower($provided), 'sha256=')) {
            return false;
        }

        $signature = trim(substr($provided, 7));
        if ($signature === '' || strlen($signature) !== 64) {
            return false;
        }

        $expected = hash_hmac('sha256', $rawPayload, $this->githubWebhookSecret);

        return hash_equals($expected, strtolower($signature));
    }
}
