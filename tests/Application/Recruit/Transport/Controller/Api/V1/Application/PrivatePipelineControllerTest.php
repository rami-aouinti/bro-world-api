<?php

declare(strict_types=1);

namespace App\Tests\Application\Recruit\Transport\Controller\Api\V1\Application;

use App\General\Domain\Utils\JSON;
use App\Platform\Domain\Entity\Application as PlatformApplication;
use App\Recruit\Domain\Entity\Applicant;
use App\Recruit\Domain\Entity\Application;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use App\Recruit\Domain\Entity\Tag;
use App\Recruit\Domain\Enum\ApplicationStatus;
use App\Tests\TestCase\WebTestCase;
use App\User\Domain\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

class PrivatePipelineControllerTest extends WebTestCase
{
    #[TestDox('GET /v1/recruit/private/{applicationSlug}/pipeline requires authentication.')]
    public function testThatPrivatePipelineRequiresAuthentication(): void
    {
        $applicationSlug = $this->getApplicationSlugForUsername('john-root');

        $client = $this->getTestClient();
        $client->request('GET', self::API_URL_PREFIX . '/v1/recruit/private/' . $applicationSlug . '/pipeline');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    #[TestDox('GET /v1/recruit/private/{applicationSlug}/pipeline applies pagination and filtering.')]
    public function testThatPrivatePipelineSupportsPaginationAndFiltering(): void
    {
        [$applicationSlug, $jobId, $tagLabel] = $this->createPipelineFixtureData();

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('GET', self::API_URL_PREFIX . '/v1/recruit/private/' . $applicationSlug . '/pipeline?limit=1&page=1');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), (string)$response);
        $payload = JSON::decode((string)$response->getContent(), true);

        self::assertSame(1, $payload['pagination']['limit'] ?? null);
        self::assertSame(1, $payload['pagination']['page'] ?? null);
        self::assertGreaterThanOrEqual(2, (int)($payload['pagination']['total'] ?? 0));

        $firstPageCount = 0;
        foreach ($payload['columns'] as $column) {
            $firstPageCount += count($column['candidates'] ?? []);
            self::assertArrayHasKey('metrics', $column);
            self::assertArrayHasKey('count', $column['metrics']);
            self::assertArrayHasKey('avgAgingDays', $column['metrics']);
        }

        self::assertSame(1, $firstPageCount);

        $client->request('GET', self::API_URL_PREFIX . '/v1/recruit/private/' . $applicationSlug . '/pipeline?limit=1&page=2');
        $secondPayload = JSON::decode((string)$client->getResponse()->getContent(), true);

        $secondPageCount = 0;
        foreach ($secondPayload['columns'] as $column) {
            $secondPageCount += count($column['candidates'] ?? []);
        }

        self::assertSame(1, $secondPageCount);

        $client->request('GET', self::API_URL_PREFIX . '/v1/recruit/private/' . $applicationSlug . '/pipeline?jobId=' . $jobId . '&source=manual&tags=' . urlencode($tagLabel));
        $filteredPayload = JSON::decode((string)$client->getResponse()->getContent(), true);

        $filteredCandidates = [];
        foreach ($filteredPayload['columns'] as $column) {
            foreach ($column['candidates'] ?? [] as $candidate) {
                $filteredCandidates[] = $candidate;
            }
        }

        self::assertCount(1, $filteredCandidates);
        self::assertSame($jobId, $filteredCandidates[0]['job']['id'] ?? null);
        self::assertSame('manual', $filteredCandidates[0]['source'] ?? null);
    }

    /**
     * @return array{0: string, 1: string, 2: string}
     */
    private function createPipelineFixtureData(): array
    {
        self::bootKernel();

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $user = $entityManager->getRepository(User::class)->findOneBy([
            'username' => 'john-root',
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

        $tagLabel = 'pipeline-test-tag-' . substr((string)microtime(true), -6);
        $tag = (new Tag())
            ->setLabel($tagLabel);

        $manualApplicant = (new Applicant())
            ->setUser($user)
            ->setCoverLetter('Manual applicant without resume');

        $resumeApplicant = $entityManager->getRepository(Applicant::class)->findOneBy([
            'user' => $user,
        ]);
        self::assertInstanceOf(Applicant::class, $resumeApplicant);

        $jobA = (new Job())
            ->setRecruit($recruit)
            ->setOwner($user)
            ->setTitle('Pipeline fixture job A')
            ->ensureGeneratedSlug()
            ->addTag($tag);

        $jobB = (new Job())
            ->setRecruit($recruit)
            ->setOwner($user)
            ->setTitle('Pipeline fixture job B')
            ->ensureGeneratedSlug();

        $applicationA = (new Application())
            ->setApplicant($manualApplicant)
            ->setJob($jobA)
            ->setStatus(ApplicationStatus::WAITING)
            ->setCreatedAt(new DateTime('-3 days'));

        $applicationB = (new Application())
            ->setApplicant($resumeApplicant)
            ->setJob($jobB)
            ->setStatus(ApplicationStatus::SCREENING)
            ->setCreatedAt(new DateTime('-1 days'));

        $entityManager->persist($tag);
        $entityManager->persist($manualApplicant);
        $entityManager->persist($jobA);
        $entityManager->persist($jobB);
        $entityManager->persist($applicationA);
        $entityManager->persist($applicationB);
        $entityManager->flush();

        return [$application->getSlug(), $jobA->getId(), $tagLabel];
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
