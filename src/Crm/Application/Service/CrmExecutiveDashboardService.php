<?php

declare(strict_types=1);

namespace App\Crm\Application\Service;

use App\Crm\Application\Dto\Dashboard\CrmDashboardAgendaItemDto;
use App\Crm\Application\Dto\Dashboard\CrmDashboardFunnelStageDto;
use App\Crm\Application\Dto\Dashboard\CrmDashboardKpiTileDto;
use App\Crm\Application\Dto\Dashboard\CrmDashboardTeamDto;
use App\Crm\Application\Dto\Dashboard\CrmExecutiveDashboardDto;

final readonly class CrmExecutiveDashboardService
{
    public function build(): CrmExecutiveDashboardDto
    {
        return new CrmExecutiveDashboardDto(
            kpiTiles: [
                new CrmDashboardKpiTileDto('MRR', '$284,700', '+12.4%', 'success', 'mdi-cash-multiple', 'vs month dernier'),
                new CrmDashboardKpiTileDto('Nouveaux leads', '1,482', '+8.1%', 'success', 'mdi-account-multiple-plus-outline', '7 derniers jours'),
                new CrmDashboardKpiTileDto('Taux de conversion', '34.8%', '-1.7%', 'warning', 'mdi-chart-line-variant', 'pipeline global'),
                new CrmDashboardKpiTileDto('Tickets SLA en retard', '17', '-6', 'success', 'mdi-timer-alert-outline', 'objectif: < 20'),
            ],
            funnelStages: [
                new CrmDashboardFunnelStageDto('Prospection', 54, '$410k'),
                new CrmDashboardFunnelStageDto('Qualification', 31, '$295k'),
                new CrmDashboardFunnelStageDto('Proposition', 19, '$188k'),
                new CrmDashboardFunnelStageDto('Négociation', 11, '$121k'),
                new CrmDashboardFunnelStageDto('Closing', 7, '$84k'),
            ],
            teams: [
                new CrmDashboardTeamDto('Sales Ops', 'Amine', '92%', 'Excellent'),
                new CrmDashboardTeamDto('Customer Success', 'Meriem', '87%', 'Stable'),
                new CrmDashboardTeamDto('Partnerships', 'Yassir', '73%', 'À surveiller'),
                new CrmDashboardTeamDto('Finance CRM', 'Lina', '95%', 'Excellent'),
            ],
            todayAgenda: [
                new CrmDashboardAgendaItemDto('09:00', 'QBR - Compte Enterprise Orbitex', 'Account Team A'),
                new CrmDashboardAgendaItemDto('11:30', 'Validation devis 2026 / segment SaaS', 'Finance CRM'),
                new CrmDashboardAgendaItemDto('14:00', 'Sprint planning - intégrations API', 'RevOps + Dev'),
                new CrmDashboardAgendaItemDto('16:30', 'Point risques churn clients premium', 'Customer Success'),
            ],
        );
    }
}
