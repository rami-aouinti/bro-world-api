<?php

declare(strict_types=1);

namespace App\Crm\Application\Dto\Report;

final readonly class CrmReportMetadataDto
{
    public function __construct(
        public string $period,
        public string $timezone,
        public string $generatedAt,
        public string $version,
    ) {
    }

    /**
     * @return array<string,string>
     */
    public function toArray(): array
    {
        return [
            'period' => $this->period,
            'timezone' => $this->timezone,
            'generatedAt' => $this->generatedAt,
            'version' => $this->version,
        ];
    }
}
