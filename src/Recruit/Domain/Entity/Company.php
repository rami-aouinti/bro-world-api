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
#[ORM\Table(name: 'recruit_company')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Company implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    #[Groups(['Company', 'Company.id'])]
    private UuidInterface $id;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    #[Groups(['Company', 'Company.name'])]
    private string $name = '';

    #[ORM\Column(name: 'logo', type: Types::STRING, length: 25, options: ['default' => ''])]
    #[Groups(['Company', 'Company.logo'])]
    private string $logo = '';

    #[ORM\Column(name: 'sector', type: Types::STRING, length: 100, options: ['default' => ''])]
    #[Groups(['Company', 'Company.sector'])]
    private string $sector = '';

    #[ORM\Column(name: 'size', type: Types::STRING, length: 100, options: ['default' => ''])]
    #[Groups(['Company', 'Company.size'])]
    private string $size = '';

    public function __construct()
    {
        $this->id = $this->createUuid();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getName(): string { return $this->name; }
    public function setName(string $name): self { $this->name = $name; return $this; }
    public function getLogo(): string { return $this->logo; }
    public function setLogo(string $logo): self { $this->logo = $logo; return $this; }
    public function getSector(): string { return $this->sector; }
    public function setSector(string $sector): self { $this->sector = $sector; return $this; }
    public function getSize(): string { return $this->size; }
    public function setSize(string $size): self { $this->size = $size; return $this; }
}
