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
    private string $baseUrl = self::API_URL_PREFIX . '/v1/recruit/jobs';

    /** @throws Throwable */
    #[TestDox('Test that `POST /v1/recruit/jobs` creates a job from applicationId for the owner.')]
    public function testThatCreateFromApplicationCreatesJob(): void
    {
        $applicationId = $this->getApplicationIdForUsername('john-root');

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('POST', $this->baseUrl, content: JSON::encode([
            'applicationId' => $applicationId,
            'title' => 'Backend Engineer API',
            'location' => 'Paris',
            'contractType' => 'CDI',
        ]));

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);
        self::assertArrayHasKey('id', $payload);
        self::assertArrayHasKey('recruitId', $payload);
        self::assertArrayHasKey('slug', $payload);
        self::assertSame('Backend Engineer API', $payload['title']);
    }

    /** @throws Throwable */
    #[TestDox('Test that `POST /v1/recruit/jobs` returns forbidden if application is not owned by logged user.')]
    public function testThatCreateFromApplicationRejectsForeignApplication(): void
    {
        $applicationId = $this->getApplicationIdForUsername('john-root');

        $client = $this->getTestClient('john-user', 'password-user');
        $client->request('POST', $this->baseUrl, content: JSON::encode([
            'applicationId' => $applicationId,
            'title' => 'Should fail',
        ]));

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    private function getApplicationIdForUsername(string $username): string
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

        return $application->getId();
    }
}
