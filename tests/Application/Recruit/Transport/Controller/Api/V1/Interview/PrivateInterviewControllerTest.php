<?php

declare(strict_types=1);

namespace App\Tests\Application\Recruit\Transport\Controller\Api\V1\Interview;

use App\General\Domain\Utils\JSON;
use App\Platform\Domain\Entity\Application as PlatformApplication;
use App\Recruit\Domain\Entity\Applicant;
use App\Recruit\Domain\Entity\Application;
use App\Recruit\Domain\Entity\Interview;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use App\Recruit\Domain\Enum\ApplicationStatus;
use App\Tests\TestCase\WebTestCase;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PrivateInterviewControllerTest extends WebTestCase
{
    /** @throws Throwable */
    #[TestDox('POST /v1/recruit/private/applications/{applicationId}/interviews creates interview for owner.')]
    public function testCreateInterview(): void
    {
        [$applicationId] = $this->createDedicatedApplicationContext('john-root');

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('POST', self::API_URL_PREFIX . '/v1/recruit/private/applications/' . $applicationId . '/interviews', content: JSON::encode([
            'scheduledAt' => (new DateTimeImmutable('+2 days'))->format(DATE_ATOM),
            'durationMinutes' => 60,
            'mode' => 'visio',
            'locationOrUrl' => 'https://meet.example/room',
            'interviewerIds' => ['u-1', 'u-2'],
            'notes' => 'Premier entretien',
        ]));

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
    }

    /** @throws Throwable */
    #[TestDox('POST /v1/recruit/private/applications/{applicationId}/interviews rejects REJECTED/HIRED applications.')]
    public function testCreateInterviewRejectsClosedApplicationStatus(): void
    {
        [$applicationId] = $this->createDedicatedApplicationContext('john-root', ApplicationStatus::REJECTED);

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('POST', self::API_URL_PREFIX . '/v1/recruit/private/applications/' . $applicationId . '/interviews', content: JSON::encode([
            'scheduledAt' => (new DateTimeImmutable('+2 days'))->format(DATE_ATOM),
            'durationMinutes' => 60,
            'mode' => 'visio',
            'locationOrUrl' => 'https://meet.example/room',
            'interviewerIds' => [],
        ]));

        self::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
    }

    /** @throws Throwable */
    #[TestDox('PATCH /v1/recruit/private/interviews/{interviewId} updates interview.')]
    public function testPatchInterview(): void
    {
        [$applicationId, $interviewId] = $this->createDedicatedInterview('john-root');
        self::assertNotEmpty($applicationId);

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('PATCH', self::API_URL_PREFIX . '/v1/recruit/private/interviews/' . $interviewId, content: JSON::encode([
            'durationMinutes' => 90,
            'status' => 'done',
        ]));

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    /** @throws Throwable */
    #[TestDox('GET /v1/recruit/private/applications/{applicationId}/interviews lists interviews.')]
    public function testListInterviews(): void
    {
        [$applicationId] = $this->createDedicatedInterview('john-root');

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('GET', self::API_URL_PREFIX . '/v1/recruit/private/applications/' . $applicationId . '/interviews');

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $content = $client->getResponse()->getContent();
        self::assertNotFalse($content);
        $payload = JSON::decode($content, true);
        self::assertIsArray($payload);
        self::assertNotEmpty($payload);
    }

    /** @throws Throwable */
    #[TestDox('DELETE /v1/recruit/private/interviews/{interviewId} deletes interview.')]
    public function testDeleteInterview(): void
    {
        [, $interviewId] = $this->createDedicatedInterview('john-root');

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('DELETE', self::API_URL_PREFIX . '/v1/recruit/private/interviews/' . $interviewId);

        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());
    }

    /** @throws Throwable */
    #[TestDox('Interview endpoints are forbidden for ROLE_USER.')]
    public function testInterviewListIsForbiddenForRegularUserRole(): void
    {
        [$applicationId] = $this->createDedicatedInterview('john-root');

        $client = $this->getTestClient('john-user', 'password-user');
        $client->request('GET', self::API_URL_PREFIX . '/v1/recruit/private/applications/' . $applicationId . '/interviews');

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    /**
     * @return array{0: string, 1?: string}
     */
    private function createDedicatedApplicationContext(string $username, ApplicationStatus $status = ApplicationStatus::WAITING): array
    {
        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = $entityManager->getRepository(User::class)->findOneBy(['username' => $username]);
        self::assertInstanceOf(User::class, $user);

        $platformApplication = $entityManager->getRepository(PlatformApplication::class)->findOneBy([
            'user' => $user,
            'title' => 'Recruit Lite App',
        ]);
        self::assertInstanceOf(PlatformApplication::class, $platformApplication);

        $recruit = $entityManager->getRepository(Recruit::class)->findOneBy(['application' => $platformApplication]);
        self::assertInstanceOf(Recruit::class, $recruit);

        $job = (new Job())
            ->setRecruit($recruit)
            ->setOwner($user)
            ->setTitle('Interview flow job')
            ->ensureGeneratedSlug();
        $entityManager->persist($job);

        $applicant = $entityManager->getRepository(Applicant::class)->findOneBy(['user' => $user]);
        self::assertInstanceOf(Applicant::class, $applicant);

        $application = (new Application())
            ->setApplicant($applicant)
            ->setJob($job)
            ->setStatus($status);

        $entityManager->persist($application);
        $entityManager->flush();

        return [$application->getId()];
    }

    /** @return array{0: string, 1: string} */
    private function createDedicatedInterview(string $username): array
    {
        [$applicationId] = $this->createDedicatedApplicationContext($username);

        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $application = $entityManager->getRepository(Application::class)->find($applicationId);
        self::assertInstanceOf(Application::class, $application);

        $interview = (new Interview())
            ->setApplication($application)
            ->setScheduledAt(new DateTimeImmutable('+1 day'))
            ->setDurationMinutes(45)
            ->setMode('visio')
            ->setLocationOrUrl('https://meet.example/seed')
            ->setInterviewerIds(['seed-user'])
            ->setStatus('planned');

        $entityManager->persist($interview);
        $entityManager->flush();

        return [$applicationId, $interview->getId()];
    }
}
