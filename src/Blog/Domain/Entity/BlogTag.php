<?php

declare(strict_types=1);

namespace App\Blog\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'blog_tag')]
class BlogTag implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Blog::class)]
    #[ORM\JoinColumn(name: 'blog_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Blog $blog;

    #[ORM\Column(name: 'label', type: 'string', length: 100)]
    private string $label = '';

    public function __construct()
    {
        $this->id = $this->createUuid();
    }
    #[Override] public function getId(): string
    {
        return $this->id->toString();
    }
    public function getBlog(): Blog
    {
        return $this->blog;
    }
    public function setBlog(Blog $blog): self
    {
        $this->blog = $blog;

        return $this;
    }
    public function getLabel(): string
    {
        return $this->label;
    }
    public function setLabel(string $label): self
    {
        $this->label = $label;

        return $this;
    }
}
