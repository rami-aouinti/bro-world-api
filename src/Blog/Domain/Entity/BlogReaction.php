<?php

declare(strict_types=1);

namespace App\Blog\Domain\Entity;

use App\Blog\Domain\Enum\BlogReactionType;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use DomainException;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(
    name: 'blog_reaction',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_blog_reaction_author_comment', columns: ['author_id', 'comment_id']),
        new ORM\UniqueConstraint(name: 'uniq_blog_reaction_author_post', columns: ['author_id', 'post_id']),
    ],
)]
#[ORM\HasLifecycleCallbacks]
class BlogReaction implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: BlogComment::class)]
    #[ORM\JoinColumn(name: 'comment_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?BlogComment $comment = null;

    #[ORM\ManyToOne(targetEntity: BlogPost::class)]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?BlogPost $post = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'author_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $author;

    #[ORM\Column(name: 'type', type: 'string', length: 40, enumType: BlogReactionType::class)]
    private BlogReactionType $type = BlogReactionType::LIKE;

    public function __construct()
    {
        $this->id = $this->createUuid();
    }
    #[Override] public function getId(): string
    {
        return $this->id->toString();
    }
    public function getComment(): ?BlogComment
    {
        return $this->comment;
    }
    public function setComment(BlogComment $comment): self
    {
        $this->comment = $comment;
        $this->post = null;
        $this->assertSingleTarget();

        return $this;
    }
    public function getPost(): ?BlogPost
    {
        if ($this->post instanceof BlogPost) {
            return $this->post;
        }

        return $this->comment?->getPost();
    }
    public function setPost(BlogPost $post): self
    {
        $this->post = $post;
        $this->comment = null;
        $this->assertSingleTarget();

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
    public function getType(): BlogReactionType
    {
        return $this->type;
    }
    public function setType(BlogReactionType $type): self
    {
        $this->type = $type;

        return $this;
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function validateTargetIntegrity(): void
    {
        $this->assertSingleTarget();
    }

    private function assertSingleTarget(): void
    {
        if (($this->comment === null) === ($this->post === null)) {
            throw new DomainException('A blog reaction must target either a comment or a post.');
        }
    }
}
