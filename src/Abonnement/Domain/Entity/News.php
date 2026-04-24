<?php

declare(strict_types=1);

namespace App\Abonnement\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Throwable;

#[ORM\Entity]
#[ORM\Table(name: 'abonnement_news')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class News implements EntityInterface
{
    use Timestampable;
    use Uuid;

    final public const string SET_NEWS = 'set.News';

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true, nullable: false)]
    #[Groups(['News', 'News.id', self::SET_NEWS])]
    private UuidInterface $id;

    #[ORM\Column(name: 'title', type: Types::STRING, length: 255, nullable: false)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 255)]
    #[Groups(['News', 'News.title', self::SET_NEWS])]
    private string $title = '';

    #[ORM\Column(name: 'description', type: Types::TEXT, nullable: false)]
    #[Assert\NotBlank]
    #[Groups(['News', 'News.description', self::SET_NEWS])]
    private string $description = '';

    #[ORM\Column(name: 'execute_at', type: Types::DATETIME_IMMUTABLE, nullable: false)]
    #[Assert\NotNull]
    #[Groups(['News', 'News.executeAt', self::SET_NEWS])]
    private DateTimeImmutable $executeAt;

    #[ORM\Column(name: 'executed', type: Types::BOOLEAN, nullable: false, options: ['default' => false])]
    #[Groups(['News', 'News.executed', self::SET_NEWS])]
    private bool $executed = false;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->executeAt = new DateTimeImmutable();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getExecuteAt(): DateTimeImmutable
    {
        return $this->executeAt;
    }

    public function setExecuteAt(DateTimeImmutable $executeAt): self
    {
        $this->executeAt = $executeAt;

        return $this;
    }

    public function isExecuted(): bool
    {
        return $this->executed;
    }

    public function setExecuted(bool $executed): self
    {
        $this->executed = $executed;

        return $this;
    }
}
