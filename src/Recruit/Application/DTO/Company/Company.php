<?php

declare(strict_types=1);

namespace App\Recruit\Application\DTO\Company;

use App\General\Application\DTO\RestDto;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\Recruit\Domain\Entity\Company as Entity;
use Override;

class Company extends RestDto
{
    protected string $name = '';
    protected string $logo = '';
    protected string $sector = '';
    protected string $size = '';

    public function getName(): string
    {
        return $this->name;
    }
    public function setName(string $name): self
    {
        $this->setVisited('name');
        $this->name = $name;

        return $this;
    }
    public function getLogo(): string
    {
        return $this->logo;
    }
    public function setLogo(string $logo): self
    {
        $this->setVisited('logo');
        $this->logo = $logo;

        return $this;
    }
    public function getSector(): string
    {
        return $this->sector;
    }
    public function setSector(string $sector): self
    {
        $this->setVisited('sector');
        $this->sector = $sector;

        return $this;
    }
    public function getSize(): string
    {
        return $this->size;
    }
    public function setSize(string $size): self
    {
        $this->setVisited('size');
        $this->size = $size;

        return $this;
    }

    #[Override]
    public function load(EntityInterface $entity): self
    {
        if ($entity instanceof Entity) {
            $this->id = $entity->getId();
            $this->name = $entity->getName();
            $this->logo = $entity->getLogo();
            $this->sector = $entity->getSector();
            $this->size = $entity->getSize();
        }

        return $this;
    }
}
