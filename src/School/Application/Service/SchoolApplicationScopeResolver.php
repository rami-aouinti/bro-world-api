<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Enum\PlatformKey;
use App\School\Domain\Entity\School;
use App\School\Infrastructure\Repository\SchoolRepository;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class SchoolApplicationScopeResolver
{
    public function __construct(
        private SchoolRepository $schoolRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function resolveOrCreateSchoolByApplicationSlug(string $applicationSlug): School
    {
        $school = $this->schoolRepository->findOneByApplicationSlug($applicationSlug);
        if ($school instanceof School) {
            $this->assertApplicationAccess($school->getApplication(), PlatformKey::SCHOOL);

            return $school;
        }

        $application = $this->entityManager->getRepository(Application::class)->findOneBy([
            'slug' => $applicationSlug,
        ]);
        if (!$application instanceof Application) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Unknown "applicationSlug".');
        }

        $this->assertApplicationAccess($application, PlatformKey::SCHOOL);

        throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'School root entity not found for this application.');
    }

    public function assertApplicationAccess(?Application $application, PlatformKey $platformKey): void
    {
        if (!$application instanceof Application || $application->getPlatform()?->getPlatformKey() !== $platformKey) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Invalid "applicationSlug" for the requested platform.');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $application->getUser()?->getId() !== $user->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You cannot access this application scope.');
        }
    }
}
