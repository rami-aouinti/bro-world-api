<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Resume;

use App\Recruit\Domain\Entity\Certification;
use App\Recruit\Domain\Entity\Education;
use App\Recruit\Domain\Entity\Experience;
use App\Recruit\Domain\Entity\Hobby;
use App\Recruit\Domain\Entity\Language;
use App\Recruit\Domain\Entity\Project;
use App\Recruit\Domain\Entity\Reference;
use App\Recruit\Domain\Entity\Resume;
use App\Recruit\Domain\Entity\Skill;
use App\Recruit\Infrastructure\Repository\ResumeRepository;
use App\User\Domain\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function array_key_exists;
use function is_array;
use function is_string;
use function trim;

#[AsController]
#[OA\Tag(name: 'Recruit Resume')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class MyResumePatchController
{
    public function __construct(
        private readonly ResumeRepository $resumeRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route(path: '/v1/recruit/private/me/resumes/{resumeId}', methods: [Request::METHOD_PATCH])]
    #[OA\Patch(
        summary: 'Met à jour un CV appartenant au user connecté.',
        parameters: [
            new OA\Parameter(
                name: 'resumeId',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'string', format: 'uuid', example: '550e8400-e29b-41d4-a716-446655440000'),
            ),
        ],
        requestBody: new OA\RequestBody(
            required: true,
            description: 'Payload partiel pour remplacer une ou plusieurs sections du CV.',
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'experiences',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'title', type: 'string', example: 'Senior Backend Developer'),
                                new OA\Property(property: 'description', type: 'string', example: 'Conception de microservices Symfony et mentoring équipe.'),
                            ],
                        ),
                    ),
                    new OA\Property(
                        property: 'educations',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'title', type: 'string', example: 'Master Informatique'),
                                new OA\Property(property: 'description', type: 'string', example: 'Université de Lille, spécialité génie logiciel.'),
                            ],
                        ),
                    ),
                    new OA\Property(
                        property: 'skills',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'title', type: 'string', example: 'PHP 8.3'),
                                new OA\Property(property: 'description', type: 'string', example: 'Architecture hexagonale, performance et tests.'),
                            ],
                        ),
                    ),
                    new OA\Property(
                        property: 'languages',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'title', type: 'string', example: 'Anglais'),
                                new OA\Property(property: 'description', type: 'string', example: 'Niveau C1 professionnel.'),
                            ],
                        ),
                    ),
                    new OA\Property(
                        property: 'certifications',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'title', type: 'string', example: 'AWS Certified Developer - Associate'),
                                new OA\Property(property: 'description', type: 'string', example: 'Certification obtenue en 2025.'),
                            ],
                        ),
                    ),
                    new OA\Property(
                        property: 'projects',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'title', type: 'string', example: 'Plateforme de recrutement B2B'),
                                new OA\Property(property: 'description', type: 'string', example: 'Développement API REST et pipelines CI/CD.'),
                            ],
                        ),
                    ),
                    new OA\Property(
                        property: 'references',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'title', type: 'string', example: 'Jean Dupont - CTO'),
                                new OA\Property(property: 'description', type: 'string', example: 'Disponible sur demande.'),
                            ],
                        ),
                    ),
                    new OA\Property(
                        property: 'hobbies',
                        type: 'array',
                        items: new OA\Items(
                            type: 'object',
                            properties: [
                                new OA\Property(property: 'title', type: 'string', example: 'Trail running'),
                                new OA\Property(property: 'description', type: 'string', example: 'Participation à des courses régionales.'),
                            ],
                        ),
                    ),
                    new OA\Property(property: 'documentUrl', type: 'string', nullable: true, example: 'https://cdn.example.com/cv/jane-doe.pdf'),
                ],
                example: [
                    'experiences' => [
                        [
                            'title' => 'Senior Backend Developer',
                            'description' => 'Conception de microservices Symfony et mentoring équipe.',
                        ],
                    ],
                    'educations' => [
                        [
                            'title' => 'Master Informatique',
                            'description' => 'Université de Lille, spécialité génie logiciel.',
                        ],
                    ],
                    'skills' => [
                        [
                            'title' => 'PHP 8.3',
                            'description' => 'Architecture hexagonale, performance et tests.',
                        ],
                    ],
                    'languages' => [
                        [
                            'title' => 'Anglais',
                            'description' => 'Niveau C1 professionnel.',
                        ],
                    ],
                    'certifications' => [
                        [
                            'title' => 'AWS Certified Developer - Associate',
                            'description' => 'Certification obtenue en 2025.',
                        ],
                    ],
                    'projects' => [
                        [
                            'title' => 'Plateforme de recrutement B2B',
                            'description' => 'Développement API REST et pipelines CI/CD.',
                        ],
                    ],
                    'references' => [
                        [
                            'title' => 'Jean Dupont - CTO',
                            'description' => 'Disponible sur demande.',
                        ],
                    ],
                    'hobbies' => [
                        [
                            'title' => 'Trail running',
                            'description' => 'Participation à des courses régionales.',
                        ],
                    ],
                    'documentUrl' => 'https://cdn.example.com/cv/jane-doe.pdf',
                ],
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: 'CV mis à jour.'),
            new OA\Response(response: 400, description: 'UUID invalide ou payload invalide.'),
            new OA\Response(response: 403, description: 'Accès interdit sur ce CV.'),
            new OA\Response(response: 404, description: 'CV introuvable.'),
        ],
    )]
    public function __invoke(string $resumeId, Request $request, User $loggedInUser): JsonResponse
    {
        if (!Uuid::isValid($resumeId)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Route "resumeId" must be a valid UUID.');
        }

        $resume = $this->resumeRepository->find($resumeId);
        if (!$resume instanceof Resume) {
            throw new HttpException(JsonResponse::HTTP_NOT_FOUND, 'Resume not found.');
        }

        if ($resume->getOwner()->getId() !== $loggedInUser->getId()) {
            throw new HttpException(JsonResponse::HTTP_FORBIDDEN, 'You cannot update this resume.');
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();

        if (is_array($payload['experiences'] ?? null)) {
            $this->replaceSections($resume->getExperiences()->toArray(), $payload['experiences'], static fn (): Experience => new Experience(), static function (Resume $item, Experience $section): void {
                $item->addExperience($section);
            }, $resume);
        }

        if (is_array($payload['educations'] ?? null)) {
            $this->replaceSections($resume->getEducations()->toArray(), $payload['educations'], static fn (): Education => new Education(), static function (Resume $item, Education $section): void {
                $item->addEducation($section);
            }, $resume);
        }

        if (is_array($payload['skills'] ?? null)) {
            $this->replaceSections($resume->getSkills()->toArray(), $payload['skills'], static fn (): Skill => new Skill(), static function (Resume $item, Skill $section): void {
                $item->addSkill($section);
            }, $resume);
        }

        if (is_array($payload['languages'] ?? null)) {
            $this->replaceSections($resume->getLanguages()->toArray(), $payload['languages'], static fn (): Language => new Language(), static function (Resume $item, Language $section): void {
                $item->addLanguage($section);
            }, $resume);
        }

        if (is_array($payload['certifications'] ?? null)) {
            $this->replaceSections($resume->getCertifications()->toArray(), $payload['certifications'], static fn (): Certification => new Certification(), static function (Resume $item, Certification $section): void {
                $item->addCertification($section);
            }, $resume);
        }

        if (is_array($payload['projects'] ?? null)) {
            $this->replaceSections($resume->getProjects()->toArray(), $payload['projects'], static fn (): Project => new Project(), static function (Resume $item, Project $section): void {
                $item->addProject($section);
            }, $resume);
        }

        if (is_array($payload['references'] ?? null)) {
            $this->replaceSections($resume->getReferences()->toArray(), $payload['references'], static fn (): Reference => new Reference(), static function (Resume $item, Reference $section): void {
                $item->addReference($section);
            }, $resume);
        }

        if (is_array($payload['hobbies'] ?? null)) {
            $this->replaceSections($resume->getHobbies()->toArray(), $payload['hobbies'], static fn (): Hobby => new Hobby(), static function (Resume $item, Hobby $section): void {
                $item->addHobby($section);
            }, $resume);
        }

        if (array_key_exists('documentUrl', $payload)) {
            $documentUrl = $payload['documentUrl'];

            if ($documentUrl !== null && !is_string($documentUrl)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "documentUrl" must be a string or null.');
            }

            $resume->setDocumentUrl($documentUrl !== null ? trim($documentUrl) : null);
        }

        $this->resumeRepository->save($resume);

        return new JsonResponse([
            'id' => $resume->getId(),
            'documentUrl' => $resume->getDocumentUrl(),
        ]);
    }

    /**
     * @param array<int, object> $existing
     * @param array<int, mixed> $input
     * @param callable(): object $factory
     * @param callable(Resume, object): void $adder
     */
    private function replaceSections(array $existing, array $input, callable $factory, callable $adder, Resume $resume): void
    {
        foreach ($existing as $section) {
            $this->entityManager->remove($section);
        }

        foreach ($input as $index => $item) {
            if (!is_array($item)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Section at index ' . $index . ' must be an object.');
            }

            $title = $item['title'] ?? null;
            $description = $item['description'] ?? '';

            if (!is_string($title) || trim($title) === '') {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "title" must be a non-empty string.');
            }

            if (!is_string($description)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "description" must be a string.');
            }

            $section = $factory();
            $section->setTitle(trim($title));
            $section->setDescription(trim($description));
            $adder($resume, $section);
        }
    }
}
