<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessExceptionInterface;
use Symfony\Component\Process\Process;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function array_map;
use function count;
use function file_get_contents;
use function gzuncompress;
use function gzinflate;
use function implode;
use function is_array;
use function is_string;
use function json_decode;
use function preg_match;
use function preg_match_all;
use function preg_replace;
use function preg_split;
use function preg_replace_callback;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strtolower;
use function strlen;
use function substr;
use function trim;

readonly class ResumeAiParsingService
{
    private const string AI_URL = 'http://127.0.0.1:11434/api/generate';
    private const string AI_MODEL = 'phi';
    private const int MAX_PROMPT_RESUME_LENGTH = 12000;

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    private function cleanResumeText(string $text): string
    {
        if (str_contains($text, "\x00")) {
            $text = $this->decodePotentialUtf16Text($text);
        }

        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }

        $text = preg_replace('/[^\P{C}\n\r\t]/u', '', $text);
        if (!is_string($text)) {
            return '';
        }

        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $text = preg_replace('/\/CIDInit\s+\/ProcSet.*?end\s+end/s', ' ', $text);
        if (!is_string($text)) {
            return '';
        }

        $text = preg_replace('/(?:\bLEBENSLAUF\.DE\b\s*){3,}/ui', ' ', $text);
        if (!is_string($text)) {
            return '';
        }

        $text = preg_replace('/(?:LEBENSLAUF\.DE){2,}/ui', ' ', $text);
        if (!is_string($text)) {
            return '';
        }

        $text = preg_replace('/Vorschau\s+mit\s+Wasserzeichen/ui', ' ', $text);
        if (!is_string($text)) {
            return '';
        }

        $text = preg_replace('/[^\p{Latin}\p{N}\p{Z}\p{P}\p{Sc}\p{Sm}\p{Sk}]/u', ' ', $text);
        if (!is_string($text)) {
            return '';
        }

        $text = preg_replace('/\s+/', ' ', $text);
        if (!is_string($text)) {
            return '';
        }

        $text = str_replace('. ', ".\n", $text);
        $lines = preg_split('/\n+/', $text);
        if (!is_array($lines)) {
            return '';
        }

        $filtered = [];
        $previousLine = '';
        foreach ($lines as $line) {
            if (!is_string($line)) {
                continue;
            }

            $line = trim($line);
            if ($line === '' || strlen($line) < 3) {
                continue;
            }

            if (preg_match('/[\p{L}\p{N}]/u', $line) !== 1) {
                continue;
            }

            if (!$this->hasSufficientLatinContent($line)) {
                continue;
            }

            if ($this->isMostlyRepeatedTokenLine($line)) {
                continue;
            }

            if ($line === $previousLine) {
                continue;
            }

            $filtered[] = $line;
            $previousLine = $line;
        }

        $cleaned = trim(implode("\n", $filtered));
        if ($cleaned === '') {
            return '';
        }

        if (strlen($cleaned) > self::MAX_PROMPT_RESUME_LENGTH) {
            return trim(substr($cleaned, 0, self::MAX_PROMPT_RESUME_LENGTH));
        }

        return $cleaned;
    }

    private function decodePotentialUtf16Text(string $text): string
    {
        $utf16Le = @mb_convert_encoding($text, 'UTF-8', 'UTF-16LE');
        $utf16Be = @mb_convert_encoding($text, 'UTF-8', 'UTF-16BE');

        $utf16LeScore = $this->scoreReadableText(is_string($utf16Le) ? $utf16Le : '');
        $utf16BeScore = $this->scoreReadableText(is_string($utf16Be) ? $utf16Be : '');

        if ($utf16LeScore === 0 && $utf16BeScore === 0) {
            return $text;
        }

        return $utf16LeScore >= $utf16BeScore ? (string) $utf16Le : (string) $utf16Be;
    }

    private function scoreReadableText(string $text): int
    {
        if ($text === '') {
            return 0;
        }

        return preg_match_all('/[\p{L}\p{N}\s.,;:!?@\-]/u', $text) ?: 0;
    }

    private function hasSufficientLatinContent(string $line): bool
    {
        $totalLetters = preg_match_all('/\p{L}/u', $line);
        if (!is_int($totalLetters) || $totalLetters === 0) {
            return true;
        }

        $latinLetters = preg_match_all('/\p{Latin}/u', $line);
        if (!is_int($latinLetters) || $latinLetters === 0) {
            return false;
        }

        return ($latinLetters / $totalLetters) >= 0.7;
    }

    private function isMostlyRepeatedTokenLine(string $line): bool
    {
        $tokens = preg_split('/\s+/', strtolower($line));
        if (!is_array($tokens) || count($tokens) < 8) {
            return false;
        }

        $firstToken = (string) preg_replace('/^[\p{P}\s]+|[\p{P}\s]+$/u', '', (string) ($tokens[0] ?? ''));
        if ($firstToken === '') {
            return false;
        }

        foreach ($tokens as $token) {
            if (!is_string($token)) {
                return false;
            }

            $normalized = (string) preg_replace('/^[\p{P}\s]+|[\p{P}\s]+$/u', '', $token);
            if ($normalized === '') {
                continue;
            }

            if (!str_contains($normalized, $firstToken) && !str_contains($firstToken, $normalized)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $pdfPath
     * @return array<string, mixed>
     * @throws ClientExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ServerExceptionInterface
     */
    public function parsePdf(string $pdfPath): array
    {
        $rawText = $this->extractTextFromPdf($pdfPath);
        if (trim($rawText) === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'No extractable text found in the provided PDF.');
        }

        $cleanedText = $this->cleanResumeText($rawText);
        $prompt = $this->buildPrompt($cleanedText !== '' ? $cleanedText : $rawText);

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
            throw new HttpException(
                Response::HTTP_BAD_GATEWAY,
                'Unable to reach local AI service. Please verify that the local model server is running and reachable.',
                $exception,
            );
        }

        $content = trim((string) ($data['response'] ?? ''));
        if ($content === '') {
            throw new HttpException(Response::HTTP_BAD_GATEWAY, 'AI service returned an empty response.');
        }

        return $this->normalizeAiPayload($content);
    }

    private function extractTextFromPdf(string $pdfPath): string
    {
        $textFromPdftotext = $this->extractTextWithPdftotext($pdfPath);
        if (trim($textFromPdftotext) !== '') {
            return $textFromPdftotext;
        }

        $textFromPdfStreams = $this->extractTextFromPdfStreams($pdfPath);
        if (trim($textFromPdfStreams) !== '') {
            return $textFromPdfStreams;
        }

        throw new HttpException(
            Response::HTTP_UNPROCESSABLE_ENTITY,
            'Unable to extract text from the PDF. The file may be image-based (scanned) or encrypted.',
        );
    }

    private function extractTextWithPdftotext(string $pdfPath): string
    {
        try {
            $process = new Process(['pdftotext', '-layout', $pdfPath, '-']);
            $process->setTimeout(60);
            $process->run();
        } catch (ProcessExceptionInterface) {
            return '';
        }

        if (!$process->isSuccessful()) {
            return '';
        }

        return (string) $process->getOutput();
    }

    private function extractTextFromPdfStreams(string $pdfPath): string
    {
        $content = file_get_contents($pdfPath);
        if (!is_string($content) || $content === '') {
            return '';
        }

        preg_match_all('/stream\\r?\\n(.*?)\\r?\\nendstream/s', $content, $matches);
        $streams = $matches[1] ?? [];
        if (!is_array($streams) || count($streams) === 0) {
            return '';
        }

        $chunks = [];
        foreach ($streams as $stream) {
            if (!is_string($stream) || $stream === '') {
                continue;
            }

            $decoded = $this->decodePdfStream($stream);
            if ($decoded === '') {
                continue;
            }

            $chunk = $this->extractTextOperators($decoded);
            if ($chunk !== '') {
                $chunks[] = $chunk;
            }
        }

        return trim(implode("\n", $chunks));
    }

    private function decodePdfStream(string $stream): string
    {
        $decoded = @gzuncompress($stream);
        if (!is_string($decoded) || $decoded === '') {
            $decoded = @gzinflate($stream);
        }

        if (is_string($decoded) && $decoded !== '') {
            return $decoded;
        }

        if (preg_match('/[\\x20-\\x7E]{10,}/', $stream) !== 1) {
            return '';
        }

        return $stream;
    }

    private function extractTextOperators(string $content): string
    {
        $content = preg_replace_callback(
            '/\\((?:\\\\.|[^\\\\)])*\\)\\s*TJ/s',
            static fn(array $m): string => preg_replace('/\\s+/', ' ', (string) ($m[0] ?? '')) ?? '',
            $content,
        ) ?? $content;

        preg_match_all('/\\((?:\\\\.|[^\\\\)])*\\)\\s*Tj/s', $content, $matches);
        $segments = $matches[0] ?? [];

        $lines = [];
        foreach ($segments as $segment) {
            if (!is_string($segment)) {
                continue;
            }

            if (preg_match('/\\((.*)\\)\\s*Tj/s', $segment, $group) !== 1) {
                continue;
            }

            $text = (string) ($group[1] ?? '');
            $text = preg_replace('/\\\\([\\\\()])/', '$1', $text) ?? $text;
            $text = preg_replace('/\\\\[rntbf]/', ' ', $text) ?? $text;
            $text = trim($text);
            if ($text !== '') {
                $lines[] = $text;
            }
        }

        if ($lines === []) {
            $raw = preg_replace('/[^\\PC\\s]+/u', ' ', $content) ?? '';
            $raw = trim($raw);
            if ($raw === '') {
                return '';
            }

            $parts = preg_split('/\\s{2,}|\\R/', $raw);
            if (!is_array($parts)) {
                return '';
            }

            $parts = array_filter($parts, static fn(mixed $part): bool => is_string($part) && trim($part) !== '');
            return trim(implode("\n", $parts));
        }

        return implode("\n", $lines);
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
