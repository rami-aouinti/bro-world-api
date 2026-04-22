<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Domain\Entity\Crm;
use App\Crm\Infrastructure\Repository\CrmRepository;
use App\General\Application\Service\ApplicationScopeResolver;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Enum\PlatformKey;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class CrmApplicationScopeResolver
{
    public function __construct(
        private CrmRepository $crmRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function resolveOrFail(string $applicationSlug): Crm
    {
        $crm = $this->crmRepository->findOneByApplicationSlug($applicationSlug);
        if ($crm instanceof Crm) {
            $this->assertApplicationAccess($crm->getApplication());

            return $crm;
        }

        $application = $this->entityManager->getRepository(Application::class)->findOneBy([
            'slug' => $applicationSlug,
        ]);
        if (!$application instanceof Application) {
            throw ApplicationScopeResolver::createInvalidApplicationSlugException();
        }

        $this->assertApplicationAccess($application);

        throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'CRM root entity not found for this application.');
    }

    private function assertApplicationAccess(?Application $application): void
    {
        if (!$application instanceof Application || $application->getPlatform()?->getPlatformKey() !== PlatformKey::CRM) {
            throw ApplicationScopeResolver::createInvalidApplicationSlugException();
        }
    }
}
