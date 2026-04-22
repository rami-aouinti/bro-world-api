<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Application;

use App\General\Application\Message\EntityPatched;
use App\General\Application\Service\ApplicationScopeResolver;
use App\School\Application\Service\SchoolApplicationScopeResolver;
use App\School\Application\Service\SchoolResourceAccessService;
use App\School\Application\Service\SchoolResourcePatchService;
use App\School\Application\Service\SchoolResourceViewService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'School')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class PatchSchoolApplicationResourceController
{
    public function __construct(
        private ApplicationScopeResolver $applicationScopeResolver,
        private SchoolApplicationScopeResolver $scopeResolver,
        private SchoolResourceAccessService $resourceAccessService,
        private SchoolResourceViewService $resourceViewService,
        private SchoolResourcePatchService $resourcePatchService,
        private MessageBusInterface $messageBus,
    ) {
    }
    #[Route('/v1/school/{resource}/{id}', methods: [Request::METHOD_PATCH, Request::METHOD_PUT], requirements: [
        'resource' => 'classes|students|teachers|exams|grades',
    ])]
    #[OA\Patch(
        summary: 'Mettre à jour partiellement une ressource school',
        parameters: [
            new OA\Parameter(name: 'resource', in: 'path', required: true, schema: new OA\Schema(type: 'string', enum: ['classes', 'students', 'teachers', 'exams', 'grades'])),
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                example: [
                    'name' => 'Terminale S1',
                    'status' => 'PUBLISHED',
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Ressource mise à jour.', content: new OA\JsonContent(example: [
                'id' => '7600e750-f92f-4f9f-883a-26404b538f66',
                'name' => 'Terminale S1',
            ])),
            new OA\Response(response: 403, description: 'Accès refusé.'),
            new OA\Response(response: 404, description: 'Ressource introuvable dans ce scope application.'),
            new OA\Response(response: 422, description: 'Payload invalide.'),
        ],
    )]
    #[OA\Parameter(name: 'applicationSlug', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $resource, string $id, Request $request, ?User $loggedInUser): JsonResponse
    {
        $applicationSlug = $this->applicationScopeResolver->resolveFromRequest($request);
        $school = $this->scopeResolver->resolveOrCreateSchoolByApplicationSlug($applicationSlug, $loggedInUser);
        $entity = $this->resourceViewService->findOr404($resource, $id);
        if (!$this->resourceAccessService->belongsToSchool($entity, $school)) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Resource not found in application scope.');
        }
        $this->resourcePatchService->patch($entity, $resource, $request->toArray());
        $this->messageBus->dispatch(new EntityPatched('school_' . substr($resource, 0, -1), $id));

        return new JsonResponse($this->resourceViewService->map($entity));
    }
}
