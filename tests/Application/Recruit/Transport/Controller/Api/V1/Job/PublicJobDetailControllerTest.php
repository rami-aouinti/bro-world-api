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

class PublicJobDetailControllerTest extends WebTestCase
{
    #[TestDox('Test that GET /v1/recruit/public/{applicationSlug}/jobs/{jobSlug} returns job detail and similar jobs list.')]
    public function testThatPublicJobDetailReturnsJobAndSimilarJobsList(): void
    {
        [$applicationSlug, $jobSlug] = $this->getApplicationSlugAndJobSlug();

        $client = $this->getTestClient();
        $client->request('GET', self::API_URL_PREFIX . '/v1/recruit/public/' . $applicationSlug . '/jobs/' . $jobSlug);

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);

        self::assertArrayHasKey('job', $payload);
        self::assertArrayHasKey('similarJobs', $payload);
        self::assertSame($jobSlug, $payload['job']['slug'] ?? null);
        self::assertIsArray($payload['similarJobs']);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function getApplicationSlugAndJobSlug(): array
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
        ]);
        self::assertInstanceOf(Job::class, $job);

        return [$application->getSlug(), $job->getSlug()];
    }
}
