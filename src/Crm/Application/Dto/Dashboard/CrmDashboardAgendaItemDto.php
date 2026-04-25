<?php

declare(strict_types=1);

namespace App\Crm\Application\Dto\Dashboard;

final readonly class CrmDashboardAgendaItemDto
{
    public function __construct(
        public string $time,
        public string $event,
        public string $owner,
    ) {
    }

    /**
     * @return array<string,string>
     */
    public function toArray(): array
    {
        return [
            'time' => $this->time,
            'event' => $this->event,
            'owner' => $this->owner,
        ];
    }
}
