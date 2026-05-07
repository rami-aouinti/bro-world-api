<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\CoverLetter;

use App\Recruit\Application\Service\ResumeAiParsingService;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;

#[AsController]
#[OA\Tag(name: 'Recruit Cover AI')]
readonly class AiCoverWritingController
{
    public function __construct(
        private ResumeAiParsingService $resumeAiParsingService,
    ) {
    }

    #[Route(path: '/v1/recruit/cover-pages/about-me/generate', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Génère un texte About Me pour cover page à partir d\'un profil utilisateur ou d\'une description de poste.', security: [])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['text'],
            properties: [
                new OA\Property(
                    property: 'text',
                    description: 'Texte source : soit un profil utilisateur, soit une description de poste.',
                    type: 'string',
                    example: 'Développeur backend PHP avec 5 ans d’expérience sur Symfony, APIs REST, Docker et CI/CD. Passionné par la qualité logicielle et l’optimisation des performances.',
                ),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Texte About Me généré par l’IA.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'textArea',
                    type: 'string',
                    example: 'Backend engineer with 5 years of hands-on experience building robust Symfony APIs, improving performance, and delivering maintainable services in Dockerized environments. I thrive in collaborative teams where clean architecture, testing discipline, and continuous delivery are core values. I enjoy turning complex business needs into reliable backend solutions, with a strong focus on scalability, code quality, and user impact. My approach combines technical rigor, product awareness, and a proactive mindset to help teams ship faster while keeping systems stable and easy to evolve.',
                ),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(
        response: 400,
        description: 'Le champ "text" est manquant ou vide.',
    )]
    public function generateAboutMe(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();
        $inputText = isset($payload['text']) ? trim((string) $payload['text']) : '';

        if ($inputText === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "text" is required and must be a non-empty string.');
        }

        return new JsonResponse([
            'textArea' => $this->resumeAiParsingService->generateAboutMeForCoverPage($inputText),
        ]);
    }

    #[Route(path: '/v1/recruit/cover-letters/generate', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Détecte la company et ses besoins depuis un texte, puis génère un cover letter adapté.', security: [])]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['text'],
            properties: [
                new OA\Property(
                    property: 'text',
                    description: 'Texte source contenant une offre, un contexte entreprise, ou un brief de candidature.',
                    type: 'string',
                    example: 'TechNova recherche un backend engineer pour renforcer sa plateforme SaaS. Besoins: forte maîtrise Symfony, PostgreSQL, architecture microservices, communication cross-team et ownership des sujets de performance.',
                ),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(
        response: 200,
        description: 'Texte de cover letter généré par l’IA.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(
                    property: 'textArea',
                    type: 'string',
                    example: 'I am excited to apply for the Backend Engineer role at TechNova. Your focus on scaling a SaaS platform and improving performance strongly matches my experience building Symfony-based services with PostgreSQL in production environments.\n\nIn my recent roles, I have designed resilient APIs, contributed to microservice-oriented architectures, and improved response times through profiling, query optimization, and caching strategies. I enjoy taking ownership of backend topics end-to-end while collaborating closely with product and frontend teams to deliver reliable features.\n\nI would welcome the opportunity to discuss how I can help TechNova strengthen platform performance and accelerate delivery. Thank you for your time and consideration.',
                ),
            ],
            type: 'object',
        ),
    )]
    #[OA\Response(
        response: 400,
        description: 'Le champ "text" est manquant ou vide.',
    )]
    public function generateCoverLetter(Request $request): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();
        $inputText = isset($payload['text']) ? trim((string) $payload['text']) : '';

        if ($inputText === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "text" is required and must be a non-empty string.');
        }

        return new JsonResponse([
            'textArea' => $this->resumeAiParsingService->generateCoverLetterFromJobText($inputText),
        ]);
    }
}
