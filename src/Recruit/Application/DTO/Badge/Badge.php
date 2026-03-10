<?php

declare(strict_types=1);

namespace App\Recruit\Application\DTO\Badge;

use App\General\Application\DTO\RestDto;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\Recruit\Domain\Entity\Badge as Entity;
use Override;

class Badge extends RestDto
{
    protected string $label = '';
    public function getLabel(): string
    {
        return $this->label;
    }
    public function setLabel(string $label): self
    {
        $this->setVisited('label');
        $this->label = $label;

        return $this;
    }
    #[Override]
    public function load(EntityInterface $entity): self
    {
        if ($entity instanceof Entity) {
            $this->id = $entity->getId();
            $this->label = $entity->getLabel();
        }

return $this;
    }
}
