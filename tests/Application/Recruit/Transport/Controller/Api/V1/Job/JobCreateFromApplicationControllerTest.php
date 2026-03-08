<?php

declare(strict_types=1);

namespace App\Tests\Application\Recruit\Transport\Controller\Api\V1\Job;

use App\General\Domain\Utils\JSON;
use App\Platform\Domain\Entity\Application as PlatformApplication;
use App\User\Domain\Entity\User;
use App\Tests\TestCase\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class JobCreateFromApplicationControllerTest extends WebTestCase
{
    /** @throws Throwable */
    #[TestDox('Test that `POST /v1/recruit/applications/{applicationSlug}/jobs` creates a job for owner without requiring matchScore.')]
    public function testThatCreateFromApplicationCreatesJobWithoutMatchScore(): void
    {
        $applicationSlug = $this->getApplicationSlugForUsername('john-root');

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('POST', self::API_URL_PREFIX . '/v1/recruit/applications/' . $applicationSlug . '/jobs', content: JSON::encode([
            'title' => 'Backend Engineer API',
            'location' => 'Paris',
            'contractType' => 'CDI',
            'matchScore' => 'should-be-ignored',
        ]));

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);
        self::assertArrayHasKey('id', $payload);
        self::assertArrayHasKey('recruitId', $payload);
        self::assertArrayHasKey('slug', $payload);
        self::assertArrayHasKey('applicationSlug', $payload);
        self::assertSame($applicationSlug, $payload['applicationSlug']);
        self::assertSame('Backend Engineer API', $payload['title']);
    }

    /** @throws Throwable */
    #[TestDox('Test that `POST /v1/recruit/applications/{applicationSlug}/jobs` returns forbidden if application is not owned by logged user.')]
    public function testThatCreateFromApplicationRejectsForeignApplication(): void
    {
        $applicationSlug = $this->getApplicationSlugForUsername('john-root');

        $client = $this->getTestClient('john-user', 'password-user');
        $client->request('POST', self::API_URL_PREFIX . '/v1/recruit/applications/' . $applicationSlug . '/jobs', content: JSON::encode([
            'title' => 'Should fail',
        ]));

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    private function getApplicationSlugForUsername(string $username): string
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

        return $application->getSlug();
    }
}
