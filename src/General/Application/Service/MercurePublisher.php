<?php

declare(strict_types=1);

namespace App\General\Application\Service;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final readonly class MercurePublisher
{
    public function __construct(
        private HubInterface $hub,
    ) {
    }

    public function publish(string $topic, array $data, bool $private = true): void
    {
        $update = new Update(
            $topic,
            json_encode($data, JSON_THROW_ON_ERROR),
            private: false
        );

        $this->hub->publish($update);
    }
}
