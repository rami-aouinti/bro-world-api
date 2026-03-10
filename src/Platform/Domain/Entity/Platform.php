<?php

declare(strict_types=1);

namespace App\Platform\Domain\Entity;

use App\Configuration\Domain\Entity\Configuration;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\Platform\Domain\Enum\PlatformKey;
use App\Platform\Domain\Enum\PlatformStatus;
use App\User\Domain\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
use function str_starts_with;

/**
 * @package App\Platform
 */
#[ORM\Entity]
#[ORM\Table(name: 'platform_platform')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Platform implements EntityInterface
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
        'Platform',
        'Platform.id',
    ])]
    private UuidInterface $id;

    #[ORM\Column(
        name: 'name',
        type: Types::STRING,
        length: 255,
    )]
    #[Groups([
        'Platform',
        'Platform.name',
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
        'Platform',
        'Platform.description',
    ])]
    #[Assert\NotNull]
    private string $description = '';

    #[ORM\Column(
        name: 'platform_key',
        type: Types::STRING,
        length: 25,
        enumType: PlatformKey::class,
    )]
    #[Groups([
        'Platform',
        'Platform.platformKey',
    ])]
    #[Assert\NotNull]
    private PlatformKey $platformKey = PlatformKey::CRM;

    #[ORM\Column(
        name: 'private',
        type: Types::BOOLEAN,
        options: [
            'default' => false,
        ],
    )]
    #[Groups([
        'Platform',
        'Platform.private',
    ])]
    #[Assert\NotNull]
    private bool $private = false;

    #[ORM\Column(
        name: 'photo',
        type: Types::STRING,
        length: 255,
        options: [
            'comment' => 'Platform photo URL',
        ],
    )]
    #[Groups([
        'Platform',
        'Platform.photo',
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
        'Platform',
        'Platform.enabled',
    ])]
    #[Assert\NotNull]
    private bool $enabled = true;

    #[ORM\Column(
        name: 'status',
        type: Types::STRING,
        length: 25,
        enumType: PlatformStatus::class,
        options: [
            'default' => PlatformStatus::ACTIVE->value,
        ],
    )]
    #[Groups([
        'Platform',
        'Platform.status',
    ])]
    #[Assert\NotNull]
    private PlatformStatus $status = PlatformStatus::ACTIVE;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    #[Groups([
        'Platform',
        'Platform.user',
    ])]
    private ?User $user = null;

    /**
     * @var Collection<int, Configuration>|ArrayCollection<int, Configuration>
     */
    #[ORM\OneToMany(targetEntity: Configuration::class, mappedBy: 'platform')]
    #[Groups([
        'Platform.configurations',
    ])]
    private Collection | ArrayCollection $configurations;

    /**
     * @var Collection<int, Plugin>|ArrayCollection<int, Plugin>
     */
    #[ORM\OneToMany(targetEntity: Plugin::class, mappedBy: 'platform')]
    #[Groups([
        'Platform.plugins',
    ])]
    private Collection | ArrayCollection $plugins;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->configurations = new ArrayCollection();
        $this->plugins = new ArrayCollection();
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

    public function getPlatformKey(): PlatformKey
    {
        return $this->platformKey;
    }

    public function getPlatformKeyValue(): string
    {
        return $this->platformKey->value;
    }

    public function setPlatformKey(PlatformKey|string $platformKey): self
    {
        $this->platformKey = $platformKey instanceof PlatformKey ? $platformKey : PlatformKey::from($platformKey);

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

    public function getStatus(): PlatformStatus
    {
        return $this->status;
    }

    public function getStatusValue(): string
    {
        return $this->status->value;
    }

    public function setStatus(PlatformStatus|string $status): self
    {
        $this->status = $status instanceof PlatformStatus ? $status : PlatformStatus::from($status);

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    /**
     * @return Collection<int, Configuration>|ArrayCollection<int, Configuration>
     */
    public function getConfigurations(): Collection | ArrayCollection
    {
        return $this->configurations;
    }

    public function addConfiguration(Configuration $configuration): self
    {
        if ($this->configurations->contains($configuration) === false) {
            $this->configurations->add($configuration);
            $configuration->setPlatform($this);
        }

        return $this;
    }

    public function removeConfiguration(Configuration $configuration): self
    {
        if ($this->configurations->removeElement($configuration) && $configuration->getPlatform() === $this) {
            $configuration->setPlatform(null);
        }

        return $this;
    }

    /**
     * @return Collection<int, Plugin>|ArrayCollection<int, Plugin>
     */
    public function getPlugins(): Collection | ArrayCollection
    {
        return $this->plugins;
    }

    public function addPlugin(Plugin $plugin): self
    {
        if ($this->plugins->contains($plugin) === false) {
            $this->plugins->add($plugin);
            $plugin->setPlatform($this);
        }

        return $this;
    }

    public function removePlugin(Plugin $plugin): self
    {
        if ($this->plugins->removeElement($plugin) && $plugin->getPlatform() === $this) {
            $plugin->setPlatform(null);
        }

        return $this;
    }

    public function ensureGeneratedPhoto(): self
    {
        if ($this->photo === '') {
            $name = rawurlencode($this->name);
            $this->photo = 'https://ui-avatars.com/api/?name=' . str_replace('%20', '+', $name);

            return $this;
        }

        if (!str_starts_with($this->photo, 'http://') && !str_starts_with($this->photo, 'https://')) {
            $this->photo = '/uploads/platforms/' . ltrim($this->photo, '/');
        }

        return $this;
    }
}
