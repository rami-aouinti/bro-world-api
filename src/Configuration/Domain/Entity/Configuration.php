<?php

declare(strict_types=1);

namespace App\Configuration\Domain\Entity;

use App\Configuration\Domain\Enum\ConfigurationScope;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Entity\ApplicationPlugin;
use App\Platform\Domain\Entity\Platform;
use App\Platform\Domain\Entity\Plugin;
use App\User\Domain\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\Validator\Constraints as AssertCollection;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Throwable;

/**
 * @package App\Configuration
 */
#[ORM\Entity]
#[ORM\Table(name: 'configuration')]
#[ORM\UniqueConstraint(name: 'uq_configuration_user_key', columns: ['user_id', 'configuration_key'])]
#[ORM\UniqueConstraint(name: 'uq_configuration_platform_key', columns: ['platform_id', 'configuration_key'])]
#[ORM\UniqueConstraint(name: 'uq_configuration_plugin_key', columns: ['plugin_id', 'configuration_key'])]
#[ORM\UniqueConstraint(name: 'uq_configuration_application_key', columns: ['application_id', 'configuration_key'])]
#[ORM\UniqueConstraint(name: 'uq_configuration_application_plugin_key', columns: ['application_plugin_id', 'configuration_key'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
#[AssertCollection\UniqueEntity(fields: ['user', 'configurationKey'])]
#[AssertCollection\UniqueEntity(fields: ['platform', 'configurationKey'])]
#[AssertCollection\UniqueEntity(fields: ['plugin', 'configurationKey'])]
#[AssertCollection\UniqueEntity(fields: ['application', 'configurationKey'])]
#[AssertCollection\UniqueEntity(fields: ['applicationPlugin', 'configurationKey'])]
class Configuration implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    #[Groups(['Configuration', 'Configuration.id'])]
    private UuidInterface $id;


    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'configurations')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Platform::class, inversedBy: 'configurations')]
    #[ORM\JoinColumn(name: 'platform_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Platform $platform = null;

    #[ORM\ManyToOne(targetEntity: Plugin::class, inversedBy: 'configurations')]
    #[ORM\JoinColumn(name: 'plugin_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Plugin $plugin = null;

    #[ORM\ManyToOne(targetEntity: Application::class, inversedBy: 'configurations')]
    #[ORM\JoinColumn(name: 'application_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Application $application = null;

    #[ORM\ManyToOne(targetEntity: ApplicationPlugin::class, inversedBy: 'configurations')]
    #[ORM\JoinColumn(name: 'application_plugin_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?ApplicationPlugin $applicationPlugin = null;

    #[ORM\Column(name: 'configuration_key', type: Types::STRING, length: 255)]
    #[Groups(['Configuration', 'Configuration.configurationKey'])]
    #[Assert\NotBlank]
    #[Assert\NotNull]
    #[Assert\Length(min: 2, max: 255)]
    private string $configurationKey = '';

    /**
     * @var array<string, mixed>
     */
    #[ORM\Column(name: 'configuration_value', type: Types::JSON)]
    #[Groups(['Configuration', 'Configuration.configurationValue'])]
    #[Assert\NotNull]
    private array $configurationValue = [];

    #[ORM\Column(name: 'scope', type: Types::STRING, length: 50, enumType: ConfigurationScope::class)]
    #[Groups(['Configuration', 'Configuration.scope'])]
    #[Assert\NotNull]
    private ConfigurationScope $scope = ConfigurationScope::SYSTEM;

    #[ORM\Column(name: 'private', type: Types::BOOLEAN, options: ['default' => false])]
    #[Groups(['Configuration', 'Configuration.private'])]
    #[Assert\NotNull]
    private bool $private = false;

    /**
     * @var array<string, string>|null
     */
    #[ORM\Column(name: 'configuration_value_parameters', type: Types::JSON, nullable: true, options: ['comment' => 'Configuration value decrypt parameters when encrypted'])]
    #[Groups(['Configuration.configurationValueParameters'])]
    private ?array $configurationValueParameters = null;

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

    public function getPlugin(): ?Plugin
    {
        return $this->plugin;
    }

    public function setPlugin(?Plugin $plugin): self
    {
        $this->plugin = $plugin;

        return $this;
    }

    public function getApplication(): ?Application
    {
        return $this->application;
    }

    public function setApplication(?Application $application): self
    {
        $this->application = $application;

        return $this;
    }

    public function getApplicationPlugin(): ?ApplicationPlugin
    {
        return $this->applicationPlugin;
    }

    public function setApplicationPlugin(?ApplicationPlugin $applicationPlugin): self
    {
        $this->applicationPlugin = $applicationPlugin;

        return $this;
    }

    public function getConfigurationKey(): string
    {
        return $this->configurationKey;
    }

    public function setConfigurationKey(string $configurationKey): self
    {
        $this->configurationKey = $configurationKey;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function getConfigurationValue(): array
    {
        return $this->configurationValue;
    }

    /**
     * @param array<string, mixed> $configurationValue
     */
    public function setConfigurationValue(array $configurationValue): self
    {
        $this->configurationValue = $configurationValue;

        return $this;
    }

    public function getScope(): ConfigurationScope
    {
        return $this->scope;
    }

    public function getScopeValue(): string
    {
        return $this->scope->value;
    }

    public function setScope(ConfigurationScope|string $scope): self
    {
        $this->scope = $scope instanceof ConfigurationScope ? $scope : ConfigurationScope::from($scope);

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
     * @return array<string, string>|null
     */
    public function getConfigurationValueParameters(): ?array
    {
        return $this->configurationValueParameters;
    }

    /**
     * @param array<string, string>|null $configurationValueParameters
     */
    public function setConfigurationValueParameters(?array $configurationValueParameters): self
    {
        $this->configurationValueParameters = $configurationValueParameters;

        return $this;
    }
}
