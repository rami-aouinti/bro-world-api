<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Domain\Entity\Project;
use App\Crm\Infrastructure\Repository\ProjectRepository;
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
final readonly class AddProjectWikiPageController
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiErrorResponseFactory $errorResponseFactory,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/projects/{id}/wiki-pages', methods: [Request::METHOD_POST])]
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

        $title = trim((string) ($payload['title'] ?? ''));
        $content = trim((string) ($payload['content'] ?? ''));
        if ($title === '' || $content === '') {
            return new JsonResponse([
                'message' => 'Both "title" and "content" are required.',
                'errors' => [],
            ], JsonResponse::HTTP_UNPROCESSABLE_ENTITY);
        }

        $wikiPage = [
            'id' => bin2hex(random_bytes(16)),
            'title' => $title,
            'content' => $content,
            'createdAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ];

        $project->addWikiPage($wikiPage);
        $this->projectRepository->save($project);

        return new JsonResponse($wikiPage, JsonResponse::HTTP_CREATED);
    }
}
