<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruit\Application\Service;

use App\General\Domain\Enum\Locale;
use App\Notification\Application\Service\NotificationPublisher;
use App\Recruit\Application\Service\RecruitNotificationService;
use App\Recruit\Domain\Entity\Applicant;
use App\Recruit\Domain\Entity\Application;
use App\Recruit\Domain\Entity\Interview;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Enum\ApplicationStatus;
use App\Recruit\Domain\Enum\InterviewMode;
use App\User\Domain\Entity\User;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\CacheInterface;

final class RecruitNotificationServiceTest extends TestCase
{
    public function testNotifyInterviewUpdatedIsDebounced(): void
    {
        $notificationPublisher = $this->createMock(NotificationPublisher::class);
        $notificationPublisher->expects(self::once())->method('publish');

        $service = new RecruitNotificationService($notificationPublisher, new InMemoryCache());
        $interview = $this->buildInterview();

        $service->notifyInterviewUpdated($interview);
        $service->notifyInterviewUpdated($interview);
    }

    public function testNotifyStatusUpdatedIsIdempotent(): void
    {
        $notificationPublisher = $this->createMock(NotificationPublisher::class);
        $notificationPublisher->expects(self::once())->method('publish');

        $service = new RecruitNotificationService($notificationPublisher, new InMemoryCache());
        $application = $this->buildInterview()->getApplication();

        $service->notifyStatusUpdated($application, ApplicationStatus::WAITING, ApplicationStatus::SCREENING);
        $service->notifyStatusUpdated($application, ApplicationStatus::WAITING, ApplicationStatus::SCREENING);
    }

    public function testTemplateSnapshotsForFrAndEn(): void
    {
        $notificationPublisher = $this->createMock(NotificationPublisher::class);
        $service = new RecruitNotificationService($notificationPublisher, new InMemoryCache());

        self::assertSame([
            'title' => 'Entretien planifié pour "Backend Engineer"',
            'description' => 'Entretien prévu le 2026-03-14T09:00:00+00:00.',
        ], $service->renderTemplateSnapshot('interview_scheduled', 'fr', [
            'jobTitle' => 'Backend Engineer',
            'interviewDate' => '2026-03-14T09:00:00+00:00',
        ]));

        self::assertSame([
            'title' => 'Application status updated: SCREENING',
            'description' => 'Your application for "Backend Engineer" is now SCREENING.',
        ], $service->renderTemplateSnapshot('status_updated', 'en', [
            'jobTitle' => 'Backend Engineer',
            'status' => 'SCREENING',
        ]));
    }

    private function buildInterview(): Interview
    {
        $owner = $this->createUser('owner', Locale::EN);
        $applicantUser = $this->createUser('applicant', Locale::EN);

        $applicant = (new Applicant())->setUser($applicantUser);
        $job = (new Job())->setOwner($owner)->setTitle('Backend Engineer')->ensureGeneratedSlug();
        $application = (new Application())->setApplicant($applicant)->setJob($job);

        return (new Interview())
            ->setApplication($application)
            ->setScheduledAt(new \DateTimeImmutable('2026-03-14T09:00:00+00:00'))
            ->setDurationMinutes(45)
            ->setMode(InterviewMode::VISIO)
            ->setLocationOrUrl('https://meet.example.com')
            ->setInterviewerIds(['u-2']);
    }

    private function createUser(string $id, Locale $locale): User
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getLocale')->willReturn($locale);

        return $user;
    }
}

final class InMemoryCache implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $values = [];

    public function get(string $key, callable $callback, ?float $beta = null, ?array &$metadata = null): mixed
    {
        if (array_key_exists($key, $this->values)) {
            return $this->values[$key];
        }

        $item = new InMemoryCacheItem();
        $value = $callback($item);
        $this->values[$key] = $value;

        return $value;
    }

    public function delete(string $key): bool
    {
        unset($this->values[$key]);

        return true;
    }
}

final class InMemoryCacheItem implements \Symfony\Contracts\Cache\ItemInterface
{
    public function getKey(): string
    {
        return 'memory';
    }

    public function get(): mixed
    {
        return true;
    }

    public function isHit(): bool
    {
        return false;
    }

    public function set(mixed $value): static
    {
        return $this;
    }

    public function expiresAt(?\DateTimeInterface $expiration): static
    {
        return $this;
    }

    public function expiresAfter(\DateInterval|int|null $time): static
    {
        return $this;
    }

    public function tag(string|iterable $tags): static
    {
        return $this;
    }

    public function getMetadata(): array
    {
        return [];
    }
}
