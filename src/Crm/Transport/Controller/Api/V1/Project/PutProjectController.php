<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project;

use App\Crm\Application\Dto\Response\EntityIdResponseDto;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Enum\ProjectStatus;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Transport\Request\CrmRequestHandler;
use App\Crm\Transport\Request\PutProjectRequest;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_ADMIN->value)]
final readonly class PutProjectController
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmRequestHandler $crmRequestHandler,
    ) {
    }

    #[Route('/v1/crm/projects/{project}', methods: [Request::METHOD_PUT])]
    #[OA\Put(
        summary: 'Replace Project',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Project replaced.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Invalid JSON payload or invalid date format.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Project not found in CRM scope.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Validation failed.'),
        ],
    )]
    public function __invoke(string $project, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail('crm-general-core');
        $entity = $this->projectRepository->findOneScopedById($project, $crm->getId());
        if ($entity === null) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Project not found for this CRM scope.');
        }

        $payload = $this->crmRequestHandler->decodeJson($request);
        if ($payload instanceof JsonResponse) {
            return $payload;
        }

        $input = $this->crmRequestHandler->mapAndValidate($payload, PutProjectRequest::class);
        if ($input instanceof JsonResponse) {
            return $input;
        }

        $startedAt = $this->crmRequestHandler->parseNullableIso8601($input->startedAt, 'startedAt');
        if ($startedAt instanceof JsonResponse) {
            return $startedAt;
        }

        $dueAt = $this->crmRequestHandler->parseNullableIso8601($input->dueAt, 'dueAt');
        if ($dueAt instanceof JsonResponse) {
            return $dueAt;
        }

        $status = ProjectStatus::from((string)$input->status);

        $entity
            ->setName((string)$input->name)
            ->setCode($input->code)
            ->setDescription($input->description)
            ->setStatus($status)
            ->setStartedAt($startedAt)
            ->setDueAt($dueAt)
            ->setGithubToken($input->githubToken)
            ->setGithubRepositories(is_array($input->githubRepositories) ? $input->githubRepositories : []);

        $this->projectRepository->save($entity);

        return new JsonResponse((new EntityIdResponseDto($entity->getId()))->toArray());
    }
}
