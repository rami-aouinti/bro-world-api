<?php

declare(strict_types=1);

namespace App\Shop\Infrastructure\DataFixtures\ORM;

use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Enum\PlatformKey;
use App\Shop\Domain\Entity\Category;
use App\Shop\Domain\Entity\Product;
use App\Shop\Domain\Entity\Shop;
use App\Shop\Domain\Entity\Tag;
use App\Shop\Domain\Enum\ProductStatus;
use App\Shop\Domain\Enum\TagType;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

final class LoadShopData extends Fixture implements OrderedFixtureInterface
{
    /**
     * @var array<non-empty-string, array<int, non-empty-string>>
     */
    private const array APPLICATION_KEYS_BY_PLATFORM = [
        PlatformKey::SHOP->value => [
            'shop-ops-center',
            'shop-catalog-lab',
            'shop-orders-watch',
        ],
    ];

    #[Override]
    public function load(ObjectManager $manager): void
    {
        foreach ($this->getApplicationsByPlatform(PlatformKey::SHOP) as $application) {
            $existingCatalog = $manager->getRepository(Shop::class)->findOneBy([
                'application' => $application,
            ]);
            if ($existingCatalog instanceof Shop) {
                continue;
            }

            $catalog = (new Shop())
                ->setName($application->getTitle() . ' Catalog')
                ->setDescription('Catalogue principal de l\'application ' . $application->getSlug())
                ->setIsActive(true)
                ->setApplication($application);
            $manager->persist($catalog);

            $categories = [
                'Electronique' => (new Category())->setShop($catalog)->setName('Electronique')->setSlug('electronique')->setDescription('Produits high-tech'),
                'Maison' => (new Category())->setShop($catalog)->setName('Maison')->setSlug('maison')->setDescription('Maison et confort'),
                'Bureau' => (new Category())->setShop($catalog)->setName('Bureau')->setSlug('bureau')->setDescription('Materiel professionnel'),
            ];

            foreach ($categories as $category) {
                $manager->persist($category);
            }

            $tags = [
                'Nouveau' => (new Tag())->setLabel('Nouveau')->setType(TagType::SEASONAL),
                'Promo' => (new Tag())->setLabel('Promo')->setType(TagType::MARKETING),
                'Eco' => (new Tag())->setLabel('Eco')->setType(TagType::INVENTORY),
            ];

            foreach ($tags as $tag) {
                $manager->persist($tag);
            }

            $products = [
                (new Product())->setShop($catalog)->setCategory($categories['Electronique'])->setName('Casque Bluetooth')->setSku($application->getSlug() . '-SKU-BT-HEADSET')->setDescription('Casque sans fil reduction de bruit')->setPrice(8999)->setCurrencyCode('EUR')->setStock(32)->setStatus(ProductStatus::ACTIVE)->setIsFeatured(true)->addTag($tags['Nouveau'])->addTag($tags['Promo']),
                (new Product())->setShop($catalog)->setCategory($categories['Maison'])->setName('Lampe LED connectee')->setSku($application->getSlug() . '-SKU-SMART-LAMP')->setDescription('Lampe connectee basse consommation')->setPrice(5990)->setCurrencyCode('EUR')->setStock(54)->setStatus(ProductStatus::ACTIVE)->addTag($tags['Eco']),
                (new Product())->setShop($catalog)->setCategory($categories['Bureau'])->setName('Chaise ergonomique')->setSku($application->getSlug() . '-SKU-ERG-CHAIR')->setDescription('Chaise bureau confort premium')->setPrice(22900)->setCurrencyCode('EUR')->setStock(8)->setStatus(ProductStatus::DRAFT)->addTag($tags['Promo']),
                (new Product())->setShop($catalog)->setCategory($categories['Electronique'])->setName('Souris sans fil')->setSku($application->getSlug() . '-SKU-WIRELESS-MOUSE')->setDescription('Souris ergonomique rechargeable')->setPrice(3450)->setCurrencyCode('EUR')->setStock(65)->setStatus(ProductStatus::ACTIVE)->addTag($tags['Nouveau'])->addTag($tags['Eco']),
            ];

            foreach ($products as $product) {
                $manager->persist($product);
            }
        }

        $manager->flush();
    }

    #[Override]
    public function getOrder(): int
    {
        return 10;
    }

    /**
     * @return array<int, Application>
     */
    private function getApplicationsByPlatform(PlatformKey $platformKey): array
    {
        $applications = [];

        foreach (self::APPLICATION_KEYS_BY_PLATFORM[$platformKey->value] ?? [] as $applicationKey) {
            $applications[] = $this->getReference('Application-' . $applicationKey, Application::class);
        }

        return $applications;
    }
}
