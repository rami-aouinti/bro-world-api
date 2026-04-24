<?php

declare(strict_types=1);

namespace App\Notification\Application\Service;

use RuntimeException;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_filter;
use function array_map;
use function array_merge;
use function array_unique;
use function count;
use function is_array;
use function is_numeric;
use function is_string;
use function max;
use function preg_match_all;
use function sort;
use function sprintf;
use function trim;

final readonly class MailjetTemplateService
{
    private const string MAILJET_TEMPLATE_ENDPOINT = 'https://api.mailjet.com/v3/REST/template';
    private const string MAILJET_TEMPLATE_DETAIL_ENDPOINT = 'https://api.mailjet.com/v3/REST/template/%d/detailcontent';
    private const string MAILJET_SEND_ENDPOINT = 'https://api.mailjet.com/v3.1/send';

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $mailjetApiKey,
        private string $mailjetSecretKey,
        private string $mailjetSenderEmail,
        private string $mailjetSenderName,
    ) {
    }

    /**
     * @return array<int, array{id:int|null,name:string,isActive:bool,createdAt:?string,updatedAt:?string,variables:array<int, string>}>
     *
     * @throws ExceptionInterface
     */
    public function listTemplates(int $limit = 50, int $offset = 0): array
    {
        $payload = $this->request('GET', self::MAILJET_TEMPLATE_ENDPOINT, [
            'query' => [
                'Limit' => max(1, $limit),
                'Offset' => max(0, $offset),
            ],
        ]);

        $templates = $payload['Data'] ?? [];
        if (!is_array($templates)) {
            return [];
        }

        return array_map(function (mixed $template): array {
            if (!is_array($template)) {
                return [
                    'id' => null,
                    'name' => '',
                    'isActive' => false,
                    'createdAt' => null,
                    'updatedAt' => null,
                    'variables' => [],
                ];
            }

            $templateId = is_numeric($template['ID'] ?? null) ? (int)$template['ID'] : null;

            return [
                'id' => $templateId,
                'name' => is_string($template['Name'] ?? null) ? $template['Name'] : '',
                'isActive' => (bool)($template['IsActive'] ?? false),
                'createdAt' => is_string($template['CreatedAt'] ?? null) ? $template['CreatedAt'] : null,
                'updatedAt' => is_string($template['EditModeUpdatedAt'] ?? null) ? $template['EditModeUpdatedAt'] : null,
                'variables' => $templateId !== null ? $this->fetchTemplateVariables($templateId) : [],
            ];
        }, $templates);
    }

    /**
     * @return array<int, string>
     *
     * @throws ExceptionInterface
     */
    public function fetchTemplateVariables(int $templateId): array
    {
        $payload = $this->request('GET', sprintf(self::MAILJET_TEMPLATE_DETAIL_ENDPOINT, $templateId));
        $details = $payload['Data'] ?? [];
        if (!is_array($details) || $details === []) {
            return [];
        }

        $detail = is_array($details[0] ?? null) ? $details[0] : [];

        $contentParts = [];
        foreach (['Html-part', 'Text-part', 'Headers'] as $field) {
            $value = $detail[$field] ?? null;
            if (is_string($value) && trim($value) !== '') {
                $contentParts[] = $value;
            }
        }

        return $this->extractVariables(array_merge($contentParts));
    }

    /**
     * @param array<string, mixed> $variables
     *
     * @return array<string, mixed>
     * @throws ExceptionInterface
     */
    public function sendTemplateEmail(int $templateId, string $toEmail, array $variables = []): array
    {
        if (trim($toEmail) === '') {
            throw new RuntimeException('Recipient email is required.');
        }

        return $this->request('POST', self::MAILJET_SEND_ENDPOINT, [
            'json' => [
                'Messages' => [
                    [
                        'From' => [
                            'Email' => $this->mailjetSenderEmail,
                            'Name' => $this->mailjetSenderName,
                        ],
                        'To' => [
                            [
                                'Email' => $toEmail,
                            ],
                        ],
                        'TemplateID' => $templateId,
                        'TemplateLanguage' => true,
                        'Variables' => $variables,
                    ],
                ],
            ],
        ]);
    }

    private function hasCredentials(): bool
    {
        return trim($this->mailjetApiKey) !== '' && trim($this->mailjetSecretKey) !== '';
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     *
     * @throws ExceptionInterface
     */
    private function request(string $method, string $url, array $options = []): array
    {
        if (!$this->hasCredentials()) {
            throw new RuntimeException('Mailjet credentials are not configured.');
        }

        $response = $this->httpClient->request($method, $url, [
            ...$options,
            'auth_basic' => [$this->mailjetApiKey, $this->mailjetSecretKey],
            'timeout' => 15,
        ]);

        $payload = $response->toArray(false);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new RuntimeException(sprintf('Mailjet API returned status code %d.', $response->getStatusCode()));
        }

        return is_array($payload) ? $payload : [];
    }

    /**
     * @param array<int, string> $contents
     * @return array<int, string>
     */
    private function extractVariables(array $contents): array
    {
        $variables = [];
        foreach ($contents as $content) {
            preg_match_all('/\{\{\s*var:([a-zA-Z0-9_\.\-]+)(?:[^}]*)\}\}/', $content, $matchesA);
            preg_match_all('/\[\[\s*var:([a-zA-Z0-9_\.\-]+)(?:[^\]]*)\]\]/', $content, $matchesB);

            $variables = array_merge($variables, $matchesA[1] ?? [], $matchesB[1] ?? []);
        }

        $variables = array_values(array_unique(array_filter($variables, static fn (mixed $value): bool => is_string($value) && trim($value) !== '')));
        sort($variables);

        return $variables;
    }
}
