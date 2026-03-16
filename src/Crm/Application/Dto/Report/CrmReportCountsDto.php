<?php

declare(strict_types=1);

namespace App\Crm\Application\Dto\Report;

final readonly class CrmReportCountsDto
{
    public function __construct(
        public int $companies,
        public int $contacts,
        public int $employees,
        public int $billings,
        public int $tasks,
    ) {
    }

    /**
     * @return array<string,int>
     */
    public function toArray(): array
    {
        return [
            'companies' => $this->companies,
            'contacts' => $this->contacts,
            'employees' => $this->employees,
            'billings' => $this->billings,
            'tasks' => $this->tasks,
        ];
    }
}
