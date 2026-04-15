<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Domain\Entity\Sprint;
use App\Crm\Domain\Enum\SprintStatus;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Role\Domain\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_string;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class CreateGeneralSprintController
{
    use GeneralCrudApiTrait;

    public function __construct(private EntityManagerInterface $entityManager, private ProjectRepository $projectRepository)
    {
    }

    #[Route('/v1/crm/general/sprints', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'General - Create Sprint', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(example: ['projectId' => 'uuid', 'name' => 'Sprint 1', 'status' => 'planned'])), responses: [new OA\Response(response: 201, description: 'Sprint créée', content: new OA\JsonContent(example: ['id' => 'uuid']))])]
    public function __invoke(Request $request): JsonResponse
    {
        $payload = $this->decodePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $projectId = $payload['projectId'] ?? null;
        $name = $payload['name'] ?? null;

        if (!is_string($projectId) || !is_string($name) || $name === '') {
            return $this->badRequest('Fields "projectId" and "name" are required.');
        }

        $project = $this->projectRepository->find($projectId);
        if ($project === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Project not found.');
        }

        $sprint = (new Sprint())
            ->setProject($project)
            ->setName($name)
            ->setGoal($this->nullableString($payload['goal'] ?? null))
            ->setStatus(SprintStatus::tryFrom((string) ($payload['status'] ?? 'planned')) ?? SprintStatus::PLANNED)
            ->setStartDate($this->parseNullableDate($payload['startDate'] ?? null))
            ->setEndDate($this->parseNullableDate($payload['endDate'] ?? null));

        $this->entityManager->persist($sprint);
        $this->entityManager->flush();

        return new JsonResponse(['id' => $sprint->getId()], JsonResponse::HTTP_CREATED);
    }
}
