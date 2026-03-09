<?php

declare(strict_types=1);

namespace App\Page\Application\DTO\Contact;

use App\General\Application\DTO\RestDto;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Enum\Language;
use App\Page\Domain\Entity\Contact as Entity;
use Override;

class Contact extends RestDto
{
    protected string $language = Language::EN->value;
    protected array $content = [];

    public function getLanguage(): string { return $this->language; }
    public function setLanguage(string $language): self { $this->setVisited('language'); $this->language = $language; return $this; }
    public function getContent(): array { return $this->content; }
    public function setContent(array $content): self { $this->setVisited('content'); $this->content = $content; return $this; }

    #[Override]
    public function load(EntityInterface $entity): self
    {
        if ($entity instanceof Entity) {
            $this->id = $entity->getId();
            $this->language = $entity->getLanguage()->value;
            $this->content = $entity->getContent();
        }

        return $this;
    }
}
