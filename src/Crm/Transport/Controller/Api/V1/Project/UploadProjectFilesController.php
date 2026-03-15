<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Project;

use App\Crm\Application\Service\CrmAttachmentUploaderService;
use App\Crm\Domain\Entity\Project;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Role\Domain\Enum\Role;
use DateTimeImmutable;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use OpenApi\Attributes as OA;
use Random\RandomException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
#[IsGranted(Role::CRM_VIEWER->value)]
final readonly class UploadProjectFilesController
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private CrmAttachmentUploaderService $attachmentUploaderService,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws RandomException
     * @throws ORMException
     */
    #[Route('/v1/crm/applications/{applicationSlug}/projects/{project}/files', methods: [Request::METHOD_POST])]
    public function __invoke(string $applicationSlug, Project $project, Request $request): JsonResponse
    {
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

        return new JsonResponse([
            'files' => $attached,
        ], JsonResponse::HTTP_CREATED);
    }
}
