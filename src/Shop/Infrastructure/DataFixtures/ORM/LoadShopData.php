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
    private const string ASSET_HOST = 'https://bro-world.org';

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
        $globalShop = $this->findOrCreateGlobalShop($manager);

        $coinsCategory = $this->findOrCreateCategory(
            $manager,
            $globalShop,
            'Coins',
            'coins',
            'Packs de coins virtuels.',
            $this->buildAssetUrl('/img/shop/categories/coins.png'),
        );

        $housesCategory = $this->findOrCreateCategory(
            $manager,
            $globalShop,
            'Houses',
            'houses',
            'Maisons et décor premium.',
            $this->buildAssetUrl('/img/shop/categories/houses.png'),
        );

        $furnitureCategory = $this->findOrCreateCategory(
            $manager,
            $globalShop,
            'Meubles',
            'meubles',
            'Mobilier et décoration intérieure.',
            $this->buildAssetUrl('/img/shop/categories/meubles.png'),
        );

        $this->findOrCreateProduct(
            manager: $manager,
            shop: $globalShop,
            category: $coinsCategory,
            name: '200 coins',
            sku: 'COINS-200',
            price: 200,
            stock: 999999,
            description: 'Pack d’entrée idéal pour commencer rapidement: crédite 200 coins, actif immédiatement, et parfait pour débloquer les premières options premium.',
            photo: $this->buildAssetUrl('/img/shop/products/200.png'),
            coinsAmount: 200,
            isFeatured: true,
            texture: 'digital-wallet',
            promotionPercentage: 10,
            seoTitle: 'Pack 200 coins - Starter',
            seoDescription: 'Pack starter de 200 coins pour booster votre progression dès les premières minutes.',
            seoKeywords: ['coins', 'starter', 'pack'],
        );

        $this->findOrCreateProduct(
            manager: $manager,
            shop: $globalShop,
            category: $coinsCategory,
            name: '400 coins',
            sku: 'COINS-400',
            price: 360,
            stock: 999999,
            description: 'Offre intermédiaire avec 400 coins, pensée pour les joueurs réguliers qui veulent une progression fluide et de meilleures marges d’optimisation.',
            photo: $this->buildAssetUrl('/img/shop/products/400.png'),
            coinsAmount: 400,
            isFeatured: true,
            texture: 'digital-wallet',
            promotionPercentage: 20,
            seoTitle: 'Pack 400 coins - Smart Value',
            seoDescription: 'Pack 400 coins avec excellent rapport quantité/prix pour progresser plus vite.',
            seoKeywords: ['coins', 'value', 'promotion'],
        );

        $this->findOrCreateProduct(
            manager: $manager,
            shop: $globalShop,
            category: $coinsCategory,
            name: '600 coins',
            sku: 'COINS-600',
            price: 500,
            stock: 999999,
            description: 'Pack le plus avantageux de la gamme coins: 600 coins crédités avec un bonus tarifaire adapté aux sessions intensives et achats récurrents.',
            photo: $this->buildAssetUrl('/img/shop/products/600.png'),
            coinsAmount: 600,
            isFeatured: true,
            texture: 'digital-wallet-premium',
            promotionPercentage: 25,
            seoTitle: 'Pack 600 coins - Best Seller',
            seoDescription: 'Pack premium 600 coins pour les utilisateurs power et achats fréquents.',
            seoKeywords: ['coins', 'premium', 'best seller'],
        );

        $this->findOrCreateProduct(
            manager: $manager,
            shop: $globalShop,
            category: $housesCategory,
            name: 'House Pack 1',
            sku: 'HOUSE-001',
            price: 15900,
            stock: 120,
            description: 'Pack maison 1.',
            photo: $this->buildAssetUrl('/img/shop/products/product-1-min.jpeg'),
        );

        $this->findOrCreateProduct(
            manager: $manager,
            shop: $globalShop,
            category: $housesCategory,
            name: 'House Pack 2',
            sku: 'HOUSE-002',
            price: 22900,
            stock: 80,
            description: 'Pack maison 2.',
            photo: $this->buildAssetUrl('/img/shop/products/product-2-min.jpeg'),
        );

        $this->findOrCreateProduct(
            manager: $manager,
            shop: $globalShop,
            category: $housesCategory,
            name: 'House Pack 3',
            sku: 'HOUSE-003',
            price: 31900,
            stock: 60,
            description: 'Pack maison 3.',
            photo: $this->buildAssetUrl('/img/shop/products/product-3-min.jpeg'),
        );

        $this->findOrCreateProduct(
            manager: $manager,
            shop: $globalShop,
            category: $furnitureCategory,
            name: 'Meuble Set 1',
            sku: 'FURN-001',
            price: 3900,
            stock: 250,
            description: 'Set meuble 1.',
            photo: $this->buildAssetUrl('/img/shop/products/product-details-1.jpg'),
        );

        $this->findOrCreateProduct(
            manager: $manager,
            shop: $globalShop,
            category: $furnitureCategory,
            name: 'Meuble Set 2',
            sku: 'FURN-002',
            price: 4900,
            stock: 220,
            description: 'Set meuble 2.',
            photo: $this->buildAssetUrl('/img/shop/products/product-details-2.jpg'),
        );

        $this->findOrCreateProduct(
            manager: $manager,
            shop: $globalShop,
            category: $furnitureCategory,
            name: 'Meuble Set 3',
            sku: 'FURN-003',
            price: 5900,
            stock: 180,
            description: 'Set meuble 3.',
            photo: $this->buildAssetUrl('/img/shop/products/product-details-3.jpg'),
        );

        $this->findOrCreateProduct(
            manager: $manager,
            shop: $globalShop,
            category: $furnitureCategory,
            name: 'Meuble Set 4',
            sku: 'FURN-004',
            price: 7900,
            stock: 140,
            description: 'Set meuble 4.',
            photo: $this->buildAssetUrl('/img/shop/products/product-details-4.jpg'),
        );

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

    private function findOrCreateGlobalShop(ObjectManager $manager): Shop
    {
        $shop = $manager->getRepository(Shop::class)->findOneBy(['isGlobal' => true]);
        if ($shop instanceof Shop) {
            return $shop
                ->setName('Global Coins Shop')
                ->setDescription('Catalogue global pour les achats de coins.')
                ->setIsActive(true)
                ->setIsGlobal(true)
                ->setApplication(null);
        }

        $shop = (new Shop())
            ->setName('Global Coins Shop')
            ->setDescription('Catalogue global pour les achats de coins.')
            ->setIsActive(true)
            ->setIsGlobal(true)
            ->setApplication(null);
        $manager->persist($shop);

        return $shop;
    }

    private function findOrCreateCategory(ObjectManager $manager, Shop $shop, string $name, string $slug, string $description, string $photo): Category
    {
        $category = $manager->getRepository(Category::class)->findOneBy([
            'shop' => $shop,
            'slug' => $slug,
        ]);

        if ($category instanceof Category) {
            return $category
                ->setName($name)
                ->setDescription($description)
                ->setPhoto($photo);
        }

        $category = (new Category())
            ->setShop($shop)
            ->setName($name)
            ->setSlug($slug)
            ->setDescription($description)
            ->setPhoto($photo);
        $manager->persist($category);

        return $category;
    }

    private function findOrCreateProduct(
        ObjectManager $manager,
        Shop $shop,
        Category $category,
        string $name,
        string $sku,
        int $price,
        int $stock,
        string $description,
        string $photo,
        int $coinsAmount = 0,
        bool $isFeatured = false,
        ?string $texture = null,
        int $promotionPercentage = 0,
        ?string $seoTitle = null,
        ?string $seoDescription = null,
        array $seoKeywords = [],
    ): Product {
        $product = $manager->getRepository(Product::class)->findOneBy(['sku' => $sku]);

        if ($product instanceof Product) {
            return $product
                ->setShop($shop)
                ->setCategory($category)
                ->setName($name)
                ->setDescription($description)
                ->setPhoto($photo)
                ->setPrice($price)
                ->setCurrencyCode('EUR')
                ->setStock($stock)
                ->setCoinsAmount($coinsAmount)
                ->setTexture($texture)
                ->setPromotionPercentage($promotionPercentage)
                ->setSeoTitle($seoTitle)
                ->setSeoDescription($seoDescription)
                ->setSeoKeywords($seoKeywords)
                ->setStatus(ProductStatus::ACTIVE)
                ->setIsFeatured($isFeatured);
        }

        $product = (new Product())
            ->setShop($shop)
            ->setCategory($category)
            ->setName($name)
            ->setSku($sku)
            ->setDescription($description)
            ->setPhoto($photo)
            ->setPrice($price)
            ->setCurrencyCode('EUR')
            ->setStock($stock)
            ->setCoinsAmount($coinsAmount)
            ->setTexture($texture)
            ->setPromotionPercentage($promotionPercentage)
            ->setSeoTitle($seoTitle)
            ->setSeoDescription($seoDescription)
            ->setSeoKeywords($seoKeywords)
            ->setStatus(ProductStatus::ACTIVE)
            ->setIsFeatured($isFeatured);
        $manager->persist($product);

        return $product;
    }

    private function buildAssetUrl(string $path): string
    {
        return self::ASSET_HOST . $path;
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
