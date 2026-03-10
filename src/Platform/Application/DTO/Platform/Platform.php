<?php

declare(strict_types=1);

namespace App\Platform\Application\DTO\Platform;

use App\General\Application\DTO\Interfaces\RestDtoInterface;
use App\General\Application\DTO\RestDto;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\Platform\Domain\Entity\Platform as Entity;
use App\Platform\Domain\Enum\PlatformKey;
use App\Platform\Domain\Enum\PlatformStatus;
use Override;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @package App\Platform
 *
 * @method self|RestDtoInterface get(string $id)
 * @method self|RestDtoInterface patch(RestDtoInterface $dto)
 * @method Entity|EntityInterface update(EntityInterface $entity)
 */
class Platform extends RestDto
{
    #[Assert\NotBlank]
    #[Assert\NotNull]
    #[Assert\Length(min: 2, max: 255)]
    protected string $name = '';

    #[Assert\NotNull]
    protected string $description = '';

    #[Assert\NotNull]
    #[Assert\Choice(choices: [
        PlatformKey::CRM->value,
        PlatformKey::SCHOOL->value,
        PlatformKey::SHOP->value,
        PlatformKey::RECRUIT->value,
    ])]
    protected string $platformKey = PlatformKey::CRM->value;

    #[Assert\NotNull]
    protected bool $private = false;

    protected string $photo = '';

    #[Assert\NotNull]
    protected bool $enabled = true;

    #[Assert\NotNull]
    #[Assert\Choice(choices: [
        PlatformStatus::ACTIVE->value,
        PlatformStatus::MAINTENANCE->value,
        PlatformStatus::DISABLED->value,
    ])]
    protected string $status = PlatformStatus::ACTIVE->value;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->setVisited('name');
        $this->name = $name;

        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->setVisited('description');
        $this->description = $description;

        return $this;
    }

    public function getPlatformKey(): string
    {
        return $this->platformKey;
    }

    public function setPlatformKey(string $platformKey): self
    {
        $this->setVisited('platformKey');
        $this->platformKey = $platformKey;

        return $this;
    }

    public function isPrivate(): bool
    {
        return $this->private;
    }

    public function setPrivate(bool $private): self
    {
        $this->setVisited('private');
        $this->private = $private;

        return $this;
    }

    public function getPhoto(): string
    {
        return $this->photo;
    }

    public function setPhoto(string $photo): self
    {
        $this->setVisited('photo');
        $this->photo = $photo;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->setVisited('enabled');
        $this->enabled = $enabled;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->setVisited('status');
        $this->status = $status;

        return $this;
    }

    /**
     * {@inheritdoc}
     *
     * @param EntityInterface|Entity $entity
     */
    #[Override]
    public function load(EntityInterface $entity): self
    {
        if ($entity instanceof Entity) {
            $this->id = $entity->getId();
            $this->name = $entity->getName();
            $this->description = $entity->getDescription();
            $this->platformKey = $entity->getPlatformKeyValue();
            $this->private = $entity->isPrivate();
            $this->photo = $entity->getPhoto();
            $this->enabled = $entity->isEnabled();
            $this->status = $entity->getStatusValue();
        }

        return $this;
    }
}
