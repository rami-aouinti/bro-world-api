<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Company;

use App\Crm\Application\Exception\CrmReferenceNotFoundException;
use App\Crm\Application\Message\DeleteCompanyCommand;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class DeleteCompanyController
{
    public function __construct(
        private MessageBusInterface $messageBus,
        private CrmApiErrorResponseFactory $errorResponseFactory,
    ) {
    }

    /**
     * @throws ExceptionInterface
     */
    #[Route('/v1/crm/companies/{company}', methods: [Request::METHOD_DELETE])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'company', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Delete(
        description: 'Exécute l action metier Delete Company dans le perimetre de l application CRM.',
        summary: 'Delete Company',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_NO_CONTENT, description: 'Ressource supprimée avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
    public function __invoke(string $applicationSlug, string $company): JsonResponse
    {
        try {
            $this->messageBus->dispatch(new DeleteCompanyCommand(
                applicationSlug: $applicationSlug,
                companyId: $company,
            ));
        } catch (CrmReferenceNotFoundException $exception) {
            return $this->errorResponseFactory->notFoundReference($exception->field);
        }

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }
}
