<?php

declare(strict_types=1);

namespace App\Tests\Application\Recruit\Transport\Controller\Api\V1\Job;

use App\General\Domain\Utils\JSON;
use App\Platform\Domain\Entity\Application as PlatformApplication;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use App\Recruit\Domain\Entity\Resume;
use App\Tests\TestCase\WebTestCase;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

use function array_filter;
use function array_unique;
use function array_values;
use function count;
use function implode;
use function is_array;
use function max;
use function mb_strtolower;
use function preg_split;
use function round;
use function str_contains;
use function strlen;
use function trim;

class PrivateJobListControllerTest extends WebTestCase
{
    #[TestDox('Test that `GET /v1/recruit/private/jobs?applicationSlug={applicationSlug}` requires authentication.')]
    public function testThatPrivateJobListRequiresAuthentication(): void
    {
        [$applicationSlug] = $this->getFixtureContext();

        $client = $this->getTestClient();
        $client->request('GET', self::API_URL_PREFIX . '/v1/recruit/applications/' . $applicationSlug . '/private/jobs');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    #[TestDox('Test that `GET /v1/recruit/private/jobs?applicationSlug={applicationSlug}` returns computed matchScore from user resume skills.')]
    public function testThatPrivateJobListReturnsComputedMatchScoreFromResumeSkills(): void
    {
        [$applicationSlug, $jobId, $expectedMatchScore] = $this->getFixtureContext();

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('GET', self::API_URL_PREFIX . '/v1/recruit/applications/' . $applicationSlug . '/private/jobs?limit=100');

        $response = $client->getResponse();
        $content = $response->getContent();

        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);

        self::assertArrayHasKey('items', $payload);
        self::assertIsArray($payload['items']);

        $jobPayload = null;
        foreach ($payload['items'] as $job) {
            if (($job['id'] ?? null) === $jobId) {
                $jobPayload = $job;
                break;
            }
        }

        self::assertIsArray($jobPayload);
        self::assertArrayHasKey('matchScore', $jobPayload);
        self::assertSame($expectedMatchScore, $jobPayload['matchScore']);
    }

    #[TestDox('Test that `GET /v1/recruit/private/jobs?applicationSlug={applicationSlug}` keeps the expected payload structure.')]
    public function testThatPrivateJobListKeepsExpectedPayloadStructure(): void
    {
        [$applicationSlug] = $this->getFixtureContext();

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('GET', self::API_URL_PREFIX . '/v1/recruit/applications/' . $applicationSlug . '/private/jobs?limit=1');

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
        self::assertArrayHasKey('owner', $item);
        self::assertArrayHasKey('apply', $item);
    }

    /**
     * @return array{0: string, 1: string, 2: int}
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
        ], [
            'createdAt' => 'DESC',
            'id' => 'DESC',
        ]);
        self::assertInstanceOf(Job::class, $job);

        $user = $entityManager->getRepository(User::class)->findOneBy([
            'username' => 'john-root',
        ]);
        self::assertInstanceOf(User::class, $user);

        $resume = $entityManager->getRepository(Resume::class)->findOneBy([
            'owner' => $user,
        ], [
            'createdAt' => 'DESC',
            'id' => 'DESC',
        ]);
        self::assertInstanceOf(Resume::class, $resume);

        return [$application->getSlug(), $job->getId(), $this->computeExpectedMatchScore($job, $resume)];
    }

    private function computeExpectedMatchScore(Job $job, Resume $resume): int
    {
        $keywords = [];
        foreach ($resume->getSkills() as $skill) {
            $title = trim(mb_strtolower($skill->getTitle()));
            if ($title === '') {
                continue;
            }

            $keywords[] = $title;

            $parts = preg_split('/[^\p{L}\p{N}]+/u', $title);
            if (!is_array($parts)) {
                continue;
            }

            foreach ($parts as $part) {
                $word = trim($part);
                if ($word !== '' && strlen($word) >= 3) {
                    $keywords[] = $word;
                }
            }
        }

        $keywords = array_values(array_unique(array_filter($keywords, static fn (string $value): bool => $value !== '')));
        self::assertNotEmpty($keywords);

        $jobCorpus = [
            mb_strtolower($job->getTitle()),
            mb_strtolower($job->getSummary()),
            mb_strtolower($job->getMissionDescription()),
            mb_strtolower(implode(' ', $job->getProfile())),
            mb_strtolower(implode(' ', $job->getResponsibilities())),
        ];

        foreach ($job->getTags() as $tag) {
            $jobCorpus[] = mb_strtolower($tag->getLabel());
        }

        $searchable = ' ' . implode(' ', $jobCorpus) . ' ';

        $matched = 0;
        foreach ($keywords as $keyword) {
            if ($keyword !== '' && str_contains($searchable, ' ' . $keyword . ' ')) {
                $matched++;
            }
        }

        return (int)max(0, min(100, round(($matched / count($keywords)) * 100)));
    }
}
