<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Employee;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Infrastructure\Repository\EmployeeRepository;
use App\Role\Domain\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_ADMIN->value)]
final readonly class DeleteEmployeeController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private EmployeeRepository $employeeRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/employees/{employeeId}', methods: [Request::METHOD_DELETE])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'employeeId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Response(response: JsonResponse::HTTP_NO_CONTENT, description: 'Employee deleted.')]
    #[OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Employee not found.')]
    public function __invoke(string $applicationSlug, string $employeeId): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $employee = $this->employeeRepository->findOneScopedById($employeeId, $crm->getId());
        if ($employee === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Employee not found for this CRM scope.');
        }

        $this->entityManager->remove($employee);
        $this->entityManager->flush();

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
