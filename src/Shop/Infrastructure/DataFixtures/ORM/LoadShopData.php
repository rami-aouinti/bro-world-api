<?php

declare(strict_types=1);

namespace App\Shop\Infrastructure\DataFixtures\ORM;

use App\Platform\Domain\Entity\Application;
use App\Platform\Domain\Enum\PlatformKey;
use App\Shop\Domain\Entity\Category;
use App\Shop\Domain\Entity\Product;
use App\Shop\Domain\Entity\Shop;
use App\Shop\Domain\Entity\Tag;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\OrderedFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Override;

final class LoadShopData extends Fixture implements OrderedFixtureInterface
{
    /** @var array<non-empty-string, array<int, non-empty-string>> */
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
            $catalog = (new Shop())
                ->setName($application->getTitle() . ' Catalog');
            $manager->persist($catalog);

            $categories = [
                'Electronique' => (new Category())->setShop($catalog)->setName('Electronique'),
                'Maison' => (new Category())->setShop($catalog)->setName('Maison'),
                'Bureau' => (new Category())->setShop($catalog)->setName('Bureau'),
            ];

            foreach ($categories as $category) {
                $manager->persist($category);
            }

            $tags = [
                'Nouveau' => (new Tag())->setLabel('Nouveau'),
                'Promo' => (new Tag())->setLabel('Promo'),
                'Eco' => (new Tag())->setLabel('Eco'),
            ];

            foreach ($tags as $tag) {
                $manager->persist($tag);
            }

            $products = [
                (new Product())->setShop($catalog)->setCategory($categories['Electronique'])->setName('Casque Bluetooth')->setPrice(89.99)->addTag($tags['Nouveau'])->addTag($tags['Promo']),
                (new Product())->setShop($catalog)->setCategory($categories['Maison'])->setName('Lampe LED connectee')->setPrice(59.90)->addTag($tags['Eco']),
                (new Product())->setShop($catalog)->setCategory($categories['Bureau'])->setName('Chaise ergonomique')->setPrice(229.00)->addTag($tags['Promo']),
                (new Product())->setShop($catalog)->setCategory($categories['Electronique'])->setName('Souris sans fil')->setPrice(34.50)->addTag($tags['Nouveau'])->addTag($tags['Eco']),
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

    /** @return array<int, Application> */
    private function getApplicationsByPlatform(PlatformKey $platformKey): array
    {
        $applications = [];

        foreach (self::APPLICATION_KEYS_BY_PLATFORM[$platformKey->value] ?? [] as $applicationKey) {
            $applications[] = $this->getReference('Application-' . $applicationKey, Application::class);
        }

        return $applications;
    }
}
