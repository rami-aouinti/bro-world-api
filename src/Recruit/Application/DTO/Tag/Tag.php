<?php

declare(strict_types=1);

namespace App\Recruit\Application\DTO\Tag;

use App\General\Application\DTO\RestDto;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\Recruit\Domain\Entity\Tag as Entity;
use Override;

class Tag extends RestDto
{
    protected string $label = '';
    public function getLabel(): string { return $this->label; }
    public function setLabel(string $label): self { $this->setVisited('label'); $this->label = $label; return $this; }
    #[Override]
    public function load(EntityInterface $entity): self { if ($entity instanceof Entity) { $this->id=$entity->getId(); $this->label=$entity->getLabel(); } return $this; }
}
