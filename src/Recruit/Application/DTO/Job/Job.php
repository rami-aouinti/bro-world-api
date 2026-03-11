<?php

declare(strict_types=1);

namespace App\Recruit\Application\DTO\Job;

use App\General\Application\DTO\RestDto;
use App\General\Application\Validator\Constraints as AppAssert;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\Recruit\Domain\Entity\Job as Entity;
use App\Recruit\Domain\Entity\Recruit as RecruitEntity;
use App\Recruit\Domain\Enum\ContractType;
use App\Recruit\Domain\Enum\ExperienceLevel;
use App\Recruit\Domain\Enum\Schedule;
use App\Recruit\Domain\Enum\WorkMode;
use Override;

class Job extends RestDto
{
    #[AppAssert\EntityReferenceExists(entityClass: RecruitEntity::class)]
    protected ?RecruitEntity $recruit = null;

    protected string $title = '';
    protected string $location = '';
    protected string $contractType = ContractType::CDI->value;
    protected string $workMode = WorkMode::HYBRID->value;
    protected string $schedule = Schedule::FULL_TIME->value;
    protected string $experienceLevel = ExperienceLevel::MID->value;
    protected int $yearsExperienceMin = 0;
    protected int $yearsExperienceMax = 0;
    protected bool $isPublished = true;
    protected string $summary = '';
    protected int $matchScore = 0;
    protected string $missionTitle = '';
    protected string $missionDescription = '';
    protected array $responsibilities = [];
    protected array $profile = [];
    protected array $benefits = [];

    public function getRecruit(): ?RecruitEntity
    {
        return $this->recruit;
    }
    public function setRecruit(RecruitEntity $recruit): self
    {
        $this->setVisited('recruit');
        $this->recruit = $recruit;

        return $this;
    }
    public function getTitle(): string
    {
        return $this->title;
    }
    public function setTitle(string $title): self
    {
        $this->setVisited('title');
        $this->title = $title;

        return $this;
    }
    public function getLocation(): string
    {
        return $this->location;
    }
    public function setLocation(string $location): self
    {
        $this->setVisited('location');
        $this->location = $location;

        return $this;
    }
    public function getContractType(): string
    {
        return $this->contractType;
    }
    public function setContractType(string $contractType): self
    {
        $this->setVisited('contractType');
        $this->contractType = $contractType;

        return $this;
    }
    public function getWorkMode(): string
    {
        return $this->workMode;
    }
    public function setWorkMode(string $workMode): self
    {
        $this->setVisited('workMode');
        $this->workMode = $workMode;

        return $this;
    }
    public function getSchedule(): string
    {
        return $this->schedule;
    }
    public function setSchedule(string $schedule): self
    {
        $this->setVisited('schedule');
        $this->schedule = $schedule;

        return $this;
    }
    public function getExperienceLevel(): string
    {
        return $this->experienceLevel;
    }
    public function setExperienceLevel(string $experienceLevel): self
    {
        $this->setVisited('experienceLevel');
        $this->experienceLevel = $experienceLevel;

        return $this;
    }
    public function getYearsExperienceMin(): int
    {
        return $this->yearsExperienceMin;
    }
    public function setYearsExperienceMin(int $yearsExperienceMin): self
    {
        $this->setVisited('yearsExperienceMin');
        $this->yearsExperienceMin = $yearsExperienceMin;

        return $this;
    }
    public function getYearsExperienceMax(): int
    {
        return $this->yearsExperienceMax;
    }
    public function setYearsExperienceMax(int $yearsExperienceMax): self
    {
        $this->setVisited('yearsExperienceMax');
        $this->yearsExperienceMax = $yearsExperienceMax;

        return $this;
    }
    public function isPublished(): bool
    {
        return $this->isPublished;
    }
    public function setIsPublished(bool $isPublished): self
    {
        $this->setVisited('isPublished');
        $this->isPublished = $isPublished;

        return $this;
    }
    public function getSummary(): string
    {
        return $this->summary;
    }
    public function setSummary(string $summary): self
    {
        $this->setVisited('summary');
        $this->summary = $summary;

        return $this;
    }
    public function getMatchScore(): int
    {
        return $this->matchScore;
    }
    public function setMatchScore(int $matchScore): self
    {
        $this->setVisited('matchScore');
        $this->matchScore = $matchScore;

        return $this;
    }
    public function getMissionTitle(): string
    {
        return $this->missionTitle;
    }
    public function setMissionTitle(string $missionTitle): self
    {
        $this->setVisited('missionTitle');
        $this->missionTitle = $missionTitle;

        return $this;
    }
    public function getMissionDescription(): string
    {
        return $this->missionDescription;
    }
    public function setMissionDescription(string $missionDescription): self
    {
        $this->setVisited('missionDescription');
        $this->missionDescription = $missionDescription;

        return $this;
    }
    public function getResponsibilities(): array
    {
        return $this->responsibilities;
    }
    public function setResponsibilities(array $responsibilities): self
    {
        $this->setVisited('responsibilities');
        $this->responsibilities = $responsibilities;

        return $this;
    }
    public function getProfile(): array
    {
        return $this->profile;
    }
    public function setProfile(array $profile): self
    {
        $this->setVisited('profile');
        $this->profile = $profile;

        return $this;
    }
    public function getBenefits(): array
    {
        return $this->benefits;
    }
    public function setBenefits(array $benefits): self
    {
        $this->setVisited('benefits');
        $this->benefits = $benefits;

        return $this;
    }

    #[Override]
    public function load(EntityInterface $entity): self
    {
        if ($entity instanceof Entity) {
            $this->id = $entity->getId();
            $this->recruit = $entity->getRecruit();
            $this->title = $entity->getTitle();
            $this->location = $entity->getLocation();
            $this->contractType = $entity->getContractTypeValue();
            $this->workMode = $entity->getWorkModeValue();
            $this->schedule = $entity->getScheduleValue();
            $this->experienceLevel = $entity->getExperienceLevelValue();
            $this->yearsExperienceMin = $entity->getYearsExperienceMin();
            $this->yearsExperienceMax = $entity->getYearsExperienceMax();
            $this->isPublished = $entity->isPublished();
            $this->summary = $entity->getSummary();
            $this->matchScore = $entity->getMatchScore();
            $this->missionTitle = $entity->getMissionTitle();
            $this->missionDescription = $entity->getMissionDescription();
            $this->responsibilities = $entity->getResponsibilities();
            $this->profile = $entity->getProfile();
            $this->benefits = $entity->getBenefits();
        }

        return $this;
    }
}
