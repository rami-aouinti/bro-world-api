<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Job;

use App\General\Application\Message\EntityPatched;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use App\Recruit\Domain\Repository\Interfaces\RecruitRepositoryInterface;
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

#[AsController]
#[OA\Tag(name: 'Recruit Job')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class JobPatchFromApplicationController
{
    use JobPayloadHydratorTrait;

    public function __construct(
        private readonly RecruitRepositoryInterface $recruitRepository,
        private readonly JobRepository $jobRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    #[Route(path: '/v1/recruit/applications/{applicationSlug}/jobs/{jobId}', methods: [Request::METHOD_PATCH])]
    #[Route(path: '/v1/recruit/private/{applicationSlug}/jobs/{jobId}', methods: [Request::METHOD_PATCH])]
    public function __invoke(string $applicationSlug, string $jobId, Request $request, User $loggedInUser): JsonResponse
    {
        $recruit = $this->resolveRecruitByApplicationSlug($applicationSlug);
        $application = $recruit->getApplication();

        if ($application?->getUser()?->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You cannot update a job for this application.');
        }

        $job = $this->jobRepository->find($jobId);
        if (!$job instanceof Job) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Job not found.');
        }

        if ($job->getRecruit()?->getId() !== $recruit->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'This job does not belong to the given application.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();
        $this->applyJobFields($job, $payload);

        $this->jobRepository->save($job);
        $this->messageBus->dispatch(new EntityPatched('recruit_job', $job->getId(), context: [
            'applicationSlug' => $application?->getSlug() ?? '',
        ]));

        return new JsonResponse([
            'id' => $job->getId(),
            'recruitId' => $recruit->getId(),
            'slug' => $job->getSlug(),
            'title' => $job->getTitle(),
        ]);
    }

    private function resolveRecruitByApplicationSlug(string $applicationSlug): Recruit
    {
        $recruit = $this->recruitRepository->findOneByApplicationSlug($applicationSlug);

        if (!$recruit instanceof Recruit) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Unknown "applicationSlug".');
        }

        return $recruit;
    }
}
