<?php

declare(strict_types=1);

namespace App\Page\Application\DTO\About;

use App\General\Application\DTO\RestDto;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\Page\Domain\Entity\About as Entity;
use Override;

class About extends RestDto
{
    protected static array $mappings = [
        'languageId' => 'mapLanguageId',
    ];

    protected string $languageId = '';
    protected array $content = [];

    public function getLanguageId(): string { return $this->languageId; }
    public function setLanguageId(string $languageId): self { $this->setVisited('languageId'); $this->languageId = $languageId; return $this; }
    public function getContent(): array { return $this->content; }
    public function setContent(array $content): self { $this->setVisited('content'); $this->content = $content; return $this; }

    #[Override]
    public function load(EntityInterface $entity): self
    {
        if ($entity instanceof Entity) {
            $this->id = $entity->getId();
            $this->languageId = $entity->getLanguageId();
            $this->content = $entity->getContent();
        }

        return $this;
    }

    protected function mapLanguageId(EntityInterface $entity, string $languageId): void {}
}
