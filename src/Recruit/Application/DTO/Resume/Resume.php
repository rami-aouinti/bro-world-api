<?php

declare(strict_types=1);

namespace App\Recruit\Application\DTO\Resume;

use App\General\Application\DTO\RestDto;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\Recruit\Domain\Entity\Resume as Entity;
use App\User\Domain\Entity\User;
use Doctrine\Common\Collections\Collection;
use Override;

class Resume extends RestDto
{
    protected ?User $owner = null;
    protected array $experiences = [];
    protected array $educations = [];
    protected array $skills = [];
    protected array $languages = [];
    protected array $certifications = [];
    protected array $projects = [];
    protected array $references = [];
    protected array $hobbies = [];
    protected ?string $documentUrl = null;

    public function getOwner(): ?User { return $this->owner; }
    public function setOwner(User $owner): self { $this->setVisited('owner'); $this->owner = $owner; return $this; }
    public function getExperiences(): array { return $this->experiences; }
    public function setExperiences(array $experiences): self { $this->setVisited('experiences'); $this->experiences = $experiences; return $this; }
    public function getEducations(): array { return $this->educations; }
    public function setEducations(array $educations): self { $this->setVisited('educations'); $this->educations = $educations; return $this; }
    public function getSkills(): array { return $this->skills; }
    public function setSkills(array $skills): self { $this->setVisited('skills'); $this->skills = $skills; return $this; }
    public function getLanguages(): array { return $this->languages; }
    public function setLanguages(array $languages): self { $this->setVisited('languages'); $this->languages = $languages; return $this; }
    public function getCertifications(): array { return $this->certifications; }
    public function setCertifications(array $certifications): self { $this->setVisited('certifications'); $this->certifications = $certifications; return $this; }
    public function getProjects(): array { return $this->projects; }
    public function setProjects(array $projects): self { $this->setVisited('projects'); $this->projects = $projects; return $this; }
    public function getReferences(): array { return $this->references; }
    public function setReferences(array $references): self { $this->setVisited('references'); $this->references = $references; return $this; }
    public function getHobbies(): array { return $this->hobbies; }
    public function setHobbies(array $hobbies): self { $this->setVisited('hobbies'); $this->hobbies = $hobbies; return $this; }
    public function getDocumentUrl(): ?string { return $this->documentUrl; }
    public function setDocumentUrl(?string $documentUrl): self { $this->setVisited('documentUrl'); $this->documentUrl = $documentUrl; return $this; }

    #[Override]
    public function load(EntityInterface $entity): self
    {
        if ($entity instanceof Entity) {
            $this->id = $entity->getId();
            $this->owner = $entity->getOwner();
            $this->experiences = $this->mapSections($entity->getExperiences());
            $this->educations = $this->mapSections($entity->getEducations());
            $this->skills = $this->mapSections($entity->getSkills());
            $this->languages = $this->mapSections($entity->getLanguages());
            $this->certifications = $this->mapSections($entity->getCertifications());
            $this->projects = $this->mapSections($entity->getProjects());
            $this->references = $this->mapSections($entity->getReferences());
            $this->hobbies = $this->mapSections($entity->getHobbies());
            $this->documentUrl = $entity->getDocumentUrl();
        }

        return $this;
    }

    private function mapSections(Collection $sections): array
    {
        $items = [];

        foreach ($sections as $section) {
            $items[] = new ResumeSection($section->getId(), $section->getTitle(), $section->getDescription());
        }

        return $items;
    }
}
