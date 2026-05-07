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
use function implode;
use function is_array;
use function is_string;
use function json_decode;
use function json_encode;
use function preg_match;
use function preg_replace;
use function preg_split;
use function str_contains;
use function str_replace;
use function str_starts_with;
use function strlen;
use function substr;
use function trim;

readonly class ResumeAiParsingService
{
    private const string AI_URL = 'http://127.0.0.1:11434/api/generate';
    private const string AI_MODEL = 'gemma:2b';
    private const int MAX_PROMPT_RESUME_LENGTH = 120000;

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @param array<string, mixed> $resumeData
     * @return string
     * @throws JsonException
     */
    public function reviewResume(array $resumeData): string
    {
        $jsonPayload = json_encode($resumeData, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if (!is_string($jsonPayload) || trim($jsonPayload) === '') {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Invalid review payload.');
        }

        return $this->generateTextResponse($this->buildResumeReviewPrompt($jsonPayload));
    }

    /**
     * @return array<string, mixed>
     */
    public function structureResumeFromText(string $resumeText): array
    {
        $cleanedText = $this->cleanResumeText($resumeText);
        $promptInput = $cleanedText !== '' ? $cleanedText : trim($resumeText);
        if ($promptInput === '') {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Field "resumeText" must not be empty.');
        }

        $content = $this->generateTextResponse($this->buildStructuredResumePrompt($promptInput));

        return $this->normalizeAiStructuredResumePayload($content);
    }

    public function generateAboutMeForCoverPage(string $inputText): string
    {
        $normalizedInput = trim($inputText);
        if ($normalizedInput === '') {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Field "text" must not be empty.');
        }

        $response = $this->generateTextResponse($this->buildAboutMePrompt($normalizedInput));

        return trim($this->stripCodeFence($response));
    }

    public function generateCoverLetterFromJobText(string $inputText): string
    {
        $normalizedInput = trim($inputText);
        if ($normalizedInput === '') {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Field "text" must not be empty.');
        }

        $raw = $this->generateTextResponse($this->buildCoverLetterPrompt($normalizedInput));

        return $this->sanitizeCoverLetterOutput($raw, $normalizedInput);
    }

    /**
     * @param array<string, mixed> $resumeData
     * @return array{percentage:int,note:string}
     */
    public function computeOfferResumeMatch(string $offerText, array $resumeData): array
    {
        $normalizedOffer = trim($offerText);
        if ($normalizedOffer === '') {
            throw new HttpException(Response::HTTP_BAD_REQUEST, 'Field "offerText" must not be empty.');
        }

        $analysis = $this->analyzeOfferResumeMatch($normalizedOffer, $resumeData);
        $percentage = $analysis['percentage'];
        $language = $this->detectPrimaryLanguage($normalizedOffer);
        $note = $this->buildProfessionalMatchNote(
            $percentage,
            $analysis['matched'],
            $analysis['missing'],
            $analysis['offerKeywords'],
            $language,
        );

        return [
            'percentage' => $percentage,
            'note' => $note,
        ];
    }

    private function cleanResumeText(string $text): string
    {
        if ($text === '') {
            return '';
        }

        // Fix encoding
        if (str_contains($text, "\x00")) {
            $text = mb_convert_encoding($text, 'UTF-8', 'UTF-16LE');
        }

        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = mb_convert_encoding($text, 'UTF-8', 'auto');
        }

        // Normalize line breaks
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // 🔥 REMOVE WATERMARKS
        $text = preg_replace('/\bLEBENSLAUF\.DE\b/iu', ' ', $text);
        $text = preg_replace('/Vorschau mit Wasserzeichen/iu', ' ', $text);

        // Remove PDF artifacts
        $text = preg_replace('/\/CIDInit\s+\/ProcSet.*?end\s+end/s', ' ', $text);

        // Remove non-readable chars
        $text = preg_replace('/[^\P{C}\n\t]/u', ' ', $text);

        // Remove weird symbols but keep useful ones
        $text = preg_replace('/[^\p{L}\p{N}\s@+&\/.,:;!?()\'"-]/u', ' ', $text);

        // Split lines
        $lines = preg_split('/\n+/', $text);

        if (!is_array($lines)) {
            return '';
        }

        $cleanLines = [];

        foreach ($lines as $line) {
            if (!is_string($line)) {
                continue;
            }

            $line = trim($line);

            // Skip empty or too short
            if ($line === '' || strlen($line) < 3) {
                continue;
            }

            // 🔥 Remove watermark-heavy lines
            if (substr_count($line, 'LEBENSLAUF') > 2) {
                continue;
            }

            // Remove useless lines (no letters)
            if (preg_match('/[a-zA-Z]/', $line) !== 1) {
                continue;
            }

            // Remove repeated tokens spam
            if (preg_match('/\b(\w+)( \1\b){3,}/i', $line)) {
                continue;
            }

            // Remove long garbage tokens
            if (preg_match('/\b[a-zA-Z0-9]{30,}\b/', $line)) {
                continue;
            }

            $cleanLines[] = $line;
        }

        $text = implode("\n", $cleanLines);

        // 🔥 FINAL CLEANING

        // Remove duplicated words
        $text = preg_replace('/\b(\w+)( \1\b)+/i', '$1', $text);

        // Fix spacing
        $text = preg_replace('/\s{2,}/', ' ', $text);
        $text = preg_replace('/\n{2,}/', "\n", $text);

        // Trim
        $text = trim($text);

        // Limit size for AI
        if (strlen($text) > self::MAX_PROMPT_RESUME_LENGTH) {
            $text = substr($text, 0, self::MAX_PROMPT_RESUME_LENGTH);
        }

        return $text;
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

        $content = $this->generateTextResponse($prompt);

        return $this->normalizeAiPayload($content);
    }

    private function extractTextFromPdf(string $pdfPath): string
    {
        try {
            $process = new Process([
                'pdftotext',
                '-layout',
                '-nopgbrk',
                '-f', '1',
                '-l', '999',
                $pdfPath,
                '-'
            ]);

            $process->setTimeout(120);
            $process->run();
        } catch (ProcessExceptionInterface) {
            $text = '';
        }

        $text = $process->isSuccessful() ? $process->getOutput() : '';

        // 2. 👉 Fallback OCR si vide
        if (trim($text) === '') {
            try {
                $ocrProcess = new Process([
                    'tesseract',
                    $pdfPath,
                    'stdout',
                    '-l', 'eng'
                ]);

                $ocrProcess->setTimeout(120);
                $ocrProcess->run();

                if ($ocrProcess->isSuccessful()) {
                    $text = $ocrProcess->getOutput();
                }
            } catch (ProcessExceptionInterface) {
                return '';
            }
        }

        return trim((string) $this->cleanResumeText($text));
    }

    private function buildPrompt(string $rawText): string
    {
        return <<<'PROMPT'
You are a strict CV extraction engine.

You MUST extract ALL information from the resume.

Return ONLY valid JSON.
No markdown.
No explanations.

RULES (VERY IMPORTANT):
- Do NOT guess missing data.
- If information exists in the text, you MUST extract it.
- If a section exists (experience (or BERUFSERFAHRUNG), education (or AUSBILDUNG), ), skill (or Kenntnisse), ),  language (or Fremdsprachen), ), hobby (or Hobbys), ), NEVER return empty array unless truly absent.
- Do NOT summarize. Extract exactly.
- Keep full details from each job/education entry.

OUTPUT FORMAT:
Return ONLY valid minified JSON:

{
  "user": {
    "fullName": "",
    "email": "",
    "phone": "",
    "address": "",
    "links": []
  },
  "experiences": [
    {
      "title": "",
      "company": "",
      "startDate": "",
      "endDate": "",
      "description": ""
    },
    {
      "title": "",
      "company": "",
      "startDate": "",
      "endDate": "",
      "description": ""
    },
  ],
  "educations": [
    {
      "title": "",
      "school": "",
      "startDate": "",
      "endDate": "",
      "description": ""
    },
    {
      "title": "",
      "school": "",
      "startDate": "",
      "endDate": "",
      "description": ""
    },
  ],
  "skills": [],
  "languages": [],
  "hobbies": []
}

EXTRA RULES:
- Extract ALL experiences (not just one)
- Extract ALL education entries
- Extract ALL skills mentioned anywhere
- Extract ALL languages mentioned anywhere
- Extract ALL hobbies mentioned anywhere
- Keep original wording from CV

Resume:
PROMPT
            . "\n" . $rawText;
    }

    private function buildResumeReviewPrompt(string $resumeJson): string
    {
        return <<<'PROMPT'

Return ONLY valid JSON.
If impossible, return {}.
No explanation.
No markdown.

You are a senior recruiter and CV reviewer.
Analyse the provided resume payload and answer ONLY as plain text (no markdown).
Your answer must include:
1) Overall quality verdict (good / needs improvement).
2) Major issues found (if any).
3) Concrete improvements section with bullet-style lines starting by "- ".
Keep answer concise and actionable.

Resume payload:
PROMPT
            . "\n" . $resumeJson;
    }

    private function buildStructuredResumePrompt(string $rawText): string
    {
        return <<<'PROMPT'
You are a CV parser.

Return ONLY valid JSON.
If impossible, return {}.
No explanation.
No markdown.

Read the resume text and return ONLY valid minified JSON with this exact schema:
{
  "user": {
    "fullName": "",
    "email": "",
    "phone": "",
    "address": "",
    "summary": "",
    "links": []
  },
  "experiences": [{"title":"","company":"","startDate":"","endDate":"","description":""}],
  "educations": [{"title":"","school":"","startDate":"","endDate":"","description":""}],
  "skills": ["", ""],
  "languages": [{"name":"","level":""}],
  "certifications": [{"title":"","issuer":"","date":"","description":""}],
  "projects": [{"title":"","description":"","link":""}],
  "references": [{"name":"","contact":"","description":""}],
  "hobbies": ["", ""]
}
Rules:
- Never include markdown.
- Always include every key.
- Missing values must stay empty strings/arrays.
- Keep output strictly as JSON object.

Resume text:
PROMPT
            . "\n" . $rawText;
    }

    private function buildAboutMePrompt(string $inputText): string
    {
        return <<<'PROMPT'
You are an expert career writer.

Generate ONLY one polished "About Me" paragraph for a cover page.
Return plain text only (no markdown, no title, no JSON, no bullet points).

Rules:
- Input can be either a user profile or a job description.
- If it is a job description, infer the ideal candidate voice and adapt it.
- Keep it concise (90 to 140 words).
- Professional, confident, concrete, and human tone.
- Avoid placeholders and avoid hallucinated facts that are not implied by the input.
- Write the output in the SAME language as the input text.

Input text:
PROMPT
            . "\n" . $inputText;
    }

    private function buildCoverLetterPrompt(string $inputText): string
    {
        return <<<'PROMPT'
You are an expert recruiter and cover letter writer.

Task:
Write ONLY the final cover letter text.

Output format:
- Return plain text only.
- No markdown, no JSON, no labels, no headings.
- Never output section titles like "Company Context", "Cover Letter", "Analysis", etc.
- 2 short paragraphs max.
- 60 to 120 words.

Writing rules:
- Mention the company naturally when possible.
- Explicitly connect candidate strengths to inferred company needs.
- Keep a professional and persuasive tone.
- End with a short call to action.
- Do not invent precise facts that are not supported by input.
- Never use placeholders like [Hiring Manager], [Your Name], <name>, etc.
- Do not include greeting ("Dear ...") and do not include signature.
- Output must start directly with the first sentence of the letter.
- Write strictly from the candidate perspective with first person ("I", "my").
- Never write as the company ("we are looking", "TechNova is seeking", etc.).
- Include a final sentence thanking the reviewer.
- Write the output in the SAME language as the input text.

Input text:
PROMPT
            . "\n" . $inputText;
    }

    /**
     * @param array<string, mixed> $resumeData
     * @return array{percentage:int, matched:array<int,string>, missing:array<int,string>, offerKeywords:array<int,string>}
     */
    private function analyzeOfferResumeMatch(string $offerText, array $resumeData): array
    {
        $offerKeywords = $this->extractKeywords($offerText);
        if ($offerKeywords === []) {
            return ['percentage' => 0, 'matched' => [], 'missing' => [], 'offerKeywords' => []];
        }

        $resumeText = json_encode($resumeData, JSON_UNESCAPED_UNICODE);
        $resumeKeywords = $this->extractKeywords(is_string($resumeText) ? $resumeText : '');
        if ($resumeKeywords === []) {
            return ['percentage' => 0, 'matched' => [], 'missing' => $offerKeywords, 'offerKeywords' => $offerKeywords];
        }

        $matchedCount = 0;
        $matchedKeywords = [];
        $missingKeywords = [];
        foreach ($offerKeywords as $keyword) {
            if (in_array($keyword, $resumeKeywords, true)) {
                $matchedCount++;
                $matchedKeywords[] = $keyword;
            } else {
                $missingKeywords[] = $keyword;
            }
        }

        $ratio = $matchedCount / count($offerKeywords);
        $percentage = (int) round($ratio * 100);

        if ($percentage < 0) {
            $percentage = 0;
        }
        if ($percentage > 100) {
            $percentage = 100;
        }

        return [
            'percentage' => $percentage,
            'matched' => $matchedKeywords,
            'missing' => $missingKeywords,
            'offerKeywords' => $offerKeywords,
        ];
    }

    /**
     * @param array<int, string> $matched
     * @param array<int, string> $missing
     * @param array<int, string> $offerKeywords
     */
    private function buildProfessionalMatchNote(int $percentage, array $matched, array $missing, array $offerKeywords, string $language): string
    {
        $matchPreview = $matched === [] ? '-' : implode(', ', array_slice($matched, 0, 10));
        $missingPreview = $missing === [] ? '-' : implode(', ', array_slice($missing, 0, 10));

        if ($language === 'de') {
            $level = $percentage >= 75 ? 'hoch' : ($percentage >= 45 ? 'mittel' : 'niedrig');

            return 'Gesamtübereinstimmung: ' . $percentage . "% (Niveau: $level).\n\n"
                . 'Abgedeckte Anforderungen: ' . ($matchPreview === '-' ? 'Keine klaren Schlüsselanforderungen wurden im Lebenslauf gefunden.' : $matchPreview) . ".\n"
                . 'Fehlende oder schwache Punkte: ' . ($missingPreview === '-' ? 'Keine wesentlichen Lücken in den Hauptanforderungen erkannt.' : $missingPreview) . ".\n\n"
                . 'Erläuterung: Die Bewertung berücksichtigt die Übereinstimmung zwischen Stellenanforderungen und Lebenslaufinhalten (Erfahrung, Ausbildung, Skills, Projekte, Sprachen). '
                . 'Je mehr zentrale Anforderungen klar belegt sind, desto höher ist der Score. ';
        }

        if ($language === 'en') {
            $level = $percentage >= 75 ? 'high' : ($percentage >= 45 ? 'medium' : 'low');

            return 'Overall match: ' . $percentage . "% ($level fit).\n\n"
                . 'Aligned requirements: ' . ($matchPreview === '-' ? 'No clear key requirement from the offer was found in the resume.' : $matchPreview) . ".\n"
                . 'Missing or weak points: ' . ($missingPreview === '-' ? 'No major gap detected on primary requirements.' : $missingPreview) . ".\n\n"
                . 'Explanation: This score compares the offer requirements with resume evidence (experience, education, skills, projects, languages). '
                . 'The more core requirements are clearly supported, the higher the percentage. ';
        }

        $level = $percentage >= 75 ? 'élevée' : ($percentage >= 45 ? 'moyenne' : 'faible');

        return 'Correspondance globale: ' . $percentage . "% (adéquation $level).\n\n"
            . 'Points alignés: ' . ($matchPreview === '-' ? 'Aucune exigence-clé de l’offre n’a été trouvée clairement dans le CV.' : $matchPreview) . ".\n"
            . 'Points manquants ou faibles: ' . ($missingPreview === '-' ? 'Aucun écart majeur détecté sur les exigences principales.' : $missingPreview) . ".\n\n"
            . 'Explication: ce score compare les exigences de l’offre avec les preuves du CV (expériences, formations, compétences, projets, langues). '
            . 'Plus les exigences clés sont démontrées, plus le pourcentage augmente.';
    }

    private function detectPrimaryLanguage(string $text): string
    {
        $lower = mb_strtolower($text);
        if (preg_match('/\b(und|mit|entwicklung|anforderungen|wartbarkeit|weiterentwicklung)\b/u', $lower) === 1) {
            return 'de';
        }
        if (preg_match('/\b(and|with|development|requirements|maintainability|experience)\b/u', $lower) === 1) {
            return 'en';
        }

        return 'fr';
    }

    /**
     * @return array<int, string>
     */
    private function extractKeywords(string $text): array
    {
        $normalized = mb_strtolower($text);
        $normalized = (string) preg_replace('/[^\\p{L}\\p{N}\\s]/u', ' ', $normalized);
        $parts = preg_split('/\\s+/', $normalized) ?: [];

        $stop = ['le','la','les','de','des','du','un','une','et','ou','pour','avec','dans','sur','the','a','an','to','of','in','is','are','recherche'];
        $keywords = [];
        foreach ($parts as $part) {
            $token = trim((string) $part);
            if (mb_strlen($token) < 3 || in_array($token, $stop, true)) {
                continue;
            }

            $keywords[] = $token;
        }

        return array_values(array_unique($keywords));
    }

    private function sanitizeCoverLetterOutput(string $content, string $inputText): string
    {
        $clean = trim($content);
        $clean = $this->stripCodeFence($clean);

        // Remove common markdown headings and labels sometimes produced by small models
        $clean = (string) preg_replace('/^#{1,6}\s.*$/m', '', $clean);
        $clean = (string) preg_replace('/^\s*(Company Context|Cover Letter(Text)?|Analysis)\s*:?\s*$/mi', '', $clean);
        $clean = (string) preg_replace('/^\s*(Dear\s+\[[^\]]+\].*)$/mi', '', $clean);
        $clean = (string) preg_replace('/\[(Hiring Manager|Your Name|Name)\]/i', '', $clean);
        $clean = (string) preg_replace('/^\s*(Sincerely|Best regards|Regards)\s*,?\s*$/mi', '', $clean);
        $clean = (string) preg_replace('/\b(we are looking|is seeking|we seek|we are hiring)\b/i', '', $clean);

        // Collapse excessive blank lines
        $clean = (string) preg_replace('/\n{3,}/', "\n\n", $clean);
        $clean = trim($clean);

        // If output is still not in candidate voice, force a ready-to-use candidate letter
        if (
            $clean === ''
            || str_contains($clean, '##')
            || preg_match('/\b(is seeking|we are hiring|our team)\b/i', $clean) === 1
            || preg_match('/\bI\b/i', $clean) !== 1
        ) {
            $company = $this->extractCompanyName($inputText);
            $language = $this->detectPrimaryLanguage($inputText);
            $clean = $this->buildCoverLetterFallback($language, $company);
        }

        return $clean;
    }

    private function extractCompanyName(string $inputText): string
    {
        if (preg_match('/^\s*([A-Za-z0-9][A-Za-z0-9 .&\\-]{1,50})\s+(recherche|cherche|hiring|is looking|is seeking)\b/ui', $inputText, $matches) === 1) {
            return trim((string) ($matches[1] ?? 'your company'));
        }

        return 'your company';
    }

    private function buildCoverLetterFallback(string $language, string $company): string
    {
        if ($language === 'de') {
            return 'Ich freue mich sehr, mich für die Position bei ' . $company . ' zu bewerben. Ich bringe fundierte Erfahrung in der Entwicklung zuverlässiger Backend-Services, sauberer Architektur und teamübergreifender Zusammenarbeit mit, um skalierbare und wartbare Lösungen mit messbarem Mehrwert zu liefern.'
                . "\n\n"
                . 'Vielen Dank für die Prüfung meiner Bewerbung. Gerne erläutere ich in einem Gespräch, wie ich die Ziele von ' . $company . ' unterstützen kann.';
        }

        if ($language === 'fr') {
            return 'Je suis très motivé(e) à l’idée de rejoindre ' . $company . '. Mon expérience en développement backend, en architecture logicielle et en collaboration transverse me permet de livrer des solutions robustes, maintenables et orientées impact.'
                . "\n\n"
                . 'Merci pour l’attention portée à ma candidature. Je serais ravi(e) d’échanger sur la manière dont je peux contribuer aux objectifs de ' . $company . '.';
        }

        return 'I am excited to apply for the position at ' . $company . '. My experience includes building reliable backend services, designing maintainable architectures, and collaborating across teams to deliver scalable solutions with measurable impact.'
            . "\n\n"
            . 'Thank you for reviewing my application. I would be glad to discuss how I can support ' . $company . '\'s goals.';
    }

    private function generateTextResponse(string $prompt): string
    {
        try {
            $response = $this->httpClient->request('POST', self::AI_URL, [
                'timeout' => 300,
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

        return $content;
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

    /**
     * @return array<string, mixed>
     */
    private function normalizeAiStructuredResumePayload(string $raw): array
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

        return [
            'user' => [
                'fullName' => $this->stringValue(is_array($user) ? ($user['fullName'] ?? '') : ''),
                'email' => $this->stringValue(is_array($user) ? ($user['email'] ?? '') : ''),
                'phone' => $this->stringValue(is_array($user) ? ($user['phone'] ?? '') : ''),
                'address' => $this->stringValue(is_array($user) ? ($user['address'] ?? '') : ''),
                'summary' => $this->stringValue(is_array($user) ? ($user['summary'] ?? '') : ''),
                'links' => $this->stringArray(is_array($user) ? ($user['links'] ?? []) : []),
            ],
            'experiences' => $this->normalizeEntries(is_array($decoded['experiences'] ?? null) ? $decoded['experiences'] : [], ['title', 'company', 'startDate', 'endDate', 'description']),
            'educations' => $this->normalizeEntries(is_array($decoded['educations'] ?? null) ? $decoded['educations'] : [], ['title', 'school', 'startDate', 'endDate', 'description']),
            'skills' => $this->stringArray($decoded['skills'] ?? []),
            'languages' => $this->normalizeEntries(is_array($decoded['languages'] ?? null) ? $decoded['languages'] : [], ['name', 'level']),
            'certifications' => $this->normalizeEntries(is_array($decoded['certifications'] ?? null) ? $decoded['certifications'] : [], ['title', 'issuer', 'date', 'description']),
            'projects' => $this->normalizeEntries(is_array($decoded['projects'] ?? null) ? $decoded['projects'] : [], ['title', 'description', 'link']),
            'references' => $this->normalizeEntries(is_array($decoded['references'] ?? null) ? $decoded['references'] : [], ['name', 'contact', 'description']),
            'hobbies' => $this->stringArray($decoded['hobbies'] ?? []),
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
