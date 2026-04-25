<?php

declare(strict_types=1);

namespace App\Crm\Application\Dto\Dashboard;

final readonly class CrmDashboardTeamDto
{
    public function __construct(
        public string $name,
        public string $owner,
        public string $velocity,
        public string $status,
    ) {
    }

    /**
     * @return array<string,string>
     */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'owner' => $this->owner,
            'velocity' => $this->velocity,
            'status' => $this->status,
        ];
    }
}
