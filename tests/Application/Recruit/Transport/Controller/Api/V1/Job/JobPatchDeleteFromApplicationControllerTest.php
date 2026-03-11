<?php

declare(strict_types=1);

namespace App\Tests\Application\Recruit\Transport\Controller\Api\V1\Job;

use App\General\Domain\Utils\JSON;
use App\Platform\Domain\Entity\Application as PlatformApplication;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use App\Tests\TestCase\WebTestCase;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class JobPatchDeleteFromApplicationControllerTest extends WebTestCase
{
    /**
     * @throws Throwable
     */
    #[TestDox('Test that PATCH /v1/recruit/applications/{applicationSlug}/jobs/{jobId} updates job for owner.')]
    public function testThatPatchFromApplicationUpdatesJob(): void
    {
        [$applicationSlug, $jobId] = $this->getApplicationSlugAndJobIdForUsername('john-root');

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('PATCH', self::API_URL_PREFIX . '/v1/recruit/applications/' . $applicationSlug . '/jobs/' . $jobId, content: JSON::encode([
            'title' => 'Updated job title',
            'location' => 'Lyon',
            'workMode' => 'REMOTE',
        ]));

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);
        self::assertSame('Updated job title', $payload['title']);
    }


    /**
     * @throws Throwable
     */
    #[TestDox('Test that PATCH /v1/recruit/applications/{applicationSlug}/jobs/{jobId} returns bad request for unknown application slug.')]
    public function testThatPatchFromApplicationRejectsUnknownApplicationSlug(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('PATCH', self::API_URL_PREFIX . '/v1/recruit/applications/unknown-slug/jobs/11111111-1111-1111-1111-111111111111', content: JSON::encode([
            'title' => 'Should fail',
        ]));

        self::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that PATCH /v1/recruit/applications/{applicationSlug}/jobs/{jobId} returns not found for missing job.')]
    public function testThatPatchFromApplicationReturnsNotFoundWhenJobIsMissing(): void
    {
        [$applicationSlug, ] = $this->getApplicationSlugAndJobIdForUsername('john-root');

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('PATCH', self::API_URL_PREFIX . '/v1/recruit/applications/' . $applicationSlug . '/jobs/11111111-1111-1111-1111-111111111111', content: JSON::encode([
            'title' => 'Should fail',
        ]));

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that PATCH /v1/recruit/applications/{applicationSlug}/jobs/{jobId} forbids non owner.')]
    public function testThatPatchFromApplicationRejectsForeignApplication(): void
    {
        [$applicationSlug, $jobId] = $this->getApplicationSlugAndJobIdForUsername('john-root');

        $client = $this->getTestClient('john-user', 'password-user');
        $client->request('PATCH', self::API_URL_PREFIX . '/v1/recruit/applications/' . $applicationSlug . '/jobs/' . $jobId, content: JSON::encode([
            'title' => 'Should fail',
        ]));

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that DELETE /v1/recruit/applications/{applicationSlug}/jobs/{jobId} deletes job for owner.')]
    public function testThatDeleteFromApplicationDeletesJob(): void
    {
        [$applicationSlug, $jobId] = $this->createDedicatedJobForUser('john-root');

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('DELETE', self::API_URL_PREFIX . '/v1/recruit/applications/' . $applicationSlug . '/jobs/' . $jobId);

        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $deletedJob = $entityManager->getRepository(Job::class)->find($jobId);
        self::assertNull($deletedJob);
    }


    /**
     * @throws Throwable
     */
    #[TestDox('Test that DELETE /v1/recruit/applications/{applicationSlug}/jobs/{jobId} returns bad request for unknown application slug.')]
    public function testThatDeleteFromApplicationRejectsUnknownApplicationSlug(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('DELETE', self::API_URL_PREFIX . '/v1/recruit/applications/unknown-slug/jobs/11111111-1111-1111-1111-111111111111');

        self::assertSame(Response::HTTP_BAD_REQUEST, $client->getResponse()->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that DELETE /v1/recruit/applications/{applicationSlug}/jobs/{jobId} returns forbidden for foreign application.')]
    public function testThatDeleteFromApplicationRejectsForeignApplication(): void
    {
        [$applicationSlug, $jobId] = $this->createDedicatedJobForUser('john-root');

        $client = $this->getTestClient('john-user', 'password-user');
        $client->request('DELETE', self::API_URL_PREFIX . '/v1/recruit/applications/' . $applicationSlug . '/jobs/' . $jobId);

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    /**
     * @throws Throwable
     */
    #[TestDox('Test that DELETE /v1/recruit/applications/{applicationSlug}/jobs/{jobId} returns not found for missing job.')]
    public function testThatDeleteFromApplicationReturnsNotFoundWhenJobIsMissing(): void
    {
        [$applicationSlug, ] = $this->getApplicationSlugAndJobIdForUsername('john-root');

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('DELETE', self::API_URL_PREFIX . '/v1/recruit/applications/' . $applicationSlug . '/jobs/11111111-1111-1111-1111-111111111111');

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }


    /**
     * @return array{0: string, 1: string}
     */
    private function getApplicationSlugAndJobIdForUsername(string $username): array
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

        return [$application->getSlug(), $job->getId()];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function createDedicatedJobForUser(string $username): array
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

        $job = (new Job())
            ->setRecruit($recruit)
            ->setOwner($user)
            ->setTitle('Delete me')
            ->ensureGeneratedSlug();

        $entityManager->persist($job);
        $entityManager->flush();

        return [$application->getSlug(), $job->getId()];
    }
}
