<?php
declare(strict_types=1);
namespace App\Recruit\Domain\Entity;
use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\User\Domain\Entity\User;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;
#[ORM\Entity]
#[ORM\Table(name: 'recruit_cover_page')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class CoverPage implements EntityInterface { use Timestampable; use Uuid;
#[ORM\Id] #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)] private UuidInterface $id;
#[ORM\ManyToOne(targetEntity: User::class)] #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')] private User $owner;
#[ORM\ManyToOne(targetEntity: Template::class)] #[ORM\JoinColumn(name: 'template_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')] private ?Template $template = null;
#[ORM\Column(name: 'full_name', type: 'string', length: 255, nullable: true)] private ?string $fullName = null;
#[ORM\Column(name: 'role_name', type: 'string', length: 255, nullable: true)] private ?string $role = null;
#[ORM\Column(name: 'photo', type: 'string', length: 1024, nullable: true)] private ?string $photo = null;
#[ORM\Column(name: 'description', type: 'text', nullable: true)] private ?string $description = null;
#[ORM\Column(name: 'email', type: 'string', length: 255, nullable: true)] private ?string $email = null;
#[ORM\Column(name: 'phone', type: 'string', length: 50, nullable: true)] private ?string $phone = null;
#[ORM\Column(name: 'header', type: 'string', length: 255, nullable: true)] private ?string $header = 'About Me';
#[ORM\Column(name: 'profile', type: 'text', nullable: true)] private ?string $profile = null;
#[ORM\Column(name: 'signature', type: 'string', length: 1024, nullable: true)] private ?string $signature = null;
public function __construct(){ $this->id=$this->createUuid(); }
#[Override] public function getId(): string { return $this->id->toString(); }
public function getOwner(): User { return $this->owner; } public function setOwner(User $owner): self { $this->owner=$owner; return $this; }
public function getTemplate(): ?Template { return $this->template; } public function setTemplate(?Template $template): self { $this->template=$template; return $this; }
public function getFullName(): ?string { return $this->fullName; } public function setFullName(?string $fullName): self { $this->fullName=$fullName; return $this; }
public function getRole(): ?string { return $this->role; } public function setRole(?string $role): self { $this->role=$role; return $this; }
public function getPhoto(): ?string { return $this->photo; } public function setPhoto(?string $photo): self { $this->photo=$photo; return $this; }
public function getDescription(): ?string { return $this->description; } public function setDescription(?string $description): self { $this->description=$description; return $this; }
public function getEmail(): ?string { return $this->email; } public function setEmail(?string $email): self { $this->email=$email; return $this; }
public function getPhone(): ?string { return $this->phone; } public function setPhone(?string $phone): self { $this->phone=$phone; return $this; }
public function getHeader(): ?string { return $this->header; } public function setHeader(?string $header): self { $this->header=$header; return $this; }
public function getProfile(): ?string { return $this->profile; } public function setProfile(?string $profile): self { $this->profile=$profile; return $this; }
public function getSignature(): ?string { return $this->signature; } public function setSignature(?string $signature): self { $this->signature=$signature; return $this; }
}
