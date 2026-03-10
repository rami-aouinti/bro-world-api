<?php

declare(strict_types=1);

namespace App\Page\Domain\Entity;

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
#[ORM\Table(name: 'page_language')]
#[ORM\UniqueConstraint(name: 'uq_page_language_code', columns: ['code'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class PageLanguage implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    #[Groups(['PageLanguage', 'PageLanguage.id'])]
    private UuidInterface $id;

    #[ORM\Column(name: 'code', type: Types::STRING, length: 10)]
    #[Groups(['PageLanguage', 'PageLanguage.code'])]
    private string $code;

    #[ORM\Column(name: 'label', type: Types::STRING, length: 64)]
    #[Groups(['PageLanguage', 'PageLanguage.label'])]
    private string $label;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->code = '';
        $this->label = '';
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }
    public function getCode(): string
    {
        return $this->code;
    }
    public function setCode(string $code): self
    {
        $this->code = $code;

        return $this;
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
}
