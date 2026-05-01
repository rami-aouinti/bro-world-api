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
use App\User\Domain\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use JsonException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

use function array_key_exists;
use function is_array;
use function is_string;
use function json_decode;
use function sprintf;
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
                if (!is_string($payload[$field] ?? null)) {
                    continue;
                }

                if (trim($payload[$field]) === '') {
                    $payload[$field] = [];
                    continue;
                }

                try {
                    $decoded = json_decode($payload[$field], true, 512, JSON_THROW_ON_ERROR);
                } catch (JsonException $exception) {
                    throw new HttpException(
                        JsonResponse::HTTP_BAD_REQUEST,
                        sprintf('Field "%s" must be a valid JSON array string.', $field),
                        $exception,
                    );
                }

                if (!is_array($decoded)) {
                    throw new HttpException(
                        JsonResponse::HTTP_BAD_REQUEST,
                        sprintf('Field "%s" must decode to an array.', $field),
                    );
                }

                $payload[$field] = $decoded;
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
    public function applyResumeInformationForCreate(Resume $resume, array $payload, User $user): void
    {
        $input = $this->extractResumeInformationInput($payload);
        $profile = $user->getProfile();

        $defaultFullName = trim($user->getFirstName() . ' ' . $user->getLastName());
        $resume->setInformationFullName($this->nullableTrimmedString($input['fullName'] ?? null) ?? ($defaultFullName !== '' ? $defaultFullName : null));
        $resume->setInformationEmail($this->nullableTrimmedString($input['email'] ?? null) ?? $user->getEmail());
        $resume->setInformationPhone($this->nullableTrimmedString($input['phone'] ?? null) ?? $profile?->getPhone());
        $resume->setInformationHomepage($this->nullableTrimmedString($input['homepage'] ?? null));
        $resume->setInformationRepoProfile($this->nullableTrimmedString($input['repo_profile'] ?? null));
        $resume->setInformationAddress($this->nullableTrimmedString($input['adresse'] ?? null) ?? $profile?->getLocation());
        $resume->setInformationBirthDate($this->nullableDate($input['birthDate'] ?? null, 'birthDate'));
        $resume->setInformationBirthPlace($this->nullableTrimmedString($input['birthPlace'] ?? null));
        $resume->setInformationProfileText($this->nullableTrimmedString($input['profileText'] ?? null));
        $resume->setInformationTitle($this->nullableTrimmedString($input['title'] ?? null));
        $resume->setInformationPhoto($this->nullableTrimmedString($input['photo'] ?? null));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function applyResumeInformationForPatch(Resume $resume, array $payload): void
    {
        $input = $this->extractResumeInformationInput($payload);
        if ($input === null) {
            return;
        }

        if (array_key_exists('fullName', $input)) {
            $resume->setInformationFullName($this->nullableTrimmedString($input['fullName']));
        }
        if (array_key_exists('email', $input)) {
            $resume->setInformationEmail($this->nullableTrimmedString($input['email']));
        }
        if (array_key_exists('phone', $input)) {
            $resume->setInformationPhone($this->nullableTrimmedString($input['phone']));
        }
        if (array_key_exists('homepage', $input)) {
            $resume->setInformationHomepage($this->nullableTrimmedString($input['homepage']));
        }
        if (array_key_exists('repo_profile', $input)) {
            $resume->setInformationRepoProfile($this->nullableTrimmedString($input['repo_profile']));
        }
        if (array_key_exists('adresse', $input)) {
            $resume->setInformationAddress($this->nullableTrimmedString($input['adresse']));
        }
        if (array_key_exists('birthDate', $input)) {
            $resume->setInformationBirthDate($this->nullableDate($input['birthDate'], 'birthDate'));
        }
        if (array_key_exists('birthPlace', $input)) {
            $resume->setInformationBirthPlace($this->nullableTrimmedString($input['birthPlace']));
        }
        if (array_key_exists('profileText', $input)) {
            $resume->setInformationProfileText($this->nullableTrimmedString($input['profileText']));
        }
        if (array_key_exists('title', $input)) {
            $resume->setInformationTitle($this->nullableTrimmedString($input['title']));
        }
        if (array_key_exists('photo', $input)) {
            $resume->setInformationPhoto($this->nullableTrimmedString($input['photo']));
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
            if ($field === 'skills' && $title === null) {
                $title = $item['name'] ?? null;
            }
            if ($field === 'languages' && $title === null) {
                $title = $item['name'] ?? null;
            }
            if ($field === 'languages' && ($item['description'] ?? null) === null && is_string($item['countryCode'] ?? null)) {
                $description = $item['countryCode'];
            }

            if (!is_string($title) || trim($title) === '') {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "title" must be a non-empty string.');
            }

            if (!is_string($description)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "description" must be a string.');
            }

            $section = $this->newSection($field);
            $section->setTitle(trim($title));
            $section->setDescription(trim($description));

            if ($field === 'languages' && $section instanceof Language) {
                $section->setLevel($this->nullableTrimmedString($item['level'] ?? null));
            }

            if ($field === 'certifications' && $section instanceof Certification) {
                $section->setAttachments($this->normalizeStringArray($item['attachments'] ?? null, 'attachments'));
            }

            if ($field === 'projects' && $section instanceof Project) {
                $section->setAttachments($this->normalizeStringArray($item['attachments'] ?? null, 'attachments'));
                $section->setHomePage($this->nullableTrimmedString($item['home_page'] ?? null));
            }
            if ($field === 'skills' && $section instanceof Skill) {
                $section->setLevel($this->nullableTrimmedString($item['level'] ?? null));
            }

            if ($field === 'educations' && $section instanceof Education) {
                $section->setSchool($this->nullableTrimmedString($item['school'] ?? null));
                $section->setStartDate($this->nullableDate($item['startDate'] ?? null, 'startDate'));
                $section->setEndDate($this->nullableDate($item['endDate'] ?? null, 'endDate'));
                $section->setLocation($this->nullableTrimmedString($item['location'] ?? null));
            }

            if ($field === 'experiences' && $section instanceof Experience) {
                $section->setCompany($this->nullableTrimmedString($item['company'] ?? null));
                $section->setStartDate($this->nullableDate($item['startDate'] ?? null, 'startDate'));
                $section->setEndDate($this->nullableDate($item['endDate'] ?? null, 'endDate'));
            }

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

    /**
     * @param mixed $value
     */
    private function nullableTrimmedString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Expected a string or null value.');
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param mixed $value
     * @return list<string>|null
     */
    private function normalizeStringArray(mixed $value, string $field): ?array
    {
        if ($value === null) {
            return null;
        }

        if (!is_array($value)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "' . $field . '" must be an array of strings or null.');
        }

        $normalized = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "' . $field . '" must contain only strings.');
            }

            $trimmed = trim($item);
            if ($trimmed !== '') {
                $normalized[] = $trimmed;
            }
        }

        return $normalized;
    }

    /**
     * @param mixed $value
     */
    private function nullableDate(mixed $value, string $field): ?DateTimeImmutable
    {
        if ($value === null) {
            return null;
        }

        if (!is_string($value) || trim($value) === '') {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "' . $field . '" must be a date string or null.');
        }

        try {
            return new DateTimeImmutable(trim($value));
        } catch (\Throwable) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "' . $field . '" must be a valid date string.');
        }
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function extractResumeInformationInput(array $payload): ?array
    {
        $input = $payload['resumeInformation'] ?? $payload['resume_infomation'] ?? null;
        if ($input === null) {
            return null;
        }

        if (!is_array($input)) {
            throw new HttpException(JsonResponse::HTTP_BAD_REQUEST, 'Field "resumeInformation" must be an object.');
        }

        return $input;
    }
}
