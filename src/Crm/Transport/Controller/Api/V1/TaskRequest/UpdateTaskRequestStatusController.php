<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\TaskRequest;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Domain\Enum\TaskRequestStatus;
use App\Crm\Infrastructure\Repository\TaskRequestRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Crm\Transport\Request\UpdateTaskRequestStatusRequest;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use App\Crm\Application\Security\CrmPermissions;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(CrmPermissions::EDIT)]
final readonly class UpdateTaskRequestStatusController
{
    public function __construct(
        private TaskRequestRepository $taskRequestRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private ValidatorInterface $validator,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/task-requests/{id}/status', methods: [Request::METHOD_PATCH, Request::METHOD_PUT])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Patch(
        summary: 'Update task request status.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['status'],
                properties: [
                    new OA\Property(property: 'status', type: 'string', enum: ['pending', 'approved', 'rejected'], example: 'approved'),
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Task request status updated.'),
            new OA\Response(response: 400, description: 'Invalid JSON payload.'),
            new OA\Response(response: 404, description: 'Task request not found in CRM scope.'),
            new OA\Response(response: 422, description: 'Validation failed.'),
        ],
    )]
    public function __invoke(string $applicationSlug, string $id, Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $taskRequest = $this->taskRequestRepository->findOneScopedById($id, $crm->getId());

        if (!$taskRequest instanceof TaskRequest) {
            return $this->errorResponseFactory->notFoundReference('taskRequestId');
        }

        try {
            $payload = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->errorResponseFactory->invalidJson();
        }

        if (!is_array($payload)) {
            return $this->errorResponseFactory->invalidJson();
        }

        $input = UpdateTaskRequestStatusRequest::fromArray($payload);
        $violations = $this->validator->validate($input);
        if ($violations->count() > 0) {
            return $this->errorResponseFactory->validationFailed($violations);
        }

        $taskRequest->setStatus(TaskRequestStatus::from((string) $input->status));
        $this->taskRequestRepository->save($taskRequest);

        return new JsonResponse([
            'id' => $taskRequest->getId(),
            'status' => $taskRequest->getStatus()->value,
        ]);
    }
}
