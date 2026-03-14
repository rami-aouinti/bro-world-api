<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Task;

use App\Crm\Application\Service\CrmApiNormalizer;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Application\Service\CrmAttachmentUploaderService;
use App\Crm\Domain\Entity\Task;
use App\Crm\Infrastructure\Repository\TaskRepository;
use App\Crm\Transport\Request\CrmApiErrorResponseFactory;
use DateTimeImmutable;
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
final readonly class UploadTaskFilesController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmApiErrorResponseFactory $errorResponseFactory,
        private CrmAttachmentUploaderService $attachmentUploaderService,
        private CrmApiNormalizer $normalizer,
    ) {
    }

    #[Route('/v1/crm/applications/{applicationSlug}/tasks/{id}/files', methods: [Request::METHOD_POST])]
    public function __invoke(string $applicationSlug, string $id, Request $request): JsonResponse
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $task = $this->taskRepository->findOneScopedById($id, $crm->getId());
        if (!$task instanceof Task) {
            return $this->errorResponseFactory->notFoundReference('taskId');
        }

        $uploadedFiles = $this->attachmentUploaderService->upload(
            $request,
            $this->attachmentUploaderService->extractFiles($request),
            '/uploads/crm/tasks/' . $task->getId(),
        );

        foreach ($uploadedFiles as $file) {
            $task->addAttachment([
                'url' => $file['url'],
                'originalName' => $file['originalName'],
                'mimeType' => $file['mimeType'],
                'size' => $file['size'],
                'extension' => $file['extension'],
                'uploadedAt' => (new DateTimeImmutable())->format(DATE_ATOM),
            ]);
        }

        $this->taskRepository->save($task);

        return new JsonResponse($this->normalizer->normalizeTask($task), JsonResponse::HTTP_CREATED);
    }
}
