<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Application\Dto\Report\CrmRecommendedActionDto;
use App\Crm\Application\Dto\Report\CrmReportContactDto;
use App\Crm\Application\Dto\Report\CrmReportCountsDto;
use App\Crm\Application\Dto\Report\CrmReportDto;
use App\Crm\Application\Dto\Report\CrmReportKpisDto;
use App\Crm\Application\Dto\Report\CrmReportMetadataDto;
use App\Crm\Domain\Entity\Crm;
use App\Crm\Infrastructure\Repository\BillingRepository;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Infrastructure\Repository\ContactRepository;
use App\Crm\Infrastructure\Repository\EmployeeRepository;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Infrastructure\Repository\TaskRepository;

use function fputcsv;

final readonly class CrmReportService
{
    public function __construct(
        private CompanyRepository $companyRepository,
        private ContactRepository $contactRepository,
        private EmployeeRepository $employeeRepository,
        private BillingRepository $billingRepository,
        private ProjectRepository $projectRepository,
        private TaskRepository $taskRepository,
    ) {
    }

    public function build(Crm $crm): CrmReportDto
    {
        $companies = $this->companyRepository->findScoped($crm->getId(), 100, 0);
        $contacts = $this->contactRepository->findScoped($crm->getId(), 200, 0);
        $employees = $this->employeeRepository->findScoped($crm->getId(), 200, 0);
        $billings = $this->billingRepository->findByCrm($crm->getId(), 200, 0);
        $projects = $this->projectRepository->findScoped($crm->getId(), 200, 0);

        $totalBilling = 0.0;
        foreach ($billings as $billing) {
            $totalBilling += $billing->getAmount();
        }

        $averageContactScore = 0.0;
        if ($contacts !== []) {
            $scoreSum = 0;
            foreach ($contacts as $contact) {
                $scoreSum += $contact->getScore();
            }
            $averageContactScore = $scoreSum / count($contacts);
        }

        $cycleDays = 0;
        if ($projects !== []) {
            $totalCycleDays = 0;
            $projectCount = 0;
            foreach ($projects as $project) {
                $startedAt = $project->getStartedAt();
                $dueAt = $project->getDueAt();
                if ($startedAt === null || $dueAt === null) {
                    continue;
                }
                $days = (int)$startedAt->diff($dueAt)->format('%a');
                $totalCycleDays += $days;
                ++$projectCount;
            }

            if ($projectCount > 0) {
                $cycleDays = (int)round($totalCycleDays / $projectCount);
            }
        }

        $counts = new CrmReportCountsDto(
            companies: count($companies),
            contacts: count($contacts),
            employees: count($employees),
            billings: count($billings),
            tasks: $this->taskRepository->countTasksByCrm($crm->getId()),
        );

        return new CrmReportDto(
            metadata: new CrmReportMetadataDto(
                period: 'rolling-30d',
                timezone: 'UTC',
                generatedAt: (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format(\DateTimeInterface::ATOM),
                version: 'v1',
            ),
            kpis: new CrmReportKpisDto(
                pipeline: round($totalBilling, 2),
                dealsWon: $this->projectRepository->countProjectsByCrm($crm->getId()),
                cycleDays: $cycleDays,
                npsClients: (int)round(max(0.0, min(100.0, $averageContactScore))),
            ),
            counts: $counts,
            contacts: array_map(static fn ($c) => new CrmReportContactDto(
                id: $c->getId(),
                name: trim($c->getFirstName() . ' ' . $c->getLastName()),
                email: $c->getEmail(),
                jobTitle: $c->getJobTitle(),
                city: $c->getCity(),
                score: $c->getScore(),
            ), $contacts),
            recommendedActions: $this->buildRecommendedActions($counts),
        );
    }

    public function toCsv(CrmReportDto $report): string
    {
        $reportData = $report->toArray();
        $h = fopen('php://temp', 'r+');
        if ($h === false) {
            return '';
        }
        fputcsv($h, ['section', 'metric', 'value']);
        foreach (($reportData['kpis'] ?? []) as $metric => $value) {
            fputcsv($h, ['kpis', (string)$metric, (string)$value]);
        }
        foreach (($reportData['counts'] ?? []) as $metric => $value) {
            fputcsv($h, ['counts', (string)$metric, (string)$value]);
        }
        foreach (($reportData['contacts'] ?? []) as $contact) {
            fputcsv($h, ['contact', (string)($contact['name'] ?? ''), (string)($contact['score'] ?? 0)]);
        }
        rewind($h);

        return (string)stream_get_contents($h);
    }

    /**
     * @return list<CrmRecommendedActionDto>
     */
    private function buildRecommendedActions(CrmReportCountsDto $counts): array
    {
        $actions = [];
        if ($counts->tasks > 50) {
            $actions[] = new CrmRecommendedActionDto('P0', 'Réduire le backlog des tâches', 'Delivery', 7);
        }
        if ($counts->contacts > 0 && $counts->companies > 0 && ($counts->contacts / max($counts->companies, 1)) < 2.0) {
            $actions[] = new CrmRecommendedActionDto('P1', 'Enrichir les contacts par entreprise', 'Sales', 10);
        }
        if ($counts->billings > 0) {
            $actions[] = new CrmRecommendedActionDto('P1', 'Suivre les factures en retard', 'Finance', 5);
        }

        if ($actions === []) {
            $actions[] = new CrmRecommendedActionDto('P2', 'Maintenir la cadence de suivi CRM', 'RevOps', 14);
        }

        return $actions;
    }
}
