<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Employee;

use App\Crm\Domain\Entity\Employee;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class GetEmployeeController
{
    #[Route('/v1/crm/applications/{applicationSlug}/employees/{employee}', methods: [Request::METHOD_GET])]
    public function __invoke(string $applicationSlug, Employee $employee): JsonResponse
    {
        return new JsonResponse([
            'id' => $employee->getId(),
            'firstName' => $employee->getFirstName(),
            'lastName' => $employee->getLastName(),
            'email' => $employee->getEmail(),
            'userId' => $employee->getUserId(),
            'positionName' => $employee->getPositionName(),
            'photo' => $employee->getUser()->getPhoto(),
            'roleName' => $employee->getRoleName(),
            'createdAt' => $employee->getCreatedAt()->format(DATE_ATOM),
            'updatedAt' => $employee->getUpdatedAt()->format(DATE_ATOM),
        ]);
    }
}
