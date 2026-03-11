<?php

declare(strict_types=1);

namespace App\Platform\Application\Service\PluginProvisioning;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\BlogPost;
use App\Blog\Domain\Entity\BlogTag;
use App\Blog\Domain\Enum\BlogType;
use App\Blog\Infrastructure\Repository\BlogPostRepository;
use App\Blog\Infrastructure\Repository\BlogRepository;
use App\Blog\Infrastructure\Repository\BlogTagRepository;
use App\Platform\Domain\Entity\Application;
use Doctrine\ORM\EntityManagerInterface;

final readonly class BlogPluginProvisioner
{
    public function __construct(
        private BlogRepository $blogRepository,
        private BlogPostRepository $blogPostRepository,
        private BlogTagRepository $blogTagRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function provision(Application $application): void
    {
        $blog = $this->blogRepository->findOneByApplication($application);
        if (!$blog instanceof Blog) {
            $blog = (new Blog())
                ->setTitle($application->getTitle() . ' Blog')
                ->setOwner($application->getUser())
                ->setType(BlogType::APPLICATION)
                ->setApplication($application);

            $this->entityManager->persist($blog);
        }

        $tag = $this->blogTagRepository->findOneBy([
            'blog' => $blog,
            'label' => 'Getting Started',
        ]);

        if (!$tag instanceof BlogTag) {
            $tag = (new BlogTag())
                ->setBlog($blog)
                ->setLabel('Getting Started');

            $this->entityManager->persist($tag);
        }

        $post = $this->blogPostRepository->findOneBy([
            'blog' => $blog,
            'content' => 'Welcome to your application blog.',
        ]);

        if ($post instanceof BlogPost) {
            return;
        }

        $post = (new BlogPost())
            ->setBlog($blog)
            ->setAuthor($application->getUser())
            ->setContent('Welcome to your application blog.');

        $this->entityManager->persist($post);
    }
}
