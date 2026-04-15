<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Domain\Enum\TaskRequestStatus;
use App\Role\Domain\Enum\Role;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_string;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class PatchGeneralTaskRequestController
{
    use GeneralCrudApiTrait;

    public function __construct(private EntityManagerInterface $entityManager)
    {
    }

    #[Route('/v1/crm/general/task-requests/{taskRequest}', methods: [Request::METHOD_PATCH])]
    #[OA\Patch(summary: 'General - Update Task Request', requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(example: ['status' => 'done', 'resolvedAt' => '2026-05-03T09:00:00+00:00'])), responses: [new OA\Response(response: 200, description: 'Task request mise à jour', content: new OA\JsonContent(example: ['id' => 'uuid']))])]
    public function __invoke(TaskRequest $taskRequest, Request $request): JsonResponse
    {
        $payload = $this->decodePayload($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        if (isset($payload['title']) && is_string($payload['title']) && $payload['title'] !== '') {
            $taskRequest->setTitle($payload['title']);
        }

        if (isset($payload['description'])) {
            $taskRequest->setDescription($this->nullableString($payload['description']));
        }

        if (isset($payload['status'])) {
            $taskRequest->setStatus(TaskRequestStatus::tryFrom((string) $payload['status']) ?? TaskRequestStatus::PENDING);
        }

        if (isset($payload['resolvedAt'])) {
            $taskRequest->setResolvedAt($this->parseNullableDate($payload['resolvedAt']));
        }

        $this->entityManager->flush();

        return new JsonResponse(['id' => $taskRequest->getId()]);
    }
}
