<?php

declare(strict_types=1);

namespace App\Tests\Application\Shop\Transport\Controller\Api\V1;

use App\Shop\Infrastructure\Repository\ProductRepository;
use App\Tests\TestCase\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class ShopMutationControllerTest extends WebTestCase
{
    public function testCreateProductReturnsCreatedWithIdAndPersistsEntity(): void
    {
        $client = $this->getTestClient('john-user', 'password-user');

        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/shop/products',
            [],
            [],
            $this->getJsonHeaders(),
            json_encode([
                'name' => 'Messenger Product',
                'price' => 12.34,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(201);

        /** @var array{id: string} $payload */
        $payload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('id', $payload);
        self::assertNotSame('', $payload['id']);

        /** @var ProductRepository $productRepository */
        $productRepository = static::getContainer()->get(ProductRepository::class);
        $products = $productRepository->findBy(['name' => 'Messenger Product']);

        self::assertNotEmpty($products);
        self::assertSame($products[0]->getId(), $payload['id']);
    }

    public function testCreateApplicationProductReturnsCreatedWithIdAndPersistsEntity(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');

        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/shop/applications/shop-ops-center/products',
            [],
            [],
            $this->getJsonHeaders(),
            json_encode([
                'name' => 'App Messenger Product',
                'price' => 23.45,
            ], JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(201);

        /** @var array{id: string, shopId: string, applicationSlug: string} $payload */
        $payload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('id', $payload);
        self::assertSame('shop-ops-center', $payload['applicationSlug']);
        self::assertNotSame('', $payload['shopId']);

        /** @var ProductRepository $productRepository */
        $productRepository = static::getContainer()->get(ProductRepository::class);
        $products = $productRepository->findBy(['name' => 'App Messenger Product']);

        self::assertNotEmpty($products);
        self::assertSame($products[0]->getId(), $payload['id']);
    }

    public function testDeleteProductDispatchesCommandAndRemovesEntity(): void
    {
        /** @var ProductRepository $productRepository */
        $productRepository = static::getContainer()->get(ProductRepository::class);
        $product = $productRepository->findBy(['name' => 'Messenger Product'])[0] ?? null;
        self::assertNotNull($product);

        $client = $this->getTestClient('john-user', 'password-user');
        $client->request(
            Request::METHOD_DELETE,
            self::API_URL_PREFIX . '/v1/shop/products/' . $product->getId(),
            [],
            [],
            $this->getJsonHeaders(),
        );

        self::assertResponseStatusCodeSame(202);

        /** @var array{operationId: string, id: string} $payload */
        $payload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($product->getId(), $payload['id']);
        self::assertArrayHasKey('operationId', $payload);

        self::assertNull($productRepository->find($product->getId()));
    }
}
