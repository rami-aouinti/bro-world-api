<?php

declare(strict_types=1);

namespace App\Tests\Application\Recruit\Transport\Controller\Api\V1\Job;

use App\General\Domain\Utils\JSON;
use App\Platform\Domain\Entity\Application as PlatformApplication;
use App\Tests\TestCase\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

class PrivateJobStatsControllerTest extends WebTestCase
{
    #[TestDox('Test that GET /v1/recruit/applications/{applicationSlug}/private/jobs/stats returns aggregate statistics for owner.')]
    public function testThatPrivateJobStatsReturnsAggregates(): void
    {
        $applicationSlug = $this->getApplicationSlug();

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('GET', self::API_URL_PREFIX . '/v1/recruit/applications/' . $applicationSlug . '/private/jobs/stats');

        $response = $client->getResponse();
        $content = $response->getContent();

        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);
        self::assertArrayHasKey('total', $payload);
        self::assertArrayHasKey('published', $payload);
        self::assertArrayHasKey('draft', $payload);
        self::assertArrayHasKey('byContractType', $payload);
        self::assertArrayHasKey('byWorkMode', $payload);
        self::assertArrayHasKey('byExperienceLevel', $payload);
    }

    private function getApplicationSlug(): string
    {
        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $application = $entityManager->getRepository(PlatformApplication::class)->findOneBy([
            'title' => 'Recruit Lite App',
        ]);

        self::assertInstanceOf(PlatformApplication::class, $application);

        return $application->getSlug();
    }
}
