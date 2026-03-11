<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Domain\Entity\Crm;
use App\Crm\Infrastructure\Repository\CrmRepository;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Enum\PlatformKey;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class CrmApplicationScopeResolver
{
    public function __construct(
        private CrmRepository $crmRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
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
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Unknown "applicationSlug".');
        }

        $this->assertApplicationAccess($application);

        throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'CRM root entity not found for this application.');
    }

    private function assertApplicationAccess(?Application $application): void
    {
        if (!$application instanceof Application || $application->getPlatform()?->getPlatformKey() !== PlatformKey::CRM) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Invalid "applicationSlug" for the requested platform.');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $application->getUser()?->getId() !== $user->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You cannot access this application scope.');
        }
    }
}
