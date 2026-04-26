<?php

declare(strict_types=1);

namespace App\Blog\Application\Service;

use App\Blog\Domain\Entity\BlogComment;
use App\Blog\Domain\Entity\BlogPost;
use App\General\Application\Service\MercurePublisher;
use App\Notification\Application\Service\NotificationPublisher;
use App\User\Domain\Entity\User;

use DateTimeImmutable;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\OptimisticLockException;
use JsonException;
use function mb_strimwidth;
use function trim;

final readonly class BlogNotificationService
{
    public const string BLOG_NOTIFICATION_TYPE = 'blog_notification';
    public const string BLOG_EVENT_TYPE = 'blog_event';

    public function __construct(
        private NotificationPublisher $notificationPublisher,
        private MercurePublisher $mercurePublisher,
    ) {
    }

    /**
     * @param array<string, mixed> $extra
     * @throws JsonException
     */
    public function publishBlogEvent(BlogPost $post, string $event, array $extra = []): void
    {
        $blog = $post->getBlog();
        $scope = $blog->getApplication()?->getSlug() ?? 'general';
        $payload = array_merge([
            'type' => self::BLOG_EVENT_TYPE,
            'event' => $event,
            'blogId' => $blog->getId(),
            'blogSlug' => $blog->getSlug(),
            'scope' => $scope,
            'postId' => $post->getId(),
            'occurredAt' => (new DateTimeImmutable())->format(DATE_ATOM),
        ], $extra);

        $this->mercurePublisher->publish('/blogs/' . $blog->getId() . '/events', $payload);
        $this->mercurePublisher->publish('/blogs/scopes/' . $scope . '/events', $payload);
        $this->mercurePublisher->publish('/blogs/' . $blog->getId() . '/posts/' . $post->getId() . '/events', $payload);
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws JsonException
     */
    public function notifyCommentCreated(BlogComment $comment): void
    {
        $actor = $comment->getAuthor();
        $post = $comment->getPost();
        $targetLabel = $this->formatPostTitle($post);

        if ($comment->getParent() instanceof BlogComment) {
            $recipient = $comment->getParent()->getAuthor();

            if ($this->shouldNotify($actor, $recipient)) {
                $this->notificationPublisher->publish(
                    from: $actor,
                    recipient: $recipient,
                    title: $this->buildTitle($actor, 'commented your comment', $targetLabel),
                    type: self::BLOG_NOTIFICATION_TYPE,
                    description: $this->buildPostLinkDescription($post),
                );
            }

            return;
        }

        $recipient = $post->getAuthor();

        if ($this->shouldNotify($actor, $recipient)) {
            $this->notificationPublisher->publish(
                from: $actor,
                recipient: $recipient,
                title: $this->buildTitle($actor, 'commented your post', $targetLabel),
                type: self::BLOG_NOTIFICATION_TYPE,
                description: $this->buildPostLinkDescription($post),
            );
        }
    }

    public function notifyReactionCreated(BlogComment $comment, User $actor, string $reactionType): void
    {
        $actionLabel = $reactionType === 'like' ? 'liked' : ('reacted (' . $reactionType . ') to');

        if ($comment->getParent() instanceof BlogComment) {
            $recipient = $comment->getAuthor();

            if ($this->shouldNotify($actor, $recipient)) {
                $this->notificationPublisher->publish(
                    from: $actor,
                    recipient: $recipient,
                    title: $this->buildTitle($actor, $actionLabel . ' your comment', $this->formatCommentPreview($comment)),
                    type: self::BLOG_NOTIFICATION_TYPE,
                    description: $this->buildPostLinkDescription($comment->getPost()),
                );
            }

            return;
        }

        $recipient = $comment->getPost()->getAuthor();

        if ($this->shouldNotify($actor, $recipient)) {
            $this->notificationPublisher->publish(
                from: $actor,
                recipient: $recipient,
                title: $this->buildTitle($actor, $actionLabel . ' your post', $this->formatPostTitle($comment->getPost())),
                type: self::BLOG_NOTIFICATION_TYPE,
                description: $this->buildPostLinkDescription($comment->getPost()),
            );
        }
    }

    /**
     * @throws OptimisticLockException
     * @throws JsonException
     * @throws ORMException
     */
    public function notifyPostReactionCreated(BlogPost $post, User $actor, string $reactionType): void
    {
        $actionLabel = $reactionType === 'like' ? 'liked' : ('reacted (' . $reactionType . ') to');

        if (!$this->shouldNotify($actor, $post->getAuthor())) {
            return;
        }

        $this->notificationPublisher->publish(
            from: $actor,
            recipient: $post->getAuthor(),
            title: $this->buildTitle($actor, $actionLabel . ' your post', $this->formatPostTitle($post)),
            type: self::BLOG_NOTIFICATION_TYPE,
            description: $this->buildPostLinkDescription($post),
        );
    }

    private function buildPostLinkDescription(BlogPost $post): string
    {
        return '/blog/post/' . $post->getSlug();
    }

    private function buildTitle(User $actor, string $action, string $target): string
    {
        return trim($actor->getFirstName() . ' ' . $actor->getLastName()) . ' ' . $action . ' ' . $target;
    }

    private function formatPostTitle(BlogPost $post): string
    {
        $content = trim((string)$post->getContent());

        if ($content === '') {
            return '"(no title)"';
        }

        return '"' . mb_strimwidth($content, 0, 60, '...') . '"';
    }

    private function formatCommentPreview(BlogComment $comment): string
    {
        $content = trim((string)$comment->getContent());

        if ($content === '') {
            return '"(comment)"';
        }

        return '"' . mb_strimwidth($content, 0, 60, '...') . '"';
    }

    private function shouldNotify(User $actor, User $recipient): bool
    {
        return $actor->getId() !== $recipient->getId();
    }
}
