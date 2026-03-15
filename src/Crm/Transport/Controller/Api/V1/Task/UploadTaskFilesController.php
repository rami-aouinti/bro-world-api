<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Task;

use App\Crm\Application\Service\CrmApiNormalizer;
use App\Crm\Application\Service\CrmAttachmentUploaderService;
use App\Crm\Domain\Entity\Task;
use App\Crm\Infrastructure\Repository\TaskRepository;
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
final readonly class UploadTaskFilesController
{
    public function __construct(
        private TaskRepository $taskRepository,
        private CrmAttachmentUploaderService $attachmentUploaderService,
        private CrmApiNormalizer $normalizer,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws RandomException
     */
    #[Route('/v1/crm/applications/{applicationSlug}/tasks/{task}/files', methods: [Request::METHOD_POST])]
    public function __invoke(string $applicationSlug, Task $task, Request $request): JsonResponse
    {
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
                'uploadedAt' => new DateTimeImmutable()->format(DATE_ATOM),
            ]);
        }

        $this->taskRepository->save($task);

        return new JsonResponse($this->normalizer->normalizeTask($task), JsonResponse::HTTP_CREATED);
    }
}
