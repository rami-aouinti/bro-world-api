<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Company;

use App\Crm\Application\Dto\Command\UpdateCompanyCommandDto;
use App\Crm\Application\Dto\Response\EntityIdResponseDto;
use App\Crm\Application\Exception\CrmReferenceNotFoundException;
use App\Crm\Application\Message\PutCompanyCommand;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Crm\Transport\Request\CrmRequestHandler;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class PutCompanyController
{
    public function __construct(
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private CrmRequestHandler $crmRequestHandler,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/companies/{companyId}', methods: [Request::METHOD_PUT])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'companyId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Put(
        summary: 'Put Company',
        description: 'Exécute l action metier Put Company dans le perimetre de l application CRM.',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Opération exécutée avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
    public function __invoke(string $applicationSlug, string $companyId, Request $request): JsonResponse
    {
        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, UpdateCompanyCommandDto::class, ['Default', 'put'], 'fromPutArray');
        if ($input instanceof JsonResponse) {
            return $input;
        }

        try {
            $this->messageBus->dispatch(new PutCompanyCommand(
                applicationSlug: $applicationSlug,
                companyId: $companyId,
                name: (string)$input->name,
                industry: $input->industry,
                website: $input->website,
                contactEmail: $input->contactEmail,
                phone: $input->phone,
            ));
        } catch (CrmReferenceNotFoundException $exception) {
            return $this->errorResponseFactory->notFoundReference($exception->field);
        }

        return new JsonResponse((new EntityIdResponseDto($companyId))->toArray());
    }
}
