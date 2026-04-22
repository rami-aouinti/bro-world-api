<?php

declare(strict_types=1);

namespace App\Tests\Application\Scoped;

use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

final class ScopedApplicationRoutesTest extends WebTestCase
{
    #[TestDox('CRM routes resolve application slug from query and enforce owner access.')]
    public function testCrmRoutesAccessControlUsingQuerySlug(): void
    {
        $ownerClient = $this->getTestClient('john-root', 'password-root');
        $ownerClient->request('GET', self::API_URL_PREFIX . '/v1/crm/companies?applicationSlug=crm-sales-hub');
        self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());
        $ownerPayload = $this->decodeResponse($ownerClient->getResponse());
        self::assertSame('crm-sales-hub', $ownerPayload['meta']['applicationSlug'] ?? null);

        $forbiddenClient = $this->getTestClient('john-user', 'password-user');
        $forbiddenClient->request('GET', self::API_URL_PREFIX . '/v1/crm/companies?applicationSlug=crm-sales-hub');
        self::assertSame(Response::HTTP_FORBIDDEN, $forbiddenClient->getResponse()->getStatusCode());
    }

    #[TestDox('CRM routes fallback to general scope when no application slug is provided.')]
    public function testCrmRoutesFallbackToGeneralWithoutApplicationSlug(): void
    {
        $client = $this->getTestClient('john-root', 'password-root');
        $client->request('GET', self::API_URL_PREFIX . '/v1/crm/companies?page=1&limit=5');

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $payload = $this->decodeResponse($client->getResponse());
        self::assertSame('general', $payload['meta']['applicationSlug'] ?? null);
    }

    #[TestDox('Shop routes resolve application slug from request header.')]
    public function testShopRoutesAccessControlUsingHeaderSlug(): void
    {
        $ownerClient = $this->getTestClient('john-root', 'password-root');
        $ownerClient->request('GET', self::API_URL_PREFIX . '/v1/shop/products', server: [
            'HTTP_X_APPLICATION_SLUG' => 'shop-ops-center',
        ]);
        self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());

        $forbiddenClient = $this->getTestClient('john-user', 'password-user');
        $forbiddenClient->request('GET', self::API_URL_PREFIX . '/v1/shop/products', server: [
            'HTTP_X_APPLICATION_SLUG' => 'shop-ops-center',
        ]);
        self::assertSame(Response::HTTP_FORBIDDEN, $forbiddenClient->getResponse()->getStatusCode());
    }

    #[TestDox('School routes return expected error when application slug is invalid.')]
    public function testSchoolRoutesInvalidSlug(): void
    {
        $invalidClient = $this->getTestClient('john-root', 'password-root');
        $invalidClient->request('GET', self::API_URL_PREFIX . '/v1/school/classes?applicationSlug=not-found-slug');

        self::assertSame(Response::HTTP_NOT_FOUND, $invalidClient->getResponse()->getStatusCode());
        $payload = $this->decodeResponse($invalidClient->getResponse());
        self::assertIsArray($payload);
        self::assertArrayHasKey('message', $payload);
    }
}
