<?php

declare(strict_types=1);

namespace App\Shop\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'shop_tag')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Tag implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\Column(name: 'label', type: Types::STRING, length: 80)]
    private string $label = '';

    /** @var Collection<int, Product>|ArrayCollection<int, Product> */
    #[ORM\ManyToMany(targetEntity: Product::class, mappedBy: 'tags')]
    private Collection|ArrayCollection $products;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->products = new ArrayCollection();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }
    public function getLabel(): string
    {
        return $this->label;
    }
    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @return Collection<int, Product>|ArrayCollection<int, Product>
     */
    public function getProducts(): Collection|ArrayCollection
    {
        return $this->products;
    }
}
