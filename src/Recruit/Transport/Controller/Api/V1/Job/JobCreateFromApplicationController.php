<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Job;

use App\General\Application\Message\EntityCreated;
use App\Recruit\Application\Service\ApplicationJobAccessService;
use App\Recruit\Application\Service\JobPayloadHydratorService;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Infrastructure\Repository\JobRepository;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_string;
use function trim;

#[AsController]
#[OA\Tag(name: 'Recruit Job')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class JobCreateFromApplicationController
{
    public function __construct(
        private ApplicationJobAccessService $applicationJobAccessService,
        private JobRepository $jobRepository,
        private MessageBusInterface $messageBus,
        private JobPayloadHydratorService $jobPayloadHydratorService,
    ) {
    }

    #[Route(path: '/v1/recruit/{applicationSlug}/jobs', methods: [Request::METHOD_POST])]
    #[OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string'))]
    public function __invoke(string $applicationSlug, Request $request, User $loggedInUser): JsonResponse
    {
        $request->attributes->set('applicationSlug', $applicationSlug);
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();
        $title = $payload['title'] ?? null;

        if (!is_string($title) || trim($title) === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "title" is required and must be a non-empty string.');
        }

        $recruit = $this->applicationJobAccessService->resolveOwnedRecruitByApplicationSlug(
            $applicationSlug,
            $loggedInUser,
            'You cannot create a job for this application.'
        );
        $application = $recruit->getApplication();

        $job = (new Job())
            ->setRecruit($recruit)
            ->setTitle(trim($title));

        $this->jobPayloadHydratorService->applyJobFields($job, $payload);

        $this->jobRepository->save($job);
        $this->messageBus->dispatch(new EntityCreated('recruit_job', $job->getId(), context: [
            'applicationSlug' => $application?->getSlug() ?? '',
        ]));

        return new JsonResponse([
            'id' => $job->getId(),
            'recruitId' => $recruit->getId(),
            'applicationSlug' => $application?->getSlug() ?? '',
            'slug' => $job->getSlug(),
            'title' => $job->getTitle(),
        ], JsonResponse::HTTP_CREATED);
    }
}
