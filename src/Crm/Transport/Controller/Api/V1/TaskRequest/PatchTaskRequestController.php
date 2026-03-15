<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\TaskRequest;

use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Domain\Enum\TaskRequestStatus;
use App\Crm\Infrastructure\Repository\TaskRequestRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Role\Domain\Enum\Role;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class PatchTaskRequestController
{
    public function __construct(
        private TaskRequestRepository $taskRequestRepository,
        private CrmApiErrorResponseFactory $errorResponseFactory,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[Route('/v1/crm/applications/{applicationSlug}/task-requests/{taskRequest}', methods: [Request::METHOD_PATCH])]
    public function __invoke(string $applicationSlug, TaskRequest $taskRequest, Request $request): JsonResponse
    {
        try {
            $payload = json_decode(
                (string)$request->getContent(),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException) {
            return $this->errorResponseFactory->invalidJson();
        }
        if (!is_array($payload)) {
            return $this->errorResponseFactory->invalidJson();
        }

        if (isset($payload['title'])) {
            $taskRequest->setTitle((string)$payload['title']);
        }
        if (array_key_exists('description', $payload)) {
            $taskRequest->setDescription($payload['description'] !== null ? (string)$payload['description'] : null);
        }
        if (isset($payload['status']) && is_string($payload['status'])) {
            $status = TaskRequestStatus::tryFrom($payload['status']);
            if ($status) {
                $taskRequest->setStatus($status);
            }
        }
        if (array_key_exists('resolvedAt', $payload)) {
            $taskRequest->setResolvedAt($this->parseDate($payload['resolvedAt']));
        }

        $this->taskRequestRepository->save($taskRequest);

        return new JsonResponse([
            'id' => $taskRequest->getId(),
        ]);
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === '' || !is_string($value)) {
            return null;
        }
        $parsed = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);

        return $parsed === false ? null : $parsed;
    }
}
