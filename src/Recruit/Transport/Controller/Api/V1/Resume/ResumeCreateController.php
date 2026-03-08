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
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

use function is_array;
use function is_string;
use function trim;

#[AsController]
#[OA\Tag(name: 'Recruit Resume')]
#[IsGranted(AuthenticatedVoter::IS_AUTHENTICATED_FULLY)]
class ResumeCreateController
{
    public function __construct(
        private readonly ResumeRepository $resumeRepository,
    ) {
    }

    #[Route(path: '/v1/recruit/resumes', methods: [Request::METHOD_POST])]
    public function __invoke(Request $request, User $loggedInUser): JsonResponse
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->toArray();

        $resume = (new Resume())->setOwner($loggedInUser);

        $this->hydrateSections($payload, 'experiences', static fn (): Experience => new Experience(), static fn (Resume $entity, Experience $item) => $entity->addExperience($item), $resume);
        $this->hydrateSections($payload, 'educations', static fn (): Education => new Education(), static fn (Resume $entity, Education $item) => $entity->addEducation($item), $resume);
        $this->hydrateSections($payload, 'skills', static fn (): Skill => new Skill(), static fn (Resume $entity, Skill $item) => $entity->addSkill($item), $resume);
        $this->hydrateSections($payload, 'languages', static fn (): Language => new Language(), static fn (Resume $entity, Language $item) => $entity->addLanguage($item), $resume);
        $this->hydrateSections($payload, 'certifications', static fn (): Certification => new Certification(), static fn (Resume $entity, Certification $item) => $entity->addCertification($item), $resume);
        $this->hydrateSections($payload, 'projects', static fn (): Project => new Project(), static fn (Resume $entity, Project $item) => $entity->addProject($item), $resume);
        $this->hydrateSections($payload, 'references', static fn (): Reference => new Reference(), static fn (Resume $entity, Reference $item) => $entity->addReference($item), $resume);
        $this->hydrateSections($payload, 'hobbies', static fn (): Hobby => new Hobby(), static fn (Resume $entity, Hobby $item) => $entity->addHobby($item), $resume);

        $this->resumeRepository->save($resume);

        return new JsonResponse(['id' => $resume->getId()], JsonResponse::HTTP_CREATED);
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
