<?php

declare(strict_types=1);

namespace App\Notification\Application\Service;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_map;
use function is_array;
use function is_numeric;
use function is_string;
use function sprintf;
use function trim;

final readonly class MailjetTemplateService
{
    private const string MAILJET_TEMPLATE_ENDPOINT = 'https://api.mailjet.com/v3/REST/template';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $mailjetApiKey,
        private string $mailjetSecretKey,
    ) {
    }

    /**
     * @return array<int, array{id:int|null,name:string,isActive:bool,createdAt:?string,updatedAt:?string}>
     *
     * @throws ExceptionInterface
     */
    public function listTemplates(int $limit = 50, int $offset = 0): array
    {
        if (trim($this->mailjetApiKey) === '' || trim($this->mailjetSecretKey) === '') {
            return [];
        }

        $response = $this->httpClient->request('GET', self::MAILJET_TEMPLATE_ENDPOINT, [
            'auth_basic' => [$this->mailjetApiKey, $this->mailjetSecretKey],
            'query' => [
                'Limit' => max(1, $limit),
                'Offset' => max(0, $offset),
            ],
            'timeout' => 10,
        ]);

        $payload = $response->toArray(false);

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new \RuntimeException(sprintf('Mailjet API returned status code %d.', $response->getStatusCode()));
        }

        $templates = $payload['Data'] ?? [];
        if (!is_array($templates)) {
            return [];
        }

        return array_map(static function (mixed $template): array {
            if (!is_array($template)) {
                return [
                    'id' => null,
                    'name' => '',
                    'isActive' => false,
                    'createdAt' => null,
                    'updatedAt' => null,
                ];
            }

            return [
                'id' => is_numeric($template['ID'] ?? null) ? (int)$template['ID'] : null,
                'name' => is_string($template['Name'] ?? null) ? $template['Name'] : '',
                'isActive' => (bool)($template['IsActive'] ?? false),
                'createdAt' => is_string($template['CreatedAt'] ?? null) ? $template['CreatedAt'] : null,
                'updatedAt' => is_string($template['EditModeUpdatedAt'] ?? null) ? $template['EditModeUpdatedAt'] : null,
            ];
        }, $templates);
    }
}
