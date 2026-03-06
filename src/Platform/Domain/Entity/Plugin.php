<?php

declare(strict_types=1);

namespace App\Platform\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Throwable;

use function rawurlencode;
use function str_replace;

/**
 * @package App\Platform
 */
#[ORM\Entity]
#[ORM\Table(name: 'plugin')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Plugin implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(
        name: 'id',
        type: UuidBinaryOrderedTimeType::NAME,
        unique: true,
    )]
    #[Groups([
        'Plugin',
        'Plugin.id',
    ])]
    private UuidInterface $id;

    #[ORM\Column(
        name: 'name',
        type: Types::STRING,
        length: 255,
    )]
    #[Groups([
        'Plugin',
        'Plugin.name',
    ])]
    #[Assert\NotBlank]
    #[Assert\NotNull]
    #[Assert\Length(
        min: 2,
        max: 255,
    )]
    private string $name = '';

    #[ORM\Column(
        name: 'description',
        type: Types::TEXT,
    )]
    #[Groups([
        'Plugin',
        'Plugin.description',
    ])]
    #[Assert\NotNull]
    private string $description = '';

    #[ORM\Column(
        name: 'private',
        type: Types::BOOLEAN,
        options: [
            'default' => false,
        ],
    )]
    #[Groups([
        'Plugin',
        'Plugin.private',
    ])]
    #[Assert\NotNull]
    private bool $private = false;

    #[ORM\Column(
        name: 'photo',
        type: Types::STRING,
        length: 255,
        options: [
            'comment' => 'Plugin photo URL',
        ],
    )]
    #[Groups([
        'Plugin',
        'Plugin.photo',
    ])]
    private string $photo = '';

    #[ORM\Column(
        name: 'enabled',
        type: Types::BOOLEAN,
        options: [
            'default' => true,
        ],
    )]
    #[Groups([
        'Plugin',
        'Plugin.enabled',
    ])]
    #[Assert\NotNull]
    private bool $enabled = true;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

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

    public function isPrivate(): bool
    {
        return $this->private;
    }

    public function setPrivate(bool $private): self
    {
        $this->private = $private;

        return $this;
    }

    public function getPhoto(): string
    {
        return $this->photo;
    }

    public function setPhoto(string $photo): self
    {
        $this->photo = $photo;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;

        return $this;
    }

    public function ensureGeneratedPhoto(): self
    {
        if ($this->photo === '') {
            $name = rawurlencode($this->name);
            $this->photo = 'https://ui-avatars.com/api/?name=' . str_replace('%20', '+', $name);
        }

        return $this;
    }
}
