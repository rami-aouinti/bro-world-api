<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\General;

use App\School\Application\Service\ListStudentsService;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\NoResultException;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'School')]
final readonly class ListGeneralStudentsController
{
    public function __construct(
        private ListStudentsService $listStudentsService,
    ) {
    }

    /**
     * @throws NonUniqueResultException
     * @throws NoResultException
     */
    #[Route('/v1/school/general/students', defaults: ['applicationSlug' => 'general'], methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'Lister globalement les étudiants school (scope General en lecture seule)')]
    public function __invoke(Request $request): JsonResponse
    {
        return new JsonResponse($this->listStudentsService->list($request));
    }
}
