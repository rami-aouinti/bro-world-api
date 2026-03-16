<?php

declare(strict_types=1);

namespace App\Crm\Application\Dto\Report;

final readonly class CrmReportContactDto
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $email,
        public ?string $jobTitle,
        public ?string $city,
        public int $score,
    ) {
    }

    /**
     * @return array{id:string,name:string,email:?string,jobTitle:?string,city:?string,score:int}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'jobTitle' => $this->jobTitle,
            'city' => $this->city,
            'score' => $this->score,
        ];
    }
}
