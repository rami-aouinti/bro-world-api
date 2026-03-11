<?php

declare(strict_types=1);

namespace App\Shop\Transport\Controller\Api\V1\Support;

use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Enum\PlatformKey;
use App\Shop\Domain\Entity\Shop;
use App\Shop\Infrastructure\Repository\ShopRepository;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpException;

final readonly class ShopApplicationResolver
{
    public function __construct(
        private ShopRepository $shopRepository,
        private EntityManagerInterface $entityManager,
        private Security $security,
    ) {
    }

    public function resolveOrCreateShopByApplicationSlug(string $applicationSlug): Shop
    {
        $shop = $this->shopRepository->findOneByApplicationSlug($applicationSlug);
        if ($shop instanceof Shop) {
            $this->assertApplicationAccess($shop->getApplication(), PlatformKey::SHOP);

            return $shop;
        }

        $application = $this->entityManager->getRepository(Application::class)->findOneBy([
            'slug' => $applicationSlug,
        ]);
        if (!$application instanceof Application) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Unknown "applicationSlug".');
        }

        $this->assertApplicationAccess($application, PlatformKey::SHOP);

        throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Shop root entity not found for this application.');
    }

    private function assertApplicationAccess(?Application $application, PlatformKey $platformKey): void
    {
        if (!$application instanceof Application || $application->getPlatform()?->getPlatformKey() !== $platformKey) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Invalid "applicationSlug" for the requested platform.');
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $application->getUser()?->getId() !== $user->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You cannot access this application scope.');
        }
    }
}
