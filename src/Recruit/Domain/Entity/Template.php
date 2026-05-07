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
#[ORM\Table(name: 'recruit_template')]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class Template implements EntityInterface { use Timestampable; use Uuid;
#[ORM\Id] #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)] private UuidInterface $id;
#[ORM\Column(name: 'name', type: 'string', length: 255)] private string $name;
#[ORM\Column(name: 'type', type: 'string', length: 30)] private string $type;
#[ORM\Column(name: 'version', type: 'integer')] private int $version = 1;
#[ORM\Column(name: 'layout', type: 'string', length: 100, nullable: true)] private ?string $layout = null;
#[ORM\Column(name: 'structure_name', type: 'string', length: 100, nullable: true)] private ?string $structure = null;
#[ORM\Column(name: 'sections', type: 'json', nullable: true)] private ?array $sections = null;
#[ORM\Column(name: 'theme', type: 'json', nullable: true)] private ?array $theme = null;
#[ORM\Column(name: 'aside_data', type: 'json', nullable: true)] private ?array $aside = null;
#[ORM\Column(name: 'photo', type: 'json', nullable: true)] private ?array $photo = null;
#[ORM\Column(name: 'decor', type: 'json', nullable: true)] private ?array $decor = null;
#[ORM\Column(name: 'layout_options', type: 'json', nullable: true)] private ?array $layoutOptions = null;
#[ORM\Column(name: 'decor_options', type: 'json', nullable: true)] private ?array $decorOptions = null;
#[ORM\Column(name: 'section_title_style', type: 'json', nullable: true)] private ?array $sectionTitleStyle = null;
#[ORM\Column(name: 'header_type', type: 'string', length: 100, nullable: true)] private ?string $headerType = null;
#[ORM\Column(name: 'fake_data', type: 'json', nullable: true)] private ?array $fakeData = null;
#[ORM\Column(name: 'text_styles', type: 'json', nullable: true)] private ?array $textStyles = null;
#[ORM\Column(name: 'typography', type: 'json', nullable: true)] private ?array $typography = null;
#[ORM\Column(name: 'section_bar', type: 'json', nullable: true)] private ?array $sectionBar = null;
#[ORM\Column(name: 'items', type: 'json', nullable: true)] private ?array $items = null;
public function __construct(){ $this->id = $this->createUuid(); }
#[Override] public function getId(): string { return $this->id->toString(); }
public function getName(): string { return $this->name; } public function setName(string $name): self { $this->name = $name; return $this; }
public function getType(): string { return $this->type; } public function setType(string $type): self { $this->type = $type; return $this; }
public function getVersion(): int { return $this->version; } public function setVersion(int $version): self { $this->version = $version; return $this; }
public function getLayout(): ?string { return $this->layout; } public function setLayout(?string $layout): self { $this->layout = $layout; return $this; }
public function getStructure(): ?string { return $this->structure; } public function setStructure(?string $structure): self { $this->structure = $structure; return $this; }
public function getSections(): ?array { return $this->sections; } public function setSections(?array $sections): self { $this->sections = $sections; return $this; }
public function getTheme(): ?array { return $this->theme; } public function setTheme(?array $theme): self { $this->theme = $theme; return $this; }
public function getAside(): ?array { return $this->aside; } public function setAside(?array $aside): self { $this->aside = $aside; return $this; }
public function getPhoto(): ?array { return $this->photo; } public function setPhoto(?array $photo): self { $this->photo = $photo; return $this; }
public function getDecor(): ?array { return $this->decor; } public function setDecor(?array $decor): self { $this->decor = $decor; return $this; }
public function getLayoutOptions(): ?array { return $this->layoutOptions; } public function setLayoutOptions(?array $layoutOptions): self { $this->layoutOptions = $layoutOptions; return $this; }
public function getDecorOptions(): ?array { return $this->decorOptions; } public function setDecorOptions(?array $decorOptions): self { $this->decorOptions = $decorOptions; return $this; }
public function getSectionTitleStyle(): ?array { return $this->sectionTitleStyle; } public function setSectionTitleStyle(?array $sectionTitleStyle): self { $this->sectionTitleStyle = $sectionTitleStyle; return $this; }
public function getHeaderType(): ?string { return $this->headerType; } public function setHeaderType(?string $headerType): self { $this->headerType = $headerType; return $this; }
public function getFakeData(): ?array { return $this->fakeData; } public function setFakeData(?array $fakeData): self { $this->fakeData = $fakeData; return $this; }
public function getTextStyles(): ?array { return $this->textStyles; } public function setTextStyles(?array $textStyles): self { $this->textStyles = $textStyles; return $this; }
public function getTypography(): ?array { return $this->typography; } public function setTypography(?array $typography): self { $this->typography = $typography; return $this; }
public function getSectionBar(): ?array { return $this->sectionBar; } public function setSectionBar(?array $sectionBar): self { $this->sectionBar = $sectionBar; return $this; }
public function getItems(): ?array { return $this->items; } public function setItems(?array $items): self { $this->items = $items; return $this; }
}
