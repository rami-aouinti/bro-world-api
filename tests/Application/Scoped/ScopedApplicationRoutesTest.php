<?php

declare(strict_types=1);

namespace App\Tests\Application\Scoped;

use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

final class ScopedApplicationRoutesTest extends WebTestCase
{
    #[TestDox('CRM scoped routes return 200 for owner, 403 for foreign owner, and 404 for invalid slug.')]
    public function testCrmScopedRoutesAccessControl(): void
    {
        $ownerClient = $this->getTestClient('john-root', 'password-root');
        $ownerClient->request('GET', self::API_URL_PREFIX . '/v1/crm/applications/crm-sales-hub/companies');
        self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());

        $forbiddenClient = $this->getTestClient('john-user', 'password-user');
        $forbiddenClient->request('GET', self::API_URL_PREFIX . '/v1/crm/applications/crm-sales-hub/companies');
        self::assertSame(Response::HTTP_FORBIDDEN, $forbiddenClient->getResponse()->getStatusCode());

        $invalidClient = $this->getTestClient('john-root', 'password-root');
        $invalidClient->request('GET', self::API_URL_PREFIX . '/v1/crm/applications/not-found-slug/companies');
        self::assertContains($invalidClient->getResponse()->getStatusCode(), [Response::HTTP_BAD_REQUEST, Response::HTTP_NOT_FOUND]);
    }

    #[TestDox('Shop scoped routes return 200 for owner, 403 for foreign owner, and 400 for wrong platform slug.')]
    public function testShopScopedRoutesAccessControl(): void
    {
        $ownerClient = $this->getTestClient('john-root', 'password-root');
        $ownerClient->request('GET', self::API_URL_PREFIX . '/v1/shop/applications/shop-ops-center/products');
        self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());

        $forbiddenClient = $this->getTestClient('john-user', 'password-user');
        $forbiddenClient->request('GET', self::API_URL_PREFIX . '/v1/shop/applications/shop-ops-center/products');
        self::assertSame(Response::HTTP_FORBIDDEN, $forbiddenClient->getResponse()->getStatusCode());

        $invalidClient = $this->getTestClient('john-root', 'password-root');
        $invalidClient->request('GET', self::API_URL_PREFIX . '/v1/shop/applications/crm-sales-hub/products');
        self::assertSame(Response::HTTP_BAD_REQUEST, $invalidClient->getResponse()->getStatusCode());
    }

    #[TestDox('School scoped routes return 200 for owner, 403 for foreign owner, and 404 for invalid slug.')]
    public function testSchoolScopedRoutesAccessControl(): void
    {
        $ownerClient = $this->getTestClient('john-root', 'password-root');
        $ownerClient->request('GET', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/classes');
        self::assertSame(Response::HTTP_OK, $ownerClient->getResponse()->getStatusCode());

        $forbiddenClient = $this->getTestClient('john-user', 'password-user');
        $forbiddenClient->request('GET', self::API_URL_PREFIX . '/v1/school/applications/school-campus-core/classes');
        self::assertSame(Response::HTTP_FORBIDDEN, $forbiddenClient->getResponse()->getStatusCode());

        $invalidClient = $this->getTestClient('john-root', 'password-root');
        $invalidClient->request('GET', self::API_URL_PREFIX . '/v1/school/applications/not-found-slug/classes');
        self::assertSame(Response::HTTP_NOT_FOUND, $invalidClient->getResponse()->getStatusCode());
    }
}
