<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Report;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
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
#[IsGranted(Role::CRM_OWNER->value)]
final readonly class ReportsController
{
    public function __construct(
        private CrmApplicationScopeResolver $scopeResolver,
        private CrmReportService $crmReportService,
        private CrmReportPdfExporter $pdfExporter,
    ) {
    }

    #[Route('/v1/crm/reports', methods: [Request::METHOD_GET])]
        #[OA\Parameter(name: 'format', in: 'query', required: false, schema: new OA\Schema(type: 'string', enum: ['json', 'csv', 'pdf']), description: 'Format de restitution du rapport')]
    #[OA\Get(
        summary: 'Exporter les rapports CRM',
        description: 'Génère un rapport CRM en JSON, CSV ou PDF selon le format demandé.',
        responses: [
            new OA\Response(response: JsonResponse::HTTP_OK, description: 'Rapport généré avec succès.'),
            new OA\Response(response: JsonResponse::HTTP_BAD_REQUEST, description: 'Format de rapport non supporté ou requête invalide.'),
            new OA\Response(response: JsonResponse::HTTP_UNAUTHORIZED, description: 'Authentification requise.'),
            new OA\Response(response: JsonResponse::HTTP_FORBIDDEN, description: 'Accès refusé.'),
            new OA\Response(response: JsonResponse::HTTP_NOT_FOUND, description: 'Application CRM introuvable.'),
            new OA\Response(response: JsonResponse::HTTP_UNPROCESSABLE_ENTITY, description: 'Erreur de validation métier.'),
        ],
    )]
    public function __invoke(Request $request): Response
    {
        $crm = $this->scopeResolver->resolveOrFail('crm-general-core');
        $report = $this->crmReportService->build($crm);
        $format = strtolower($request->query->getString('format', 'json'));

        if ($format === 'csv') {
            return new Response($this->crmReportService->toCsv($report), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, sprintf('crm-report-%s.csv', 'crm-general-core')),
            ]);
        }

        if ($format === 'pdf') {
            return new Response($this->pdfExporter->export($report), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, sprintf('crm-report-%s.pdf', 'crm-general-core')),
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
