<?php

declare(strict_types=1);

namespace App\Recruit\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\User\Domain\Entity\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'recruit_resume')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Resume implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\OneToOne(targetEntity: Applicant::class, mappedBy: 'resume')]
    private ?Applicant $applicant = null;

    #[ORM\Column(name: 'document_url', type: 'string', length: 255, nullable: true)]
    private ?string $documentUrl = null;

    #[ORM\ManyToOne(targetEntity: Template::class)]
    #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Template $template = null;

    #[ORM\Column(name: 'information_full_name', type: 'string', length: 255, nullable: true)]
    private ?string $informationFullName = null;

    #[ORM\Column(name: 'information_email', type: 'string', length: 255, nullable: true)]
    private ?string $informationEmail = null;

    #[ORM\Column(name: 'information_phone', type: 'string', length: 50, nullable: true)]
    private ?string $informationPhone = null;

    #[ORM\Column(name: 'information_homepage', type: 'string', length: 255, nullable: true)]
    private ?string $informationHomepage = null;

    #[ORM\Column(name: 'information_repo_profile', type: 'string', length: 255, nullable: true)]
    private ?string $informationRepoProfile = null;

    #[ORM\Column(name: 'information_address', type: 'string', length: 255, nullable: true)]
    private ?string $informationAddress = null;

    #[ORM\Column(name: 'information_birth_date', type: 'date_immutable', nullable: true)]
    private ?\DateTimeImmutable $informationBirthDate = null;

    #[ORM\Column(name: 'information_birth_place', type: 'string', length: 255, nullable: true)]
    private ?string $informationBirthPlace = null;

    #[ORM\Column(name: 'information_profile_text', type: 'text', nullable: true)]
    private ?string $informationProfileText = null;

    #[ORM\Column(name: 'information_title', type: 'string', length: 255, nullable: true)]
    private ?string $informationTitle = null;

    #[ORM\Column(name: 'information_photo', type: 'string', length: 1024, nullable: true)]
    private ?string $informationPhoto = null;

    /** @var Collection<int, Experience>|ArrayCollection<int, Experience> */
    #[ORM\OneToMany(targetEntity: Experience::class, mappedBy: 'resume', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection|ArrayCollection $experiences;

    /** @var Collection<int, Education>|ArrayCollection<int, Education> */
    #[ORM\OneToMany(targetEntity: Education::class, mappedBy: 'resume', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection|ArrayCollection $educations;

    /** @var Collection<int, Skill>|ArrayCollection<int, Skill> */
    #[ORM\OneToMany(targetEntity: Skill::class, mappedBy: 'resume', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection|ArrayCollection $skills;

    /** @var Collection<int, Language>|ArrayCollection<int, Language> */
    #[ORM\OneToMany(targetEntity: Language::class, mappedBy: 'resume', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection|ArrayCollection $languages;

    /** @var Collection<int, Certification>|ArrayCollection<int, Certification> */
    #[ORM\OneToMany(targetEntity: Certification::class, mappedBy: 'resume', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection|ArrayCollection $certifications;

    /** @var Collection<int, Project>|ArrayCollection<int, Project> */
    #[ORM\OneToMany(targetEntity: Project::class, mappedBy: 'resume', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection|ArrayCollection $projects;

    /** @var Collection<int, Reference>|ArrayCollection<int, Reference> */
    #[ORM\OneToMany(targetEntity: Reference::class, mappedBy: 'resume', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection|ArrayCollection $references;

    /** @var Collection<int, Hobby>|ArrayCollection<int, Hobby> */
    #[ORM\OneToMany(targetEntity: Hobby::class, mappedBy: 'resume', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection|ArrayCollection $hobbies;

    public function __construct()
    {
        $this->id = $this->createUuid();
        $this->experiences = new ArrayCollection();
        $this->educations = new ArrayCollection();
        $this->skills = new ArrayCollection();
        $this->languages = new ArrayCollection();
        $this->certifications = new ArrayCollection();
        $this->projects = new ArrayCollection();
        $this->references = new ArrayCollection();
        $this->hobbies = new ArrayCollection();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }
    public function getOwner(): User
    {
        return $this->owner;
    }
    public function setOwner(User $owner): self
    {
        $this->owner = $owner;

        return $this;
    }
    public function getApplicant(): ?Applicant
    {
        return $this->applicant;
    }
    public function getTemplate(): ?Template
    {
        return $this->template;
    }
    public function setTemplate(?Template $template): self
    {
        $this->template = $template;

        return $this;
    }
    public function getDocumentUrl(): ?string
    {
        return $this->documentUrl;
    }
    public function setDocumentUrl(?string $documentUrl): self
    {
        $this->documentUrl = $documentUrl;

        return $this;
    }
    public function getInformationFullName(): ?string
    {
        return $this->informationFullName;
    }
    public function setInformationFullName(?string $informationFullName): self
    {
        $this->informationFullName = $informationFullName;

        return $this;
    }
    public function getInformationEmail(): ?string
    {
        return $this->informationEmail;
    }
    public function setInformationEmail(?string $informationEmail): self
    {
        $this->informationEmail = $informationEmail;

        return $this;
    }
    public function getInformationPhone(): ?string
    {
        return $this->informationPhone;
    }
    public function setInformationPhone(?string $informationPhone): self
    {
        $this->informationPhone = $informationPhone;

        return $this;
    }
    public function getInformationHomepage(): ?string
    {
        return $this->informationHomepage;
    }
    public function setInformationHomepage(?string $informationHomepage): self
    {
        $this->informationHomepage = $informationHomepage;

        return $this;
    }
    public function getInformationRepoProfile(): ?string
    {
        return $this->informationRepoProfile;
    }
    public function setInformationRepoProfile(?string $informationRepoProfile): self
    {
        $this->informationRepoProfile = $informationRepoProfile;

        return $this;
    }
    public function getInformationAddress(): ?string
    {
        return $this->informationAddress;
    }
    public function setInformationAddress(?string $informationAddress): self
    {
        $this->informationAddress = $informationAddress;

        return $this;
    }
    public function getInformationBirthDate(): ?\DateTimeImmutable
    {
        return $this->informationBirthDate;
    }
    public function setInformationBirthDate(?\DateTimeImmutable $informationBirthDate): self
    {
        $this->informationBirthDate = $informationBirthDate;

        return $this;
    }
    public function getInformationBirthPlace(): ?string
    {
        return $this->informationBirthPlace;
    }
    public function setInformationBirthPlace(?string $informationBirthPlace): self
    {
        $this->informationBirthPlace = $informationBirthPlace;

        return $this;
    }
    public function getInformationProfileText(): ?string
    {
        return $this->informationProfileText;
    }
    public function setInformationProfileText(?string $informationProfileText): self
    {
        $this->informationProfileText = $informationProfileText;

        return $this;
    }
    public function getInformationTitle(): ?string
    {
        return $this->informationTitle;
    }
    public function setInformationTitle(?string $informationTitle): self
    {
        $this->informationTitle = $informationTitle;

        return $this;
    }
    public function getInformationPhoto(): ?string
    {
        return $this->informationPhoto;
    }
    public function setInformationPhoto(?string $informationPhoto): self
    {
        $this->informationPhoto = $informationPhoto;

        return $this;
    }

    /**
     * @return Collection<int, Experience>|ArrayCollection<int, Experience>
     */
    public function getExperiences(): Collection|ArrayCollection
    {
        return $this->experiences;
    }
    public function addExperience(Experience $experience): self
    {
        if (!$this->experiences->contains($experience)) {
            $this->experiences->add($experience);
            $experience->setResume($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Education>|ArrayCollection<int, Education>
     */
    public function getEducations(): Collection|ArrayCollection
    {
        return $this->educations;
    }
    public function addEducation(Education $education): self
    {
        if (!$this->educations->contains($education)) {
            $this->educations->add($education);
            $education->setResume($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Skill>|ArrayCollection<int, Skill>
     */
    public function getSkills(): Collection|ArrayCollection
    {
        return $this->skills;
    }
    public function addSkill(Skill $skill): self
    {
        if (!$this->skills->contains($skill)) {
            $this->skills->add($skill);
            $skill->setResume($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Language>|ArrayCollection<int, Language>
     */
    public function getLanguages(): Collection|ArrayCollection
    {
        return $this->languages;
    }
    public function addLanguage(Language $language): self
    {
        if (!$this->languages->contains($language)) {
            $this->languages->add($language);
            $language->setResume($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Certification>|ArrayCollection<int, Certification>
     */
    public function getCertifications(): Collection|ArrayCollection
    {
        return $this->certifications;
    }
    public function addCertification(Certification $certification): self
    {
        if (!$this->certifications->contains($certification)) {
            $this->certifications->add($certification);
            $certification->setResume($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Project>|ArrayCollection<int, Project>
     */
    public function getProjects(): Collection|ArrayCollection
    {
        return $this->projects;
    }
    public function addProject(Project $project): self
    {
        if (!$this->projects->contains($project)) {
            $this->projects->add($project);
            $project->setResume($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Reference>|ArrayCollection<int, Reference>
     */
    public function getReferences(): Collection|ArrayCollection
    {
        return $this->references;
    }
    public function addReference(Reference $reference): self
    {
        if (!$this->references->contains($reference)) {
            $this->references->add($reference);
            $reference->setResume($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Hobby>|ArrayCollection<int, Hobby>
     */
    public function getHobbies(): Collection|ArrayCollection
    {
        return $this->hobbies;
    }
    public function addHobby(Hobby $hobby): self
    {
        if (!$this->hobbies->contains($hobby)) {
            $this->hobbies->add($hobby);
            $hobby->setResume($this);
        }

        return $this;
    }
}
