<?php

declare(strict_types=1);

namespace App\Tests\Application\Recruit\Transport\Controller\Api\V1\Job;

use App\General\Domain\Service\Interfaces\ElasticsearchServiceInterface;
use App\General\Domain\Utils\JSON;
use App\Platform\Domain\Entity\Application as PlatformApplication;
use App\Recruit\Application\Service\JobSimilarIndexerService;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use App\Tests\TestCase\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

class PublicJobDetailControllerTest extends WebTestCase
{
    #[TestDox('Test that GET /v1/recruit/applications/{applicationSlug}/public/jobs/{jobSlug} returns job detail and similar jobs list.')]
    public function testThatPublicJobDetailReturnsJobAndSimilarJobsList(): void
    {
        [$applicationSlug, $jobSlug, $jobId, $similarJobSlug, $similarJobId] = $this->getApplicationSlugAndJobDetails();

        self::bootKernel();
        /** @var ElasticsearchServiceInterface $elasticsearchService */
        $elasticsearchService = static::getContainer()->get(ElasticsearchServiceInterface::class);
        $elasticsearchService->index(JobSimilarIndexerService::INDEX_NAME, $jobId, [
            'jobId' => $jobId,
            'similarJobIds' => [$similarJobId],
            'updatedAt' => (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);

        $client = $this->getTestClient();
        $client->request('GET', self::API_URL_PREFIX . '/v1/recruit/applications/' . $applicationSlug . '/public/jobs/' . $jobSlug);

        $response = $client->getResponse();
        $content = $response->getContent();
        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);

        self::assertArrayHasKey('job', $payload);
        self::assertArrayHasKey('similarJobs', $payload);
        self::assertSame($jobSlug, $payload['job']['slug'] ?? null);
        self::assertIsArray($payload['similarJobs']);
        self::assertCount(1, $payload['similarJobs']);
        self::assertSame($similarJobSlug, $payload['similarJobs'][0]['slug'] ?? null);
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string}
     */
    private function getApplicationSlugAndJobDetails(): array
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

        /** @var list<Job> $jobs */
        $jobs = $entityManager->getRepository(Job::class)->findBy([
            'recruit' => $recruit,
            'isPublished' => true,
        ]);
        self::assertGreaterThanOrEqual(2, count($jobs));

        $job = $jobs[0];
        $similarJob = $jobs[1];

        return [$application->getSlug(), $job->getSlug(), $job->getId(), $similarJob->getSlug(), $similarJob->getId()];
    }
}
