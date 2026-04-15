<?php

declare(strict_types=1);

namespace App\Tests\Application\Shop\Transport\Controller\Api\V1;

use App\Tests\TestCase\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class GeneralShopCrudDocumentationExampleTest extends WebTestCase
{
    public function testGeneralShopCrudAndPromotionFilterFlowForApiDoc(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/shop/general/categories',
            [],
            [],
            $this->getJsonHeaders(),
            json_encode([
                'name' => 'General Decor Pro',
                'description' => 'Catégorie premium de démonstration pour le flux API doc.',
                'photo' => 'https://bro-world.org/img/shop/categories/decor-pro.png',
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(201);
        /** @var array{id: string} $categoryPayload */
        $categoryPayload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/shop/general/products',
            [],
            [],
            $this->getJsonHeaders(),
            json_encode([
                'name' => 'General Product Pro',
                'sku' => 'GENERAL-PRO-001',
                'description' => 'Description longue pour un produit global de qualité professionnelle, optimisé pour la vitrine et le référencement.',
                'texture' => 'matte-wood',
                'price' => 129.90,
                'promotionPercentage' => 25,
                'seoTitle' => 'General Product Pro | Shop',
                'seoDescription' => 'Produit premium optimisé SEO.',
                'seoKeywords' => ['general', 'premium', 'seo'],
                'categoryId' => $categoryPayload['id'],
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(201);
        /** @var array{id: string} $productPayload */
        $productPayload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $client->request(
            Request::METHOD_PATCH,
            self::API_URL_PREFIX . '/v1/shop/general/products/' . $productPayload['id'],
            [],
            [],
            $this->getJsonHeaders(),
            json_encode([
                'similarProductIds' => [],
                'tagIds' => [],
                'promotionPercentage' => 30,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(200);

        $client->request(
            Request::METHOD_GET,
            self::API_URL_PREFIX . '/v1/shop/general/products?promotion=20&minPrice=50&maxPrice=500'
        );

        self::assertResponseIsSuccessful();
        /** @var array{items: array<int, array{promotionPercentage?: int}>} $listPayload */
        $listPayload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertNotEmpty($listPayload['items']);
        self::assertContains(30, array_map(static fn (array $item): int => (int)($item['promotionPercentage'] ?? 0), $listPayload['items']));
    }
}
