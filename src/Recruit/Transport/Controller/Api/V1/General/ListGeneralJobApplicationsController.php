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
            new OA\Response(
                response: 200,
                description: 'Liste des candidatures du job.',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'status', type: 'string'),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', nullable: true),
                            new OA\Property(
                                property: 'applicant',
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'coverLetter', type: 'string', nullable: true),
                                    new OA\Property(
                                        property: 'user',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                            new OA\Property(property: 'username', type: 'string', nullable: true),
                                            new OA\Property(property: 'firstName', type: 'string', nullable: true),
                                            new OA\Property(property: 'lastName', type: 'string', nullable: true),
                                            new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                                        ],
                                        type: 'object',
                                    ),
                                    new OA\Property(
                                        property: 'resume',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'string', format: 'uuid', nullable: true),
                                        ],
                                        type: 'object',
                                    ),
                                ],
                                type: 'object',
                            ),
                        ],
                        type: 'object',
                    ),
                ),
            ),
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
