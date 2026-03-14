<?php

declare(strict_types=1);

namespace App\Tests\Application\Recruit\Transport\Controller\Api\V1\Analytics;

use App\General\Domain\Utils\JSON;
use App\Platform\Domain\Entity\Application as PlatformApplication;
use App\Recruit\Domain\Entity\Job;
use App\Tests\TestCase\WebTestCase;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

use function array_map;
use function array_sum;
use function is_array;

class PrivateRecruitAnalyticsControllerTest extends WebTestCase
{
    #[TestDox('GET private analytics returns aggregates and coherent conversion values.')]
    public function testThatPrivateAnalyticsReturnsCoherentAggregations(): void
    {
        $applicationSlug = $this->getApplicationSlug();

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('GET', self::API_URL_PREFIX . '/v1/recruit/applications/' . $applicationSlug . '/private/analytics');

        $response = $client->getResponse();
        $content = $response->getContent();

        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        /** @var array<string, mixed> $payload */
        $payload = JSON::decode($content, true);

        self::assertArrayHasKey('applications', $payload);
        self::assertArrayHasKey('conversionByStep', $payload);
        self::assertArrayHasKey('timeToStage', $payload);
        self::assertArrayHasKey('offerAcceptanceRate', $payload);
        self::assertArrayHasKey('rejectionCauses', $payload);

        $total = (int)($payload['applications']['total'] ?? 0);
        $byCurrentStatus = $payload['applications']['byCurrentStatus'] ?? [];
        self::assertIsArray($byCurrentStatus);
        self::assertSame($total, array_sum(array_map('intval', $byCurrentStatus)));

        $conversion = $payload['conversionByStep'] ?? [];
        self::assertTrue(is_array($conversion));
        self::assertSame($total, (int)($conversion['APPLIED']['count'] ?? -1));
        self::assertLessThanOrEqual((int)($conversion['OFFER_SENT']['count'] ?? 0), (int)($conversion['APPLIED']['count'] ?? 0));

        $offerAcceptanceRate = $payload['offerAcceptanceRate'] ?? [];
        self::assertIsArray($offerAcceptanceRate);
        self::assertLessThanOrEqual((int)($offerAcceptanceRate['accepted'] ?? 0), (int)($offerAcceptanceRate['offered'] ?? 0));

        $rejectedCount = (int)($byCurrentStatus['REJECTED'] ?? 0);
        $rejectionCauses = $payload['rejectionCauses'] ?? [];
        self::assertIsArray($rejectionCauses);

        $sumRejectionCauses = 0;
        foreach ($rejectionCauses as $row) {
            self::assertIsArray($row);
            $sumRejectionCauses += (int)($row['count'] ?? 0);
        }

        self::assertSame($rejectedCount, $sumRejectionCauses);
    }

    #[TestDox('GET private analytics supports CSV export and job filter.')]
    public function testThatPrivateAnalyticsCsvExportWorksWithJobFilter(): void
    {
        [$applicationSlug, $jobId] = $this->getApplicationSlugAndOwnedJobId();

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            'GET',
            self::API_URL_PREFIX . '/v1/recruit/applications/' . $applicationSlug . '/private/analytics?format=csv&jobId=' . $jobId,
        );

        $response = $client->getResponse();
        $content = $response->getContent();

        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);
        self::assertStringContainsString('text/csv', (string)$response->headers->get('content-type'));
        self::assertStringContainsString('section,metric,value', $content);
        self::assertStringContainsString('conversionByStep.count,APPLIED', $content);
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

    /**
     * @return array{0: string, 1: string}
     */
    private function getApplicationSlugAndOwnedJobId(): array
    {
        self::bootKernel();
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $applicationSlug = $this->getApplicationSlug();

        $job = $entityManager->getRepository(Job::class)->findOneBy([
            'title' => 'Lead Product Engineer',
        ]);
        self::assertInstanceOf(Job::class, $job);

        return [$applicationSlug, $job->getId()];
    }
}
