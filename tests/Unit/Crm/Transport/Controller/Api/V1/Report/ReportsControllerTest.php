<?php

declare(strict_types=1);

namespace App\Tests\Unit\Crm\Transport\Controller\Api\V1\Report;

use App\Crm\Application\Dto\Report\CrmReportCountsDto;
use App\Crm\Application\Dto\Report\CrmReportDto;
use App\Crm\Application\Dto\Report\CrmReportKpisDto;
use App\Crm\Application\Dto\Report\CrmReportMetadataDto;
use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Application\Service\CrmReportService;
use App\Crm\Application\Service\Report\CrmReportPdfExporter;
use App\Crm\Domain\Entity\Crm;
use App\Crm\Transport\Controller\Api\V1\Report\ReportsController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

final class ReportsControllerTest extends TestCase
{
    public function testUnsupportedFormatReturns400(): void
    {
        $scopeResolver = $this->createMock(CrmApplicationScopeResolver::class);
        $reportService = $this->createMock(CrmReportService::class);
        $pdfExporter = $this->createMock(CrmReportPdfExporter::class);
        $crm = $this->createMock(Crm::class);

        $scopeResolver->method('resolveOrFail')->willReturn($crm);
        $reportService->method('build')->willReturn(new CrmReportDto(
            new CrmReportMetadataDto('rolling-30d', 'UTC', '2024-05-01T00:00:00+00:00', 'v1'),
            new CrmReportKpisDto(1.0, 1, 1, 1),
            new CrmReportCountsDto(1, 1, 1, 1, 1),
            [],
            [],
        ));

        $controller = new ReportsController($scopeResolver, $reportService, $pdfExporter);
        $response = $controller->__invoke('app', new Request(['format' => 'xml']));

        self::assertSame(JsonResponse::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testJsonPayloadIncludesMetadata(): void
    {
        $scopeResolver = $this->createMock(CrmApplicationScopeResolver::class);
        $reportService = $this->createMock(CrmReportService::class);
        $pdfExporter = $this->createMock(CrmReportPdfExporter::class);
        $crm = $this->createMock(Crm::class);

        $scopeResolver->method('resolveOrFail')->willReturn($crm);
        $reportService->method('build')->willReturn(new CrmReportDto(
            new CrmReportMetadataDto('rolling-30d', 'UTC', '2024-05-01T00:00:00+00:00', 'v1'),
            new CrmReportKpisDto(1.0, 1, 1, 1),
            new CrmReportCountsDto(1, 1, 1, 1, 1),
            [],
            [],
        ));

        $controller = new ReportsController($scopeResolver, $reportService, $pdfExporter);
        $response = $controller->__invoke('app', new Request());
        $payload = json_decode((string)$response->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame('rolling-30d', $payload['metadata']['period']);
        self::assertSame('UTC', $payload['metadata']['timezone']);
        self::assertSame('v1', $payload['metadata']['version']);
    }
}
