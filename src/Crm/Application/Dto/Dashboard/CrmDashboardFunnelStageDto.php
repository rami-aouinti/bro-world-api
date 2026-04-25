<?php

declare(strict_types=1);

namespace App\Crm\Application\Dto\Dashboard;

final readonly class CrmDashboardFunnelStageDto
{
    public function __construct(
        public string $label,
        public int $deals,
        public string $amount,
    ) {
    }

    /**
     * @return array<string,int|string>
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'deals' => $this->deals,
            'amount' => $this->amount,
        ];
    }
}
