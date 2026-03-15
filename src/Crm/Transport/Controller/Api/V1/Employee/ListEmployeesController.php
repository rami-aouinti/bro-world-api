<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Employee;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Employee;
use App\Crm\Infrastructure\Repository\EmployeeRepository;
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
final readonly class ListEmployeesController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private EmployeeRepository $employeeRepository,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/employees', methods: [Request::METHOD_GET])]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $page = max(1, $request->query->getInt('page', 1));
        $limit = max(1, min(100, $request->query->getInt('limit', 20)));

        $items = array_map(
            static fn (Employee $employee): array => [
                'id' => $employee->getId(),
                'firstName' => $employee->getFirstName(),
                'lastName' => $employee->getLastName(),
                'email' => $employee->getEmail(),
                'positionName' => $employee->getPositionName(),
                'roleName' => $employee->getRoleName(),
                'createdAt' => $employee->getCreatedAt()->format(DATE_ATOM),
                'updatedAt' => $employee->getUpdatedAt()->format(DATE_ATOM),
            ],
            $this->employeeRepository->findScoped($crm->getId(), $limit, ($page - 1) * $limit)
        );
        $totalItems = $this->employeeRepository->countByCrm($crm->getId());

        return new JsonResponse([
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'totalItems' => $totalItems,
                'totalPages' => $totalItems > 0 ? (int)ceil($totalItems / $limit) : 0,
            ],
        ]);
    }
}
