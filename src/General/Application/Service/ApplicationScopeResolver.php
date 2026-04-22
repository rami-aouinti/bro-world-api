<?php

declare(strict_types=1);

namespace App\General\Application\Service;

use App\Platform\Domain\Entity\Application;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class ApplicationScopeResolver
{
    public const string DEFAULT_APPLICATION_SLUG = 'general';
    public const string UNKNOWN_APPLICATION_SLUG_MESSAGE = 'Unknown "applicationSlug".';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public static function createInvalidApplicationSlugException(): HttpException
    {
        return new HttpException(JsonResponse::HTTP_NOT_FOUND, self::UNKNOWN_APPLICATION_SLUG_MESSAGE);
    }

    public function resolveFromRequest(Request $request): string
    {
        $resolvedSlug = $this->extractApplicationSlug($request);
        $application = $this->entityManager->getRepository(Application::class)->findOneBy([
            'slug' => $resolvedSlug,
        ]);

        if (!$application instanceof Application) {
            throw self::createInvalidApplicationSlugException();
        }

        $request->attributes->set('applicationSlug', $resolvedSlug);

        return $resolvedSlug;
    }

    private function extractApplicationSlug(Request $request): string
    {
        $applicationSlug = trim((string) ($request->query->get('applicationSlug')
            ?? $request->headers->get('X-Application-Slug')
            ?? $request->headers->get('Application-Slug')
            ?? $request->attributes->get('applicationSlug')
            ?? ''));

        if ($applicationSlug === '') {
            return self::DEFAULT_APPLICATION_SLUG;
        }

        return $applicationSlug;
    }
}
