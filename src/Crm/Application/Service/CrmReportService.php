<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

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

    /**
     * @return array<string,mixed>
     */
    public function build(Crm $crm): array
    {
        $companies = $this->companyRepository->findScoped($crm->getId(), 100, 0);
        $contacts = $this->contactRepository->findScoped($crm->getId(), 200, 0);
        $employees = $this->employeeRepository->findScoped($crm->getId(), 200, 0);
        $billings = $this->billingRepository->findByCrm($crm->getId(), 200, 0);

        $totalBilling = 0.0;
        foreach ($billings as $billing) {
            $totalBilling += $billing->getAmount();
        }

        return [
            'kpis' => [
                'pipeline' => round($totalBilling, 2),
                'dealsWon' => $this->projectRepository->countProjectsByCrm($crm->getId()),
                'cycleDays' => 23,
                'npsClients' => 68,
            ],
            'counts' => [
                'companies' => count($companies),
                'contacts' => count($contacts),
                'employees' => count($employees),
                'billings' => count($billings),
                'tasks' => $this->taskRepository->countTasksByCrm($crm->getId()),
            ],
            'contacts' => array_map(static fn ($c) => [
                'id' => $c->getId(),
                'name' => trim($c->getFirstName() . ' ' . $c->getLastName()),
                'email' => $c->getEmail(),
                'jobTitle' => $c->getJobTitle(),
                'city' => $c->getCity(),
                'score' => $c->getScore(),
            ], $contacts),
            'recommendedActions' => [
                [
                    'priority' => 'P0',
                    'title' => 'Automatiser séquences email',
                    'owner' => 'RevOps',
                    'etaDays' => 5,
                ],
                [
                    'priority' => 'P1',
                    'title' => 'Rapports forecast avancés',
                    'owner' => 'Finance',
                    'etaDays' => 8,
                ],
                [
                    'priority' => 'P1',
                    'title' => 'Vue 360 contacts',
                    'owner' => 'Sales',
                    'etaDays' => 6,
                ],
            ],
        ];
    }

    /**
     * @param array<string,mixed> $report
     */
    public function toCsv(array $report): string
    {
        $h = fopen('php://temp', 'r+');
        if ($h === false) {
            return '';
        }
        fputcsv($h, ['section', 'metric', 'value']);
        foreach (($report['kpis'] ?? []) as $metric => $value) {
            fputcsv($h, ['kpis', (string)$metric, (string)$value]);
        }
        foreach (($report['counts'] ?? []) as $metric => $value) {
            fputcsv($h, ['counts', (string)$metric, (string)$value]);
        }
        foreach (($report['contacts'] ?? []) as $contact) {
            fputcsv($h, ['contact', (string)($contact['name'] ?? ''), (string)($contact['score'] ?? 0)]);
        }
        rewind($h);

        return (string)stream_get_contents($h);
    }

    /**
     * @param array<string,mixed> $report
     */
    public function toPdf(array $report): string
    {
        $text = 'CRM REPORT\nPipeline: ' . ($report['kpis']['pipeline'] ?? 0) . '\nDeals: ' . ($report['kpis']['dealsWon'] ?? 0);
        $stream = 'BT /F1 18 Tf 40 760 Td (' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text) . ') Tj ET';
        $len = strlen($stream);

        return "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj\n4 0 obj << /Length {$len} >> stream\n{$stream}\nendstream endobj\n5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\nxref\n0 6\n0000000000 65535 f \n0000000010 00000 n \n0000000060 00000 n \n0000000117 00000 n \n0000000243 00000 n \n0000000365 00000 n \ntrailer << /Size 6 /Root 1 0 R >>\nstartxref\n435\n%%EOF";
    }
}
