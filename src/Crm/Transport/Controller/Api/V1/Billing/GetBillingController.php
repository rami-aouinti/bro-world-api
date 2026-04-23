<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Billing;

use App\Crm\Application\Service\BillingReadService;
use App\Crm\Domain\Entity\Billing;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class GetBillingController
{
    public function __construct(
        private BillingReadService $billingReadService
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    #[Route('/v1/crm/billings/{billing}', methods: [Request::METHOD_GET])]
        #[OA\Parameter(name: 'billing', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Get(
        description: 'Exécute l action metier Get Billing dans le perimetre de l application CRM.',
        summary: 'Get Billing',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Opération exécutée avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
    public function __invoke(string $applicationSlug, Billing $billing): JsonResponse
    {
        $payload = $this->billingReadService->getDetail($applicationSlug, $billing->getId());
        if ($payload === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Billing not found for this CRM scope.');
        }

        return new JsonResponse($payload);
    }
}
