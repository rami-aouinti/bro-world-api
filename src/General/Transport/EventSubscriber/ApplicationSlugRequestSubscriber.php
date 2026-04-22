<?php

declare(strict_types=1);

namespace App\General\Transport\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApplicationSlugRequestSubscriber implements EventSubscriberInterface
{
    private const DEFAULT_APPLICATION_SLUG = 'general';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 64],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (!str_starts_with($path, '/v1/')) {
            return;
        }

        $applicationSlug = trim((string) ($request->attributes->get('applicationSlug')
            ?? $request->query->get('applicationSlug')
            ?? $request->headers->get('X-Application-Slug')
            ?? $request->headers->get('Application-Slug')
            ?? ''));

        if ($applicationSlug == '') {
            $applicationSlug = self::DEFAULT_APPLICATION_SLUG;
        }

        $request->attributes->set('applicationSlug', $applicationSlug);
    }
}
