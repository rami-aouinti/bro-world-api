<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Entity\Certification;
use App\Recruit\Domain\Entity\Education;
use App\Recruit\Domain\Entity\Experience;
use App\Recruit\Domain\Entity\Language;
use App\Recruit\Domain\Entity\Project;
use App\Recruit\Domain\Entity\Resume;
use App\Recruit\Domain\Entity\Skill;

class ResumeNormalizerService
{
    /**
     * @param list<Resume> $resumes
     *
     * @return list<array<string, mixed>>
     */
    public function normalizeCollection(array $resumes): array
    {
        return array_map($this->normalize(...), $resumes);
    }

    /**
     * @return array<string, mixed>
     */
    public function normalize(Resume $resume): array
    {
        return [
            'id' => $resume->getId(),
            'documentUrl' => $resume->getDocumentUrl(),
            'resumeInformation' => [
                'fullName' => $resume->getInformationFullName(),
                'email' => $resume->getInformationEmail(),
                'phone' => $resume->getInformationPhone(),
                'homepage' => $resume->getInformationHomepage(),
                'repo_profile' => $resume->getInformationRepoProfile(),
                'adresse' => $resume->getInformationAddress(),
                'birthDate' => $resume->getInformationBirthDate()?->format('Y-m-d'),
                'birthPlace' => $resume->getInformationBirthPlace(),
                'profileText' => $resume->getInformationProfileText(),
                'title' => $resume->getInformationTitle(),
            ],
            'experiences' => $this->normalizeSections($resume->getExperiences()->toArray()),
            'educations' => $this->normalizeSections($resume->getEducations()->toArray()),
            'skills' => $this->normalizeSections($resume->getSkills()->toArray()),
            'languages' => $this->normalizeLanguages($resume->getLanguages()->toArray()),
            'certifications' => $this->normalizeSections($resume->getCertifications()->toArray()),
            'projects' => $this->normalizeSections($resume->getProjects()->toArray()),
            'references' => $this->normalizeSections($resume->getReferences()->toArray()),
            'hobbies' => $this->normalizeSections($resume->getHobbies()->toArray()),
        ];
    }


    /**
     * @param array<int, Language> $languages
     *
     * @return array<int, array<string, string|null>>
     */
    private function normalizeLanguages(array $languages): array
    {
        return array_map(function (Language $language): array {
            $countryCode = strtoupper(trim($language->getDescription()));
            if ($countryCode === '') {
                $countryCode = null;
            }

            return [
                'name' => $language->getTitle(),
                'countryCode' => $countryCode,
                'flag' => $this->countryCodeToFlag($countryCode),
            ];
        }, $languages);
    }

    private function countryCodeToFlag(?string $countryCode): ?string
    {
        if ($countryCode === null || strlen($countryCode) !== 2) {
            return null;
        }

        $first = ord($countryCode[0]);
        $second = ord($countryCode[1]);

        if ($first < 65 || $first > 90 || $second < 65 || $second > 90) {
            return null;
        }

        return mb_chr(0x1F1E6 + ($first - 65), 'UTF-8') . mb_chr(0x1F1E6 + ($second - 65), 'UTF-8');
    }

    /**
     * @param array<int, object> $sections
     *
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSections(array $sections): array
    {
        return array_map(function (object $section): array {
            $payload = [
                'id' => $section->getId(),
                'title' => $section->getTitle(),
                'description' => $section->getDescription(),
            ];

            if ($section instanceof Language) {
                $payload['level'] = $section->getLevel();
            }
            if ($section instanceof Skill) {
                $payload['level'] = $section->getLevel();
            }

            if ($section instanceof Certification) {
                $payload['attachments'] = $section->getAttachments() ?? [];
            }

            if ($section instanceof Project) {
                $payload['attachments'] = $section->getAttachments() ?? [];
                $payload['home_page'] = $section->getHomePage();
            }

            if ($section instanceof Education) {
                $payload['school'] = $section->getSchool();
                $payload['startDate'] = $section->getStartDate()?->format('Y-m-d');
                $payload['endDate'] = $section->getEndDate()?->format('Y-m-d');
                $payload['location'] = $section->getLocation();
            }

            if ($section instanceof Experience) {
                $payload['company'] = $section->getCompany();
                $payload['startDate'] = $section->getStartDate()?->format('Y-m-d');
                $payload['endDate'] = $section->getEndDate()?->format('Y-m-d');
            }

            return $payload;
        }, $sections);
    }
}
