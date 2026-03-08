<?php

declare(strict_types=1);

namespace App\Blog\Infrastructure\DataFixtures\ORM;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\BlogComment;
use App\Blog\Domain\Entity\BlogPost;
use App\Blog\Domain\Entity\BlogReaction;
use App\Blog\Domain\Entity\BlogTag;
use App\Blog\Domain\Enum\BlogType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use App\Platform\Domain\Entity\Application;
use App\User\Domain\Entity\User;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

final class LoadBlogData extends Fixture implements OrderedFixtureInterface
{
    #[Override]
    public function load(ObjectManager $manager): void
    {
        $johnRoot = $this->getReference('User-john-root', User::class);
        $application = $this->getReference('Application-shop-ops-center', Application::class);

        $generalBlog = (new Blog())->setTitle('General Blog Root')->setOwner($johnRoot)->setType(BlogType::GENERAL);
        $applicationBlog = (new Blog())->setTitle('Shop Blog')->setOwner($johnRoot)->setType(BlogType::APPLICATION)->setApplication($application);
        $manager->persist($generalBlog); $manager->persist($applicationBlog);

        foreach ([$generalBlog, $applicationBlog] as $i => $blog) {
            for ($p = 1; $p <= 4; ++$p) {
                $post = (new BlogPost())->setBlog($blog)->setAuthor($johnRoot)->setContent(sprintf('Fixture post %d for %s', $p, $blog->getTitle()));
                $manager->persist($post);
                $tag = (new BlogTag())->setBlog($blog)->setLabel(sprintf('tag-%d-%d', $i + 1, $p));
                $manager->persist($tag);

                $parent = (new BlogComment())->setPost($post)->setAuthor($johnRoot)->setContent('Parent comment #' . $p);
                $child = (new BlogComment())->setPost($post)->setAuthor($johnRoot)->setContent('Child comment #' . $p)->setParent($parent);
                $manager->persist($parent); $manager->persist($child);

                $manager->persist((new BlogReaction())->setComment($parent)->setAuthor($johnRoot)->setType('like'));
                $manager->persist((new BlogReaction())->setComment($child)->setAuthor($johnRoot)->setType('heart'));
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
