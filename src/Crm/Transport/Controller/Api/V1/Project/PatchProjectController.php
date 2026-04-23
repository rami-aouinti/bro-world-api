<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project;

use App\Crm\Domain\Entity\Project;
use App\Crm\Domain\Enum\ProjectStatus;
use App\Crm\Infrastructure\Repository\ProjectRepository;
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
#[IsGranted(Role::CRM_ADMIN->value)]
final readonly class PatchProjectController
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private CrmApiErrorResponseFactory $errorResponseFactory,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     */
    #[Route('/v1/crm/projects/{project}', methods: [Request::METHOD_PATCH])]
        #[OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Patch(
        summary: 'Patch Project',
        description: 'Exécute l action metier Patch Project dans le perimetre de l application CRM.',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Opération exécutée avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
    public function __invoke(string $applicationSlug, Project $project, Request $request): JsonResponse
    {
        try {
            $payload = json_decode((string)$request->getContent(), true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return $this->errorResponseFactory->invalidJson();
        }

        if (!is_array($payload)) {
            return $this->errorResponseFactory->invalidJson();
        }

        if (isset($payload['name'])) {
            $project->setName((string)$payload['name']);
        }
        if (array_key_exists('code', $payload)) {
            $project->setCode($payload['code'] !== null ? (string)$payload['code'] : null);
        }
        if (array_key_exists('description', $payload)) {
            $project->setDescription($payload['description'] !== null ? (string)$payload['description'] : null);
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
        if (array_key_exists('githubToken', $payload)) {
            $project->setGithubToken($payload['githubToken'] !== null ? (string)$payload['githubToken'] : null);
        }
        if (array_key_exists('githubRepositories', $payload) && is_array($payload['githubRepositories'])) {
            $repositories = $this->normalizeGithubRepositories($payload['githubRepositories']);
            if ($repositories !== []) {
                $project->setGithubRepositories($repositories);
            }
        }

        $this->projectRepository->save($project);

        return new JsonResponse([
            'id' => $project->getId(),
        ]);
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

    /**
     * @param array<mixed> $repositories
     * @return list<array{fullName:string,defaultBranch?:string|null}>
     */
    private function normalizeGithubRepositories(array $repositories): array
    {
        $normalized = [];
        foreach ($repositories as $repository) {
            if (!is_array($repository)) {
                continue;
            }

            $fullName = isset($repository['fullName']) ? trim((string)$repository['fullName']) : '';
            if ($fullName === '') {
                continue;
            }

            $defaultBranch = isset($repository['defaultBranch']) ? trim((string)$repository['defaultBranch']) : null;

            $normalized[] = [
                'fullName' => $fullName,
                'defaultBranch' => $defaultBranch !== '' ? $defaultBranch : null,
            ];
        }

        return $normalized;
    }
}
