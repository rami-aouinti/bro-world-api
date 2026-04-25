<?php

declare(strict_types=1);

namespace App\Crm\Application\Dto\Dashboard;

final readonly class CrmDashboardKpiTileDto
{
    public function __construct(
        public string $title,
        public string $value,
        public string $trend,
        public string $tone,
        public string $icon,
        public string $caption,
    ) {
    }

    /**
     * @return array<string,string>
     */
    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'value' => $this->value,
            'trend' => $this->trend,
            'tone' => $this->tone,
            'icon' => $this->icon,
            'caption' => $this->caption,
        ];
    }
}
