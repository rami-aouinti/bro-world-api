<?php
declare(strict_types=1);
namespace App\Recruit\Domain\Entity;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
#[ORM\Entity]
#[ORM\Table(name: 'recruit_cover_letter')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class CoverLetter implements EntityInterface { use Timestampable; use Uuid;
#[ORM\Id] #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)] private UuidInterface $id;
#[ORM\ManyToOne(targetEntity: Template::class)] #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')] private ?Template $template = null;
#[ORM\Column(name: 'full_name', type: 'string', length: 255, nullable: true)] private ?string $fullName = null;
#[ORM\Column(name: 'role_name', type: 'string', length: 255, nullable: true)] private ?string $role = null;
#[ORM\Column(name: 'photo', type: 'string', length: 1024, nullable: true)] private ?string $photo = null;
#[ORM\Column(name: 'sender_date', type: 'date_immutable', nullable: true)] private ?\DateTimeImmutable $senderDate = null;
#[ORM\Column(name: 'location', type: 'string', length: 255, nullable: true)] private ?string $location = null;
#[ORM\Column(name: 'header', type: 'string', length: 255, nullable: true)] private ?string $header = 'Motivation Letter';
#[ORM\Column(name: 'description_1', type: 'text', nullable: true)] private ?string $description1 = null;
#[ORM\Column(name: 'description_2', type: 'text', nullable: true)] private ?string $description2 = null;
public function __construct(){ $this->id=$this->createUuid(); }
#[Override] public function getId(): string { return $this->id->toString(); }
public function getTemplate(): ?Template { return $this->template; } public function setTemplate(?Template $template): self { $this->template=$template; return $this; }
public function getFullName(): ?string { return $this->fullName; } public function setFullName(?string $fullName): self { $this->fullName=$fullName; return $this; }
public function getRole(): ?string { return $this->role; } public function setRole(?string $role): self { $this->role=$role; return $this; }
public function getPhoto(): ?string { return $this->photo; } public function setPhoto(?string $photo): self { $this->photo=$photo; return $this; }
public function getSenderDate(): ?\DateTimeImmutable { return $this->senderDate; } public function setSenderDate(?\DateTimeImmutable $senderDate): self { $this->senderDate=$senderDate; return $this; }
public function getLocation(): ?string { return $this->location; } public function setLocation(?string $location): self { $this->location=$location; return $this; }
public function getHeader(): ?string { return $this->header; } public function setHeader(?string $header): self { $this->header=$header; return $this; }
public function getDescription1(): ?string { return $this->description1; } public function setDescription1(?string $description1): self { $this->description1=$description1; return $this; }
public function getDescription2(): ?string { return $this->description2; } public function setDescription2(?string $description2): self { $this->description2=$description2; return $this; }
}
