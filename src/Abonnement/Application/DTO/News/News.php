<?php

declare(strict_types=1);

namespace App\Abonnement\Application\DTO\News;

use App\Abonnement\Domain\Entity\News as Entity;
use App\General\Application\DTO\Interfaces\RestDtoInterface;
use App\General\Application\DTO\RestDto;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use DateTimeImmutable;
use Override;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @method self|RestDtoInterface get(string $id)
 * @method self|RestDtoInterface patch(RestDtoInterface $dto)
 * @method Entity|EntityInterface update(EntityInterface $entity)
 */
class News extends RestDto
{
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 255)]
    protected string $title = '';

    #[Assert\NotBlank]
    protected string $description = '';

    #[Assert\NotNull]
    protected DateTimeImmutable $executeAt;

    protected bool $executed = false;

    public function __construct()
    {
        $this->executeAt = new DateTimeImmutable();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->setVisited('title');
        $this->title = $title;

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

    public function getExecuteAt(): DateTimeImmutable
    {
        return $this->executeAt;
    }

    public function setExecuteAt(DateTimeImmutable $executeAt): self
    {
        $this->setVisited('executeAt');
        $this->executeAt = $executeAt;

        return $this;
    }

    public function isExecuted(): bool
    {
        return $this->executed;
    }

    public function setExecuted(bool $executed): self
    {
        $this->setVisited('executed');
        $this->executed = $executed;

        return $this;
    }

    /** @param EntityInterface|Entity $entity */
    #[Override]
    public function load(EntityInterface $entity): self
    {
        if ($entity instanceof Entity) {
            $this->id = $entity->getId();
            $this->title = $entity->getTitle();
            $this->description = $entity->getDescription();
            $this->executeAt = $entity->getExecuteAt();
            $this->executed = $entity->isExecuted();
        }

        return $this;
    }
}
