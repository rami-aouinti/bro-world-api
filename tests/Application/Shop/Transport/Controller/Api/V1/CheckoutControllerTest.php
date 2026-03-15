<?php

declare(strict_types=1);

namespace App\Tests\Application\Shop\Transport\Controller\Api\V1;

use App\Shop\Infrastructure\Repository\ProductRepository;
use App\Shop\Infrastructure\Repository\ShopRepository;
use App\Tests\TestCase\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

final class CheckoutControllerTest extends WebTestCase
{
    public function testCheckoutReturnsCreatedOrAcceptedWhenScopeIsValid(): void
    {
        /** @var ShopRepository $shopRepository */
        $shopRepository = static::getContainer()->get(ShopRepository::class);
        $shop = $shopRepository->findOneByApplicationSlug('shop-ops-center');
        self::assertNotNull($shop);

        /** @var ProductRepository $productRepository */
        $productRepository = static::getContainer()->get(ProductRepository::class);
        $product = $productRepository->findBy([
            'shop' => $shop,
        ], [
            'createdAt' => 'ASC',
        ])[0] ?? null;
        self::assertNotNull($product);

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/shop/applications/shop-ops-center/carts/' . $shop->getId() . '/items',
            [],
            [],
            $this->getJsonHeaders(),
            json_encode([
                'productId' => $product->getId(),
                'quantity' => 1,
            ], JSON_THROW_ON_ERROR)
        );
        self::assertResponseStatusCodeSame(201);

        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/shop/applications/shop-ops-center/checkout/' . $shop->getId(),
            [],
            [],
            $this->getJsonHeaders(),
            json_encode($this->validCheckoutPayload(), JSON_THROW_ON_ERROR)
        );

        self::assertContains($client->getResponse()->getStatusCode(), [201, 202]);
    }

    public function testCheckoutReturnsForbiddenWhenShopBelongsToAnotherApplicationScope(): void
    {
        /** @var ShopRepository $shopRepository */
        $shopRepository = static::getContainer()->get(ShopRepository::class);
        $foreignShop = $shopRepository->findOneByApplicationSlug('shop-catalog-lab');
        self::assertNotNull($foreignShop);

        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/shop/applications/shop-ops-center/checkout/' . $foreignShop->getId(),
            [],
            [],
            $this->getJsonHeaders(),
            json_encode($this->validCheckoutPayload(), JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(403);

        /** @var array{message?: string} $payload */
        $payload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Shop does not belong to the requested application scope.', $payload['message'] ?? null);
    }

    public function testCheckoutReturnsNotFoundWhenShopDoesNotExist(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request(
            Request::METHOD_POST,
            self::API_URL_PREFIX . '/v1/shop/applications/shop-ops-center/checkout/11111111-1111-1111-1111-111111111111',
            [],
            [],
            $this->getJsonHeaders(),
            json_encode($this->validCheckoutPayload(), JSON_THROW_ON_ERROR)
        );

        self::assertResponseStatusCodeSame(404);

        /** @var array{message?: string} $payload */
        $payload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('Shop not found.', $payload['message'] ?? null);
    }

    /**
     * @return array<string, string>
     */
    private function validCheckoutPayload(): array
    {
        return [
            'billingAddress' => '10 Main street',
            'shippingAddress' => '20 Main street',
            'email' => 'john@doe.test',
            'phone' => '123456',
            'shippingMethod' => 'express',
        ];
    }
}
