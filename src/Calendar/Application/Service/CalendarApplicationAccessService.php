<?php

declare(strict_types=1);

namespace App\Calendar\Application\Service;

use App\Crm\Infrastructure\Repository\EmployeeRepository;
use App\Platform\Domain\Entity\Application;
use App\Platform\Infrastructure\Repository\ApplicationRepository;
use App\User\Domain\Entity\User;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class CalendarApplicationAccessService
{
    public function __construct(
        private ApplicationRepository $applicationRepository,
        private EmployeeRepository $employeeRepository,
    ) {
    }

    public function requireOwner(string $applicationSlug, User $user): Application
    {
        $application = $this->findApplication($applicationSlug);
        if ($application->getUser()?->getId() !== $user->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Only the application owner can access this endpoint.');
        }

        return $application;
    }

    public function requireEmployee(string $applicationSlug, User $user): Application
    {
        $application = $this->findApplication($applicationSlug);
        $isEmployee = $this->employeeRepository->existsByApplicationSlugAndUser($applicationSlug, $user);

        if (!$isEmployee) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'Only CRM employees of this application can access this endpoint.');
        }

        return $application;
    }

    private function findApplication(string $applicationSlug): Application
    {
        $application = $this->applicationRepository->findOneBy([
            'slug' => $applicationSlug,
        ]);

        if (!$application instanceof Application) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Unknown application scope.');
        }

        return $application;
    }
}
