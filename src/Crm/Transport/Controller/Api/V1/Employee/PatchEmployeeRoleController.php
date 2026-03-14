<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Employee;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Infrastructure\Repository\EmployeeRepository;
use App\Crm\Transport\Request\AssignEmployeeRoleRequest;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Role\Domain\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_ADMIN->value)]
final readonly class PatchEmployeeRoleController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private EmployeeRepository $employeeRepository,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private ValidatorInterface $validator,
        private EntityManagerInterface $entityManager,
    ) {}

    #[Route('/v1/crm/applications/{applicationSlug}/employees/{employeeId}/roles', methods: [Request::METHOD_PATCH])]
    public function __invoke(string $applicationSlug, string $employeeId, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $employee = $this->employeeRepository->findOneScopedById($employeeId, $crm->getId());
        if ($employee === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Employee not found for this CRM scope.');
        }

        try {
            $payload = json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->errorResponseFactory->invalidJson();
        }

        if (!is_array($payload)) {
            return $this->errorResponseFactory->invalidJson();
        }

        $input = AssignEmployeeRoleRequest::fromArray($payload);
        $violations = $this->validator->validate($input);
        if ($violations->count() > 0) {
            return $this->errorResponseFactory->validationFailed($violations);
        }

        $employee->setRoleName($input->role);
        $this->entityManager->flush();

        return new JsonResponse(['id' => $employee->getId(), 'role' => $employee->getRoleName()]);
    }
}
