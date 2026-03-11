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
#[ORM\Table(name: 'blog_post')]
class BlogPost implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Blog::class)]
    #[ORM\JoinColumn(name: 'blog_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Blog $blog;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(name: 'title', type: 'string', length: 255)]
    private string $title = '';

    #[ORM\Column(name: 'content', type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(name: 'is_pinned', type: 'boolean', options: [
        'default' => false,
    ])]
    private bool $isPinned = false;

    #[ORM\Column(name: 'file_path', type: 'string', length: 255, nullable: true)]
    private ?string $filePath = null;

    /**
     * @var Collection<int, BlogComment>
     */
    #[ORM\OneToMany(targetEntity: BlogComment::class, mappedBy: 'post', cascade: ['remove'])]
    private Collection $comments;

    /**
     * @var Collection<int, BlogReaction>
     */
    #[ORM\OneToMany(targetEntity: BlogReaction::class, mappedBy: 'post', cascade: ['remove'])]
    private Collection $reactions;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->comments = new ArrayCollection();
        $this->reactions = new ArrayCollection();
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
    public function getAuthor(): User
    {
        return $this->author;
    }
    public function setAuthor(User $author): self
    {
        $this->author = $author;

        return $this;
    }
    public function getTitle(): string
    {
        return $this->title;
    }
    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }
    public function getContent(): ?string
    {
        return $this->content;
    }
    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }
    public function isPinned(): bool
    {
        return $this->isPinned;
    }
    public function setIsPinned(bool $isPinned): self
    {
        $this->isPinned = $isPinned;

        return $this;
    }
    public function getFilePath(): ?string
    {
        return $this->filePath;
    }
    public function setFilePath(?string $filePath): self
    {
        $this->filePath = $filePath;

        return $this;
    }
    /**
     * @return Collection<int, BlogComment>
     */ public function getComments(): Collection
    {
        return $this->comments;
    }

    /**
     * @return Collection<int, BlogReaction>
     */
    public function getReactions(): Collection
    {
        return $this->reactions;
    }
}

