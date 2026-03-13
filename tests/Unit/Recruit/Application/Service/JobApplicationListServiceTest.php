<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruit\Application\Service;

use App\Recruit\Application\Service\JobApplicationListService;
use App\Recruit\Domain\Entity\Applicant;
use App\Recruit\Domain\Entity\Application;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Infrastructure\Repository\JobRepository;
use App\User\Domain\Entity\User;
use Doctrine\ORM\AbstractQuery;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\TestCase;

final class JobApplicationListServiceTest extends TestCase
{
    public function testGetListReturnsNullResumeIdWhenApplicantHasNoResume(): void
    {
        $loggedInUser = $this->createMock(User::class);
        $owner = $this->createMock(User::class);
        $job = $this->createMock(Job::class);
        $application = $this->createMock(Application::class);
        $applicant = $this->createMock(Applicant::class);
        $applicantUser = $this->createMock(User::class);

        $loggedInUser->method('getId')->willReturn('owner-id');
        $owner->method('getId')->willReturn('owner-id');
        $job->method('getOwner')->willReturn($owner);

        $application->method('getId')->willReturn('application-id');
        $application->method('getStatusValue')->willReturn('in_progress');
        $application->method('getCreatedAt')->willReturn(null);
        $application->method('getApplicant')->willReturn($applicant);

        $applicant->method('getId')->willReturn('applicant-id');
        $applicant->method('getCoverLetter')->willReturn('cover-letter');
        $applicant->method('getUser')->willReturn($applicantUser);
        $applicant->method('getResume')->willReturn(null);

        $applicantUser->method('getId')->willReturn('user-id');
        $applicantUser->method('getUsername')->willReturn('applicant-user');
        $applicantUser->method('getFirstName')->willReturn('John');
        $applicantUser->method('getLastName')->willReturn('Doe');
        $applicantUser->method('getEmail')->willReturn('john@example.test');

        $jobRepository = $this->createMock(JobRepository::class);
        $jobRepository->method('find')->with('job-id')->willReturn($job);

        $query = $this->createMock(AbstractQuery::class);
        $query->method('getResult')->willReturn([$application]);

        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->method('innerJoin')->willReturnSelf();
        $queryBuilder->method('addSelect')->willReturnSelf();
        $queryBuilder->method('leftJoin')->willReturnSelf();
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('orderBy')->willReturnSelf();
        $queryBuilder->method('addOrderBy')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository = $this->createMock(EntityRepository::class);
        $repository->method('createQueryBuilder')->with('application')->willReturn($queryBuilder);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getRepository')->with(Application::class)->willReturn($repository);

        $service = new JobApplicationListService($jobRepository, $entityManager);

        $result = $service->getList($loggedInUser, 'job-id', null);

        self::assertSame([
            [
                'id' => 'application-id',
                'status' => 'in_progress',
                'createdAt' => null,
                'applicant' => [
                    'id' => 'applicant-id',
                    'coverLetter' => 'cover-letter',
                    'user' => [
                        'id' => 'user-id',
                        'username' => 'applicant-user',
                        'firstName' => 'John',
                        'lastName' => 'Doe',
                        'email' => 'john@example.test',
                    ],
                    'resume' => [
                        'id' => null,
                    ],
                ],
            ],
        ], $result);
    }
}
