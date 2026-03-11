<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1;

use App\Crm\Domain\Enum\TaskRequestStatus;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\Crm\Infrastructure\Repository\TaskRequestRepository;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class GetCrmDashboardController
{
    public function __construct(
        private CompanyRepository $companyRepository,
        private ProjectRepository $projectRepository,
        private TaskRepository $taskRepository,
        private TaskRequestRepository $taskRequestRepository,
    ) {
    }

    #[Route('/v1/crm/dashboard', methods: [Request::METHOD_GET])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'companies' => $this->companyRepository->count([]),
            'projects' => $this->projectRepository->count([]),
            'tasks' => $this->taskRepository->count([]),
            'taskRequests' => [
                TaskRequestStatus::PENDING->value => $this->taskRequestRepository->count(['status' => TaskRequestStatus::PENDING]),
                TaskRequestStatus::APPROVED->value => $this->taskRequestRepository->count(['status' => TaskRequestStatus::APPROVED]),
                TaskRequestStatus::REJECTED->value => $this->taskRequestRepository->count(['status' => TaskRequestStatus::REJECTED]),
            ],
        ]);
    }
}
