<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Application;

use App\General\Application\Message\EntityPatched;
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
        private SchoolApplicationScopeResolver $scopeResolver,
        private SchoolResourceAccessService $resourceAccessService,
        private SchoolResourceViewService $resourceViewService,
        private SchoolResourcePatchService $resourcePatchService,
        private MessageBusInterface $messageBus,
    ) {
    }
    #[Route('/v1/school/applications/{applicationSlug}/{resource}/{id}', methods: [Request::METHOD_PATCH], requirements: [
        'resource' => 'classes|students|teachers|exams|grades',
    ])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, string $resource, string $id, Request $request, ?User $loggedInUser): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $school = $this->scopeResolver->resolveOrCreateSchoolByApplicationSlug($applicationSlug, $loggedInUser);
        $entity = $this->resourceViewService->findOr404($resource, $id);
        if (!$this->resourceAccessService->belongsToSchool($entity, $school)) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Resource not found in application scope.');
        }
        $this->resourcePatchService->patch($entity, $resource, $request->toArray());
        $this->messageBus->dispatch(new EntityPatched('school_' . substr($resource, 0, -1), $id, context: [
            'applicationSlug' => $applicationSlug,
        ]));

        return new JsonResponse($this->resourceViewService->map($entity));
    }
}
