<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Job;

use App\General\Application\Message\EntityPatched;
use App\Recruit\Application\Security\RecruitPermissions;
use App\Recruit\Application\Service\ApplicationJobAccessService;
use App\Recruit\Application\Service\JobPayloadHydratorService;
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
#[IsGranted(RecruitPermissions::OFFER_MANAGE)]
readonly class JobPatchFromApplicationController
{
    public function __construct(
        private ApplicationJobAccessService $applicationJobAccessService,
        private JobRepository $jobRepository,
        private MessageBusInterface $messageBus,
        private JobPayloadHydratorService $jobPayloadHydratorService,
    ) {
    }

    #[Route(path: '/v1/recruit/jobs/{jobId}', methods: [Request::METHOD_PATCH])]
    #[OA\Parameter(name: 'applicationSlug', in: 'query', required: true, schema: new OA\Schema(type: 'string'))]
    #[Route(path: '/v1/recruit/private/jobs/{jobId}', methods: [Request::METHOD_PATCH])]
    public function __invoke(string $applicationSlug, string $jobId, Request $request, User $loggedInUser): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        $recruit = $this->applicationJobAccessService->resolveOwnedRecruitByApplicationSlug(
            $applicationSlug,
            $loggedInUser,
            'You cannot update a job for this application.'
        );
        $application = $recruit->getApplication();

        $job = $this->applicationJobAccessService->resolveJobForRecruit($jobId, $recruit);

        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();
        $this->jobPayloadHydratorService->applyJobFields($job, $payload);

        $this->jobRepository->save($job);
        $this->messageBus->dispatch(new EntityPatched('recruit_job', $job->getId(), context: [
            'applicationSlug' => $application?->getSlug() ?? '',
        ]));

        return new JsonResponse([
            'id' => $job->getId(),
            'recruitId' => $recruit->getId(),
            'slug' => $job->getSlug(),
            'title' => $job->getTitle(),
            'quizId' => $job->getQuiz()?->getId(),
        ]);
    }
}
