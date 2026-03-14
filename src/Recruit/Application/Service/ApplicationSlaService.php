<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Enum\ApplicationStatus;
use DateInterval;
use DateTimeImmutable;

readonly class ApplicationSlaService
{
    /**
     * @var array<string, int>
     */
    private const HOURS_BY_STATUS = [
        ApplicationStatus::WAITING->value => 72,
        ApplicationStatus::SCREENING->value => 120,
        ApplicationStatus::INTERVIEW_PLANNED->value => 168,
        ApplicationStatus::INTERVIEW_DONE->value => 96,
        ApplicationStatus::OFFER_SENT->value => 120,
    ];

    /**
     * @return array<string, int>
     */
    public function getRulesInHours(): array
    {
        return self::HOURS_BY_STATUS;
    }

    public function hasRule(ApplicationStatus $status): bool
    {
        return isset(self::HOURS_BY_STATUS[$status->value]);
    }

    public function getThresholdHours(ApplicationStatus $status): ?int
    {
        return self::HOURS_BY_STATUS[$status->value] ?? null;
    }

    public function isBreached(ApplicationStatus $status, DateTimeImmutable $updatedAt, ?DateTimeImmutable $now = null): bool
    {
        $thresholdHours = $this->getThresholdHours($status);
        if ($thresholdHours === null) {
            return false;
        }

        $currentDate = $now ?? new DateTimeImmutable();
        $deadline = $updatedAt->add(new DateInterval('PT' . $thresholdHours . 'H'));

        return $deadline <= $currentDate;
    }
}
