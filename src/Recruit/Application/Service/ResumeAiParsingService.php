<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_map;
use function is_array;
use function is_string;
use function json_decode;
use function preg_replace;
use function str_starts_with;
use function trim;

readonly class ResumeAiParsingService
{
    private const string AI_URL = 'http://127.0.0.1:11434/api/generate';
    private const string AI_MODEL = 'phi';

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function parsePdf(string $pdfPath): array
    {
        $rawText = $this->extractTextFromPdf($pdfPath);
        if (trim($rawText) === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'No extractable text found in the provided PDF.');
        }

        $prompt = $this->buildPrompt($rawText);

        try {
            $response = $this->httpClient->request('POST', self::AI_URL, [
                'timeout' => 120,
                'json' => [
                    'model' => self::AI_MODEL,
                    'prompt' => $prompt,
                    'stream' => false,
                ],
            ]);
            $data = $response->toArray(false);
        } catch (TransportExceptionInterface $exception) {
            throw new HttpException(Response::HTTP_BAD_GATEWAY, 'Unable to reach local AI service.', $exception);
        }

        $content = trim((string) ($data['response'] ?? ''));
        if ($content === '') {
            throw new HttpException(Response::HTTP_BAD_GATEWAY, 'AI service returned an empty response.');
        }

        return $this->normalizeAiPayload($content);
    }

    private function extractTextFromPdf(string $pdfPath): string
    {
        $process = new Process(['pdftotext', '-layout', $pdfPath, '-']);
        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new HttpException(
                Response::HTTP_BAD_REQUEST,
                'Unable to extract text from the PDF. Ensure pdftotext is installed and the file is readable.',
                new ProcessFailedException($process),
            );
        }

        return (string) $process->getOutput();
    }

    private function buildPrompt(string $rawText): string
    {
        return <<<'PROMPT'
You are a CV parser.
Extract structured information from this resume text and return ONLY valid minified JSON.
Expected JSON schema:
{
  "user": {
    "fullName": "",
    "email": "",
    "phone": "",
    "address": "",
    "links": []
  },
  "experiences": [{"title":"","company":"","startDate":"","endDate":"","description":""}],
  "educations": [{"title":"","school":"","startDate":"","endDate":"","description":""}],
  "skills": ["", ""]
}
Rules:
- Never include markdown.
- Missing values must be empty string, empty array or empty object field.
- Keep arrays always present.
- Keep keys exactly as specified.

Resume text:
PROMPT
            . "\n" . $rawText;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeAiPayload(string $raw): array
    {
        $normalizedRaw = $this->stripCodeFence($raw);

        try {
            /** @var array<string, mixed> $decoded */
            $decoded = json_decode($normalizedRaw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new HttpException(Response::HTTP_BAD_GATEWAY, 'AI service returned invalid JSON.', $exception);
        }

        if (!is_array($decoded)) {
            throw new HttpException(Response::HTTP_BAD_GATEWAY, 'AI service JSON must be an object.');
        }

        $user = $decoded['user'] ?? [];
        $experiences = $decoded['experiences'] ?? [];
        $educations = $decoded['educations'] ?? [];
        $skills = $decoded['skills'] ?? [];

        if (!is_array($user) || !is_array($experiences) || !is_array($educations) || !is_array($skills)) {
            throw new HttpException(Response::HTTP_BAD_GATEWAY, 'AI service JSON does not match expected schema.');
        }

        return [
            'user' => [
                'fullName' => $this->stringValue($user['fullName'] ?? ''),
                'email' => $this->stringValue($user['email'] ?? ''),
                'phone' => $this->stringValue($user['phone'] ?? ''),
                'address' => $this->stringValue($user['address'] ?? ''),
                'links' => $this->stringArray($user['links'] ?? []),
            ],
            'experiences' => $this->normalizeEntries($experiences, ['title', 'company', 'startDate', 'endDate', 'description']),
            'educations' => $this->normalizeEntries($educations, ['title', 'school', 'startDate', 'endDate', 'description']),
            'skills' => $this->stringArray($skills),
        ];
    }

    private function stripCodeFence(string $content): string
    {
        $content = trim($content);

        if (str_starts_with($content, '```')) {
            $content = (string) preg_replace('/^```(?:json)?\s*/', '', $content);
            $content = (string) preg_replace('/\s*```$/', '', $content);
        }

        return trim($content);
    }

    /**
     * @param array<int, mixed> $input
     * @param list<string> $keys
     * @return array<int, array<string, string>>
     */
    private function normalizeEntries(array $input, array $keys): array
    {
        return array_map(function (mixed $item) use ($keys): array {
            $entry = [];
            if (is_array($item)) {
                foreach ($keys as $key) {
                    $entry[$key] = $this->stringValue($item[$key] ?? '');
                }
            }

            if ($entry === []) {
                foreach ($keys as $key) {
                    $entry[$key] = '';
                }
            }

            return $entry;
        }, $input);
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    /**
     * @param mixed $input
     * @return array<int, string>
     */
    private function stringArray(mixed $input): array
    {
        if (!is_array($input)) {
            return [];
        }

        $result = [];
        foreach ($input as $value) {
            $string = $this->stringValue($value);
            if ($string === '') {
                continue;
            }

            $result[] = $string;
        }

        return $result;
    }
}
