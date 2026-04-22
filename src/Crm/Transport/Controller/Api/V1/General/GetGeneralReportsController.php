<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\General;

use App\Crm\Application\Service\CrmReportService;
use App\Crm\Application\Service\Report\CrmReportPdfExporter;
use App\Role\Domain\Enum\Role;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Crm')]
final readonly class GetGeneralReportsController
{
    public function __construct(
        private CrmReportService $crmReportService,
        private CrmReportPdfExporter $pdfExporter,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $report = $this->crmReportService->buildGlobal();
        $format = strtolower($request->query->getString('format', 'json'));

        if ($format === 'csv') {
            return new Response($this->crmReportService->toCsv($report), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'crm-report-general.csv'),
            ]);
        }

        if ($format === 'pdf') {
            return new Response($this->pdfExporter->export($report), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'crm-report-general.pdf'),
            ]);
        }

        if ($format !== 'json') {
            return new JsonResponse([
                'message' => sprintf('Unsupported report format "%s".', $format),
                'supportedFormats' => ['json', 'csv', 'pdf'],
            ], JsonResponse::HTTP_BAD_REQUEST);
        }

        return new JsonResponse($report->toArray());
    }
}
