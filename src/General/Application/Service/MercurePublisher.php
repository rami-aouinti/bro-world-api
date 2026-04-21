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
            'data' => (string)json_encode($data, JSON_THROW_ON_ERROR),
        ];

        if ($private) {
            $payload['private'] = 'on';
        }

        try {
            $this->httpClient->request('POST', $this->mercurePublishUrl, [
                'body' => $payload,
            ])->getStatusCode();
        } catch (ExceptionInterface $exception) {
            $this->logger->error('Mercure publish failed.', [
                'topic' => $topic,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
