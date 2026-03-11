<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Job;

use App\General\Application\Message\EntityCreated;
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

use function is_string;
use function trim;

#[AsController]
#[OA\Tag(name: 'Recruit Job')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class JobCreateFromApplicationController
{
    use JobPayloadHydratorTrait;

    public function __construct(
        private readonly RecruitRepositoryInterface $recruitRepository,
        private readonly JobRepository $jobRepository,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    #[Route(path: '/v1/recruit/applications/{applicationSlug}/jobs', methods: [Request::METHOD_POST])]
    public function __invoke(string $applicationSlug, Request $request, User $loggedInUser): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();
        $title = $payload['title'] ?? null;

        if (!is_string($title) || trim($title) === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "title" is required and must be a non-empty string.');
        }

        $recruit = $this->resolveRecruitByApplicationSlug($applicationSlug);
        $application = $recruit->getApplication();

        if ($application?->getUser()?->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You cannot create a job for this application.');
        }

        $job = (new Job())
            ->setRecruit($recruit)
            ->setTitle(trim($title));

        $this->applyJobFields($job, $payload);

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

    private function resolveRecruitByApplicationSlug(string $applicationSlug): Recruit
    {
        $recruit = $this->recruitRepository->findOneByApplicationSlug($applicationSlug);

        if (!$recruit instanceof Recruit) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Unknown "applicationSlug".');
        }

        return $recruit;
    }
}
