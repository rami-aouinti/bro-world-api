<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1;

use App\Crm\Application\Service\TaskListService;
use App\Crm\Domain\Entity\Company;
use App\Crm\Domain\Entity\Project;
use App\Crm\Domain\Entity\Sprint;
use App\Crm\Domain\Entity\Task;
use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Domain\Entity\Crm;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Infrastructure\Repository\CrmRepository;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Infrastructure\Repository\SprintRepository;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\Crm\Infrastructure\Repository\TaskRequestRepository;
use App\General\Application\Message\EntityCreated;
use App\General\Application\Message\EntityDeleted;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Enum\PlatformKey;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class CrmController
{
    public function __construct(
        private CompanyRepository $companyRepository,
        private ProjectRepository $projectRepository,
        private TaskRepository $taskRepository,
        private TaskRequestRepository $taskRequestRepository,
        private SprintRepository $sprintRepository,
        private CrmRepository $crmRepository,
        private TaskListService $taskListService,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/v1/crm/companies', methods: [Request::METHOD_GET])]
    public function companies(): JsonResponse
    {
        $items = array_map(static fn (Company $company): array => ['id' => $company->getId(), 'name' => $company->getName()], $this->companyRepository->findBy([], ['createdAt' => 'DESC'], 200));
        return new JsonResponse(['items' => $items]);
    }

    #[Route('/v1/crm/companies', methods: [Request::METHOD_POST])]
    public function createCompany(Request $request): JsonResponse
    {
        $payload = (array) json_decode((string) $request->getContent(), true);
        $company = new Company();
        $company->setName((string) ($payload['name'] ?? ''));
        if (is_string($payload['crmId'] ?? null)) {
            $company->setCrm($this->crmRepository->find($payload['crmId']));
        }

        $this->entityManager->persist($company);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('crm_company', $company->getId()));

        return new JsonResponse(['id' => $company->getId()], JsonResponse::HTTP_CREATED);
    }

    #[Route('/v1/crm/companies/{id}', methods: [Request::METHOD_DELETE])]
    public function deleteCompany(string $id): JsonResponse
    {
        $company = $this->companyRepository->find($id);
        if (!$company instanceof Company) {
            return new JsonResponse(status: JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($company);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityDeleted('crm_company', $id));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/v1/crm/projects', methods: [Request::METHOD_GET])]
    public function projects(): JsonResponse
    {
        $items = array_map(static fn (Project $project): array => ['id' => $project->getId(), 'name' => $project->getName(), 'companyId' => $project->getCompany()?->getId()], $this->projectRepository->findBy([], ['createdAt' => 'DESC'], 200));
        return new JsonResponse(['items' => $items]);
    }

    #[Route('/v1/crm/projects', methods: [Request::METHOD_POST])]
    public function createProject(Request $request): JsonResponse
    {
        $payload = (array) json_decode((string) $request->getContent(), true);
        $project = new Project();
        $project->setName((string) ($payload['name'] ?? ''));
        if (is_string($payload['companyId'] ?? null)) {
            $project->setCompany($this->companyRepository->find($payload['companyId']));
        }

        $this->entityManager->persist($project);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('crm_project', $project->getId()));

        return new JsonResponse(['id' => $project->getId()], JsonResponse::HTTP_CREATED);
    }

    #[Route('/v1/crm/projects/{id}', methods: [Request::METHOD_DELETE])]
    public function deleteProject(string $id): JsonResponse
    {
        $project = $this->projectRepository->find($id);
        if (!$project instanceof Project) {
            return new JsonResponse(status: JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($project);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityDeleted('crm_project', $id));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/v1/crm/tasks', methods: [Request::METHOD_GET])]
    public function tasks(Request $request): JsonResponse
    {
        return new JsonResponse($this->taskListService->getList($request));
    }

    #[Route('/v1/crm/tasks', methods: [Request::METHOD_POST])]
    public function createTask(Request $request): JsonResponse
    {
        $payload = (array) json_decode((string) $request->getContent(), true);
        $task = new Task();
        $task->setTitle((string) ($payload['title'] ?? ''));
        if (is_string($payload['projectId'] ?? null)) {
            $task->setProject($this->projectRepository->find($payload['projectId']));
        }
        if (is_string($payload['sprintId'] ?? null)) {
            $task->setSprint($this->sprintRepository->find($payload['sprintId']));
        }

        $this->entityManager->persist($task);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('crm_task', $task->getId()));

        return new JsonResponse(['id' => $task->getId()], JsonResponse::HTTP_CREATED);
    }

    #[Route('/v1/crm/tasks/{id}', methods: [Request::METHOD_DELETE])]
    public function deleteTask(string $id): JsonResponse
    {
        $task = $this->taskRepository->find($id);
        if (!$task instanceof Task) {
            return new JsonResponse(status: JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($task);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityDeleted('crm_task', $id));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/v1/crm/task-requests', methods: [Request::METHOD_GET])]
    public function taskRequests(): JsonResponse
    {
        $items = array_map(static fn (TaskRequest $taskRequest): array => ['id' => $taskRequest->getId(), 'title' => $taskRequest->getTitle(), 'status' => $taskRequest->getStatus(), 'taskId' => $taskRequest->getTask()?->getId()], $this->taskRequestRepository->findBy([], ['createdAt' => 'DESC'], 200));
        return new JsonResponse(['items' => $items]);
    }

    #[Route('/v1/crm/task-requests', methods: [Request::METHOD_POST])]
    public function createTaskRequest(Request $request): JsonResponse
    {
        $payload = (array) json_decode((string) $request->getContent(), true);
        $taskRequest = new TaskRequest();
        $taskRequest->setTitle((string) ($payload['title'] ?? ''))->setStatus((string) ($payload['status'] ?? 'pending'));
        if (is_string($payload['taskId'] ?? null)) {
            $taskRequest->setTask($this->taskRepository->find($payload['taskId']));
        }

        $this->entityManager->persist($taskRequest);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('crm_task_request', $taskRequest->getId()));

        return new JsonResponse(['id' => $taskRequest->getId()], JsonResponse::HTTP_CREATED);
    }

    #[Route('/v1/crm/task-requests/{id}', methods: [Request::METHOD_DELETE])]
    public function deleteTaskRequest(string $id): JsonResponse
    {
        $taskRequest = $this->taskRequestRepository->find($id);
        if (!$taskRequest instanceof TaskRequest) {
            return new JsonResponse(status: JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($taskRequest);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityDeleted('crm_task_request', $id));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/v1/crm/sprints', methods: [Request::METHOD_GET])]
    public function sprints(): JsonResponse
    {
        $items = array_map(static fn (Sprint $sprint): array => ['id' => $sprint->getId(), 'name' => $sprint->getName(), 'projectId' => $sprint->getProject()?->getId()], $this->sprintRepository->findBy([], ['createdAt' => 'DESC'], 200));
        return new JsonResponse(['items' => $items]);
    }

    #[Route('/v1/crm/sprints', methods: [Request::METHOD_POST])]
    public function createSprint(Request $request): JsonResponse
    {
        $payload = (array) json_decode((string) $request->getContent(), true);
        $sprint = new Sprint();
        $sprint->setName((string) ($payload['name'] ?? ''));
        if (is_string($payload['projectId'] ?? null)) {
            $sprint->setProject($this->projectRepository->find($payload['projectId']));
        }

        $this->entityManager->persist($sprint);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('crm_sprint', $sprint->getId()));

        return new JsonResponse(['id' => $sprint->getId()], JsonResponse::HTTP_CREATED);
    }

    #[Route('/v1/crm/sprints/{id}', methods: [Request::METHOD_DELETE])]
    public function deleteSprint(string $id): JsonResponse
    {
        $sprint = $this->sprintRepository->find($id);
        if (!$sprint instanceof Sprint) {
            return new JsonResponse(status: JsonResponse::HTTP_NOT_FOUND);
        }

        $this->entityManager->remove($sprint);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityDeleted('crm_sprint', $id));

        return new JsonResponse(status: JsonResponse::HTTP_NO_CONTENT);
    }

    #[Route('/v1/crm/applications/{applicationSlug}/companies', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'crm-sales-hub')]
    #[OA\Response(response: 200, description: 'Companies list scoped to CRM application.')]
    public function companiesByApplication(string $applicationSlug): JsonResponse
    {
        $crm = $this->resolveOrCreateCrmByApplicationSlug($applicationSlug);
        $items = array_map(static fn (Company $company): array => ['id' => $company->getId(), 'name' => $company->getName()], $this->companyRepository->findBy(['crm' => $crm], ['createdAt' => 'DESC'], 200));

        return new JsonResponse(['applicationSlug' => $applicationSlug, 'crmId' => $crm->getId(), 'items' => $items]);
    }

    #[Route('/v1/crm/applications/{applicationSlug}/companies', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'crm-sales-hub')]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            required: ['name'],
            properties: [
                new OA\Property(property: 'name', type: 'string', minLength: 1, example: 'Acme Europe'),
            ],
            example: ['name' => 'Acme Europe'],
        )
    )]
    #[OA\Response(response: 201, description: 'Company created under CRM application.', content: new OA\JsonContent(example: ['id' => 'uuid', 'crmId' => 'uuid', 'applicationSlug' => 'crm-sales-hub']))]
    public function createCompanyByApplication(string $applicationSlug, Request $request): JsonResponse
    {
        $crm = $this->resolveOrCreateCrmByApplicationSlug($applicationSlug);
        $payload = (array) json_decode((string) $request->getContent(), true);

        $company = (new Company())
            ->setCrm($crm)
            ->setName((string) ($payload['name'] ?? ''));

        $this->entityManager->persist($company);
        $this->entityManager->flush();
        $this->messageBus->dispatch(new EntityCreated('crm_company', $company->getId(), context: ['applicationSlug' => $applicationSlug]));

        return new JsonResponse(['id' => $company->getId(), 'crmId' => $crm->getId(), 'applicationSlug' => $applicationSlug], JsonResponse::HTTP_CREATED);
    }

    private function resolveOrCreateCrmByApplicationSlug(string $applicationSlug): Crm
    {
        $crm = $this->crmRepository->findOneByApplicationSlug($applicationSlug);
        if ($crm instanceof Crm) {
            return $crm;
        }

        $application = $this->entityManager->getRepository(Application::class)->findOneBy(['slug' => $applicationSlug]);
        if (!$application instanceof Application || $application->getPlatform()?->getPlatformKey() !== PlatformKey::CRM) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Unknown "applicationSlug" for CRM platform.');
        }

        $crm = (new Crm())->setApplication($application);
        $this->entityManager->persist($crm);
        $this->entityManager->flush();

        return $crm;
    }

}
