<?php

declare(strict_types=1);

namespace App\Recruit\Application\Service;

use App\Recruit\Domain\Entity\Certification;
use App\Recruit\Domain\Entity\Education;
use App\Recruit\Domain\Entity\Experience;
use App\Recruit\Domain\Entity\Language;
use App\Recruit\Domain\Entity\Project;
use App\Recruit\Domain\Entity\Resume;

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
            ],
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
