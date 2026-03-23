<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project\Github;

use App\Crm\Domain\Entity\CrmRepository;
use App\Crm\Domain\Entity\Project;
use App\Crm\Infrastructure\Repository\CrmProjectRepositoryRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use App\Crm\Transport\Request\CrmGithubApiErrorResponseFactory;
use App\Crm\Transport\Request\CrmRequestHandler;
use App\Crm\Transport\Request\UpdateProjectGithubRepositoryRequest;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_MANAGER->value)]
final readonly class UpdateProjectGithubRepositoryController
{
    use HandlesGithubApiExceptions;

    public function __construct(
        private CrmProjectRepositoryRepository $crmProjectRepositoryRepository,
        private CrmRequestHandler $crmRequestHandler,
        private CrmApiErrorResponseFactory $apiErrorResponseFactory,
        private CrmGithubApiErrorResponseFactory $errorResponseFactory,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/projects/{project}/github/repositories/{repositoryId}', methods: [Request::METHOD_PUT])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'), example: 'crm-sales-hub')]
    #[OA\Parameter(name: 'project', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), example: 'ebf77366-d60c-4ac4-b204-9f91a7f7ee12')]
    #[OA\Parameter(name: 'repositoryId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'), example: '03463358-2e8f-4f63-a893-69d5313b05d2')]
    #[OA\Put(
        summary: 'PUT /v1/crm/applications/{applicationSlug}/projects/{project}/github/repositories/{repositoryId}',
        description: 'Met à jour la liaison CRM d\'un repository GitHub (branche par défaut, statut de synchro, métadonnées).',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'defaultBranch', type: 'string', nullable: true, example: 'main'),
                    new OA\Property(property: 'syncStatus', type: 'string', nullable: true, example: 'synced'),
                    new OA\Property(property: 'metadata', type: 'object', nullable: true, example: ['nodeId' => 'R_kgDOABC123']),
                ],
            ),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Liaison repository mise à jour.',
                content: new OA\JsonContent(
                    example: [
                        'id' => '03463358-2e8f-4f63-a893-69d5313b05d2',
                        'provider' => 'github',
                        'fullName' => 'john-root/bro-world-api',
                        'defaultBranch' => 'main',
                        'syncStatus' => 'synced',
                        'payload' => ['nodeId' => 'R_kgDOABC123'],
                    ],
                ),
            ),
            new OA\Response(response: 400, description: 'Payload JSON invalide.'),
            new OA\Response(response: 404, description: 'Repository inconnu ou non lié à ce projet.'),
            new OA\Response(response: 422, description: 'Validation échouée.'),
        ],
    )]
    public function __invoke(string $applicationSlug, Project $project, string $repositoryId, Request $request): JsonResponse
    {
        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, UpdateProjectGithubRepositoryRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        return $this->withGithubApiErrors(function () use ($project, $repositoryId, $input): JsonResponse {
            $repository = $this->crmProjectRepositoryRepository->find($repositoryId);
            if (!$repository instanceof CrmRepository || $repository->getProject()?->getId() !== $project->getId()) {
                return $this->apiErrorResponseFactory->notFoundReference('repositoryId');
            }

            if ($input->defaultBranch !== null) {
                $repository->setDefaultBranch($input->defaultBranch !== '' ? $input->defaultBranch : null);
            }

            if ($input->syncStatus !== null) {
                $repository->setSyncStatus($input->syncStatus !== '' ? $input->syncStatus : 'pending');
            }

            if ($input->metadata !== null) {
                $repository->setPayload($input->metadata);
            }

            $this->crmProjectRepositoryRepository->save($repository);

            return new JsonResponse(self::toResponse($repository));
        }, $this->errorResponseFactory);
    }

    /**
     * @return array<string,mixed>
     */
    private static function toResponse(CrmRepository $repository): array
    {
        return [
            'id' => $repository->getId(),
            'provider' => $repository->getProvider(),
            'owner' => $repository->getOwner(),
            'name' => $repository->getName(),
            'fullName' => $repository->getFullName(),
            'defaultBranch' => $repository->getDefaultBranch(),
            'isPrivate' => $repository->isPrivate(),
            'htmlUrl' => $repository->getHtmlUrl(),
            'externalId' => $repository->getExternalId(),
            'syncStatus' => $repository->getSyncStatus(),
            'payload' => $repository->getPayload(),
        ];
    }
}
