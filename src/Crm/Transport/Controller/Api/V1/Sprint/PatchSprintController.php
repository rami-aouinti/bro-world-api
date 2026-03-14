<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Sprint;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Sprint;
use App\Crm\Domain\Enum\SprintStatus;
use App\Crm\Infrastructure\Repository\SprintRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use DateTimeImmutable;
use JsonException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use App\Crm\Application\Security\CrmPermissions;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(CrmPermissions::EDIT)]
final readonly class PatchSprintController
{
    public function __construct(
        private SprintRepository $sprintRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiErrorResponseFactory $errorResponseFactory,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/sprints/{id}', methods: [Request::METHOD_PATCH])]
    public function __invoke(string $applicationSlug, string $id, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $sprint = $this->sprintRepository->findOneScopedById($id, $crm->getId());
        if (!$sprint instanceof Sprint) {
            return $this->errorResponseFactory->notFoundReference('sprintId');
        }

        try { $payload = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);} catch (JsonException) { return $this->errorResponseFactory->invalidJson(); }
        if (!is_array($payload)) { return $this->errorResponseFactory->invalidJson(); }

        if (isset($payload['name'])) { $sprint->setName((string) $payload['name']); }
        if (array_key_exists('goal', $payload)) { $sprint->setGoal($payload['goal'] !== null ? (string) $payload['goal'] : null); }
        if (isset($payload['status']) && is_string($payload['status'])) { $status = SprintStatus::tryFrom($payload['status']); if ($status) { $sprint->setStatus($status); } }
        if (array_key_exists('startDate', $payload)) { $sprint->setStartDate($this->parseDate($payload['startDate'])); }
        if (array_key_exists('endDate', $payload)) { $sprint->setEndDate($this->parseDate($payload['endDate'])); }

        $this->sprintRepository->save($sprint);

        return new JsonResponse(['id' => $sprint->getId()]);
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '' || !is_string($value)) { return null; }
        try { return new DateTimeImmutable($value); } catch (\Throwable) { return null; }
    }
}
