<?php

declare(strict_types=1);

namespace App\Platform\Domain\Entity;

use App\Configuration\Domain\Entity\Configuration;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\Platform\Domain\Enum\PluginKey;
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
#[ORM\Table(name: 'platform_plugin')]
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
        name: 'plugin_key',
        type: Types::STRING,
        length: 25,
        enumType: PluginKey::class,
    )]
    #[Groups([
        'Plugin',
        'Plugin.pluginKey',
    ])]
    #[Assert\NotNull]
    private PluginKey $pluginKey = PluginKey::CHAT;

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

    #[ORM\ManyToOne(targetEntity: Platform::class, inversedBy: 'plugins')]
    #[ORM\JoinColumn(name: 'platform_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    #[Groups([
        'Plugin',
        'Plugin.platform',
    ])]
    private ?Platform $platform = null;

    /**
     * @var Collection<int, Configuration>|ArrayCollection<int, Configuration>
     */
    #[ORM\OneToMany(targetEntity: Configuration::class, mappedBy: 'plugin')]
    #[Groups([
        'Plugin.configurations',
    ])]
    private Collection | ArrayCollection $configurations;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->configurations = new ArrayCollection();
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

    public function getPluginKey(): PluginKey
    {
        return $this->pluginKey;
    }

    public function getPluginKeyValue(): string
    {
        return $this->pluginKey->value;
    }

    public function setPluginKey(PluginKey|string $pluginKey): self
    {
        $this->pluginKey = $pluginKey instanceof PluginKey ? $pluginKey : PluginKey::from($pluginKey);

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

    public function getPlatform(): ?Platform
    {
        return $this->platform;
    }

    public function setPlatform(?Platform $platform): self
    {
        $this->platform = $platform;

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
            $configuration->setPlugin($this);
        }

        return $this;
    }

    public function removeConfiguration(Configuration $configuration): self
    {
        if ($this->configurations->removeElement($configuration) && $configuration->getPlugin() === $this) {
            $configuration->setPlugin(null);
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
            $this->photo = '/uploads/plugins/' . ltrim($this->photo, '/');
        }

        return $this;
    }
}
