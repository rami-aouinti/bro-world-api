<?php

declare(strict_types=1);

namespace App\Recruit\Application\DTO\Salary;

use App\General\Application\DTO\RestDto;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\Recruit\Domain\Entity\Salary as Entity;
use Override;

class Salary extends RestDto
{
    protected int $min = 0;
    protected int $max = 0;
    protected string $currency = 'EUR';
    protected string $period = 'year';

    public function getMin(): int
    {
        return $this->min;
    }
    public function setMin(int $min): self
    {
        $this->setVisited('min');
        $this->min = $min;

        return $this;
    }
    public function getMax(): int
    {
        return $this->max;
    }
    public function setMax(int $max): self
    {
        $this->setVisited('max');
        $this->max = $max;

        return $this;
    }
    public function getCurrency(): string
    {
        return $this->currency;
    }
    public function setCurrency(string $currency): self
    {
        $this->setVisited('currency');
        $this->currency = $currency;

        return $this;
    }
    public function getPeriod(): string
    {
        return $this->period;
    }
    public function setPeriod(string $period): self
    {
        $this->setVisited('period');
        $this->period = $period;

        return $this;
    }

    #[Override]
    public function load(EntityInterface $entity): self
    {
        if ($entity instanceof Entity) {
            $this->id = $entity->getId();
            $this->min = $entity->getMin();
            $this->max = $entity->getMax();
            $this->currency = $entity->getCurrency();
            $this->period = $entity->getPeriod();
        }

        return $this;
    }
}
