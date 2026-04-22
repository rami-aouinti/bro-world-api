<?php

declare(strict_types=1);

namespace App\Tests\Unit\General\Application\Service;

use App\General\Application\Service\ApplicationScopeResolver;
use App\Platform\Domain\Entity\Application;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpFoundation\Request;

final class ApplicationScopeResolverTest extends TestCase
{
    public function testResolveApplicationSlugTrimsQueryValue(): void
    {
        $request = new Request(['applicationSlug' => '  recruit-general-core  ']);
        $resolver = $this->createResolverExpectingLookup('recruit-general-core', true);

        $slug = $resolver->resolveApplicationSlug($request);

        self::assertSame('recruit-general-core', $slug);
        self::assertSame('recruit-general-core', $request->attributes->get('applicationSlug'));
    }

    public function testResolveApplicationSlugFallsBackToGeneralWhenEmpty(): void
    {
        $request = new Request(['applicationSlug' => '   ']);
        $resolver = $this->createResolverExpectingLookup(ApplicationScopeResolver::DEFAULT_APPLICATION_SLUG, true);

        $slug = $resolver->resolveApplicationSlug($request);

        self::assertSame(ApplicationScopeResolver::DEFAULT_APPLICATION_SLUG, $slug);
    }

    public function testResolveApplicationSlugThrowsWhenUnknown(): void
    {
        $request = new Request(['applicationSlug' => 'unknown-scope']);
        $resolver = $this->createResolverExpectingLookup('unknown-scope', false);

        $this->expectException(HttpException::class);
        $this->expectExceptionCode(404);
        $this->expectExceptionMessage(ApplicationScopeResolver::UNKNOWN_APPLICATION_SLUG_MESSAGE);

        $resolver->resolveApplicationSlug($request);
    }

    public function testResolveApplicationSlugUsesGeneralFromHeadersWhenMissingInQuery(): void
    {
        $request = new Request(server: ['HTTP_X_APPLICATION_SLUG' => '   general   ']);
        $resolver = $this->createResolverExpectingLookup('general', true);

        $slug = $resolver->resolveApplicationSlug($request);

        self::assertSame('general', $slug);
    }

    private function createResolverExpectingLookup(string $expectedSlug, bool $exists): ApplicationScopeResolver
    {
        $repository = $this->createMock(ObjectRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['slug' => $expectedSlug])
            ->willReturn($exists ? new Application() : null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('getRepository')
            ->with(Application::class)
            ->willReturn($repository);

        return new ApplicationScopeResolver($entityManager);
    }
}
