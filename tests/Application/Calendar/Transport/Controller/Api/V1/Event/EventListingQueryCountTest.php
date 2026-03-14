<?php

declare(strict_types=1);

namespace App\Tests\Application\Calendar\Transport\Controller\Api\V1\Event;

use App\Calendar\Domain\Entity\Calendar;
use App\Calendar\Domain\Entity\Event;
use App\Calendar\Domain\Enum\EventVisibility;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Entity\Platform;
use App\Platform\Domain\Enum\PlatformStatus;
use App\Tests\TestCase\WebTestCase;
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Response;

use function count;
use function is_array;
use function method_exists;
use function sprintf;

final class EventListingQueryCountTest extends WebTestCase
{
    #[TestDox('Event list SQL count stays bounded when listing many more application events (anti-N+1 guard).')]
    public function testApplicationEventListingQueryCountStaysBoundedAsItemsIncrease(): void
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $smallDataset = $this->createApplicationCalendarWithEvents($entityManager, 'small', 5);
        $largeDataset = $this->createApplicationCalendarWithEvents($entityManager, 'large', 30);

        $client = static::createClient(['debug' => true], $this->getJsonHeaders());

        $smallQueryCount = $this->countSqlQueriesForListingRequest($client, $smallDataset['slug']);
        $largeQueryCount = $this->countSqlQueriesForListingRequest($client, $largeDataset['slug']);

        // Seuil métier : la page liste doit rester pilotée par un nombre fixe de requêtes
        // (ex: lecture des items + pagination), et ne jamais évoluer linéairement avec le volume d'items.
        self::assertLessThanOrEqual(
            $smallQueryCount + 2,
            $largeQueryCount,
            sprintf('Expected bounded SQL growth for event listing: small=%d, large=%d', $smallQueryCount, $largeQueryCount),
        );
        self::assertLessThanOrEqual(
            12,
            $largeQueryCount,
            sprintf('Expected absolute SQL ceiling for calendar event listing, got %d queries', $largeQueryCount),
        );
    }

    private function countSqlQueriesForListingRequest(KernelBrowser $client, string $applicationSlug): int
    {
        $client->enableProfiler();
        $client->request('GET', self::API_URL_PREFIX . '/v1/calendar/applications/' . $applicationSlug . '/events?limit=100');

        $response = $client->getResponse();
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $profile = $client->getProfile();
        self::assertNotFalse($profile, 'Symfony profiler profile should be available in this test.');

        $dbCollector = $profile->getCollector('db');

        if (method_exists($dbCollector, 'getQueryCount')) {
            return (int)$dbCollector->getQueryCount();
        }

        $queries = $dbCollector->getQueries();

        if (!is_array($queries)) {
            self::fail('Doctrine profiler collector did not expose SQL queries as an array.');
        }

        $defaultConnectionQueries = $queries['default'] ?? $queries;
        self::assertIsArray($defaultConnectionQueries);

        return count($defaultConnectionQueries);
    }

    /**
     * @return array{slug: string}
     */
    private function createApplicationCalendarWithEvents(
        EntityManagerInterface $entityManager,
        string $suffix,
        int $eventCount,
    ): array {
        $owner = $entityManager->getRepository(User::class)->findOneBy([
            'username' => 'john-root',
        ]);
        self::assertInstanceOf(User::class, $owner);

        $platform = $entityManager->getRepository(Platform::class)->findOneBy([]);
        self::assertInstanceOf(Platform::class, $platform);

        $application = (new Application())
            ->setTitle(sprintf('Calendar SQL Guard %s', $suffix))
            ->setDescription('Integration test application for SQL query count guard.')
            ->setStatus(PlatformStatus::ACTIVE)
            ->setPrivate(false)
            ->setUser($owner)
            ->setPlatform($platform)
            ->ensureGeneratedPhoto()
            ->ensureGeneratedSlug();

        $calendar = (new Calendar())
            ->setTitle(sprintf('Calendar %s', $suffix))
            ->setApplication($application)
            ->setUser($owner);

        $entityManager->persist($application);
        $entityManager->persist($calendar);

        $baseDate = new DateTimeImmutable('2031-01-01 08:00:00');

        for ($index = 0; $index < $eventCount; ++$index) {
            $startAt = $baseDate->modify(sprintf('+%d day', $index));
            self::assertInstanceOf(DateTimeImmutable::class, $startAt);

            $event = (new Event())
                ->setTitle(sprintf('SQL guard event %s #%d', $suffix, $index + 1))
                ->setDescription('Event used by integration test to guard query count scaling.')
                ->setStartAt($startAt)
                ->setEndAt($startAt->modify('+1 hour'))
                ->setVisibility(EventVisibility::PUBLIC)
                ->setCalendar($calendar)
                ->setUser($owner);

            $entityManager->persist($event);
        }

        $entityManager->flush();

        return [
            'slug' => $application->getSlug(),
        ];
    }
}
