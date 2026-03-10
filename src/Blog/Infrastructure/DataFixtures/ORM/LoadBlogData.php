<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\DataFixtures\ORM;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\BlogComment;
use App\Blog\Domain\Entity\BlogPost;
use App\Blog\Domain\Entity\BlogReaction;
use App\Blog\Domain\Entity\BlogTag;
use App\Blog\Domain\Enum\BlogType;
use App\Platform\Domain\Entity\Application;
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

        $applications = [
            $this->getReference('Application-shop-ops-center', Application::class),
            $this->getReference('Application-crm-sales-hub', Application::class),
            $this->getReference('Application-school-campus-core', Application::class),
        ];

        $generalBlog = (new Blog())
            ->setTitle('General Blog Root')
            ->setOwner($johnRoot)
            ->setType(BlogType::GENERAL);
        $manager->persist($generalBlog);

        /** @var list<Blog> $blogs */
        $blogs = [$generalBlog];
        foreach ($applications as $index => $application) {
            $blog = (new Blog())
                ->setTitle(sprintf('Application Blog %d', $index + 1))
                ->setOwner($johnRoot)
                ->setType(BlogType::APPLICATION)
                ->setApplication($application);
            $manager->persist($blog);
            $blogs[] = $blog;
        }

        $authors = [$johnRoot, $johnAdmin, $johnUser];
        $reactionTypes = ['like', 'heart', 'laugh'];

        foreach ($blogs as $blogIndex => $blog) {
            $postCount = $blog->getType() === BlogType::GENERAL ? 40 : 6;

            for ($postIndex = 1; $postIndex <= $postCount; $postIndex++) {
                $author = $authors[($blogIndex + $postIndex) % count($authors)];

                $post = (new BlogPost())
                    ->setBlog($blog)
                    ->setAuthor($author)
                    ->setContent(sprintf('Fixture post %d for %s', $postIndex, $blog->getTitle()));
                $manager->persist($post);

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
                $manager->persist((new BlogReaction())
                    ->setComment($child)
                    ->setAuthor($authors[($blogIndex + 2) % count($authors)])
                    ->setType($reactionTypes[($blogIndex + $postIndex + 1) % count($reactionTypes)]));
                $manager->persist((new BlogReaction())
                    ->setComment($subChild)
                    ->setAuthor($authors[($blogIndex + $postIndex + 2) % count($authors)])
                    ->setType($reactionTypes[($blogIndex + $postIndex + 2) % count($reactionTypes)]));
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
