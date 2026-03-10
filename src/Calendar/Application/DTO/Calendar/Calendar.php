<?php

declare(strict_types=1);

namespace App\Calendar\Application\DTO\Calendar;

use App\Calendar\Domain\Entity\Calendar as Entity;
use App\General\Application\DTO\RestDto;
use App\General\Application\Validator\Constraints as AppAssert;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\User\Domain\Entity\User;
use Override;

class Calendar extends RestDto
{
    #[AppAssert\EntityReferenceExists(entityClass: User::class)]
    protected ?User $user = null;

    protected string $title = '';

    public function getUser(): ?User
    {
        return $this->user;
    }
    public function setUser(?User $user): self
    {
        $this->setVisited('user');
        $this->user = $user;

        return $this;
    }
    public function getTitle(): string
    {
        return $this->title;
    }
    public function setTitle(string $title): self
    {
        $this->setVisited('title');
        $this->title = $title;

        return $this;
    }

    #[Override]
    public function load(EntityInterface $entity): self
    {
        if ($entity instanceof Entity) {
            $this->id = $entity->getId();
            $this->user = $entity->getUser();
            $this->title = $entity->getTitle();
        }

        return $this;
    }
}
