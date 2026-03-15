<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\TaskRequest;

use App\Crm\Application\Service\CrmApiNormalizer;
use App\Crm\Application\Service\CrmAttachmentUploaderService;
use App\Crm\Domain\Entity\TaskRequest;
use App\Crm\Infrastructure\Repository\TaskRequestRepository;
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
final readonly class UploadTaskRequestFilesController
{
    public function __construct(
        private TaskRequestRepository $taskRequestRepository,
        private CrmAttachmentUploaderService $attachmentUploaderService,
        private CrmApiNormalizer $normalizer,
    ) {
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws RandomException
     */
    #[Route('/v1/crm/applications/{applicationSlug}/task-requests/{taskRequest}/files', methods: [Request::METHOD_POST])]
    public function __invoke(string $applicationSlug, TaskRequest $taskRequest, Request $request): JsonResponse
    {
        $uploadedFiles = $this->attachmentUploaderService->upload(
            $request,
            $this->attachmentUploaderService->extractFiles($request),
            '/uploads/crm/task-requests/' . $taskRequest->getId(),
        );

        foreach ($uploadedFiles as $file) {
            $taskRequest->addAttachment([
                'url' => $file['url'],
                'originalName' => $file['originalName'],
                'mimeType' => $file['mimeType'],
                'size' => $file['size'],
                'extension' => $file['extension'],
                'uploadedAt' => new DateTimeImmutable()->format(DATE_ATOM),
            ]);
        }

        $this->taskRequestRepository->save($taskRequest);

        return new JsonResponse($this->normalizer->normalizeTaskRequest($taskRequest), JsonResponse::HTTP_CREATED);
    }
}
