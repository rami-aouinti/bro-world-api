<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use App\Recruit\Domain\Enum\ApplicationStatus;
use App\Recruit\Domain\Repository\Interfaces\ApplicationRepositoryInterface;
use App\Recruit\Domain\Repository\Interfaces\ApplicationStatusHistoryRepositoryInterface;
use App\Recruit\Domain\Repository\Interfaces\InterviewRepositoryInterface;

use function array_key_exists;
use function array_reduce;
use function count;
use function fputcsv;
use function max;
use function round;
use function strtolower;
use function trim;

readonly class RecruitAnalyticsService
{
    /**
     * Notes de perf attendues avec cette implémentation :
     * - plus d'hydratation d'entités `Application`, `ApplicationStatusHistory` et `Interview` pour les analytics ;
     * - récupération via snapshots/scalaires (`getArrayResult`) et agrégats SQL (`COUNT`, `MIN`) ;
     * - allocation mémoire réduite et latence plus stable quand le volume d'applications augmente.
     */
    public function __construct(
        private ApplicationRepositoryInterface $applicationRepository,
        private ApplicationStatusHistoryRepositoryInterface $applicationStatusHistoryRepository,
        private InterviewRepositoryInterface $interviewRepository,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getAnalytics(Recruit $recruit, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null, ?Job $job = null): array
    {
        $applicationSnapshots = $this->findApplications($recruit, $from, $to, $job);
        $applicationIds = array_values(array_map(
            static fn (array $row): string => $row['id'],
            $applicationSnapshots
        ));
        $historiesByApplication = $this->findHistoriesByApplication($applicationIds);
        $interviewTimes = $this->findFirstInterviewAtByApplication($applicationIds);

        $total = count($applicationSnapshots);
        $byCurrentStatus = $this->buildCurrentStatusCounts($recruit, $from, $to, $job);
        $conversion = $this->buildConversion($recruit, $from, $to, $job);
        $timeToStage = $this->buildTimeToStage($applicationSnapshots, $historiesByApplication, $interviewTimes);
        $offerAcceptanceRate = $this->buildOfferAcceptanceRate($conversion);
        $rejectionCauses = $this->buildRejectionCauses($applicationSnapshots, $historiesByApplication);

        return [
            'filters' => [
                'from' => $from?->format(DATE_ATOM),
                'to' => $to?->format(DATE_ATOM),
                'jobId' => $job?->getId(),
            ],
            'applications' => [
                'total' => $total,
                'byCurrentStatus' => $byCurrentStatus,
            ],
            'conversionByStep' => $conversion,
            'timeToStage' => $timeToStage,
            'offerAcceptanceRate' => $offerAcceptanceRate,
            'rejectionCauses' => $rejectionCauses,
        ];
    }

    /**
     * @param array<string, mixed> $analytics
     */
    public function toCsv(array $analytics): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, ['section', 'metric', 'value']);
        fputcsv($handle, ['applications', 'total', (string)($analytics['applications']['total'] ?? 0)]);

        foreach (($analytics['applications']['byCurrentStatus'] ?? []) as $status => $count) {
            fputcsv($handle, ['applications.byCurrentStatus', (string)$status, (string)$count]);
        }

        foreach (($analytics['conversionByStep'] ?? []) as $step => $row) {
            fputcsv($handle, ['conversionByStep.count', (string)$step, (string)($row['count'] ?? 0)]);
            fputcsv($handle, ['conversionByStep.rateFromPrevious', (string)$step, (string)($row['rateFromPrevious'] ?? 0)]);
            fputcsv($handle, ['conversionByStep.rateFromTotal', (string)$step, (string)($row['rateFromTotal'] ?? 0)]);
        }

        foreach (($analytics['timeToStage'] ?? []) as $stage => $row) {
            fputcsv($handle, ['timeToStage.averageHours', (string)$stage, (string)($row['averageHours'] ?? 0)]);
            fputcsv($handle, ['timeToStage.sampleSize', (string)$stage, (string)($row['sampleSize'] ?? 0)]);
        }

        fputcsv($handle, ['offerAcceptanceRate', 'accepted', (string)($analytics['offerAcceptanceRate']['accepted'] ?? 0)]);
        fputcsv($handle, ['offerAcceptanceRate', 'offered', (string)($analytics['offerAcceptanceRate']['offered'] ?? 0)]);
        fputcsv($handle, ['offerAcceptanceRate', 'rate', (string)($analytics['offerAcceptanceRate']['rate'] ?? 0)]);

        foreach (($analytics['rejectionCauses'] ?? []) as $row) {
            fputcsv($handle, ['rejectionCauses', (string)($row['cause'] ?? ''), (string)($row['count'] ?? 0)]);
        }

        rewind($handle);

        return (string)stream_get_contents($handle);
    }

    /**
     * @return list<array{id: string, status: string, createdAt: ?\DateTimeImmutable}>
     */
    private function findApplications(Recruit $recruit, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to, ?Job $job): array
    {
        return $this->applicationRepository->findAnalyticsApplicationSnapshots($recruit, $from, $to, $job);
    }

    /**
     * @param list<string> $applicationIds
     *
     * @return array<string, list<array{toStatus: string, createdAt: \DateTimeImmutable, comment: ?string}>>
     */
    private function findHistoriesByApplication(array $applicationIds): array
    {
        return $this->applicationStatusHistoryRepository->findAnalyticsHistoryRowsByApplicationId($applicationIds);
    }

    /**
     * @param list<string> $applicationIds
     *
     * @return array<string, \DateTimeImmutable>
     */
    private function findFirstInterviewAtByApplication(array $applicationIds): array
    {
        return $this->interviewRepository->findFirstInterviewAtByApplicationId($applicationIds);
    }

    /**
     * @return array<string, int>
     */
    private function buildCurrentStatusCounts(Recruit $recruit, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to, ?Job $job): array
    {
        $counts = [];

        foreach (ApplicationStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        $statusCounts = $this->applicationRepository->countByCurrentStatusForAnalytics($recruit, $from, $to, $job);

        foreach ($statusCounts as $status => $count) {
            if (!array_key_exists($status, $counts)) {
                continue;
            }

            $counts[$status] = $count;
        }

        return $counts;
    }

    /**
     * @return array<string, array{count: int, rateFromPrevious: float, rateFromTotal: float}>
     */
    private function buildConversion(Recruit $recruit, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to, ?Job $job): array
    {
        $steps = [
            'APPLIED',
            ApplicationStatus::SCREENING->value,
            'INTERVIEW',
            ApplicationStatus::OFFER_SENT->value,
            ApplicationStatus::HIRED->value,
        ];

        $counts = $this->applicationRepository->countConversionsByStepForAnalytics($recruit, $from, $to, $job);

        $result = [];

        foreach ($steps as $index => $step) {
            $countForStep = $counts[$step];
            $previousStepCount = $index === 0 ? $countForStep : $counts[$steps[$index - 1]];
            $totalCount = $counts['APPLIED'];

            $result[$step] = [
                'count' => $countForStep,
                'rateFromPrevious' => $previousStepCount > 0 ? round(($countForStep / $previousStepCount) * 100, 2) : 0.0,
                'rateFromTotal' => $totalCount > 0 ? round(($countForStep / $totalCount) * 100, 2) : 0.0,
            ];
        }

        return $result;
    }

    /**
     * @param list<array{id: string, status: string, createdAt: ?\DateTimeImmutable}> $applications
     * @param array<string, list<array{toStatus: string, createdAt: \DateTimeImmutable, comment: ?string}>> $historiesByApplication
     * @param array<string, \DateTimeImmutable> $interviewTimes
     *
     * @return array<string, array{averageHours: float, sampleSize: int}>
     */
    private function buildTimeToStage(array $applications, array $historiesByApplication, array $interviewTimes): array
    {
        $screenHours = [];
        $interviewHours = [];
        $hireHours = [];

        foreach ($applications as $application) {
            $createdAt = $application['createdAt'];
            if ($createdAt === null) {
                continue;
            }

            $applicationId = $application['id'];
            $history = $historiesByApplication[$applicationId] ?? [];

            $firstScreenAt = $this->findFirstStatusAt($history, ApplicationStatus::SCREENING);
            if ($firstScreenAt !== null) {
                $screenHours[] = $this->hoursBetween($createdAt, $firstScreenAt);
            }

            if (array_key_exists($applicationId, $interviewTimes)) {
                $interviewHours[] = $this->hoursBetween($createdAt, $interviewTimes[$applicationId]);
            }

            $firstHireAt = $this->findFirstStatusAt($history, ApplicationStatus::HIRED);
            if ($firstHireAt !== null) {
                $hireHours[] = $this->hoursBetween($createdAt, $firstHireAt);
            }
        }

        return [
            'screen' => $this->buildTimingRow($screenHours),
            'interview' => $this->buildTimingRow($interviewHours),
            'hire' => $this->buildTimingRow($hireHours),
        ];
    }

    /**
     * @param list<float> $hours
     *
     * @return array{averageHours: float, sampleSize: int}
     */
    private function buildTimingRow(array $hours): array
    {
        $sampleSize = count($hours);
        if ($sampleSize === 0) {
            return [
                'averageHours' => 0.0,
                'sampleSize' => 0,
            ];
        }

        $sum = array_reduce($hours, static fn (float $carry, float $item): float => $carry + $item, 0.0);

        return [
            'averageHours' => round($sum / max($sampleSize, 1), 2),
            'sampleSize' => $sampleSize,
        ];
    }

    /**
     * @param array<string, array{count: int, rateFromPrevious: float, rateFromTotal: float}> $conversion
     *
     * @return array{accepted: int, offered: int, rate: float}
     */
    private function buildOfferAcceptanceRate(array $conversion): array
    {
        $accepted = (int)($conversion[ApplicationStatus::HIRED->value]['count'] ?? 0);
        $offered = (int)($conversion[ApplicationStatus::OFFER_SENT->value]['count'] ?? 0);

        return [
            'accepted' => $accepted,
            'offered' => $offered,
            'rate' => $offered > 0 ? round(($accepted / $offered) * 100, 2) : 0.0,
        ];
    }

    /**
     * @param list<array{id: string, status: string, createdAt: ?\DateTimeImmutable}> $applications
     * @param array<string, list<array{toStatus: string, createdAt: \DateTimeImmutable, comment: ?string}>> $historiesByApplication
     *
     * @return list<array{cause: string, count: int}>
     */
    private function buildRejectionCauses(array $applications, array $historiesByApplication): array
    {
        $counts = [];

        foreach ($applications as $application) {
            if ($application['status'] !== ApplicationStatus::REJECTED->value) {
                continue;
            }

            $history = $historiesByApplication[$application['id']] ?? [];
            $cause = 'Unspecified';

            foreach ($history as $row) {
                if ($row['toStatus'] !== ApplicationStatus::REJECTED->value) {
                    continue;
                }

                $comment = trim((string)$row['comment']);
                $cause = $comment !== '' ? $comment : 'Unspecified';
            }

            $normalized = strtolower($cause);
            $counts[$normalized] ??= [
                'cause' => $cause,
                'count' => 0,
            ];
            $counts[$normalized]['count']++;
        }

        return array_values($counts);
    }

    /**
     * @param list<array{toStatus: string, createdAt: \DateTimeImmutable, comment: ?string}> $history
     */
    private function findFirstStatusAt(array $history, ApplicationStatus $status): ?\DateTimeImmutable
    {
        foreach ($history as $row) {
            if ($row['toStatus'] !== $status->value) {
                continue;
            }

            return $row['createdAt'];
        }

        return null;
    }

    private function hoursBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): float
    {
        $seconds = $to->getTimestamp() - $from->getTimestamp();

        return round($seconds / 3600, 2);
    }
}
