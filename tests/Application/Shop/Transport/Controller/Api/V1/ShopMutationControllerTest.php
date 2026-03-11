<?php

declare(strict_types=1);

namespace App\Tests\Application\Shop\Transport\Controller\Api\V1;

use App\Shop\Infrastructure\Repository\ProductRepository;
use App\Tests\TestCase\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class ShopMutationControllerTest extends WebTestCase
{
    public function testCreateProductDispatchesCommandAndPersistsEntity(): void
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

        self::assertResponseStatusCodeSame(202);

        /** @var array{operationId: string} $payload */
        $payload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('operationId', $payload);
        self::assertNotSame('', $payload['operationId']);

        /** @var ProductRepository $productRepository */
        $productRepository = static::getContainer()->get(ProductRepository::class);
        $products = $productRepository->findBy(['name' => 'Messenger Product']);

        self::assertNotEmpty($products);
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
