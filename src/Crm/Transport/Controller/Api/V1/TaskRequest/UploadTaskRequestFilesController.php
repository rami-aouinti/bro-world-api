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
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    #[OA\Parameter(name: 'taskRequest', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid'))]
    #[OA\Post(
        summary: 'Upload Task Request Files dans le CRM',
        description: 'Exécute l action metier Upload Task Request Files dans le perimetre de l application CRM.',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_CREATED, description: 'Ressource créée avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Ressource introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
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
