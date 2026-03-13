<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Application;

use App\Recruit\Application\Service\JobApplicationListService;
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
class JobApplicationListController
{
    public function __construct(
        private readonly JobApplicationListService $jobApplicationListService
    ) {
    }

    #[Route(path: '/v1/recruit/applications/{applicationSlug}/private/job-applications', methods: [Request::METHOD_GET])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
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
                        type: 'object',
                        properties: [
                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'status', type: 'string'),
                            new OA\Property(property: 'createdAt', type: 'string', format: 'date-time', nullable: true),
                            new OA\Property(
                                property: 'applicant',
                                type: 'object',
                                properties: [
                                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                    new OA\Property(property: 'coverLetter', type: 'string'),
                                    new OA\Property(
                                        property: 'user',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                                            new OA\Property(property: 'username', type: 'string', nullable: true),
                                            new OA\Property(property: 'firstName', type: 'string', nullable: true),
                                            new OA\Property(property: 'lastName', type: 'string', nullable: true),
                                            new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
                                        ],
                                    ),
                                    new OA\Property(
                                        property: 'resume',
                                        type: 'object',
                                        properties: [
                                            new OA\Property(property: 'id', type: 'string', format: 'uuid', nullable: true),
                                        ],
                                    ),
                                ],
                            ),
                        ],
                    ),
                ),
            ),
            new OA\Response(response: 403, description: 'Vous n\'êtes pas propriétaire du job.'),
        ],
    )]
    public function __invoke(string $applicationSlug, Request $request, User $loggedInUser): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        return new JsonResponse($this->jobApplicationListService->getList(
            $loggedInUser,
            $request->query->getString('jobId', ''),
            $request->query->getString('jobSlug', ''),
        ));
    }
}
