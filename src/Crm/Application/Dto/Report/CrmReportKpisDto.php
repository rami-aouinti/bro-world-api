<?php

declare(strict_types=1);

namespace App\Crm\Application\Dto\Report;

final readonly class CrmReportKpisDto
{
    public function __construct(
        public float $pipeline,
        public int $dealsWon,
        public int $cycleDays,
        public int $npsClients,
    ) {
    }

    /**
     * @return array<string,int|float>
     */
    public function toArray(): array
    {
        return [
            'pipeline' => $this->pipeline,
            'dealsWon' => $this->dealsWon,
            'cycleDays' => $this->cycleDays,
            'npsClients' => $this->npsClients,
        ];
    }
}
