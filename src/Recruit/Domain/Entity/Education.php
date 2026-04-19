<?php

declare(strict_types=1);

namespace App\Recruit\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'recruit_resume_education')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Education implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: Resume::class)]
    #[ORM\JoinColumn(name: 'resume_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private Resume $resume;

    #[ORM\Column(name: 'title', type: Types::STRING, length: 255, options: [
        'default' => '',
    ])]
    private string $title = '';

    #[ORM\Column(name: 'description', type: Types::TEXT, options: [
        'default' => '',
    ])]
    private string $description = '';

    #[ORM\Column(name: 'school', type: Types::STRING, length: 255, nullable: true)]
    private ?string $school = null;

    #[ORM\Column(name: 'start_date', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(name: 'end_date', type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(name: 'location', type: Types::STRING, length: 255, nullable: true)]
    private ?string $location = null;

    public function __construct()
    {
        $this->id = $this->createUuid();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }
    public function getResume(): Resume
    {
        return $this->resume;
    }
    public function setResume(Resume $resume): self
    {
        $this->resume = $resume;

        return $this;
    }
    public function getTitle(): string
    {
        return $this->title;
    }
    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }
    public function getDescription(): string
    {
        return $this->description;
    }
    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }
    public function getSchool(): ?string
    {
        return $this->school;
    }
    public function setSchool(?string $school): self
    {
        $this->school = $school;

        return $this;
    }
    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }
    public function setStartDate(?\DateTimeImmutable $startDate): self
    {
        $this->startDate = $startDate;

        return $this;
    }
    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }
    public function setEndDate(?\DateTimeImmutable $endDate): self
    {
        $this->endDate = $endDate;

        return $this;
    }
    public function getLocation(): ?string
    {
        return $this->location;
    }
    public function setLocation(?string $location): self
    {
        $this->location = $location;

        return $this;
    }
}
