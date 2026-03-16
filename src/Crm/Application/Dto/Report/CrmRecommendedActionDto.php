<?php

declare(strict_types=1);

namespace App\Crm\Application\Dto\Report;

final readonly class CrmRecommendedActionDto
{
    public function __construct(
        public string $priority,
        public string $title,
        public string $owner,
        public int $etaDays,
    ) {
    }

    /**
     * @return array{priority:string,title:string,owner:string,etaDays:int}
     */
    public function toArray(): array
    {
        return [
            'priority' => $this->priority,
            'title' => $this->title,
            'owner' => $this->owner,
            'etaDays' => $this->etaDays,
        ];
    }
}
