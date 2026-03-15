<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Entity\Certification;
use App\Recruit\Domain\Entity\Education;
use App\Recruit\Domain\Entity\Experience;
use App\Recruit\Domain\Entity\Hobby;
use App\Recruit\Domain\Entity\Language;
use App\Recruit\Domain\Entity\Project;
use App\Recruit\Domain\Entity\Reference;
use App\Recruit\Domain\Entity\Resume;
use App\Recruit\Domain\Entity\Skill;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function array_key_exists;
use function is_array;
use function is_string;
use function json_decode;
use function trim;

class ResumePayloadService
{
    /**
     * @var list<string>
     */
    private const array RESUME_SECTION_FIELDS = [
        'experiences',
        'educations',
        'skills',
        'languages',
        'certifications',
        'projects',
        'references',
        'hobbies',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array<string, mixed>
     * @throws JsonException
     */
    public function extractPayload(Request $request): array
    {
        if ($request->request->count() > 0) {
            /** @var array<string, mixed> $payload */
            $payload = $request->request->all();

            foreach (self::RESUME_SECTION_FIELDS as $field) {
                if (is_string($payload[$field] ?? null)) {
                    $decoded = json_decode($payload[$field], true, 512, JSON_THROW_ON_ERROR);
                    $payload[$field] = is_array($decoded) ? $decoded : [];
                }
            }

            return $payload;
        }

        return $request->toArray();
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function hydrateResumeSections(Resume $resume, array $payload): void
    {
        foreach (self::RESUME_SECTION_FIELDS as $field) {
            $sections = $payload[$field] ?? [];
            if (!is_array($sections)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "' . $field . '" must be an array.');
            }

            $this->appendSections($resume, $field, $sections);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function replaceResumeSections(Resume $resume, array $payload): void
    {
        foreach (self::RESUME_SECTION_FIELDS as $field) {
            if (!is_array($payload[$field] ?? null)) {
                continue;
            }

            $this->clearSections($resume, $field);
            /** @var array<int, mixed> $sections */
            $sections = $payload[$field];
            $this->appendSections($resume, $field, $sections);
        }

        if (array_key_exists('documentUrl', $payload)) {
            $documentUrl = $payload['documentUrl'];

            if ($documentUrl !== null && !is_string($documentUrl)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "documentUrl" must be a string or null.');
            }

            $resume->setDocumentUrl($documentUrl !== null ? trim($documentUrl) : null);
        }
    }

    /**
     * @param array<int, mixed> $input
     */
    private function appendSections(Resume $resume, string $field, array $input): void
    {
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

            $section = $this->newSection($field);
            $section->setTitle(trim($title));
            $section->setDescription(trim($description));
            $this->addSection($resume, $field, $section);
        }
    }

    private function clearSections(Resume $resume, string $field): void
    {
        $sections = match ($field) {
            'experiences' => $resume->getExperiences()->toArray(),
            'educations' => $resume->getEducations()->toArray(),
            'skills' => $resume->getSkills()->toArray(),
            'languages' => $resume->getLanguages()->toArray(),
            'certifications' => $resume->getCertifications()->toArray(),
            'projects' => $resume->getProjects()->toArray(),
            'references' => $resume->getReferences()->toArray(),
            'hobbies' => $resume->getHobbies()->toArray(),
        };

        foreach ($sections as $section) {
            $this->entityManager->remove($section);
        }
    }

    private function newSection(string $field): Experience|Education|Skill|Language|Certification|Project|Reference|Hobby
    {
        return match ($field) {
            'experiences' => new Experience(),
            'educations' => new Education(),
            'skills' => new Skill(),
            'languages' => new Language(),
            'certifications' => new Certification(),
            'projects' => new Project(),
            'references' => new Reference(),
            'hobbies' => new Hobby(),
        };
    }

    private function addSection(Resume $resume, string $field, Experience|Education|Skill|Language|Certification|Project|Reference|Hobby $section): void
    {
        match ($field) {
            'experiences' => $resume->addExperience($section),
            'educations' => $resume->addEducation($section),
            'skills' => $resume->addSkill($section),
            'languages' => $resume->addLanguage($section),
            'certifications' => $resume->addCertification($section),
            'projects' => $resume->addProject($section),
            'references' => $resume->addReference($section),
            'hobbies' => $resume->addHobby($section),
        };
    }
}
