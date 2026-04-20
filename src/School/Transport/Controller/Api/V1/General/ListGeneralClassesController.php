<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\General;

use App\School\Application\Service\ClassApplicationListService;
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
final readonly class ListGeneralClassesController
{
    public function __construct(
        private ClassApplicationListService $classApplicationListService,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     * @throws JsonException
     */
    #[Route('/v1/school/general/classes', defaults: ['applicationSlug' => 'general'], methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'Lister globalement les classes school (scope General en lecture seule)')]
    public function __invoke(Request $request): JsonResponse
    {
        $request->attributes->set('applicationSlug', 'general');

        return new JsonResponse($this->classApplicationListService->list($request));
    }
}
