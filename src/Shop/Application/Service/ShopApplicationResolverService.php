<?php

declare(strict_types=1);

namespace App\Shop\Application\Service;

use App\General\Application\Service\ApplicationScopeResolver;
use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Enum\PlatformKey;
use App\Shop\Domain\Entity\Shop;
use App\Shop\Infrastructure\Repository\ShopRepository;
use App\User\Domain\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class ShopApplicationResolverService
{
    public function __construct(
        private ShopRepository $shopRepository,
        private Security $security,
    ) {
    }

    public function resolveOrCreateShopByApplicationSlug(string $applicationSlug): Shop
    {
        $this->assertNoGlobalScopeAmbiguity();

        $shop = $this->shopRepository->findOneByApplicationSlug($applicationSlug);
        if ($shop instanceof Shop) {
            if ($shop->isGlobal()) {
                throw new HttpException(JsonResponse::HTTP_CONFLICT, 'Application route cannot resolve a global shop.');
            }

            $this->assertApplicationAccess($shop->getApplication(), PlatformKey::SHOP);

            return $shop;
        }

        $application = $this->shopRepository->findApplicationBySlug($applicationSlug);
        if (!$application instanceof Application) {
            throw ApplicationScopeResolver::createInvalidApplicationSlugException();
        }

        $this->assertApplicationAccess($application, PlatformKey::SHOP);

        throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Shop root entity not found for this application.');
    }

    public function resolveGlobalShop(): Shop
    {
        $this->assertNoGlobalScopeAmbiguity();

        $shop = $this->shopRepository->findGlobalShop();
        if (!$shop instanceof Shop) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Global shop root entity not found.');
        }

        if ($shop->getApplication() instanceof Application) {
            throw new HttpException(JsonResponse::HTTP_CONFLICT, 'Global shop cannot be attached to an application.');
        }

        return $shop;
    }

    public function assertApplicationAccess(?Application $application, PlatformKey $platformKey): void
    {
        if (!$application instanceof Application || $application->getPlatform()?->getPlatformKey() !== $platformKey) {
            throw ApplicationScopeResolver::createInvalidApplicationSlugException();
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $application->getUser()?->getId() !== $user->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You cannot access this application scope.');
        }
    }

    private function assertNoGlobalScopeAmbiguity(): void
    {
        if ($this->shopRepository->countGlobalShops() > 1) {
            throw new HttpException(JsonResponse::HTTP_CONFLICT, 'Ambiguous global scope: multiple global shops are configured.');
        }
    }
}
