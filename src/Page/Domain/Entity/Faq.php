<?php

declare(strict_types=1);

namespace App\Page\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity]
#[ORM\Table(name: 'page_faq')]
#[ORM\Index(name: 'idx_page_faq_language_id', columns: ['language_id'])]
#[ORM\UniqueConstraint(name: 'uq_page_faq_language_id', columns: ['language_id'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Faq implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    #[Groups(['Faq', 'Faq.id'])]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: PageLanguage::class)]
    #[ORM\JoinColumn(name: 'language_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['Faq', 'Faq.languageId'])]
    private PageLanguage $language;

    #[ORM\Column(name: 'content', type: Types::JSON)]
    #[Groups(['Faq', 'Faq.content'])]
    private array $content = [];

    public function __construct() { $this->id = $this->createUuid(); }

    #[Override]
    public function getId(): string { return $this->id->toString(); }
    public function getLanguage(): PageLanguage { return $this->language; }
    public function getLanguageId(): string { return $this->language->getId(); }
    public function setLanguage(PageLanguage $language): self { $this->language = $language; return $this; }
    public function getContent(): array { return $this->content; }
    public function setContent(array $content): self { $this->content = $content; return $this; }
}
