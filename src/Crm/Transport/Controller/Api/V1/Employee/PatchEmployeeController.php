<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Employee;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Infrastructure\Repository\EmployeeRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Crm\Transport\Request\CrmRequestHandler;
use App\Crm\Transport\Request\UpdateEmployeeRequest;
use App\Role\Domain\Enum\Role;
use App\User\Domain\Entity\User;
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
final readonly class PatchEmployeeController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private EmployeeRepository $employeeRepository,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private CrmRequestHandler $crmRequestHandler,
        private EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/employees/{employeeId}', methods: [Request::METHOD_PATCH])]
    #[OA\Patch(
        summary: 'Patch Employee',
        description: 'Exécute l action metier Patch Employee dans le perimetre de l application CRM.',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Opération exécutée avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'employeeId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'firstName', type: 'string', maxLength: 120, nullable: true),
                new OA\Property(property: 'lastName', type: 'string', maxLength: 120, nullable: true),
                new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                new OA\Property(property: 'positionName', type: 'string', maxLength: 120, nullable: true),
                new OA\Property(property: 'userId', type: 'string', format: 'uuid', nullable: true),
            ],
            type: 'object',
        ),
    )]
    public function __invoke(string $applicationSlug, string $employeeId, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $employee = $this->employeeRepository->findOneScopedById($employeeId, $crm->getId());
        if ($employee === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Employee not found for this CRM scope.');
        }

        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, UpdateEmployeeRequest::class, mapperMethod: 'fromPatchArray');
        if ($input instanceof JsonResponse) {
            return $input;
        }

        if ($input->hasFirstName && $input->firstName !== null) {
            $employee->setFirstName($input->firstName);
        }

        if ($input->hasLastName && $input->lastName !== null) {
            $employee->setLastName($input->lastName);
        }

        if ($input->hasEmail) {
            $employee->setEmail($input->email);
        }

        if ($input->hasPositionName) {
            $employee->setPositionName($input->positionName);
        }

        if ($input->hasUserId) {
            if ($input->userId === null || $input->userId === '') {
                $employee->setUser(null);
            } else {
                $user = $this->entityManager->getRepository(User::class)->find($input->userId);
                if (!$user instanceof User) {
                    return $this->errorResponseFactory->notFoundReference('userId');
                }

                $employee->setUser($user);
            }
        }

        $this->entityManager->flush();

        return new JsonResponse([
            'id' => $employee->getId(),
            'firstName' => $employee->getFirstName(),
            'lastName' => $employee->getLastName(),
            'email' => $employee->getEmail(),
            'positionName' => $employee->getPositionName(),
            'userId' => $employee->getUserId(),
        ]);
    }
}
