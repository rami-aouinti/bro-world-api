<?php

declare(strict_types=1);

namespace App\Crm\Transport\Controller\Api\V1\Report;

use App\Crm\Application\Service\CrmApplicationScopeResolver;
use App\Crm\Application\Service\CrmReportService;
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
    ) {}

    #[Route('/v1/crm/applications/{applicationSlug}/reports', methods: [Request::METHOD_GET])]
    public function __invoke(string $applicationSlug, Request $request): Response
    {
        $crm = $this->scopeResolver->resolveOrFail($applicationSlug);
        $report = $this->crmReportService->build($crm);
        $format = strtolower($request->query->getString('format', 'json'));

        if ($format === 'csv') {
            return new Response($this->crmReportService->toCsv($report), 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, sprintf('crm-report-%s.csv', $applicationSlug)),
            ]);
        }

        if ($format === 'pdf') {
            return new Response($this->crmReportService->toPdf($report), 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, sprintf('crm-report-%s.pdf', $applicationSlug)),
            ]);
        }

        return new JsonResponse($report);
    }
}
