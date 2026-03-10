<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Resume;

use App\Recruit\Domain\Entity\Resume;
use App\Recruit\Infrastructure\Repository\ResumeRepository;
use App\User\Domain\Entity\User;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AsController]
#[OA\Tag(name: 'Recruit Resume')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class MyResumeListController
{
    public function __construct(
        private readonly ResumeRepository $resumeRepository
    ) {
    }

    #[Route(path: '/v1/recruit/private/me/resumes', methods: [Request::METHOD_GET])]
    #[OA\Get(summary: 'Retourne les CV du user connecté.')]
    #[OA\Response(
        response: 200,
        description: 'List of resumes for the connected user',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(
                type: 'object',
                properties: [
                    new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                    new OA\Property(property: 'documentUrl', type: 'string', nullable: true),
                    new OA\Property(property: 'experiences', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'educations', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'skills', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'languages', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'certifications', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'projects', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'references', type: 'array', items: new OA\Items(type: 'object')),
                    new OA\Property(property: 'hobbies', type: 'array', items: new OA\Items(type: 'object')),
                ],
            ),
        ),
    )]
    #[OA\Response(response: 401, description: 'Authentication required')]
    public function __invoke(User $loggedInUser): JsonResponse
    {
        $resumes = $this->resumeRepository->findBy([
            'owner' => $loggedInUser,
        ], [
            'createdAt' => 'DESC',
        ]);

        return new JsonResponse(array_map([$this, 'normalizeResume'], $resumes));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeResume(Resume $resume): array
    {
        return [
            'id' => $resume->getId(),
            'documentUrl' => $resume->getDocumentUrl(),
            'experiences' => $this->normalizeSections($resume->getExperiences()->toArray()),
            'educations' => $this->normalizeSections($resume->getEducations()->toArray()),
            'skills' => $this->normalizeSections($resume->getSkills()->toArray()),
            'languages' => $this->normalizeSections($resume->getLanguages()->toArray()),
            'certifications' => $this->normalizeSections($resume->getCertifications()->toArray()),
            'projects' => $this->normalizeSections($resume->getProjects()->toArray()),
            'references' => $this->normalizeSections($resume->getReferences()->toArray()),
            'hobbies' => $this->normalizeSections($resume->getHobbies()->toArray()),
        ];
    }

    /**
     * @param array<int, object> $sections
     *
     * @return array<int, array<string, string>>
     */
    private function normalizeSections(array $sections): array
    {
        return array_map(
            static fn (object $section): array => [
                'id' => $section->getId(),
                'title' => $section->getTitle(),
                'description' => $section->getDescription(),
            ],
            $sections,
        );
    }
}
