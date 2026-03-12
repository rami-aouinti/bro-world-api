<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\DataFixtures\ORM;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\BlogComment;
use App\Blog\Domain\Entity\BlogPost;
use App\Blog\Domain\Entity\BlogReaction;
use App\Blog\Domain\Entity\BlogTag;
use App\Blog\Domain\Enum\BlogReactionType;
use App\Blog\Domain\Enum\BlogType;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Entity\Plugin;
use App\User\Domain\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

use function sprintf;

final class LoadBlogData extends Fixture implements OrderedFixtureInterface
{
    #[Override]
    public function load(ObjectManager $manager): void
    {
        $johnRoot = $this->getReference('User-john-root', User::class);
        $johnAdmin = $this->getReference('User-john-admin', User::class);
        $johnUser = $this->getReference('User-john-user', User::class);
        $blogPlugin = $this->getReference('Plugin-Knowledge-Base-Connector', Plugin::class);

        $applications = $manager->getRepository(Application::class)
            ->createQueryBuilder('application')
            ->innerJoin('application.applicationPlugins', 'applicationPlugin')
            ->andWhere('applicationPlugin.plugin = :plugin')
            ->setParameter('plugin', $blogPlugin)
            ->orderBy('application.title', 'ASC')
            ->getQuery()
            ->getResult();

        $generalBlog = (new Blog())
            ->setTitle('General Blog Root')
            ->setSlug('general')
            ->setDescription('Blog communautaire global pour toute la plateforme.')
            ->setOwner($johnRoot)
            ->setType(BlogType::GENERAL);
        $manager->persist($generalBlog);

        /** @var list<Blog> $blogs */
        $blogs = [$generalBlog];
        foreach ($applications as $index => $application) {
            if (!$application instanceof Application) {
                continue;
            }

            $blog = (new Blog())
                ->setTitle(sprintf('Application Blog %d', $index + 1))
                ->setSlug(sprintf('application-blog-%d', $index + 1))
                ->setDescription(sprintf('Espace communautaire pour %s', $application->getTitle()))
                ->setOwner($johnRoot)
                ->setType(BlogType::APPLICATION)
                ->setApplication($application);
            $manager->persist($blog);
            $blogs[] = $blog;
        }

        $authors = [$johnRoot, $johnAdmin, $johnUser];
        $reactionTypes = [BlogReactionType::LIKE, BlogReactionType::HEART, BlogReactionType::LAUGH, BlogReactionType::CELEBRATE];

        foreach ($blogs as $blogIndex => $blog) {
            $postCount = $blog->getType() === BlogType::GENERAL ? 30 : 5;

            for ($postIndex = 1; $postIndex <= $postCount; $postIndex++) {
                $author = $authors[($blogIndex + $postIndex) % count($authors)];

                $post = (new BlogPost())
                    ->setBlog($blog)
                    ->setAuthor($author)
                    ->setTitle(sprintf('Post fixture %d', $postIndex))
                    ->setSlug(sprintf('fixture-%d-%d-root', $blogIndex + 1, $postIndex))
                    ->setContent(sprintf('Fixture post %d for %s', $postIndex, $blog->getTitle()))
                    ->setIsPinned($postIndex === 1);

                if ($postIndex % 5 === 0) {
                    $post
                        ->setSharedUrl(sprintf('https://example.com/shared/%d/%d', $blogIndex + 1, $postIndex))
                        ->setContent(null);
                }

                if ($postIndex % 4 === 0) {
                    $post->setMediaUrls([
                        sprintf('https://cdn.example.com/blog/%d/%d-photo.jpg', $blogIndex + 1, $postIndex),
                        sprintf('https://cdn.example.com/blog/%d/%d-video.mp4', $blogIndex + 1, $postIndex),
                    ]);
                }

                $manager->persist($post);

                if ($postIndex <= 2) {
                    $sharedChild = (new BlogPost())
                        ->setBlog($blog)
                        ->setAuthor($authors[($blogIndex + $postIndex + 1) % count($authors)])
                        ->setTitle(sprintf('Shared fixture %d', $postIndex))
                        ->setSlug(sprintf('fixture-%d-%d-child', $blogIndex + 1, $postIndex))
                        ->setParentPost($post)
                        ->setSharedUrl(sprintf('https://example.com/original/%d/%d', $blogIndex + 1, $postIndex))
                        ->setContent(null)
                        ->setMediaUrls([
                            sprintf('https://cdn.example.com/blog/%d/%d-child-image.webp', $blogIndex + 1, $postIndex),
                        ]);
                    $manager->persist($sharedChild);
                }

                for ($tagIndex = 1; $tagIndex <= 2; $tagIndex++) {
                    $manager->persist((new BlogTag())
                        ->setBlog($blog)
                        ->setLabel(sprintf('tag-%d-%d-%d', $blogIndex + 1, $postIndex, $tagIndex)));
                }

                $parent = (new BlogComment())
                    ->setPost($post)
                    ->setAuthor($author)
                    ->setContent('Parent comment #' . $postIndex);
                $child = (new BlogComment())
                    ->setPost($post)
                    ->setAuthor($authors[($blogIndex + $postIndex + 1) % count($authors)])
                    ->setContent('Child comment #' . $postIndex)
                    ->setParent($parent);
                $subChild = (new BlogComment())
                    ->setPost($post)
                    ->setAuthor($authors[($blogIndex + $postIndex + 2) % count($authors)])
                    ->setContent('Sub child comment #' . $postIndex)
                    ->setParent($child);

                $manager->persist($parent);
                $manager->persist($child);
                $manager->persist($subChild);

                $manager->persist((new BlogReaction())
                    ->setComment($parent)
                    ->setAuthor($authors[($blogIndex + 1) % count($authors)])
                    ->setType($reactionTypes[($blogIndex + $postIndex) % count($reactionTypes)]));
            }
        }

        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 41;
    }
}
