<?php

declare(strict_types=1);

namespace App\School\Transport\Controller\Api\V1\Exam;

use App\School\Application\Service\ExamListService;
use App\School\Application\Service\SchoolApplicationScopeResolver;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'School')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
final readonly class ListExamsController
{
    public function __construct(
        private ExamListService $examListService,
        private SchoolApplicationScopeResolver $scopeResolver,
    ) {
    }

    #[Route('/v1/school/exams', methods: [Request::METHOD_GET], defaults: ['applicationSlug' => 'general'])]
        public function __invoke(string $applicationSlug, Request $request, ?User $loggedInUser): JsonResponse
    {
        $school = $this->scopeResolver->resolveOrCreateSchoolByApplicationSlug($applicationSlug, $loggedInUser);
        $request->attributes->set('applicationSlug', $applicationSlug);

        return new JsonResponse($this->examListService->list($request, $school->getId()));
    }
}
