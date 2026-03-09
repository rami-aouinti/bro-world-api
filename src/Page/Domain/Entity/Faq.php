<?php

declare(strict_types=1);

namespace App\Page\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\General\Domain\Enum\Language;
use App\General\Domain\Doctrine\DBAL\Types\Types as AppTypes;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'page_faq')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Faq implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    #[Groups(['Faq', 'Faq.id'])]
    private UuidInterface $id;

    #[ORM\Column(name: 'language', type: AppTypes::ENUM_LANGUAGE, nullable: false)]
    #[Groups(['Faq', 'Faq.language'])]
    private Language $language = Language::EN;

    #[ORM\Column(name: 'content', type: Types::JSON)]
    #[Groups(['Faq', 'Faq.content'])]
    private array $content = [];

    public function __construct() { $this->id = $this->createUuid(); }

    #[Override]
    public function getId(): string { return $this->id->toString(); }
    public function getLanguage(): Language { return $this->language; }
    public function setLanguage(Language|string $language): self { $this->language = $language instanceof Language ? $language : Language::from($language); return $this; }
    public function getContent(): array { return $this->content; }
    public function setContent(array $content): self { $this->content = $content; return $this; }
}
