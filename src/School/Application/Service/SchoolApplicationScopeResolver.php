<?php

declare(strict_types=1);

namespace App\School\Application\Service;

use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Enum\PlatformKey;
use App\School\Domain\Entity\School;
use App\School\Infrastructure\Repository\SchoolRepository;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class SchoolApplicationScopeResolver
{
    public function __construct(
        private SchoolRepository $schoolRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    public function resolveOrCreateSchoolByApplicationSlug(string $applicationSlug, ?User $user): School
    {
        $school = $this->schoolRepository->findOneByApplicationSlug($applicationSlug);
        if ($school instanceof School) {
            $this->assertApplicationAccess($school->getApplication(), PlatformKey::SCHOOL, $user);

            return $school;
        }

        $application = $this->entityManager->getRepository(Application::class)->findOneBy([
            'slug' => $applicationSlug,
        ]);
        if (!$application instanceof Application) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Application scope not found.');
        }

        $this->assertApplicationAccess($application, PlatformKey::SCHOOL, $user);

        throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'School scope not found.');
    }

    public function assertApplicationAccess(?Application $application, PlatformKey $platformKey, ?User $user): void
    {
        if (!$application instanceof Application || $application->getPlatform()?->getPlatformKey() !== $platformKey) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Invalid application scope for school platform.');
        }

        if (!$user instanceof User || $application->getUser()?->getId() !== $user->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Forbidden application scope access.');
        }
    }
}
