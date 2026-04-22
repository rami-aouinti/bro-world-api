<?php

declare(strict_types=1);

namespace App\Tests\Application\Recruit\Transport\Controller\Api\V1\Job;

use App\General\Domain\Utils\JSON;
use App\Platform\Domain\Entity\Application as PlatformApplication;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use App\Tests\TestCase\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

use function count;

class PublicJobListControllerTest extends WebTestCase
{
    #[TestDox('Test that `GET /v1/recruit/applications/{applicationSlug}/public/jobs` filters by experienceLevel.')]
    public function testThatPublicJobListFiltersByExperienceLevel(): void
    {
        [$applicationSlug, $referenceJob] = $this->getFixtureContext();

        $client = $this->getTestClient();
        $client->request(
            'GET',
            self::API_URL_PREFIX . '/v1/recruit/applications/' . $applicationSlug . '/public/jobs?limit=100&experienceLevel=' . $referenceJob->getExperienceLevelValue(),
        );

        $response = $client->getResponse();
        $content = $response->getContent();

        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);

        self::assertIsArray($payload['items'] ?? null);
        self::assertGreaterThan(0, count($payload['items']));

        foreach ($payload['items'] as $item) {
            self::assertSame($referenceJob->getExperienceLevelValue(), $item['experienceLevel'] ?? null);
        }
    }

    #[TestDox('Test that `GET /v1/recruit/applications/{applicationSlug}/public/jobs` filters by yearsExperience range intersection.')]
    public function testThatPublicJobListFiltersByYearsExperienceIntersection(): void
    {
        [$applicationSlug, $referenceJob] = $this->getFixtureContext();

        $yearsExperienceMin = $referenceJob->getYearsExperienceMin() + 1;
        $yearsExperienceMax = $referenceJob->getYearsExperienceMax() - 1;

        $client = $this->getTestClient();
        $client->request(
            'GET',
            self::API_URL_PREFIX . '/v1/recruit/applications/' . $applicationSlug . '/public/jobs?limit=100&yearsExperienceMin=' . $yearsExperienceMin . '&yearsExperienceMax=' . $yearsExperienceMax,
        );

        $response = $client->getResponse();
        $content = $response->getContent();

        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);

        self::assertIsArray($payload['items'] ?? null);
        self::assertGreaterThan(0, count($payload['items']));

        foreach ($payload['items'] as $item) {
            self::assertGreaterThanOrEqual($yearsExperienceMin, (int)($item['yearsExperienceMax'] ?? 0));
            self::assertLessThanOrEqual($yearsExperienceMax, (int)($item['yearsExperienceMin'] ?? 0));
        }
    }

    #[TestDox('Test that `GET /v1/recruit/applications/{applicationSlug}/public/jobs` keeps the expected job payload structure.')]
    public function testThatPublicJobListKeepsExpectedPayloadStructure(): void
    {
        [$applicationSlug] = $this->getFixtureContext();

        $client = $this->getTestClient();
        $client->request('GET', self::API_URL_PREFIX . '/v1/recruit/applications/' . $applicationSlug . '/public/jobs?limit=1');

        $response = $client->getResponse();
        $content = $response->getContent();

        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);
        self::assertIsArray($payload['items'] ?? null);
        self::assertNotEmpty($payload['items']);

        $item = $payload['items'][0];
        self::assertIsArray($item);
        self::assertArrayHasKey('missionTitle', $item);
        self::assertArrayHasKey('missionDescription', $item);
        self::assertArrayHasKey('responsibilities', $item);
        self::assertArrayHasKey('benefits', $item);
        self::assertArrayHasKey('matchScore', $item);
        self::assertArrayNotHasKey('owner', $item);
        self::assertArrayNotHasKey('apply', $item);
    }

    /**
     * @return array{0: string, 1: Job}
     */
    private function getFixtureContext(): array
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $application = $entityManager->getRepository(PlatformApplication::class)->findOneBy([
            'title' => 'Recruit Lite App',
        ]);
        self::assertInstanceOf(PlatformApplication::class, $application);

        $recruit = $entityManager->getRepository(Recruit::class)->findOneBy([
            'application' => $application,
        ]);
        self::assertInstanceOf(Recruit::class, $recruit);

        $job = $entityManager->getRepository(Job::class)->findOneBy([
            'recruit' => $recruit,
            'isPublished' => true,
        ], [
            'createdAt' => 'DESC',
            'id' => 'DESC',
        ]);
        self::assertInstanceOf(Job::class, $job);

        return [$application->getSlug(), $job];
    }
}
