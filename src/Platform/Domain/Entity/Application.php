<?php

declare(strict_types=1);

namespace App\Platform\Domain\Entity;

use App\Configuration\Domain\Entity\Configuration;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\Platform\Domain\Enum\PlatformStatus;
use App\User\Domain\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Throwable;

use function iconv;
use function is_string;
use function preg_replace;
use function rawurlencode;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function substr;
use function trim;

#[ORM\Entity]
#[ORM\Table(name: 'platform_application', indexes: [new ORM\Index(name: 'idx_platform_application_slug', columns: ['slug'])])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Application implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Platform::class)]
    #[ORM\JoinColumn(name: 'platform_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?Platform $platform = null;

    #[ORM\Column(name: 'title', type: Types::STRING, length: 255)]
    #[Assert\NotBlank]
    #[Assert\NotNull]
    #[Assert\Length(min: 2, max: 255)]
    private string $title = '';

    #[ORM\Column(name: 'slug', type: Types::STRING, length: 255, options: [
        'default' => '',
    ])]
    #[Assert\NotNull]
    private string $slug = '';

    #[ORM\Column(name: 'description', type: Types::TEXT, options: [
        'default' => '',
    ])]
    #[Assert\NotNull]
    private string $description = '';

    #[ORM\Column(
        name: 'photo',
        type: Types::STRING,
        length: 255,
        options: [
            'comment' => 'Application photo URL',
        ],
    )]
    private string $photo = '';

    #[ORM\Column(name: 'status', type: Types::STRING, length: 25, enumType: PlatformStatus::class, options: [
        'default' => PlatformStatus::ACTIVE->value,
    ])]
    #[Assert\NotNull]
    private PlatformStatus $status = PlatformStatus::ACTIVE;

    #[ORM\Column(name: 'private', type: Types::BOOLEAN, options: [
        'default' => false,
    ])]
    #[Assert\NotNull]
    private bool $private = false;

    /**
     * @var Collection<int, Configuration>|ArrayCollection<int, Configuration>
     */
    #[ORM\OneToMany(targetEntity: Configuration::class, mappedBy: 'application', cascade: ['persist', 'remove'])]
    private Collection | ArrayCollection $configurations;

    /**
     * @var Collection<int, ApplicationPlugin>|ArrayCollection<int, ApplicationPlugin>
     */
    #[ORM\OneToMany(targetEntity: ApplicationPlugin::class, mappedBy: 'application', cascade: ['persist', 'remove'])]
    private Collection | ArrayCollection $applicationPlugins;

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->configurations = new ArrayCollection();
        $this->applicationPlugins = new ArrayCollection();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
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

    public function getPlatform(): ?Platform
    {
        return $this->platform;
    }

    public function setPlatform(?Platform $platform): self
    {
        $this->platform = $platform;

        return $this;
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function getStatus(): PlatformStatus
    {
        return $this->status;
    }

    public function setStatus(PlatformStatus|string $status): self
    {
        $this->status = $status instanceof PlatformStatus ? $status : PlatformStatus::from($status);

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

    public function getPhoto(): string
    {
        return $this->photo;
    }

    public function setPhoto(string $photo): self
    {
        $this->photo = $photo;

        return $this;
    }

    public function ensureGeneratedPhoto(): self
    {
        if ($this->photo === '') {
            $title = rawurlencode($this->title);
            $this->photo = 'https://ui-avatars.com/api/?name=' . str_replace('%20', '+', $title);

            return $this;
        }

        if (!str_starts_with($this->photo, 'http://') && !str_starts_with($this->photo, 'https://')) {
            $this->photo = '/uploads/applications/' . ltrim($this->photo, '/');
        }

        return $this;
    }

    public function ensureGeneratedSlug(): self
    {
        $normalizedTitle = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $this->title);
        $base = is_string($normalizedTitle) ? $normalizedTitle : $this->title;
        $slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($base));
        $this->slug = trim($slug ?? '', '-');

        if ($this->slug === '') {
            $this->slug = 'app-' . substr($this->getId(), 0, 8);
        }

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
            $configuration->setApplication($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, ApplicationPlugin>|ArrayCollection<int, ApplicationPlugin>
     */
    public function getApplicationPlugins(): Collection | ArrayCollection
    {
        return $this->applicationPlugins;
    }

    public function addApplicationPlugin(ApplicationPlugin $applicationPlugin): self
    {
        if ($this->applicationPlugins->contains($applicationPlugin) === false) {
            $this->applicationPlugins->add($applicationPlugin);
            $applicationPlugin->setApplication($this);
        }

        return $this;
    }
}
