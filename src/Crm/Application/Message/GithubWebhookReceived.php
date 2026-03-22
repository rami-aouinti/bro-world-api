<?php

declare(strict_types=1);

namespace App\Crm\Application\Message;

use App\General\Domain\Message\Interfaces\MessageLowInterface;

final readonly class GithubWebhookReceived implements MessageLowInterface
{
    /**
     * @param array<string,mixed> $payload
     */
    public function __construct(
        public string $webhookEventId,
        public string $deliveryId,
        public string $eventName,
        public ?string $action,
        public ?string $repositoryFullName,
        public array $payload,
        public string $checksum,
    ) {
    }
}
