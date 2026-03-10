<?php

declare(strict_types=1);

namespace App\Platform\Domain\Entity;

use App\Configuration\Domain\Entity\Configuration;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Throwable;

#[ORM\Entity]
#[ORM\Table(name: 'platform_application_plugin')]
#[ORM\UniqueConstraint(name: 'uq_application_plugin', columns: ['application_id', 'plugin_id'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class ApplicationPlugin implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Application::class, inversedBy: 'applicationPlugins')]
    #[ORM\JoinColumn(name: 'application_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?Application $application = null;

    #[ORM\ManyToOne(targetEntity: Plugin::class)]
    #[ORM\JoinColumn(name: 'plugin_id', referencedColumnName: 'id', nullable: false, onDelete: 'RESTRICT')]
    #[Assert\NotNull]
    private ?Plugin $plugin = null;

    /**
     * @var Collection<int, Configuration>|ArrayCollection<int, Configuration>
     */
    #[ORM\OneToMany(targetEntity: Configuration::class, mappedBy: 'applicationPlugin', cascade: ['persist', 'remove'])]
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

    public function getApplication(): ?Application
    {
        return $this->application;
    }

    public function setApplication(?Application $application): self
    {
        $this->application = $application;

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
            $configuration->setApplicationPlugin($this);
        }

        return $this;
    }
}
