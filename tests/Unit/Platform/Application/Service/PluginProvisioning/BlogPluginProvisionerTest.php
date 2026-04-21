<?php

declare(strict_types=1);

namespace App\Tests\Unit\Platform\Application\Service\PluginProvisioning;

use App\Blog\Domain\Entity\Blog;
use App\Blog\Domain\Entity\BlogPost;
use App\Blog\Domain\Entity\BlogTag;
use App\Blog\Infrastructure\Repository\BlogPostRepository;
use App\Blog\Infrastructure\Repository\BlogRepository;
use App\Blog\Infrastructure\Repository\BlogTagRepository;
use App\Platform\Application\Service\PlatformBusinessKeyResolver;
use App\Platform\Application\Service\PluginProvisioning\BlogPluginProvisioner;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Enum\PlatformKey;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

final class BlogPluginProvisionerTest extends TestCase
{
    public function testProvisionCreatesBlogWithUniqueSlugAndBootstrapPostTitle(): void
    {
        $application = (new Application())
            ->setTitle('My App')
            ->setUser(new User());

        $blogRepository = $this->createMock(BlogRepository::class);
        $blogRepository->method('findOneByApplication')->willReturn(null);

        $existingBlog = (new Blog())->setSlug('my-app-blog');
        $blogRepository
            ->method('findOneBy')
            ->willReturnCallback(static function (array $criteria) use ($existingBlog): ?Blog {
                if (($criteria['slug'] ?? null) === 'my-app-blog') {
                    return $existingBlog;
                }

                return null;
            });

        $blogPostRepository = $this->createMock(BlogPostRepository::class);
        $blogPostRepository->method('findOneBy')->willReturn(null);

        $blogTagRepository = $this->createMock(BlogTagRepository::class);
        $blogTagRepository->method('findOneBy')->willReturn(null);

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::exactly(3))
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });

        $platformBusinessKeyResolver = $this->createMock(PlatformBusinessKeyResolver::class);
        $platformBusinessKeyResolver->method('resolve')->willReturn(null);

        $provisioner = new BlogPluginProvisioner(
            blogRepository: $blogRepository,
            blogPostRepository: $blogPostRepository,
            blogTagRepository: $blogTagRepository,
            entityManager: $entityManager,
            platformBusinessKeyResolver: $platformBusinessKeyResolver,
        );

        $provisioner->provision($application);

        $blog = self::findPersistedEntity($persisted, Blog::class);
        self::assertSame('my-app-blog-2', $blog->getSlug());

        $post = self::findPersistedEntity($persisted, BlogPost::class);
        self::assertSame('Welcome to My App Blog', $post->getTitle());

        $tag = self::findPersistedEntity($persisted, BlogTag::class);
        self::assertSame('Getting Started', $tag->getLabel());
    }

    public function testProvisionUsesSafeSlugFallbackWhenApplicationSlugGenerationIsEmpty(): void
    {
        $application = (new Application())
            ->setTitle('***')
            ->setUser(new User());

        $blogRepository = $this->createMock(BlogRepository::class);
        $blogRepository->method('findOneByApplication')->willReturn(null);
        $blogRepository->method('findOneBy')->willReturn(null);

        $blogPostRepository = $this->createMock(BlogPostRepository::class);
        $blogPostRepository->method('findOneBy')->willReturn(null);

        $blogTagRepository = $this->createMock(BlogTagRepository::class);
        $blogTagRepository->method('findOneBy')->willReturn(null);

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use (&$persisted): void {
                $persisted[] = $entity;
            });

        $platformBusinessKeyResolver = $this->createMock(PlatformBusinessKeyResolver::class);
        $platformBusinessKeyResolver->method('resolve')->willReturn(null);

        $provisioner = new BlogPluginProvisioner(
            blogRepository: $blogRepository,
            blogPostRepository: $blogPostRepository,
            blogTagRepository: $blogTagRepository,
            entityManager: $entityManager,
            platformBusinessKeyResolver: $platformBusinessKeyResolver,
        );

        $provisioner->provision($application);

        $blog = self::findPersistedEntity($persisted, Blog::class);
        self::assertMatchesRegularExpression('/^app-[a-f0-9]{8}-blog$/', $blog->getSlug());
    }

    public function testProvisionSkipsBootstrapForSchoolAndRecruitBusinessPlatforms(): void
    {
        $application = (new Application())
            ->setTitle('Learning App')
            ->setUser(new User());

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');

        $platformBusinessKeyResolver = $this->createMock(PlatformBusinessKeyResolver::class);
        $platformBusinessKeyResolver->method('resolve')->willReturn(PlatformKey::SCHOOL);

        $provisioner = new BlogPluginProvisioner(
            blogRepository: $this->createMock(BlogRepository::class),
            blogPostRepository: $this->createMock(BlogPostRepository::class),
            blogTagRepository: $this->createMock(BlogTagRepository::class),
            entityManager: $entityManager,
            platformBusinessKeyResolver: $platformBusinessKeyResolver,
        );

        $provisioner->provision($application);
    }

    /**
     * @template T of object
     * @param list<object> $persisted
     * @param class-string<T> $expectedClass
     * @return T
     */
    private static function findPersistedEntity(array $persisted, string $expectedClass): object
    {
        foreach ($persisted as $entity) {
            if ($entity instanceof $expectedClass) {
                return $entity;
            }
        }

        self::fail('Entity of type ' . $expectedClass . ' was not persisted.');
    }
}
