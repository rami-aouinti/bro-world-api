<?php

declare(strict_types=1);

namespace App\General\Application\Service;

use JsonException;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function json_encode;

final readonly class MercurePublisher
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire('%env(string:MERCURE_PUBLISH_URL)%')]
        private string $mercurePublishUrl,
        #[Autowire('%env(string:MERCURE_JWT_SECRET)%')]
        private string $mercureJwtSecret,
    ) {
    }

    /**
     * @param string $topic
     * @param array<string, mixed> $data
     * @param bool $private
     * @throws JsonException
     */
    public function publish(string $topic, array $data, bool $private = true): void
    {
        $payload = [
            'topic' => $topic,
            'data' => (string) json_encode($data, JSON_THROW_ON_ERROR),
        ];

        if ($private) {
            $payload['private'] = 'on';
        }

        $this->logger->info('Mercure publish start', [
            'url' => $this->mercurePublishUrl,
            'topic' => $topic,
            'private' => $private,
            'payload' => $payload,
        ]);

        try {
            $response = $this->httpClient->request('POST', $this->mercurePublishUrl, [
                'auth_bearer' => $this->mercureJwtSecret,
                'body' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->getContent(false);

            $this->logger->info('Mercure publish success', [
                'url' => $this->mercurePublishUrl,
                'statusCode' => $statusCode,
                'response' => $content,
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Mercure publish failed', [
                'url' => $this->mercurePublishUrl,
                'topic' => $topic,
                'private' => $private,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
