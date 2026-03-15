<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Entity\Application;
use App\Recruit\Domain\Entity\ApplicationStatusHistory;
use App\Recruit\Domain\Entity\Interview;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Entity\Recruit;
use App\Recruit\Domain\Enum\ApplicationStatus;
use Doctrine\ORM\EntityManagerInterface;

use function array_key_exists;
use function array_reduce;
use function count;
use function fputcsv;
use function in_array;
use function max;
use function round;
use function strtolower;
use function trim;

readonly class RecruitAnalyticsService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getAnalytics(Recruit $recruit, ?\DateTimeImmutable $from = null, ?\DateTimeImmutable $to = null, ?Job $job = null): array
    {
        $applications = $this->findApplications($recruit, $from, $to, $job);
        $historiesByApplication = $this->findHistoriesByApplication($applications);
        $interviewTimes = $this->findFirstInterviewAtByApplication($applications);

        $total = count($applications);
        $byCurrentStatus = $this->buildCurrentStatusCounts($applications);
        $conversion = $this->buildConversion($applications, $historiesByApplication);
        $timeToStage = $this->buildTimeToStage($applications, $historiesByApplication, $interviewTimes);
        $offerAcceptanceRate = $this->buildOfferAcceptanceRate($conversion);
        $rejectionCauses = $this->buildRejectionCauses($applications, $historiesByApplication);

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
     * @return list<Application>
     */
    private function findApplications(Recruit $recruit, ?\DateTimeImmutable $from, ?\DateTimeImmutable $to, ?Job $job): array
    {
        $queryBuilder = $this->entityManager->getRepository(Application::class)
            ->createQueryBuilder('application')
            ->innerJoin('application.job', 'job')
            ->andWhere('job.recruit = :recruit')
            ->setParameter('recruit', $recruit);

        if ($from !== null) {
            $queryBuilder
                ->andWhere('application.createdAt >= :from')
                ->setParameter('from', $from);
        }

        if ($to !== null) {
            $queryBuilder
                ->andWhere('application.createdAt <= :to')
                ->setParameter('to', $to);
        }

        if ($job !== null) {
            $queryBuilder
                ->andWhere('application.job = :job')
                ->setParameter('job', $job);
        }

        /** @var list<Application> $applications */
        $applications = $queryBuilder->getQuery()->getResult();

        return $applications;
    }

    /**
     * @param list<Application> $applications
     *
     * @return array<string, list<ApplicationStatusHistory>>
     */
    private function findHistoriesByApplication(array $applications): array
    {
        if ($applications === []) {
            return [];
        }

        /** @var list<ApplicationStatusHistory> $historyRows */
        $historyRows = $this->entityManager->getRepository(ApplicationStatusHistory::class)
            ->createQueryBuilder('history')
            ->innerJoin('history.application', 'application')
            ->andWhere('history.application IN (:applications)')
            ->setParameter('applications', $applications)
            ->orderBy('history.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];

        foreach ($historyRows as $row) {
            $applicationId = $row->getApplication()->getId();
            $result[$applicationId] ??= [];
            $result[$applicationId][] = $row;
        }

        return $result;
    }

    /**
     * @param list<Application> $applications
     *
     * @return array<string, \DateTimeImmutable>
     */
    private function findFirstInterviewAtByApplication(array $applications): array
    {
        if ($applications === []) {
            return [];
        }

        /** @var list<Interview> $interviews */
        $interviews = $this->entityManager->getRepository(Interview::class)
            ->createQueryBuilder('interview')
            ->innerJoin('interview.application', 'application')
            ->andWhere('interview.application IN (:applications)')
            ->setParameter('applications', $applications)
            ->orderBy('interview.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();

        $result = [];

        foreach ($interviews as $interview) {
            $applicationId = $interview->getApplication()->getId();
            if (!array_key_exists($applicationId, $result)) {
                $result[$applicationId] = $interview->getScheduledAt();
            }
        }

        return $result;
    }

    /**
     * @param list<Application> $applications
     *
     * @return array<string, int>
     */
    private function buildCurrentStatusCounts(array $applications): array
    {
        $counts = [];

        foreach (ApplicationStatus::cases() as $status) {
            $counts[$status->value] = 0;
        }

        foreach ($applications as $application) {
            $counts[$application->getStatus()->value]++;
        }

        return $counts;
    }

    /**
     * @param list<Application> $applications
     * @param array<string, list<ApplicationStatusHistory>> $historiesByApplication
     *
     * @return array<string, array{count: int, rateFromPrevious: float, rateFromTotal: float}>
     */
    private function buildConversion(array $applications, array $historiesByApplication): array
    {
        $steps = [
            'APPLIED',
            ApplicationStatus::SCREENING->value,
            'INTERVIEW',
            ApplicationStatus::OFFER_SENT->value,
            ApplicationStatus::HIRED->value,
        ];

        $counts = [
            'APPLIED' => count($applications),
            ApplicationStatus::SCREENING->value => 0,
            'INTERVIEW' => 0,
            ApplicationStatus::OFFER_SENT->value => 0,
            ApplicationStatus::HIRED->value => 0,
        ];

        foreach ($applications as $application) {
            $applicationId = $application->getId();
            $history = $historiesByApplication[$applicationId] ?? [];
            $status = $application->getStatus();

            $reachedScreening = $this->applicationReachedStatuses($status, $history, [ApplicationStatus::SCREENING]);
            $reachedInterview = $this->applicationReachedStatuses($status, $history, [ApplicationStatus::INTERVIEW_PLANNED, ApplicationStatus::INTERVIEW_DONE]);
            $reachedOffer = $this->applicationReachedStatuses($status, $history, [ApplicationStatus::OFFER_SENT]);
            $reachedHired = $this->applicationReachedStatuses($status, $history, [ApplicationStatus::HIRED]);

            $counts[ApplicationStatus::SCREENING->value] += $reachedScreening ? 1 : 0;
            $counts['INTERVIEW'] += $reachedInterview ? 1 : 0;
            $counts[ApplicationStatus::OFFER_SENT->value] += $reachedOffer ? 1 : 0;
            $counts[ApplicationStatus::HIRED->value] += $reachedHired ? 1 : 0;
        }

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
     * @param list<ApplicationStatusHistory> $history
     * @param list<ApplicationStatus> $statuses
     */
    private function applicationReachedStatuses(ApplicationStatus $currentStatus, array $history, array $statuses): bool
    {
        if (in_array($currentStatus, $statuses, true)) {
            return true;
        }

        if ($currentStatus === ApplicationStatus::INTERVIEW_DONE && in_array(ApplicationStatus::INTERVIEW_PLANNED, $statuses, true)) {
            return true;
        }

        if (
            in_array($currentStatus, [ApplicationStatus::INTERVIEW_DONE, ApplicationStatus::OFFER_SENT, ApplicationStatus::HIRED], true)
            && in_array(ApplicationStatus::INTERVIEW_DONE, $statuses, true)
        ) {
            return true;
        }

        if (
            in_array($currentStatus, [ApplicationStatus::INTERVIEW_PLANNED, ApplicationStatus::INTERVIEW_DONE, ApplicationStatus::OFFER_SENT, ApplicationStatus::HIRED], true)
            && in_array(ApplicationStatus::INTERVIEW_PLANNED, $statuses, true)
        ) {
            return true;
        }

        if (
            in_array($currentStatus, [ApplicationStatus::OFFER_SENT, ApplicationStatus::HIRED], true)
            && in_array(ApplicationStatus::OFFER_SENT, $statuses, true)
        ) {
            return true;
        }

        if ($currentStatus === ApplicationStatus::HIRED && in_array(ApplicationStatus::HIRED, $statuses, true)) {
            return true;
        }

        foreach ($history as $row) {
            if (in_array($row->getToStatus(), $statuses, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<Application> $applications
     * @param array<string, list<ApplicationStatusHistory>> $historiesByApplication
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
            $createdAt = $application->getCreatedAt();
            if ($createdAt === null) {
                continue;
            }

            $applicationId = $application->getId();
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
     * @param list<Application> $applications
     * @param array<string, list<ApplicationStatusHistory>> $historiesByApplication
     *
     * @return list<array{cause: string, count: int}>
     */
    private function buildRejectionCauses(array $applications, array $historiesByApplication): array
    {
        $counts = [];

        foreach ($applications as $application) {
            if ($application->getStatus() !== ApplicationStatus::REJECTED) {
                continue;
            }

            $history = $historiesByApplication[$application->getId()] ?? [];
            $cause = 'Unspecified';

            foreach ($history as $row) {
                if ($row->getToStatus() !== ApplicationStatus::REJECTED) {
                    continue;
                }

                $comment = trim((string)$row->getComment());
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
     * @param list<ApplicationStatusHistory> $history
     */
    private function findFirstStatusAt(array $history, ApplicationStatus $status): ?\DateTimeImmutable
    {
        foreach ($history as $row) {
            if ($row->getToStatus() !== $status) {
                continue;
            }

            return $row->getCreatedAt();
        }

        return null;
    }

    private function hoursBetween(\DateTimeImmutable $from, \DateTimeImmutable $to): float
    {
        $seconds = $to->getTimestamp() - $from->getTimestamp();

        return round($seconds / 3600, 2);
    }
}
