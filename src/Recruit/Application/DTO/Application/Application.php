<?php

declare(strict_types=1);

namespace App\Recruit\Application\DTO\Application;

use App\General\Application\DTO\RestDto;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\Recruit\Domain\Entity\Applicant;
use App\Recruit\Domain\Entity\Application as Entity;
use App\Recruit\Domain\Entity\Job;
use App\Recruit\Domain\Enum\ApplicationStatus;
use Override;

class Application extends RestDto
{
    protected ?Applicant $applicant = null;
    protected ?Job $job = null;
    protected string $status = ApplicationStatus::WAITING->value;

    public function getApplicant(): ?Applicant
    {
        return $this->applicant;
    }
    public function setApplicant(Applicant $applicant): self
    {
        $this->setVisited('applicant');
        $this->applicant = $applicant;

        return $this;
    }
    public function getJob(): ?Job
    {
        return $this->job;
    }
    public function setJob(Job $job): self
    {
        $this->setVisited('job');
        $this->job = $job;

        return $this;
    }
    public function getStatus(): string
    {
        return $this->status;
    }
    public function setStatus(string $status): self
    {
        $this->setVisited('status');
        $this->status = $status;

        return $this;
    }

    #[Override]
    public function load(EntityInterface $entity): self
    {
        if ($entity instanceof Entity) {
            $this->id = $entity->getId();
            $this->applicant = $entity->getApplicant();
            $this->job = $entity->getJob();
            $this->status = $entity->getStatusValue();
        }

        return $this;
    }
}
