<?php

declare(strict_types=1);

namespace App\Crm\Application\Dto\Dashboard;

final readonly class CrmExecutiveDashboardDto
{
    /**
     * @param list<CrmDashboardKpiTileDto> $kpiTiles
     * @param list<CrmDashboardFunnelStageDto> $funnelStages
     * @param list<CrmDashboardTeamDto> $teams
     * @param list<CrmDashboardAgendaItemDto> $todayAgenda
     */
    public function __construct(
        public array $kpiTiles,
        public array $funnelStages,
        public array $teams,
        public array $todayAgenda,
    ) {
    }

    /**
     * @return array<string,list<array<string,int|string>>>
     */
    public function toArray(): array
    {
        return [
            'kpiTiles' => array_map(static fn (CrmDashboardKpiTileDto $tile) => $tile->toArray(), $this->kpiTiles),
            'funnelStages' => array_map(static fn (CrmDashboardFunnelStageDto $stage) => $stage->toArray(), $this->funnelStages),
            'teams' => array_map(static fn (CrmDashboardTeamDto $team) => $team->toArray(), $this->teams),
            'todayAgenda' => array_map(static fn (CrmDashboardAgendaItemDto $agendaItem) => $agendaItem->toArray(), $this->todayAgenda),
        ];
    }
}
