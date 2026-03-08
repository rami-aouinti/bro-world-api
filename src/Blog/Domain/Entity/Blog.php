<?php

declare(strict_types=1);

namespace App\Blog\Domain\Entity;

use App\Blog\Domain\Enum\BlogStatus;
use App\Blog\Domain\Enum\BlogType;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\Platform\Domain\Entity\Application;
use App\User\Domain\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'blog')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Blog implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\Column(name: 'title', type: 'string', length: 255)]
    private string $title = '';

    #[ORM\Column(name: 'type', type: 'string', length: 20, enumType: BlogType::class)]
    private BlogType $type = BlogType::APPLICATION;

    #[ORM\Column(name: 'post_status', type: 'string', length: 20, enumType: BlogStatus::class)]
    private BlogStatus $postStatus = BlogStatus::OPEN;

    #[ORM\Column(name: 'comment_status', type: 'string', length: 20, enumType: BlogStatus::class)]
    private BlogStatus $commentStatus = BlogStatus::OPEN;

    #[ORM\ManyToOne(targetEntity: Application::class)]
    #[ORM\JoinColumn(name: 'application_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Application $application = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    /** @var Collection<int, BlogPost> */
    #[ORM\OneToMany(targetEntity: BlogPost::class, mappedBy: 'blog', cascade: ['remove'])]
    private Collection $posts;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->posts = new ArrayCollection();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getTitle(): string { return $this->title; }
    public function setTitle(string $title): self { $this->title = $title; return $this; }
    public function getType(): BlogType { return $this->type; }
    public function setType(BlogType $type): self { $this->type = $type; return $this; }
    public function getPostStatus(): BlogStatus { return $this->postStatus; }
    public function setPostStatus(BlogStatus $postStatus): self { $this->postStatus = $postStatus; return $this; }
    public function getCommentStatus(): BlogStatus { return $this->commentStatus; }
    public function setCommentStatus(BlogStatus $commentStatus): self { $this->commentStatus = $commentStatus; return $this; }
    public function getApplication(): ?Application { return $this->application; }
    public function setApplication(?Application $application): self { $this->application = $application; return $this; }
    public function getOwner(): User { return $this->owner; }
    public function setOwner(User $owner): self { $this->owner = $owner; return $this; }
    /** @return Collection<int, BlogPost> */
    public function getPosts(): Collection { return $this->posts; }
}
