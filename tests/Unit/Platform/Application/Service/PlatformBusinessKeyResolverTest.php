<?php

declare(strict_types=1);

namespace App\Tests\Unit\Platform\Application\Service;

use App\Configuration\Domain\Entity\Configuration;
use App\Platform\Application\Service\PlatformBusinessKeyResolver;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Entity\ApplicationPlugin;
use App\Platform\Domain\Entity\Platform;
use App\Platform\Domain\Enum\PlatformKey;
use PHPUnit\Framework\TestCase;

final class PlatformBusinessKeyResolverTest extends TestCase
{
    public function testResolveReturnsNativeBusinessPlatformWhenAlreadySchool(): void
    {
        $platform = (new Platform())->setPlatformKey(PlatformKey::SCHOOL);
        $application = (new Application())->setPlatform($platform);

        $resolver = new PlatformBusinessKeyResolver();

        self::assertSame(PlatformKey::SCHOOL, $resolver->resolve($application));
    }

    public function testResolveMapsLearningAliasToSchool(): void
    {
        $application = (new Application())
            ->setPlatform((new Platform())->setPlatformKey(PlatformKey::CRM));

        $application->addConfiguration(
            (new Configuration())->setConfigurationKey('application.general.learning')->setConfigurationValue([]),
        );

        $resolver = new PlatformBusinessKeyResolver();

        self::assertSame(PlatformKey::SCHOOL, $resolver->resolve($application));
    }

    public function testResolveMapsJobAliasToRecruitFromPluginConfiguration(): void
    {
        $application = (new Application())
            ->setPlatform((new Platform())->setPlatformKey(PlatformKey::SHOP));

        $applicationPlugin = new ApplicationPlugin();
        $applicationPlugin->addConfiguration(
            (new Configuration())->setConfigurationKey('plugin.blog.job')->setConfigurationValue([]),
        );
        $application->addApplicationPlugin($applicationPlugin);

        $resolver = new PlatformBusinessKeyResolver();

        self::assertSame(PlatformKey::RECRUIT, $resolver->resolve($application));
    }
}
