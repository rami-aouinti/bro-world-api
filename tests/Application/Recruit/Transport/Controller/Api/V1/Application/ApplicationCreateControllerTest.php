<?php

declare(strict_types=1);

namespace App\Tests\Application\Recruit\Transport\Controller\Api\V1\Application;

use App\General\Domain\Utils\JSON;
use App\Platform\Domain\Entity\Application as PlatformApplication;
use App\Recruit\Domain\Entity\Applicant;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use App\Tests\TestCase\WebTestCase;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class ApplicationCreateControllerTest extends WebTestCase
{
    /**
     * @throws Throwable
     */
    #[TestDox('Test that POST /v1/recruit/applications?applicationSlug={applicationSlug} rejects a job from another application.')]
    public function testThatCreateRejectsJobApplicationSlugMismatch(): void
    {
        $rootContext = $this->getApplicantAndApplicationSlugForUsername('john-root');
        $foreignJobId = $this->getAnyJobIdForUsername('john-user');

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('POST', self::API_URL_PREFIX . '/v1/recruit/applications/' . $rootContext['applicationSlug'] . '/applications', content: JSON::encode([
            'applicantId' => $rootContext['applicantId'],
            'jobId' => $foreignJobId,
        ]));

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that POST /v1/recruit/applications?applicationSlug={applicationSlug} rejects duplicate active applications for same applicant and job.')]
    public function testThatCreateRejectsDuplicateActiveApplication(): void
    {
        [$applicationSlug, $applicantId, $jobId] = $this->createDedicatedContextForUsername('john-root');

        $client = $this->getTestClient('john-root', 'password-root');
        $payload = JSON::encode([
            'applicantId' => $applicantId,
            'jobId' => $jobId,
        ]);

        $client->request('POST', self::API_URL_PREFIX . '/v1/recruit/applications/' . $applicationSlug . '/applications', content: $payload);
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $client->request('POST', self::API_URL_PREFIX . '/v1/recruit/applications/' . $applicationSlug . '/applications', content: $payload);
        self::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
    }

    /**
     * @return array{applicationSlug: string, applicantId: string}
     */
    private function getApplicantAndApplicationSlugForUsername(string $username): array
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = $entityManager->getRepository(User::class)->findOneBy([
            'username' => $username,
        ]);
        self::assertInstanceOf(User::class, $user);

        $application = $entityManager->getRepository(PlatformApplication::class)->findOneBy([
            'user' => $user,
            'title' => 'Recruit Lite App',
        ]);
        self::assertInstanceOf(PlatformApplication::class, $application);

        $applicant = $entityManager->getRepository(Applicant::class)->findOneBy([
            'user' => $user,
        ]);
        self::assertInstanceOf(Applicant::class, $applicant);

        return [
            'applicationSlug' => $application->getSlug(),
            'applicantId' => $applicant->getId(),
        ];
    }

    private function getAnyJobIdForUsername(string $username): string
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = $entityManager->getRepository(User::class)->findOneBy([
            'username' => $username,
        ]);
        self::assertInstanceOf(User::class, $user);

        $application = $entityManager->getRepository(PlatformApplication::class)->findOneBy([
            'user' => $user,
            'title' => 'Recruit Lite App',
        ]);
        self::assertInstanceOf(PlatformApplication::class, $application);

        $recruit = $entityManager->getRepository(Recruit::class)->findOneBy([
            'application' => $application,
        ]);
        self::assertInstanceOf(Recruit::class, $recruit);

        $job = $entityManager->getRepository(Job::class)->findOneBy([
            'recruit' => $recruit,
        ]);
        self::assertInstanceOf(Job::class, $job);

        return $job->getId();
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function createDedicatedContextForUsername(string $username): array
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = $entityManager->getRepository(User::class)->findOneBy([
            'username' => $username,
        ]);
        self::assertInstanceOf(User::class, $user);

        $application = $entityManager->getRepository(PlatformApplication::class)->findOneBy([
            'user' => $user,
            'title' => 'Recruit Lite App',
        ]);
        self::assertInstanceOf(PlatformApplication::class, $application);

        $recruit = $entityManager->getRepository(Recruit::class)->findOneBy([
            'application' => $application,
        ]);
        self::assertInstanceOf(Recruit::class, $recruit);

        $applicant = $entityManager->getRepository(Applicant::class)->findOneBy([
            'user' => $user,
        ]);
        self::assertInstanceOf(Applicant::class, $applicant);

        $job = (new Job())
            ->setRecruit($recruit)
            ->setOwner($user)
            ->setTitle('Application create controller dedicated job')
            ->ensureGeneratedSlug();

        $entityManager->persist($job);
        $entityManager->flush();

        return [$application->getSlug(), $applicant->getId(), $job->getId()];
    }
}
