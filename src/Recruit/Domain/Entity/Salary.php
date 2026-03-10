<?php

declare(strict_types=1);

namespace App\Recruit\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'recruit_salary')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Salary implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    #[Groups(['Salary', 'Salary.id'])]
    private UuidInterface $id;

    #[ORM\Column(name: 'min_salary', type: Types::INTEGER)]
    #[Groups(['Salary', 'Salary.min'])]
    private int $min = 0;

    #[ORM\Column(name: 'max_salary', type: Types::INTEGER)]
    #[Groups(['Salary', 'Salary.max'])]
    private int $max = 0;

    #[ORM\Column(name: 'currency', type: Types::STRING, length: 5, options: [
        'default' => 'EUR',
    ])]
    #[Groups(['Salary', 'Salary.currency'])]
    private string $currency = 'EUR';

    #[ORM\Column(name: 'period', type: Types::STRING, length: 20, options: [
        'default' => 'year',
    ])]
    #[Groups(['Salary', 'Salary.period'])]
    private string $period = 'year';

    public function __construct()
    {
        $this->id = $this->createUuid();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }
    public function getMin(): int
    {
        return $this->min;
    }
    public function setMin(int $min): self
    {
        $this->min = $min;

        return $this;
    }
    public function getMax(): int
    {
        return $this->max;
    }
    public function setMax(int $max): self
    {
        $this->max = $max;

        return $this;
    }
    public function getCurrency(): string
    {
        return $this->currency;
    }
    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;

        return $this;
    }
    public function getPeriod(): string
    {
        return $this->period;
    }
    public function setPeriod(string $period): self
    {
        $this->period = $period;

        return $this;
    }
}
