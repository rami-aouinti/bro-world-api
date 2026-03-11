<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Job;

use App\General\Application\Message\EntityDeleted;
use App\Recruit\Application\Service\ApplicationJobAccessService;
use App\Recruit\Infrastructure\Repository\JobRepository;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit Job')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class JobDeleteFromApplicationController
{
    public function __construct(
        private ApplicationJobAccessService $applicationJobAccessService,
        private JobRepository $jobRepository,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route(path: '/v1/recruit/{applicationSlug}/jobs/{jobId}', methods: [Request::METHOD_DELETE])]
    #[OA\Delete(
        summary: 'Supprime un job via applicationSlug et contrôle propriétaire.',
        parameters: [
            new OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'jobId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
        responses: [
            new OA\Response(response: 204, description: 'Job supprimé.'),
            new OA\Response(response: 403, description: 'Accès interdit.'),
            new OA\Response(response: 404, description: 'Job introuvable.'),
        ],
    )]
    public function __invoke(string $applicationSlug, string $jobId, User $loggedInUser): JsonResponse
    {
        $recruit = $this->applicationJobAccessService->resolveOwnedRecruitByApplicationSlug(
            $applicationSlug,
            $loggedInUser,
            'You cannot delete a job for this application.'
        );
        $application = $recruit->getApplication();

        $job = $this->applicationJobAccessService->resolveJobForRecruit($jobId, $recruit);

        $this->jobRepository->remove($job);

        $applicationSlugValue = $application?->getSlug() ?? '';
        $this->messageBus->dispatch(new EntityDeleted('recruit_job', $jobId, context: [
            'applicationSlug' => $applicationSlugValue,
        ]));

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }
}
