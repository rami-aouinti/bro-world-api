<?php

declare(strict_types=1);

namespace App\Recruit\Application\DTO\Applicant;

use App\General\Application\DTO\RestDto;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\Recruit\Domain\Entity\Applicant as Entity;
use App\Recruit\Domain\Entity\Resume;
use App\User\Domain\Entity\User;
use Override;

class Applicant extends RestDto
{
    protected ?User $user = null;
    protected ?Resume $resume = null;
    protected string $coverLetter = '';

    public function getUser(): ?User { return $this->user; }
    public function setUser(User $user): self { $this->setVisited('user'); $this->user = $user; return $this; }
    public function getResume(): ?Resume { return $this->resume; }
    public function setResume(Resume $resume): self { $this->setVisited('resume'); $this->resume = $resume; return $this; }
    public function getCoverLetter(): string { return $this->coverLetter; }
    public function setCoverLetter(string $coverLetter): self { $this->setVisited('coverLetter'); $this->coverLetter = $coverLetter; return $this; }

    #[Override]
    public function load(EntityInterface $entity): self
    {
        if ($entity instanceof Entity) {
            $this->id = $entity->getId();
            $this->user = $entity->getUser();
            $this->resume = $entity->getResume();
            $this->coverLetter = $entity->getCoverLetter();
        }

        return $this;
    }
}
