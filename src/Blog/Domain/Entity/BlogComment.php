<?php

declare(strict_types=1);

namespace App\Blog\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\User\Domain\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'blog_comment')]
class BlogComment implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: BlogPost::class)]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private BlogPost $post;

    #[ORM\ManyToOne(targetEntity: BlogComment::class)]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?BlogComment $parent = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(name: 'content', type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(name: 'file_path', type: 'string', length: 255, nullable: true)]
    private ?string $filePath = null;

    /** @var Collection<int, BlogReaction> */
    #[ORM\OneToMany(targetEntity: BlogReaction::class, mappedBy: 'comment', cascade: ['remove'])]
    private Collection $reactions;

    /** @var Collection<int, BlogComment> */
    #[ORM\OneToMany(targetEntity: BlogComment::class, mappedBy: 'parent', cascade: ['remove'])]
    private Collection $children;

    public function __construct() { $this->id = $this->createUuid(); $this->reactions = new ArrayCollection(); $this->children = new ArrayCollection(); }
    #[Override] public function getId(): string { return $this->id->toString(); }
    public function getPost(): BlogPost { return $this->post; }
    public function setPost(BlogPost $post): self { $this->post = $post; return $this; }
    public function getParent(): ?BlogComment { return $this->parent; }
    public function setParent(?BlogComment $parent): self { $this->parent = $parent; return $this; }
    public function getAuthor(): User { return $this->author; }
    public function setAuthor(User $author): self { $this->author = $author; return $this; }
    public function getContent(): ?string { return $this->content; }
    public function setContent(?string $content): self { $this->content = $content; return $this; }
    public function getFilePath(): ?string { return $this->filePath; }
    public function setFilePath(?string $filePath): self { $this->filePath = $filePath; return $this; }
    /** @return Collection<int, BlogReaction> */ public function getReactions(): Collection { return $this->reactions; }
    /** @return Collection<int, BlogComment> */ public function getChildren(): Collection { return $this->children; }
}
