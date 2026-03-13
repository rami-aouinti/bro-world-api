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
use Throwable;

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

    #[ORM\Column(name: 'slug', type: 'string', length: 255, unique: true)]
    private string $slug = '';

    #[ORM\Column(name: 'content', type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(name: 'is_pinned', type: 'boolean', options: [
        'default' => false,
    ])]
    private bool $isPinned = false;

    #[ORM\Column(name: 'file_path', type: 'string', length: 255, nullable: true)]
    private ?string $filePath = null;

    /**
     * @var list<string>
     */
    #[ORM\Column(name: 'media_urls', type: 'json', nullable: true)]
    private array $mediaUrls = [];

    #[ORM\Column(name: 'shared_url', type: 'string', length: 1024, nullable: true)]
    private ?string $sharedUrl = null;

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'childrenPosts')]
    #[ORM\JoinColumn(name: 'parent_post_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?self $parentPost = null;

    /**
     * @var Collection<int, self>
     */
    #[ORM\OneToMany(targetEntity: self::class, mappedBy: 'parentPost')]
    private Collection $childrenPosts;

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

    /**
     * @throws Throwable
     */
    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->comments = new ArrayCollection();
        $this->reactions = new ArrayCollection();
        $this->childrenPosts = new ArrayCollection();
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
    public function getSlug(): string
    {
        return $this->slug;
    }
    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

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
     * @return list<string>
     */
    public function getMediaUrls(): array
    {
        return $this->mediaUrls;
    }
    /**
     * @param list<string> $mediaUrls
     */
    public function setMediaUrls(array $mediaUrls): self
    {
        $this->mediaUrls = array_values($mediaUrls);

        return $this;
    }
    public function getSharedUrl(): ?string
    {
        return $this->sharedUrl;
    }
    public function setSharedUrl(?string $sharedUrl): self
    {
        $this->sharedUrl = $sharedUrl;

        return $this;
    }
    public function getParentPost(): ?self
    {
        return $this->parentPost;
    }
    public function setParentPost(?self $parentPost): self
    {
        $this->parentPost = $parentPost;

        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildrenPosts(): Collection
    {
        return $this->childrenPosts;
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
