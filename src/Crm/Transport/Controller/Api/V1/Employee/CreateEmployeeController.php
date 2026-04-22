<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Employee;

use App\Crm\Application\Message\CreateEmployeeCommand;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Employee;
use App\Crm\Transport\Request\CreateEmployeeRequest;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Role\Domain\Enum\Role;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_ADMIN->value)]
final readonly class CreateEmployeeController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private ValidatorInterface $validator,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/crm/employees', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Post(
        summary: 'Create Employee',
        description: 'Exécute l action metier Create Employee dans le perimetre de l application CRM.',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_CREATED, description: 'Ressource créée avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
    public function __invoke(string $applicationSlug, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);

        try {
            $payload = json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->errorResponseFactory->invalidJson();
        }

        if (!is_array($payload)) {
            return $this->errorResponseFactory->invalidJson();
        }

        $input = CreateEmployeeRequest::fromArray($payload);
        $violations = $this->validator->validate($input);
        if ($violations->count() > 0) {
            return $this->errorResponseFactory->validationFailed($violations);
        }

        $employee = (new Employee())
            ->setFirstName((string)$input->firstName)
            ->setLastName((string)$input->lastName)
            ->setEmail($input->email)
            ->setPositionName($input->positionName)
            ->setRoleName($input->roleName);

        $this->messageBus->dispatch(new CreateEmployeeCommand(
            id: $employee->getId(),
            crmId: $crm->getId(),
            firstName: $employee->getFirstName(),
            lastName: $employee->getLastName(),
            email: $employee->getEmail(),
            positionName: $employee->getPositionName(),
            roleName: $employee->getRoleName(),
            userId: $input->userId,
            applicationSlug: $applicationSlug,
        ));

        return new JsonResponse([
            'id' => $employee->getId(),
        ], JsonResponse::HTTP_CREATED);
    }
}
