<?php

declare(strict_types=1);

namespace App\Tests\Application\Page\Transport\Controller\Api\V1\Public;

use App\General\Domain\Utils\JSON;
use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;

final class PublicPageControllerTest extends WebTestCase
{
    #[TestDox('Public page endpoint returns expected JSON payload for French language.')]
    #[DataProvider('providePublicPageRoutesForFrench')]
    public function testPublicPageEndpointReturns200ForFrench(string $route, array $expectedSubset): void
    {
        $this->assertRouteResponseContainsSubset($route, 'fr', $expectedSubset);
    }

    #[TestDox('Public page endpoint returns expected JSON payload for English language.')]
    #[DataProvider('providePublicPageRoutesForEnglish')]
    public function testPublicPageEndpointReturns200ForEnglish(string $route, array $expectedSubset): void
    {
        $this->assertRouteResponseContainsSubset($route, 'en', $expectedSubset);
    }

    #[TestDox('Public page endpoint returns 404 for missing language.')]
    #[DataProvider('providePublicPageRoutes')]
    public function testPublicPageEndpointReturns404WhenLanguageIsMissing(string $route): void
    {
        $client = $this->getTestClient();
        $client->request('GET', self::API_URL_PREFIX . $route . '/xx');

        $response = $client->getResponse();

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode(), "Response:\n" . $response);
    }

    /**
     * @return iterable<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function providePublicPageRoutesForFrench(): iterable
    {
        yield 'home-fr' => [
            '/v1/page/public/home', [
                'hero' => [
                    'title' => 'Pilotez votre activité depuis un espace unique',
                ],
                'featuresTitle' => 'Fonctionnalités principales',
            ]];
        yield 'about-fr' => [
            '/v1/page/public/about', [
                'hero' => [
                    'badge' => 'À propos',
                ],
                'metricsTitle' => 'Chiffres clés',
            ]];
        yield 'contact-fr' => [
            '/v1/page/public/contact', [
                'title' => 'Contact',
                'form' => [
                    'submit' => 'Envoyer',
                ],
            ]];
        yield 'faq-fr' => [
            '/v1/page/public/faq', [
                'hero' => [
                    'title' => 'Questions fréquentes',
                ],
                'emptyState' => [
                    'title' => 'Aucun résultat',
                ],
            ]];
        yield 'crm-fr' => ['/v1/page/public/crm', ['slug' => 'crm']];
        yield 'shop-fr' => ['/v1/page/public/shop', ['slug' => 'shop']];
        yield 'jobs-applications-fr' => ['/v1/page/public/jobs-applications', ['slug' => 'jobs-applications']];
        yield 'jobs-offers-fr' => ['/v1/page/public/jobs-offers', ['slug' => 'jobs-offers']];
        yield 'job-fr' => ['/v1/page/public/job', ['slug' => 'job']];
        yield 'school-fr' => ['/v1/page/public/school', ['slug' => 'school']];
        yield 'learning-teachers-fr' => ['/v1/page/public/learning-teachers', ['slug' => 'learning-teachers']];
        yield 'learning-courses-fr' => ['/v1/page/public/learning-courses', ['slug' => 'learning-courses']];
    }

    /**
     * @return iterable<string, array{0: string, 1: array<string, mixed>}>
     */
    public static function providePublicPageRoutesForEnglish(): iterable
    {
        yield 'home-en' => [
            '/v1/page/public/home', [
                'hero' => [
                    'title' => 'Manage your business from one unified space',
                ],
                'featuresTitle' => 'Key features',
            ]];
        yield 'about-en' => [
            '/v1/page/public/about', [
                'hero' => [
                    'badge' => 'About',
                ],
                'metricsTitle' => 'Key figures',
            ]];
        yield 'contact-en' => [
            '/v1/page/public/contact', [
                'title' => 'Contact',
                'form' => [
                    'submit' => 'Send',
                ],
            ]];
        yield 'faq-en' => [
            '/v1/page/public/faq', [
                'hero' => [
                    'title' => 'Frequently asked questions',
                ],
                'emptyState' => [
                    'title' => 'No results',
                ],
            ]];
        yield 'crm-en' => ['/v1/page/public/crm', ['slug' => 'crm']];
        yield 'shop-en' => ['/v1/page/public/shop', ['slug' => 'shop']];
        yield 'jobs-applications-en' => ['/v1/page/public/jobs-applications', ['slug' => 'jobs-applications']];
        yield 'jobs-offers-en' => ['/v1/page/public/jobs-offers', ['slug' => 'jobs-offers']];
        yield 'job-en' => ['/v1/page/public/job', ['slug' => 'job']];
        yield 'school-en' => ['/v1/page/public/school', ['slug' => 'school']];
        yield 'learning-teachers-en' => ['/v1/page/public/learning-teachers', ['slug' => 'learning-teachers']];
        yield 'learning-courses-en' => ['/v1/page/public/learning-courses', ['slug' => 'learning-courses']];
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function providePublicPageRoutes(): iterable
    {
        yield 'home' => ['/v1/page/public/home'];
        yield 'about' => ['/v1/page/public/about'];
        yield 'contact' => ['/v1/page/public/contact'];
        yield 'faq' => ['/v1/page/public/faq'];
        yield 'crm' => ['/v1/page/public/crm'];
        yield 'shop' => ['/v1/page/public/shop'];
        yield 'jobs-applications' => ['/v1/page/public/jobs-applications'];
        yield 'jobs-offers' => ['/v1/page/public/jobs-offers'];
        yield 'job' => ['/v1/page/public/job'];
        yield 'school' => ['/v1/page/public/school'];
        yield 'learning-teachers' => ['/v1/page/public/learning-teachers'];
        yield 'learning-courses' => ['/v1/page/public/learning-courses'];
    }

    /**
     * @param array<string, mixed> $expectedSubset
     */
    private function assertRouteResponseContainsSubset(string $route, string $language, array $expectedSubset): void
    {
        $client = $this->getTestClient();
        $client->request('GET', self::API_URL_PREFIX . $route . '/' . $language);

        $response = $client->getResponse();
        $content = $response->getContent();

        self::assertNotFalse($content);
        self::assertSame(Response::HTTP_OK, $response->getStatusCode(), "Response:\n" . $response);

        $payload = JSON::decode($content, true);
        self::assertIsArray($payload);

        foreach ($expectedSubset as $key => $value) {
            self::assertArrayHasKey($key, $payload);
            self::assertSame($value, $payload[$key]);
        }
    }
}
