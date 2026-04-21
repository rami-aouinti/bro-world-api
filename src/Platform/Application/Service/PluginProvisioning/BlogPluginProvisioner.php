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
use App\Platform\Application\Service\PlatformBusinessKeyResolver;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Enum\PlatformKey;
use Doctrine\ORM\EntityManagerInterface;

use function iconv;
use function in_array;
use function is_string;
use function preg_replace;
use function strlen;
use function strtolower;
use function substr;
use function trim;

final readonly class BlogPluginProvisioner
{
    public function __construct(
        private BlogRepository $blogRepository,
        private BlogPostRepository $blogPostRepository,
        private BlogTagRepository $blogTagRepository,
        private EntityManagerInterface $entityManager,
        private PlatformBusinessKeyResolver $platformBusinessKeyResolver,
    ) {
    }

    public function provision(Application $application): void
    {
        $platformKey = $this->platformBusinessKeyResolver->resolve($application);
        if (in_array($platformKey, [PlatformKey::SCHOOL, PlatformKey::RECRUIT], true)) {
            return;
        }

        $blog = $this->blogRepository->findOneByApplication($application);
        if (!$blog instanceof Blog) {
            $blogSlug = $this->generateUniqueBlogSlug($application);

            $blog = (new Blog())
                ->setTitle($application->getTitle() . ' Blog')
                ->setSlug($blogSlug)
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
            ->setTitle('Welcome to ' . $application->getTitle() . ' Blog')
            ->setContent('Welcome to your application blog.');

        $this->entityManager->persist($post);
    }

    private function generateUniqueBlogSlug(Application $application): string
    {
        $baseSlug = $this->buildBaseSlug($application);
        $slug = $baseSlug;
        $suffix = 2;

        while (
            $this->blogRepository->findOneBy([
                'slug' => $slug,
            ]) instanceof Blog
        ) {
            $suffixToken = '-' . $suffix;
            $slug = substr($baseSlug, 0, 150 - strlen($suffixToken)) . $suffixToken;
            $suffix++;
        }

        return $slug;
    }

    private function buildBaseSlug(Application $application): string
    {
        $application->ensureGeneratedSlug();

        $candidate = trim($application->getSlug(), '-');
        if ($candidate === '') {
            $candidate = 'app-' . substr($application->getId(), 0, 8);
        }

        $normalizedValue = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $candidate . '-blog');
        $base = is_string($normalizedValue) ? $normalizedValue : $candidate . '-blog';
        $slug = trim((string)preg_replace('/[^a-z0-9]+/i', '-', strtolower($base)), '-');

        if ($slug === '') {
            $slug = 'blog-' . substr($application->getId(), 0, 8);
        }

        return substr($slug, 0, 150);
    }
}
