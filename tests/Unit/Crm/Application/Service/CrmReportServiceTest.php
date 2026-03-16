<?php

declare(strict_types=1);

namespace App\Tests\Unit\Crm\Application\Service;

use App\Crm\Application\Dto\Report\CrmReportContactDto;
use App\Crm\Application\Dto\Report\CrmReportCountsDto;
use App\Crm\Application\Dto\Report\CrmReportDto;
use App\Crm\Application\Dto\Report\CrmReportKpisDto;
use App\Crm\Application\Dto\Report\CrmReportMetadataDto;
use App\Crm\Application\Dto\Report\CrmRecommendedActionDto;
use App\Crm\Application\Service\CrmReportService;
use App\Crm\Application\Service\Report\CrmReportPdfExporter;
use App\Crm\Domain\Entity\Billing;
use App\Crm\Domain\Entity\Company;
use App\Crm\Domain\Entity\Contact;
use App\Crm\Domain\Entity\Crm;
use App\Crm\Domain\Entity\Employee;
use App\Crm\Domain\Entity\Project;
use App\Crm\Infrastructure\Repository\BillingRepository;
use App\Crm\Infrastructure\Repository\CompanyRepository;
use App\Crm\Infrastructure\Repository\ContactRepository;
use App\Crm\Infrastructure\Repository\EmployeeRepository;
use App\Crm\Infrastructure\Repository\ProjectRepository;
use App\Crm\Infrastructure\Repository\TaskRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CrmReportServiceTest extends TestCase
{
    public function testBuildComputesKpisFromRepositoryData(): void
    {
        $companyRepository = $this->createMock(CompanyRepository::class);
        $contactRepository = $this->createMock(ContactRepository::class);
        $employeeRepository = $this->createMock(EmployeeRepository::class);
        $billingRepository = $this->createMock(BillingRepository::class);
        $projectRepository = $this->createMock(ProjectRepository::class);
        $taskRepository = $this->createMock(TaskRepository::class);

        $crm = $this->createMock(Crm::class);
        $crm->method('getId')->willReturn('crm-1');

        $companyRepository->method('findScoped')->willReturn([$this->createMock(Company::class)]);

        $contactA = $this->createMock(Contact::class);
        $contactA->method('getId')->willReturn('c-1');
        $contactA->method('getFirstName')->willReturn('Alice');
        $contactA->method('getLastName')->willReturn('Doe');
        $contactA->method('getEmail')->willReturn('alice@example.test');
        $contactA->method('getJobTitle')->willReturn('VP Sales');
        $contactA->method('getCity')->willReturn('Paris');
        $contactA->method('getScore')->willReturn(50);

        $contactB = $this->createMock(Contact::class);
        $contactB->method('getId')->willReturn('c-2');
        $contactB->method('getFirstName')->willReturn('Bob');
        $contactB->method('getLastName')->willReturn('Doe');
        $contactB->method('getEmail')->willReturn('bob@example.test');
        $contactB->method('getJobTitle')->willReturn('CTO');
        $contactB->method('getCity')->willReturn('Lyon');
        $contactB->method('getScore')->willReturn(80);
        $contactRepository->method('findScoped')->willReturn([$contactA, $contactB]);

        $employeeRepository->method('findScoped')->willReturn([$this->createMock(Employee::class)]);

        $billingA = $this->createMock(Billing::class);
        $billingA->method('getAmount')->willReturn(100.25);
        $billingB = $this->createMock(Billing::class);
        $billingB->method('getAmount')->willReturn(49.75);
        $billingRepository->method('findByCrm')->willReturn([$billingA, $billingB]);

        $projectA = $this->createMock(Project::class);
        $projectA->method('getStartedAt')->willReturn(new DateTimeImmutable('2024-01-01'));
        $projectA->method('getDueAt')->willReturn(new DateTimeImmutable('2024-01-11'));
        $projectB = $this->createMock(Project::class);
        $projectB->method('getStartedAt')->willReturn(new DateTimeImmutable('2024-02-01'));
        $projectB->method('getDueAt')->willReturn(new DateTimeImmutable('2024-02-21'));
        $projectRepository->method('findScoped')->willReturn([$projectA, $projectB]);
        $projectRepository->method('countProjectsByCrm')->willReturn(2);

        $taskRepository->method('countTasksByCrm')->willReturn(60);

        $service = new CrmReportService(
            $companyRepository,
            $contactRepository,
            $employeeRepository,
            $billingRepository,
            $projectRepository,
            $taskRepository,
        );

        $report = $service->build($crm)->toArray();

        self::assertSame(150.0, $report['kpis']['pipeline']);
        self::assertSame(2, $report['kpis']['dealsWon']);
        self::assertSame(15, $report['kpis']['cycleDays']);
        self::assertSame(65, $report['kpis']['npsClients']);
        self::assertSame('rolling-30d', $report['metadata']['period']);
        self::assertCount(2, $report['recommendedActions']);
    }

    public function testCsvSnapshotMinimal(): void
    {
        $service = new CrmReportService(
            $this->createMock(CompanyRepository::class),
            $this->createMock(ContactRepository::class),
            $this->createMock(EmployeeRepository::class),
            $this->createMock(BillingRepository::class),
            $this->createMock(ProjectRepository::class),
            $this->createMock(TaskRepository::class),
        );

        $report = new CrmReportDto(
            new CrmReportMetadataDto('rolling-30d', 'UTC', '2024-05-01T00:00:00+00:00', 'v1'),
            new CrmReportKpisDto(1200.5, 6, 14, 71),
            new CrmReportCountsDto(5, 10, 3, 4, 8),
            [new CrmReportContactDto('id-1', 'Alice Doe', 'alice@example.test', 'VP', 'Paris', 70)],
            [new CrmRecommendedActionDto('P1', 'Action', 'RevOps', 5)],
        );

        $csv = $service->toCsv($report);

        self::assertSame(
            "section,metric,value\n"
            . "kpis,pipeline,1200.5\n"
            . "kpis,dealsWon,6\n"
            . "kpis,cycleDays,14\n"
            . "kpis,npsClients,71\n"
            . "counts,companies,5\n"
            . "counts,contacts,10\n"
            . "counts,employees,3\n"
            . "counts,billings,4\n"
            . "counts,tasks,8\n"
            . "contact," . '"Alice Doe"' . ",70\n",
            $csv,
        );
    }

    public function testPdfSnapshotMinimal(): void
    {
        $exporter = new CrmReportPdfExporter();
        $report = new CrmReportDto(
            new CrmReportMetadataDto('rolling-30d', 'UTC', '2024-05-01T00:00:00+00:00', 'v1'),
            new CrmReportKpisDto(123.45, 3, 9, 55),
            new CrmReportCountsDto(1, 1, 1, 1, 1),
            [],
            [],
        );

        $pdf = $exporter->export($report);

        self::assertStringStartsWith('%PDF-1.4', $pdf);
        self::assertStringContainsString('CRM REPORT\\nPipeline: 123.45\\nDeals: 3', $pdf);
        self::assertStringContainsString('%%EOF', $pdf);
    }

    public function testBuildClampsNpsAndProvidesFallbackActionWhenNoCountsTrigger(): void
    {
        $companyRepository = $this->createMock(CompanyRepository::class);
        $contactRepository = $this->createMock(ContactRepository::class);
        $employeeRepository = $this->createMock(EmployeeRepository::class);
        $billingRepository = $this->createMock(BillingRepository::class);
        $projectRepository = $this->createMock(ProjectRepository::class);
        $taskRepository = $this->createMock(TaskRepository::class);

        $crm = $this->createMock(Crm::class);
        $crm->method('getId')->willReturn('crm-clamp');

        $companyRepository->method('findScoped')->willReturn([]);
        $employeeRepository->method('findScoped')->willReturn([]);
        $billingRepository->method('findByCrm')->willReturn([]);
        $projectRepository->method('findScoped')->willReturn([]);
        $projectRepository->method('countProjectsByCrm')->willReturn(0);
        $taskRepository->method('countTasksByCrm')->willReturn(0);

        $contact = $this->createMock(Contact::class);
        $contact->method('getId')->willReturn('contact-high-score');
        $contact->method('getFirstName')->willReturn('Alice');
        $contact->method('getLastName')->willReturn('High');
        $contact->method('getEmail')->willReturn('alice.high@example.test');
        $contact->method('getJobTitle')->willReturn('Head of CRM');
        $contact->method('getCity')->willReturn('Paris');
        $contact->method('getScore')->willReturn(999);
        $contactRepository->method('findScoped')->willReturn([$contact]);

        $service = new CrmReportService(
            $companyRepository,
            $contactRepository,
            $employeeRepository,
            $billingRepository,
            $projectRepository,
            $taskRepository,
        );

        $report = $service->build($crm)->toArray();

        self::assertSame(100, $report['kpis']['npsClients']);
        self::assertCount(1, $report['recommendedActions']);
        self::assertSame('Maintenir la cadence de suivi CRM', $report['recommendedActions'][0]['title']);
    }
}
