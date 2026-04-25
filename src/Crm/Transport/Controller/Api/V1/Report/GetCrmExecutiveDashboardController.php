<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Report;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Application\Service\CrmExecutiveDashboardService;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_OWNER->value)]
final readonly class GetCrmExecutiveDashboardController
{
    public function __construct(
        private CrmExecutiveDashboardService $dashboardService,
        private CrmApplicationScopeResolver $scopeResolver,
    ) {
    }

    #[Route('/v1/crm/dashboard/executive', methods: [Request::METHOD_GET])]
    #[OA\Get(
        description: 'Retourne les widgets du dashboard exécutif CRM (KPIs, funnel, équipes, agenda).',
        summary: 'Consulter le dashboard exécutif CRM',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Dashboard exécutif récupéré avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Application CRM introuvable.'),
        ],
    )]
    public function __invoke(): JsonResponse
    {
        $this->scopeResolver->resolveOrFail('crm-general-core');

        return new JsonResponse($this->dashboardService->build()->toArray());
    }
}
