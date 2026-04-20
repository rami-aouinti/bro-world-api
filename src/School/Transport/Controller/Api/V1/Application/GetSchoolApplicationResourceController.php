<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Application;

use App\School\Application\Service\SchoolApplicationScopeResolver;
use App\School\Application\Service\SchoolResourceAccessService;
use App\School\Application\Service\SchoolResourceViewService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'School')]
final readonly class GetSchoolApplicationResourceController
{
    public function __construct(
        private SchoolApplicationScopeResolver $scopeResolver,
        private SchoolResourceAccessService $resourceAccessService,
        private SchoolResourceViewService $resourceViewService,
    ) {
    }
    #[Route('/v1/school/applications/{applicationSlug}/{resource}/{id}', requirements: [
        'resource' => 'classes|students|teachers|exams|grades',
    ], methods: [Request::METHOD_GET])]
    #[OA\Get(
        summary: 'Détail d\'une ressource school dans le scope application',
        parameters: [
            new OA\Parameter(name: 'resource', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['classes', 'students', 'teachers', 'exams', 'grades'])),
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Ressource trouvée.', content: new OA\JsonContent(example: [
                'id' => '4cfada53-2cf2-49a7-a4fb-4a9682c3a0c0',
                'name' => 'Alice Martin',
            ])),
            new OA\Response(response: 403, description: 'Accès refusé.'),
            new OA\Response(response: 404, description: 'Ressource introuvable dans ce scope application.'),
        ],
    )]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: false, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, string $resource, string $id, ?User $loggedInUser): JsonResponse
    {
        $school = $this->scopeResolver->resolveOrCreateSchoolByApplicationSlug($applicationSlug, $loggedInUser);
        $entity = $this->resourceViewService->findOr404($resource, $id);
        if (!$this->resourceAccessService->belongsToSchool($entity, $school)) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Resource not found in application scope.');
        }

        return new JsonResponse($this->resourceViewService->map($entity));
    }
}
