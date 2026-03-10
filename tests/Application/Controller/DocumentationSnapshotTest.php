<?php

declare(strict_types=1);

namespace App\Tests\Application\Controller;

use App\Tests\TestCase\WebTestCase;
use PHPUnit\Framework\Attributes\TestDox;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DocumentationSnapshotTest extends WebTestCase
{
    /**
     * @throws Throwable
     */
    #[TestDox('Documentation snapshot for critical Calendar/Chat/Configuration/Platform/Recruit paths')]
    public function testDocumentationCriticalPathsSnapshot(): void
    {
        $client = $this->getTestClient();
        $client->request('GET', '/api/doc.json');

        static::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $payload = json_decode((string)$client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $paths = $payload['paths'] ?? [];

        $snapshot = [
            '/v1/configuration' => ['get', 'post'],
            '/v1/configuration/{id}' => ['patch', 'delete'],
            '/v1/platform' => ['get', 'post'],
            '/v1/plugin' => ['get', 'post'],
            '/v1/recruit/tag' => ['get', 'post'],
            '/v1/recruit/company' => ['get', 'post'],
            '/v1/calendar/private/events' => ['get', 'post'],
            '/v1/chat/private/messages/{messageId}' => ['patch', 'delete'],
            '/v1/notifications' => ['get', 'post'],
            '/v1/notifications/read-all' => ['patch'],
            '/v1/notifications/{id}' => ['get'],
            '/v1/media/upload' => ['post'],
        ];

        foreach ($snapshot as $path => $methods) {
            static::assertArrayHasKey($path, $paths, 'Missing path in documentation: ' . $path);
            foreach ($methods as $method) {
                static::assertArrayHasKey($method, $paths[$path], 'Missing method in documentation: ' . strtoupper($method) . ' ' . $path);
            }
        }
    }
}
