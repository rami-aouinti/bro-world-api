<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Job;

use App\Platform\Domain\Entity\Application as PlatformApplication;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use App\Recruit\Domain\Enum\ContractType;
use App\Recruit\Domain\Enum\Schedule;
use App\Recruit\Domain\Enum\WorkMode;
use App\Recruit\Infrastructure\Repository\JobRepository;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_array;
use function is_int;
use function is_string;
use function trim;

#[AsController]
#[OA\Tag(name: 'Recruit Job')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class JobCreateFromApplicationController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JobRepository $jobRepository,
    ) {
    }

    #[Route(path: '/v1/recruit/jobs', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Crée un job en résolvant automatiquement le recruit via applicationId.')]
    public function __invoke(Request $request, User $loggedInUser): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();

        $applicationId = $payload['applicationId'] ?? null;
        $title = $payload['title'] ?? null;

        if (!is_string($applicationId) || !Uuid::isValid($applicationId)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "applicationId" must be a valid UUID.');
        }

        if (!is_string($title) || trim($title) === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "title" is required and must be a non-empty string.');
        }

        $application = $this->entityManager->getRepository(PlatformApplication::class)->find($applicationId);
        if (!$application instanceof PlatformApplication) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Unknown "applicationId".');
        }

        if ($application->getUser()?->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You cannot create a job for this application.');
        }

        $recruit = $this->entityManager->getRepository(Recruit::class)->findOneBy([
            'application' => $application,
        ]);

        if (!$recruit instanceof Recruit) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'No recruit found for the given "applicationId".');
        }

        $job = (new Job())
            ->setRecruit($recruit)
            ->setTitle(trim($title));

        $this->applyOptionalFields($job, $payload);

        $this->jobRepository->save($job);

        return new JsonResponse([
            'id' => $job->getId(),
            'recruitId' => $recruit->getId(),
            'slug' => $job->getSlug(),
            'title' => $job->getTitle(),
        ], JsonResponse::HTTP_CREATED);
    }

    /** @param array<string, mixed> $payload */
    private function applyOptionalFields(Job $job, array $payload): void
    {
        $location = $payload['location'] ?? null;
        if ($location !== null) {
            if (!is_string($location)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "location" must be a string.');
            }

            $job->setLocation($location);
        }

        $summary = $payload['summary'] ?? null;
        if ($summary !== null) {
            if (!is_string($summary)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "summary" must be a string.');
            }

            $job->setSummary($summary);
        }

        $missionTitle = $payload['missionTitle'] ?? null;
        if ($missionTitle !== null) {
            if (!is_string($missionTitle)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "missionTitle" must be a string.');
            }

            $job->setMissionTitle($missionTitle);
        }

        $missionDescription = $payload['missionDescription'] ?? null;
        if ($missionDescription !== null) {
            if (!is_string($missionDescription)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "missionDescription" must be a string.');
            }

            $job->setMissionDescription($missionDescription);
        }

        $matchScore = $payload['matchScore'] ?? null;
        if ($matchScore !== null) {
            if (!is_int($matchScore)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "matchScore" must be an integer.');
            }

            $job->setMatchScore($matchScore);
        }

        $contractType = $payload['contractType'] ?? null;
        if ($contractType !== null) {
            if (!is_string($contractType) || ContractType::tryFrom($contractType) === null) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "contractType" has an invalid value.');
            }

            $job->setContractType($contractType);
        }

        $workMode = $payload['workMode'] ?? null;
        if ($workMode !== null) {
            if (!is_string($workMode) || WorkMode::tryFrom($workMode) === null) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "workMode" has an invalid value.');
            }

            $job->setWorkMode($workMode);
        }

        $schedule = $payload['schedule'] ?? null;
        if ($schedule !== null) {
            if (!is_string($schedule) || Schedule::tryFrom($schedule) === null) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "schedule" has an invalid value.');
            }

            $job->setSchedule($schedule);
        }

        $responsibilities = $payload['responsibilities'] ?? null;
        if ($responsibilities !== null) {
            if (!is_array($responsibilities)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "responsibilities" must be an array.');
            }

            $job->setResponsibilities($responsibilities);
        }

        $profile = $payload['profile'] ?? null;
        if ($profile !== null) {
            if (!is_array($profile)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "profile" must be an array.');
            }

            $job->setProfile($profile);
        }

        $benefits = $payload['benefits'] ?? null;
        if ($benefits !== null) {
            if (!is_array($benefits)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "benefits" must be an array.');
            }

            $job->setBenefits($benefits);
        }
    }
}
