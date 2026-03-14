<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Project;
use App\Crm\Domain\Enum\ProjectStatus;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use DateTimeImmutable;
use DateTimeInterface;
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
final readonly class PatchProjectController
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiErrorResponseFactory $errorResponseFactory,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/projects/{id}', methods: [Request::METHOD_PATCH])]
    public function __invoke(string $applicationSlug, string $id, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $project = $this->projectRepository->findOneScopedById($id, $crm->getId());
        if (!$project instanceof Project) {
            return $this->errorResponseFactory->notFoundReference('projectId');
        }

        try {
            $payload = json_decode((string) $request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->errorResponseFactory->invalidJson();
        }

        if (!is_array($payload)) {
            return $this->errorResponseFactory->invalidJson();
        }

        if (isset($payload['name'])) {
            $project->setName((string) $payload['name']);
        }
        if (array_key_exists('code', $payload)) {
            $project->setCode($payload['code'] !== null ? (string) $payload['code'] : null);
        }
        if (array_key_exists('description', $payload)) {
            $project->setDescription($payload['description'] !== null ? (string) $payload['description'] : null);
        }
        if (isset($payload['status']) && is_string($payload['status'])) {
            $status = ProjectStatus::tryFrom($payload['status']);
            if ($status !== null) {
                $project->setStatus($status);
            }
        }
        if (array_key_exists('startedAt', $payload)) {
            $project->setStartedAt($this->parseDate($payload['startedAt']));
        }
        if (array_key_exists('dueAt', $payload)) {
            $project->setDueAt($this->parseDate($payload['dueAt']));
        }

        $this->projectRepository->save($project);

        return new JsonResponse(['id' => $project->getId()]);
    }

    private function parseDate(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            return null;
        }

        $parsed = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value);

        return $parsed === false ? null : $parsed;
    }
}
