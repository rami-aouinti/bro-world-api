<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Application\Service\CrmAttachmentUploaderService;
use App\Crm\Domain\Entity\Project;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use DateTimeImmutable;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class UploadProjectFilesController
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private CrmAttachmentUploaderService $attachmentUploaderService,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/projects/{id}/files', methods: [Request::METHOD_POST])]
    public function __invoke(string $applicationSlug, string $id, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $project = $this->projectRepository->findOneScopedById($id, $crm->getId());
        if (!$project instanceof Project) {
            return $this->errorResponseFactory->notFoundReference('projectId');
        }

        $uploadedFiles = $this->attachmentUploaderService->upload(
            $request,
            $this->attachmentUploaderService->extractFiles($request),
            '/uploads/crm/projects/' . $project->getId(),
        );

        $attached = [];
        foreach ($uploadedFiles as $file) {
            $attachment = [
                'url' => $file['url'],
                'originalName' => $file['originalName'],
                'mimeType' => $file['mimeType'],
                'size' => $file['size'],
                'extension' => $file['extension'],
                'uploadedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            ];

            $project->addAttachment($attachment);
            $attached[] = $attachment;
        }

        $this->projectRepository->save($project);

        return new JsonResponse(['files' => $attached], JsonResponse::HTTP_CREATED);
    }
}
