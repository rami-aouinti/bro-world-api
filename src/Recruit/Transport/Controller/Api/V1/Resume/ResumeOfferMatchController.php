<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Resume;

use App\Recruit\Application\Service\ResumeAiParsingService;
use App\Recruit\Domain\Entity\Education;
use App\Recruit\Domain\Entity\Experience;
use App\Recruit\Domain\Entity\Resume;
use App\Recruit\Domain\Entity\Skill;
use App\Recruit\Infrastructure\Repository\ResumeRepository;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit Resume')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
readonly class ResumeOfferMatchController
{
    public function __construct(
        private ResumeRepository $resumeRepository,
        private ResumeAiParsingService $resumeAiParsingService,
    ) {
    }

    #[Route(path: '/v1/recruit/private/me/resumes/match-offer', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Calcule la correspondance (%) entre une offre et le CV actif du user connecté.')]
    #[OA\RequestBody(
        required: true,
        content: [
            new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    required: ['offerText'],
                    properties: [
                        new OA\Property(property: 'offerText', type: 'string', example: 'TechNova recherche un backend engineer Symfony avec PostgreSQL et microservices.'),
                    ],
                    type: 'object',
                ),
            ),
            new OA\MediaType(
                mediaType: 'application/x-www-form-urlencoded',
                schema: new OA\Schema(
                    required: ['offerText'],
                    properties: [
                        new OA\Property(property: 'offerText', type: 'string', example: 'Entwicklung und Weiterentwicklung von modernen, skalierbaren Backend-Services in TypeScript ...'),
                    ],
                    type: 'object',
                ),
            ),
        ],
    )]
    #[OA\Response(
        response: 200,
        description: 'Score de matching et explication détaillée.',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'percentage', type: 'integer', example: 82),
                new OA\Property(property: 'note', type: 'string', example: 'Le profil correspond fortement sur Symfony/PostgreSQL et l’expérience SaaS. Quelques écarts subsistent sur la profondeur microservices.'),
            ],
            type: 'object',
        ),
    )]
    public function __invoke(Request $request, User $loggedInUser): JsonResponse
    {
        $offerText = trim((string) $request->request->get('offerText', ''));
        if ($offerText === '') {
            /** @var array<string, mixed> $payload */
            $payload = $request->toArray();
            $offerText = trim((string) ($payload['offerText'] ?? ''));
        }
        if ($offerText === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "offerText" is required and must be non-empty.');
        }

        $resume = $this->resumeRepository->findOneBy(['owner' => $loggedInUser, 'isActive' => true]);
        if (!$resume instanceof Resume) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'No active resume found for this user.');
        }

        $result = $this->resumeAiParsingService->computeOfferResumeMatch($offerText, $this->normalizeResume($resume));

        return new JsonResponse($result);
    }

    /** @return array<string, mixed> */
    private function normalizeResume(Resume $resume): array
    {
        return [
            'title' => $resume->getInformationTitle(),
            'profile' => $resume->getInformationProfileText(),
            'experiences' => array_map(static fn (Experience $experience): array => [
                'title' => $experience->getTitle(),
                'company' => $experience->getCompany(),
                'description' => $experience->getDescription(),
            ], $resume->getExperiences()->toArray()),
            'educations' => array_map(static fn (Education $education): array => [
                'title' => $education->getTitle(),
                'school' => $education->getSchool(),
                'description' => $education->getDescription(),
            ], $resume->getEducations()->toArray()),
            'skills' => array_map(static fn (Skill $skill): array => [
                'title' => $skill->getTitle(),
                'description' => $skill->getDescription(),
                'level' => $skill->getLevel(),
            ], $resume->getSkills()->toArray()),
        ];
    }
}
