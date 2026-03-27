<?php

declare(strict_types=1);

namespace App\Library\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use App\Library\Domain\Enum\LibraryFileType;
use App\User\Domain\Entity\User;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity(repositoryClass: \App\Library\Infrastructure\Repository\LibraryFileRepository::class)]
#[ORM\Table(name: 'library_file')]
class LibraryFile implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'owner_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $owner;

    #[ORM\ManyToOne(targetEntity: LibraryFolder::class)]
    #[ORM\JoinColumn(name: 'folder_id', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?LibraryFolder $folder = null;

    #[ORM\Column(name: 'name', type: Types::STRING, length: 255)]
    private string $name = '';

    #[ORM\Column(name: 'url', type: Types::STRING, length: 255)]
    private string $url = '';

    #[ORM\Column(name: 'mime_type', type: Types::STRING, length: 120)]
    private string $mimeType = '';

    #[ORM\Column(name: 'size', type: Types::INTEGER)]
    private int $size = 0;

    #[ORM\Column(name: 'extension', type: Types::STRING, length: 20)]
    private string $extension = '';

    #[ORM\Column(name: 'file_type', type: Types::STRING, length: 20, enumType: LibraryFileType::class)]
    private LibraryFileType $fileType = LibraryFileType::OTHER;

    public function __construct()
    {
        $this->id = $this->createUuid();
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

    public function getFolder(): ?LibraryFolder
    {
        return $this->folder;
    }

    public function setFolder(?LibraryFolder $folder): self
    {
        $this->folder = $folder;

        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): self
    {
        $this->extension = $extension;

        return $this;
    }

    public function getFileType(): LibraryFileType
    {
        return $this->fileType;
    }

    public function setFileType(LibraryFileType $fileType): self
    {
        $this->fileType = $fileType;

        return $this;
    }
}
