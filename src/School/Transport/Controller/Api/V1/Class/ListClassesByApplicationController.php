<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Class;

use App\School\Application\Service\ClassApplicationListService;
use App\School\Application\Service\SchoolApplicationScopeResolver;
use App\User\Domain\Entity\User;
use JsonException;
use OpenApi\Attributes as OA;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'School')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class ListClassesByApplicationController
{
    public function __construct(
        private ClassApplicationListService $classApplicationListService,
        private SchoolApplicationScopeResolver $scopeResolver,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    #[Route('/v1/school/applications/{applicationSlug}/classes', methods: [Request::METHOD_GET])]
    #[OA\Get(
        summary: 'Lister les classes d\'une application',
        parameters: [
            new OA\Parameter(name: 'page', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, default: 1)),
            new OA\Parameter(name: 'limit', in: 'query', required: false, schema: new OA\Schema(type: 'integer', minimum: 1, maximum: 100, default: 20)),
            new OA\Parameter(name: 'q', in: 'query', required: false, schema: new OA\Schema(type: 'string'), description: 'Filtre partiel sur le nom de classe.'),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des classes.', content: new OA\JsonContent(example: ['items' => [['id' => '7600e750-f92f-4f9f-883a-26404b538f66', 'name' => 'Terminale S']], 'meta' => ['pagination' => ['page' => 1, 'limit' => 20, 'totalItems' => 1, 'totalPages' => 1], 'filters' => ['q' => 'term']]])),
            new OA\Response(response: 403, description: 'Accès refusé.'),
            new OA\Response(response: 404, description: 'Application introuvable.'),
            new OA\Response(response: 422, description: 'Pagination ou filtres invalides.'),
        ],
    )]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, Request $request, ?User $loggedInUser): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $school = $this->scopeResolver->resolveOrCreateSchoolByApplicationSlug($applicationSlug, $loggedInUser);

        return new JsonResponse($this->classApplicationListService->getList($request, $applicationSlug, $school));
    }
}
