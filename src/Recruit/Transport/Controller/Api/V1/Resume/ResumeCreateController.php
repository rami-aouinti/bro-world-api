<?php

declare(strict_types=1);

namespace App\Recruit\Transport\Controller\Api\V1\Resume;

use App\Recruit\Application\Service\ResumeDocumentUploaderService;
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
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_array;
use function is_string;
use function json_decode;
use function trim;

#[AsController]
#[OA\Tag(name: 'Recruit Resume')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class ResumeCreateController
{
    public function __construct(
        private readonly ResumeRepository $resumeRepository,
        private readonly ResumeDocumentUploaderService $resumeDocumentUploaderService,
    ) {
    }

    #[Route(path: '/v1/recruit/resumes', methods: [Request::METHOD_POST])]
    #[OA\Post(summary: 'Crée un CV et permet l’upload optionnel d’un PDF.')]
    #[OA\RequestBody(
        required: false,
        content: [
            new OA\MediaType(
                mediaType: 'application/json',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'experiences', type: 'array', items: new OA\Items(type: 'object', required: ['title'], properties: [new OA\Property(property: 'title', type: 'string'), new OA\Property(property: 'description', type: 'string')])),
                        new OA\Property(property: 'educations', type: 'array', items: new OA\Items(type: 'object', required: ['title'], properties: [new OA\Property(property: 'title', type: 'string'), new OA\Property(property: 'description', type: 'string')])),
                        new OA\Property(property: 'skills', type: 'array', items: new OA\Items(type: 'object', required: ['title'], properties: [new OA\Property(property: 'title', type: 'string'), new OA\Property(property: 'description', type: 'string')])),
                        new OA\Property(property: 'languages', type: 'array', items: new OA\Items(type: 'object', required: ['title'], properties: [new OA\Property(property: 'title', type: 'string'), new OA\Property(property: 'description', type: 'string')])),
                        new OA\Property(property: 'certifications', type: 'array', items: new OA\Items(type: 'object', required: ['title'], properties: [new OA\Property(property: 'title', type: 'string'), new OA\Property(property: 'description', type: 'string')])),
                        new OA\Property(property: 'projects', type: 'array', items: new OA\Items(type: 'object', required: ['title'], properties: [new OA\Property(property: 'title', type: 'string'), new OA\Property(property: 'description', type: 'string')])),
                        new OA\Property(property: 'references', type: 'array', items: new OA\Items(type: 'object', required: ['title'], properties: [new OA\Property(property: 'title', type: 'string'), new OA\Property(property: 'description', type: 'string')])),
                        new OA\Property(property: 'hobbies', type: 'array', items: new OA\Items(type: 'object', required: ['title'], properties: [new OA\Property(property: 'title', type: 'string'), new OA\Property(property: 'description', type: 'string')])),
                    ],
                    example: [
                        'experiences' => [['title' => 'Backend Developer', 'description' => 'Symfony API']],
                        'skills' => [['title' => 'PHP', 'description' => '8.x']],
                    ],
                ),
            ),
            new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'document', type: 'string', format: 'binary', description: 'Fichier CV PDF.'),
                        new OA\Property(property: 'experiences', type: 'string', description: 'JSON stringifié: [{"title":"...","description":"..."}]'),
                        new OA\Property(property: 'educations', type: 'string', description: 'JSON stringifié'),
                        new OA\Property(property: 'skills', type: 'string', description: 'JSON stringifié'),
                        new OA\Property(property: 'languages', type: 'string', description: 'JSON stringifié'),
                        new OA\Property(property: 'certifications', type: 'string', description: 'JSON stringifié'),
                        new OA\Property(property: 'projects', type: 'string', description: 'JSON stringifié'),
                        new OA\Property(property: 'references', type: 'string', description: 'JSON stringifié'),
                        new OA\Property(property: 'hobbies', type: 'string', description: 'JSON stringifié'),
                    ],
                ),
            ),
        ],
    )]
    #[OA\Response(
        response: 201,
        description: 'Resume created',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                new OA\Property(property: 'documentUrl', type: 'string', nullable: true),
            ],
            example: [
                'id' => '0195f9b4-7c29-7dd2-89f6-2f7d3ef2e9aa',
                'documentUrl' => 'https://localhost/uploads/resumes/0af6fe1514bdbce22f637d970a6e6042.pdf',
            ],
        ),
    )]
    #[OA\Response(response: 400, description: 'Invalid payload or file format')]
    #[OA\Response(response: 401, description: 'Authentication required')]
    public function __invoke(Request $request, User $loggedInUser): JsonResponse
    {
        $payload = $this->extractPayload($request);

        $resume = (new Resume())->setOwner($loggedInUser);

        /** @var UploadedFile|null $document */
        $document = $request->files->get('document');
        if ($document instanceof UploadedFile) {
            $documentUrl = $this->resumeDocumentUploaderService->upload($request, $document, '/uploads/resumes');
            $resume->setDocumentUrl($documentUrl);
        }

        $this->hydrateSections($payload, 'experiences', static fn (): Experience => new Experience(), static fn (Resume $entity, Experience $item) => $entity->addExperience($item), $resume);
        $this->hydrateSections($payload, 'educations', static fn (): Education => new Education(), static fn (Resume $entity, Education $item) => $entity->addEducation($item), $resume);
        $this->hydrateSections($payload, 'skills', static fn (): Skill => new Skill(), static fn (Resume $entity, Skill $item) => $entity->addSkill($item), $resume);
        $this->hydrateSections($payload, 'languages', static fn (): Language => new Language(), static fn (Resume $entity, Language $item) => $entity->addLanguage($item), $resume);
        $this->hydrateSections($payload, 'certifications', static fn (): Certification => new Certification(), static fn (Resume $entity, Certification $item) => $entity->addCertification($item), $resume);
        $this->hydrateSections($payload, 'projects', static fn (): Project => new Project(), static fn (Resume $entity, Project $item) => $entity->addProject($item), $resume);
        $this->hydrateSections($payload, 'references', static fn (): Reference => new Reference(), static fn (Resume $entity, Reference $item) => $entity->addReference($item), $resume);
        $this->hydrateSections($payload, 'hobbies', static fn (): Hobby => new Hobby(), static fn (Resume $entity, Hobby $item) => $entity->addHobby($item), $resume);

        $this->resumeRepository->save($resume);

        return new JsonResponse([
            'id' => $resume->getId(),
            'documentUrl' => $resume->getDocumentUrl(),
        ], JsonResponse::HTTP_CREATED);
    }

    /** @return array<string, mixed> */
    private function extractPayload(Request $request): array
    {
        if ($request->request->count() > 0) {
            /** @var array<string, mixed> $payload */
            $payload = $request->request->all();

            foreach (['experiences', 'educations', 'skills', 'languages', 'certifications', 'projects', 'references', 'hobbies'] as $field) {
                if (is_string($payload[$field] ?? null)) {
                    $decoded = json_decode($payload[$field], true);
                    $payload[$field] = is_array($decoded) ? $decoded : [];
                }
            }

            return $payload;
        }

        return $request->toArray();
    }

    /**
     * @param array<string, mixed> $payload
     * @param callable(): object $factory
     * @param callable(Resume, object): void $adder
     */
    private function hydrateSections(array $payload, string $field, callable $factory, callable $adder, Resume $resume): void
    {
        $sections = $payload[$field] ?? [];

        if (!is_array($sections)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "' . $field . '" must be an array.');
        }

        foreach ($sections as $index => $sectionData) {
            if (!is_array($sectionData)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "' . $field . '[' . $index . ']" must be an object.');
            }

            $title = $sectionData['title'] ?? null;
            $description = $sectionData['description'] ?? '';

            if (!is_string($title) || trim($title) === '') {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "' . $field . '[' . $index . '].title" must be a non-empty string.');
            }

            if (!is_string($description)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "' . $field . '[' . $index . '].description" must be a string.');
            }

            $section = $factory();
            $section->setTitle(trim($title));
            $section->setDescription(trim($description));
            $adder($resume, $section);
        }
    }
}

#[OA\Schema(
    schema: 'RecruitResumeSectionInput',
    type: 'object',
    required: ['title'],
    properties: [
        new OA\Property(property: 'title', type: 'string', example: 'Backend Developer'),
        new OA\Property(property: 'description', type: 'string', example: 'Symfony / API Platform'),
    ],
)]
final class RecruitResumeSectionInputSchema
{
}
