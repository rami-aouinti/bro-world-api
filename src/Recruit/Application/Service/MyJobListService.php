<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Entity\Application as RecruitApplication;
use App\Recruit\Domain\Entity\Job;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

use function array_map;

class MyJobListService
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @return array<string, list<array<string, mixed>>>
     */
    public function getList(User $loggedInUser): array
    {
        /** @var list<Job> $createdJobs */
        $createdJobs = $this->entityManager
            ->getRepository(Job::class)
            ->createQueryBuilder('job')
            ->innerJoin('job.recruit', 'recruit')
            ->innerJoin('recruit.application', 'platformApplication')
            ->innerJoin('platformApplication.user', 'owner')
            ->leftJoin('job.company', 'company')->addSelect('company')
            ->andWhere('owner = :owner')
            ->setParameter('owner', $loggedInUser)
            ->orderBy('job.createdAt', 'DESC')
            ->addOrderBy('job.id', 'DESC')
            ->getQuery()
            ->getResult();

        /** @var list<RecruitApplication> $appliedApplications */
        $appliedApplications = $this->entityManager
            ->getRepository(RecruitApplication::class)
            ->createQueryBuilder('application')
            ->innerJoin('application.applicant', 'applicant')
            ->innerJoin('applicant.user', 'user')
            ->innerJoin('application.job', 'job')->addSelect('job')
            ->leftJoin('job.company', 'company')->addSelect('company')
            ->andWhere('user = :user')
            ->setParameter('user', $loggedInUser)
            ->orderBy('application.createdAt', 'DESC')
            ->addOrderBy('application.id', 'DESC')
            ->getQuery()
            ->getResult();

        return [
            'createdJobs' => array_map(static fn (Job $job): array => [
                'id' => $job->getId(),
                'slug' => $job->getSlug(),
                'title' => $job->getTitle(),
                'company' => $job->getCompany()?->getName() ?? '',
                'location' => $job->getLocation(),
                'contractType' => $job->getContractTypeValue(),
                'workMode' => $job->getWorkModeValue(),
                'schedule' => $job->getScheduleValue(),
                'createdAt' => $job->getCreatedAt()?->format(DATE_ATOM),
                'owner' => true,
                'apply' => false,
            ], $createdJobs),
            'appliedJobs' => array_map(static fn (RecruitApplication $application): array => [
                'applicationId' => $application->getId(),
                'status' => $application->getStatusValue(),
                'appliedAt' => $application->getCreatedAt()?->format(DATE_ATOM),
                'job' => [
                    'id' => $application->getJob()->getId(),
                    'slug' => $application->getJob()->getSlug(),
                    'title' => $application->getJob()->getTitle(),
                    'company' => $application->getJob()->getCompany()?->getName() ?? '',
                    'location' => $application->getJob()->getLocation(),
                    'contractType' => $application->getJob()->getContractTypeValue(),
                    'workMode' => $application->getJob()->getWorkModeValue(),
                    'schedule' => $application->getJob()->getScheduleValue(),
                    'owner' => false,
                    'apply' => true,
                ],
            ], $appliedApplications),
        ];
    }
}
