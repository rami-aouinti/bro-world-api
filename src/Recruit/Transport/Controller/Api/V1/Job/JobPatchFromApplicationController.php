<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Job;

use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use App\Recruit\Domain\Enum\ContractType;
use App\Recruit\Domain\Enum\Schedule;
use App\Recruit\Domain\Enum\WorkMode;
use App\Recruit\Infrastructure\Repository\JobRepository;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
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
class JobPatchFromApplicationController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly JobRepository $jobRepository,
    ) {
    }

    #[Route(path: '/v1/recruit/applications/{applicationSlug}/jobs/{jobId}', methods: [Request::METHOD_PATCH])]
    #[OA\Patch(
        summary: 'Met à jour un job via applicationSlug et contrôle propriétaire.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'title', type: 'string'),
                    new OA\Property(property: 'location', type: 'string'),
                    new OA\Property(property: 'summary', type: 'string'),
                    new OA\Property(property: 'missionTitle', type: 'string'),
                    new OA\Property(property: 'missionDescription', type: 'string'),
                    new OA\Property(property: 'matchScore', type: 'integer'),
                    new OA\Property(property: 'contractType', type: 'string', enum: ['CDI', 'CDD', 'Freelance', 'Internship']),
                    new OA\Property(property: 'workMode', type: 'string', enum: ['Onsite', 'Remote', 'Hybrid']),
                    new OA\Property(property: 'schedule', type: 'string', enum: ['Vollzeit', 'Teilzeit', 'Contract']),
                    new OA\Property(property: 'responsibilities', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'profile', type: 'array', items: new OA\Items(type: 'string')),
                    new OA\Property(property: 'benefits', type: 'array', items: new OA\Items(type: 'string')),
                ],
            ),
        ),
        parameters: [
            new OA\Parameter(name: 'applicationSlug', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'jobId', in: 'path', required: true, schema: new OA\Schema(type: 'string', format: 'uuid')),
        ],
    )]
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
        $this->applyPatchFields($job, $payload);

        $this->jobRepository->save($job);

        return new JsonResponse([
            'id' => $job->getId(),
            'recruitId' => $recruit->getId(),
            'slug' => $job->getSlug(),
            'title' => $job->getTitle(),
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function applyPatchFields(Job $job, array $payload): void
    {
        $title = $payload['title'] ?? null;
        if ($title !== null) {
            if (!is_string($title) || trim($title) === '') {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "title" must be a non-empty string.');
            }

            $job->setTitle(trim($title));
        }

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
    private function resolveRecruitByApplicationSlug(string $applicationSlug): Recruit
    {
        $recruit = $this->entityManager
            ->getRepository(Recruit::class)
            ->createQueryBuilder('recruit')
            ->innerJoin('recruit.application', 'application')
            ->addSelect('application')
            ->where('application.slug = :applicationSlug')
            ->setParameter('applicationSlug', $applicationSlug)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$recruit instanceof Recruit) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Unknown "applicationSlug".');
        }

        return $recruit;
    }

}
