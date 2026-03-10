<?php

declare(strict_types=1);

namespace App\Blog\Application\Service;

use App\Blog\Domain\Entity\BlogComment;
use App\Blog\Domain\Entity\BlogPost;
use App\Notification\Application\Service\NotificationPublisher;
use App\User\Domain\Entity\User;

use function mb_strimwidth;
use function trim;

final readonly class BlogNotificationService
{
    public const string BLOG_NOTIFICATION_TYPE = 'blog_notification';

    public function __construct(private NotificationPublisher $notificationPublisher) {}

    public function notifyCommentCreated(BlogComment $comment): void
    {
        $actor = $comment->getAuthor();
        $post = $comment->getPost();
        $targetLabel = $this->formatPostTitle($post);

        if ($comment->getParent() instanceof BlogComment) {
            $this->notificationPublisher->publish(
                from: $actor,
                recipient: $comment->getParent()->getAuthor(),
                title: $this->buildTitle($actor, 'commented your comment', $targetLabel),
                type: self::BLOG_NOTIFICATION_TYPE,
                description: $this->buildPostLinkDescription($post),
            );

            return;
        }

        $this->notificationPublisher->publish(
            from: $actor,
            recipient: $post->getAuthor(),
            title: $this->buildTitle($actor, 'commented your post', $targetLabel),
            type: self::BLOG_NOTIFICATION_TYPE,
            description: $this->buildPostLinkDescription($post),
        );
    }

    public function notifyReactionCreated(BlogComment $comment, User $actor, string $reactionType): void
    {
        $actionLabel = $reactionType === 'like' ? 'liked' : ('reacted (' . $reactionType . ') to');

        if ($comment->getParent() instanceof BlogComment) {
            $this->notificationPublisher->publish(
                from: $actor,
                recipient: $comment->getAuthor(),
                title: $this->buildTitle($actor, $actionLabel . ' your comment', $this->formatCommentPreview($comment)),
                type: self::BLOG_NOTIFICATION_TYPE,
                description: $this->buildPostLinkDescription($comment->getPost()),
            );

            return;
        }

        $this->notificationPublisher->publish(
            from: $actor,
            recipient: $comment->getPost()->getAuthor(),
            title: $this->buildTitle($actor, $actionLabel . ' your post', $this->formatPostTitle($comment->getPost())),
            type: self::BLOG_NOTIFICATION_TYPE,
            description: $this->buildPostLinkDescription($comment->getPost()),
        );
    }

    private function buildPostLinkDescription(BlogPost $post): string
    {
        return '/blog/post/' . $post->getId();
    }

    private function buildTitle(User $actor, string $action, string $target): string
    {
        return trim($actor->getFirstName() . ' ' . $actor->getLastName()) . ' ' . $action . ' ' . $target;
    }

    private function formatPostTitle(BlogPost $post): string
    {
        $content = trim((string) $post->getContent());

        if ($content === '') {
            return '"(no title)"';
        }

        return '"' . mb_strimwidth($content, 0, 60, '...') . '"';
    }

    private function formatCommentPreview(BlogComment $comment): string
    {
        $content = trim((string) $comment->getContent());

        if ($content === '') {
            return '"(comment)"';
        }

        return '"' . mb_strimwidth($content, 0, 60, '...') . '"';
    }
}
