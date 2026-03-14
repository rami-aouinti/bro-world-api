<?php

declare(strict_types=1);

namespace App\Tests\Unit\Recruit\Application\Service;

use App\Recruit\Application\Service\ApplicationSlaService;
use App\Recruit\Domain\Enum\ApplicationStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class ApplicationSlaServiceTest extends TestCase
{
    public function testWaitingStatusIsBreachedAfter72Hours(): void
    {
        $service = new ApplicationSlaService();
        $now = new DateTimeImmutable('2025-01-01 12:00:00');
        $updatedAt = $now->modify('-72 hours');

        self::assertTrue($service->isBreached(ApplicationStatus::WAITING, $updatedAt, $now));
    }

    public function testWaitingStatusIsNotBreachedBefore72Hours(): void
    {
        $service = new ApplicationSlaService();
        $now = new DateTimeImmutable('2025-01-01 12:00:00');
        $updatedAt = $now->modify('-71 hours');

        self::assertFalse($service->isBreached(ApplicationStatus::WAITING, $updatedAt, $now));
    }

    public function testFinalStatusesHaveNoSlaRule(): void
    {
        $service = new ApplicationSlaService();
        $now = new DateTimeImmutable('2025-01-01 12:00:00');
        $updatedAt = $now->modify('-500 hours');

        self::assertFalse($service->hasRule(ApplicationStatus::HIRED));
        self::assertFalse($service->isBreached(ApplicationStatus::HIRED, $updatedAt, $now));
        self::assertFalse($service->hasRule(ApplicationStatus::REJECTED));
        self::assertFalse($service->isBreached(ApplicationStatus::REJECTED, $updatedAt, $now));
    }
}
