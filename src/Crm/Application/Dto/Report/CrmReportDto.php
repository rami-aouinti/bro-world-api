<?php

declare(strict_types=1);

namespace App\Crm\Application\Dto\Report;

final readonly class CrmReportDto
{
    /**
     * @param list<CrmReportContactDto> $contacts
     * @param list<CrmRecommendedActionDto> $recommendedActions
     */
    public function __construct(
        public CrmReportMetadataDto $metadata,
        public CrmReportKpisDto $kpis,
        public CrmReportCountsDto $counts,
        public array $contacts,
        public array $recommendedActions,
    ) {
    }

    /**
     * @return array<string,mixed>
     */
    public function toArray(): array
    {
        return [
            'metadata' => $this->metadata->toArray(),
            'kpis' => $this->kpis->toArray(),
            'counts' => $this->counts->toArray(),
            'contacts' => array_map(static fn (CrmReportContactDto $contact) => $contact->toArray(), $this->contacts),
            'recommendedActions' => array_map(static fn (CrmRecommendedActionDto $action) => $action->toArray(), $this->recommendedActions),
        ];
    }
}
