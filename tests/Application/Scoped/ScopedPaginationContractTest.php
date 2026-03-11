<?php

declare(strict_types=1);

namespace App\Tests\Application\Scoped;

use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

final class ScopedPaginationContractTest extends WebTestCase
{
    #[TestDox('Scoped CRM list returns items/pagination/meta and accepts filters.')]
    public function testCrmScopedPaginationContract(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('GET', self::API_URL_PREFIX . '/v1/crm/applications/crm-sales-hub/companies?page=1&limit=5&q=acme');

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $payload = $this->decodeResponse($client->getResponse());
        self::assertArrayHasKey('items', $payload);
        self::assertArrayHasKey('pagination', $payload);
        self::assertArrayHasKey('meta', $payload);
        self::assertSame('crm-sales-hub', $payload['meta']['applicationSlug']);
        self::assertSame(1, $payload['pagination']['page']);
        self::assertSame(5, $payload['pagination']['limit']);
    }

    #[TestDox('Scoped Shop list returns items/pagination/meta and accepts filters.')]
    public function testShopScopedPaginationContract(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('GET', self::API_URL_PREFIX . '/v1/shop/applications/shop-ops-center/products?page=1&limit=5&name=phone&q=phone');

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $payload = $this->decodeResponse($client->getResponse());
        self::assertArrayHasKey('items', $payload);
        self::assertArrayHasKey('pagination', $payload);
        self::assertArrayHasKey('meta', $payload);
        self::assertSame('shop-ops-center', $payload['meta']['applicationSlug']);
        self::assertSame(1, $payload['pagination']['page']);
        self::assertSame(5, $payload['pagination']['limit']);
    }

    #[TestDox('Scoped School list returns items/pagination/meta and accepts filters.')]
    public function testSchoolScopedPaginationContract(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('GET', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/classes?page=1&limit=5&q=classe');

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $payload = $this->decodeResponse($client->getResponse());
        self::assertArrayHasKey('items', $payload);
        self::assertArrayHasKey('pagination', $payload);
        self::assertArrayHasKey('meta', $payload);
        self::assertSame('school-campus-core', $payload['meta']['applicationSlug']);
        self::assertSame(1, $payload['pagination']['page']);
        self::assertSame(5, $payload['pagination']['limit']);
    }

    #[TestDox('Recruit public list returns items/pagination/meta and accepts filters.')]
    public function testRecruitPublicPaginationContract(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('GET', self::API_URL_PREFIX . '/v1/recruit/applications/recruit-talent-core/public/jobs?page=1&limit=5&q=dev');

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $payload = $this->decodeResponse($client->getResponse());
        self::assertArrayHasKey('items', $payload);
        self::assertArrayHasKey('pagination', $payload);
        self::assertArrayHasKey('meta', $payload);
        self::assertSame('recruit-talent-core', $payload['meta']['applicationSlug']);
    }
}
