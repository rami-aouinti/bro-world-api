<?php

declare(strict_types=1);

namespace App\General\Application\Service;

use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class MessageIdempotenceGuard
{
    public function __construct(
        private readonly CacheInterface $cache
    ) {
    }

    public function shouldProcess(string $eventId): bool
    {
        $isFirstHandling = false;

        $this->cache->get('message_handled_' . $eventId, static function (ItemInterface $item) use (&$isFirstHandling): bool {
            $isFirstHandling = true;
            $item->expiresAfter(86400);

            return true;
        });

        return $isFirstHandling;
    }
}
