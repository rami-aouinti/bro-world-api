<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Class;

use App\School\Application\Serializer\SchoolApiResponseSerializer;
use App\School\Application\Serializer\SchoolViewMapper;
use App\School\Application\Service\SchoolApplicationScopeResolver;
use App\School\Infrastructure\Repository\SchoolClassRepository;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'School')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class ListClassesController
{
    public function __construct(
        private SchoolApplicationScopeResolver $scopeResolver,
        private SchoolClassRepository $classRepository,
        private SchoolViewMapper $viewMapper,
        private SchoolApiResponseSerializer $responseSerializer,
    ) {
    }

    public function __invoke(string $applicationSlug, ?User $loggedInUser): JsonResponse
    {
        $school = $this->scopeResolver->resolveOrCreateSchoolByApplicationSlug($applicationSlug, $loggedInUser);

        $items = $this->viewMapper->mapClassCollection($this->classRepository->findBy([
            'school' => $school,
        ], [
            'createdAt' => 'DESC',
        ], 200));

        return new JsonResponse($this->responseSerializer->list($items));
    }
}
