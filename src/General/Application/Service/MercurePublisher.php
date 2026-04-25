<?php

declare(strict_types=1);

namespace App\General\Application\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Exception\RuntimeException;
use Symfony\Component\Mercure\Update;

final readonly class MercurePublisher
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
    ) {
    }

    public function publish(string $topic, array $data, bool $private = true): void
    {
        $update = new Update(
            $topic,
            json_encode($data, JSON_THROW_ON_ERROR),
            private: false
        );

        try {
            $this->hub->publish($update);
        } catch (RuntimeException $exception) {
            $this->logger->error('Mercure publish failed.', [
                'topic' => $topic,
                'private' => $private,
                'exception' => $exception->getMessage(),
            ]);
        }
    }
}
