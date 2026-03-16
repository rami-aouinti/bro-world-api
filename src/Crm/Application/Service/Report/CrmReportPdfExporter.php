<?php

declare(strict_types=1);

namespace App\Crm\Application\Service\Report;

use App\Crm\Application\Dto\Report\CrmReportDto;

final class CrmReportPdfExporter
{
    public function export(CrmReportDto $report): string
    {
        $reportData = $report->toArray();
        $text = 'CRM REPORT\nPipeline: ' . ($reportData['kpis']['pipeline'] ?? 0) . '\nDeals: ' . ($reportData['kpis']['dealsWon'] ?? 0);
        $stream = 'BT /F1 18 Tf 40 760 Td (' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text) . ') Tj ET';
        $len = strlen($stream);

        return "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n3 0 obj << /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >> endobj\n4 0 obj << /Length {$len} >> stream\n{$stream}\nendstream endobj\n5 0 obj << /Type /Font /Subtype /Type1 /BaseFont /Helvetica >> endobj\nxref\n0 6\n0000000000 65535 f \n0000000010 00000 n \n0000000060 00000 n \n0000000117 00000 n \n0000000243 00000 n \n0000000365 00000 n \ntrailer << /Size 6 /Root 1 0 R >>\nstartxref\n435\n%%EOF";
    }
}
