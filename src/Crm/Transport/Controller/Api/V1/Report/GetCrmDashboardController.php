<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Report;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Enum\TaskRequestStatus;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\Crm\Infrastructure\Repository\TaskRequestRepository;
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
final readonly class GetCrmDashboardController
{
    public function __construct(
        private CompanyRepository $companyRepository,
        private ProjectRepository $projectRepository,
        private TaskRepository $taskRepository,
        private TaskRequestRepository $taskRequestRepository,
        private CrmApplicationScopeResolver $scopeResolver,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/dashboard', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Get(
        summary: 'Consulter le tableau de bord CRM',
        description: 'Retourne les indicateurs consolidés (entreprises, projets, tâches et demandes) pour une application CRM.',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Tableau de bord récupéré avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Application CRM introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
    public function __invoke(string $applicationSlug): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);

        return new JsonResponse([
            'companies' => $this->companyRepository->countCompaniesByCrm($crm->getId()),
            'projects' => $this->projectRepository->countProjectsByCrm($crm->getId()),
            'tasks' => $this->taskRepository->countTasksByCrm($crm->getId()),
            'taskRequests' => [
                TaskRequestStatus::PENDING->value => $this->taskRequestRepository->countTaskRequestsByCrmAndStatus($crm->getId(), TaskRequestStatus::PENDING->value),
                TaskRequestStatus::APPROVED->value => $this->taskRequestRepository->countTaskRequestsByCrmAndStatus($crm->getId(), TaskRequestStatus::APPROVED->value),
                TaskRequestStatus::REJECTED->value => $this->taskRequestRepository->countTaskRequestsByCrmAndStatus($crm->getId(), TaskRequestStatus::REJECTED->value),
            ],
        ]);
    }
}
