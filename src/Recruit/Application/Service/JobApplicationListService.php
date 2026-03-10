<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Entity\Application;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Infrastructure\Repository\JobRepository;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

use function array_map;
use function is_string;
use function trim;

class JobApplicationListService
{
    public function __construct(
        private readonly JobRepository $jobRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getList(User $loggedInUser, ?string $jobId, ?string $jobSlug): array
    {
        $job = $this->resolveJob($jobId, $jobSlug);
        $this->assertJobOwnership($job, $loggedInUser);

        /** @var list<Application> $applications */
        $applications = $this->entityManager
            ->getRepository(Application::class)
            ->createQueryBuilder('application')
            ->innerJoin('application.applicant', 'applicant')->addSelect('applicant')
            ->innerJoin('applicant.user', 'user')->addSelect('user')
            ->leftJoin('applicant.resume', 'resume')->addSelect('resume')
            ->andWhere('application.job = :job')
            ->setParameter('job', $job->getId(), UuidBinaryOrderedTimeType::NAME)
            ->orderBy('application.createdAt', 'DESC')
            ->addOrderBy('application.id', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(static function (Application $application): array {
            $applicant = $application->getApplicant();
            $applicantUser = $applicant->getUser();

            return [
                'id' => $application->getId(),
                'status' => $application->getStatusValue(),
                'createdAt' => $application->getCreatedAt()?->format(DATE_ATOM),
                'applicant' => [
                    'id' => $applicant->getId(),
                    'coverLetter' => $applicant->getCoverLetter(),
                    'user' => [
                        'id' => $applicantUser->getId(),
                        'username' => $applicantUser->getUsername(),
                        'firstName' => $applicantUser->getFirstName(),
                        'lastName' => $applicantUser->getLastName(),
                        'email' => $applicantUser->getEmail(),
                    ],
                    'resume' => [
                        'id' => $applicant->getResume()->getId(),
                    ],
                ],
            ];
        }, $applications);
    }

    private function resolveJob(?string $jobId, ?string $jobSlug): Job
    {
        $cleanJobId = is_string($jobId) ? trim($jobId) : '';
        $cleanJobSlug = is_string($jobSlug) ? trim($jobSlug) : '';

        if ($cleanJobId === '' && $cleanJobSlug === '') {
            throw new BadRequestHttpException('One of "jobId" or "jobSlug" query parameters must be provided.');
        }

        if ($cleanJobId !== '') {
            $job = $this->jobRepository->find($cleanJobId);

            if ($job instanceof Job) {
                return $job;
            }
        }

        if ($cleanJobSlug !== '') {
            $job = $this->jobRepository->findOneBy([
                'slug' => $cleanJobSlug,
            ]);

            if ($job instanceof Job) {
                return $job;
            }
        }

        throw new NotFoundHttpException('Job not found for the provided "jobId" or "jobSlug".');
    }

    private function assertJobOwnership(Job $job, User $loggedInUser): void
    {
        $ownerId = $job->getOwner()?->getId();

        if ($ownerId === null || $ownerId !== $loggedInUser->getId()) {
            throw new AccessDeniedHttpException('You are not allowed to access applications for this job.');
        }
    }
}
