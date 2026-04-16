<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\General;

use App\Recruit\Application\Security\RecruitPermissions;
use App\Recruit\Application\Service\GeneralJobApplicationListService;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit Application')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
#[IsGranted(RecruitPermissions::INTERVIEW_VIEW)]
final readonly class ListGeneralJobApplicationsController
{
    public function __construct(
        private GeneralJobApplicationListService $generalJobApplicationListService,
    ) {
    }

    #[Route(path: '/v1/recruit/general/private/job-applications', methods: [Request::METHOD_GET])]
    #[OA\Get(
        summary: 'Liste privée des candidatures d\'un job.',
        parameters: [
            new OA\Parameter(name: 'jobId', description: 'UUID du job', in: 'query', required: false, schema: new OA\Schema(type: 'string', format: 'uuid')),
            new OA\Parameter(name: 'jobSlug', description: 'Slug du job', in: 'query', required: false, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Liste des candidatures du job.'),
            new OA\Response(response: 403, description: 'Vous n\'êtes pas propriétaire du job.'),
        ],
    )]
    public function __invoke(Request $request, User $loggedInUser): JsonResponse
    {
        return new JsonResponse($this->generalJobApplicationListService->getList(
            $loggedInUser,
            $request->query->getString('jobId', ''),
            $request->query->getString('jobSlug', ''),
        ));
    }
}
