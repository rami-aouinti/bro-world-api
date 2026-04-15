<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

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
final readonly class GetGeneralDashboardController
{
    public function __construct(
        private CompanyRepository $companyRepository,
        private ProjectRepository $projectRepository,
        private TaskRepository $taskRepository,
        private TaskRequestRepository $taskRequestRepository,
    ) {
    }

    #[Route('/v1/crm/general/dashboard', methods: [Request::METHOD_GET])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'companies' => (int)count($this->companyRepository->findAll()),
            'projects' => (int)count($this->projectRepository->findAll()),
            'tasks' => (int)count($this->taskRepository->findAll()),
            'taskRequests' => [
                TaskRequestStatus::PENDING->value => (int)count($this->taskRequestRepository->findBy(['status' => TaskRequestStatus::PENDING->value])),
                TaskRequestStatus::APPROVED->value => (int)count($this->taskRequestRepository->findBy(['status' => TaskRequestStatus::APPROVED->value])),
                TaskRequestStatus::REJECTED->value => (int)count($this->taskRequestRepository->findBy(['status' => TaskRequestStatus::REJECTED->value])),
            ],
        ]);
    }
}
