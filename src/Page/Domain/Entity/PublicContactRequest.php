<?php

declare(strict_types=1);

namespace App\Page\Domain\Entity;

use App\General\Domain\Entity\Interfaces\EntityInterface;
use App\General\Domain\Entity\Traits\Timestampable;
use App\General\Domain\Entity\Traits\Uuid;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Override;
use Ramsey\Uuid\Doctrine\UuidBinaryOrderedTimeType;
use Ramsey\Uuid\UuidInterface;

#[ORM\Entity]
#[ORM\Table(name: 'page_public_contact_request')]
#[ORM\Index(name: 'idx_page_public_contact_request_email', columns: ['email'])]
#[ORM\Index(name: 'idx_page_public_contact_request_created_at', columns: ['created_at'])]
#[ORM\ChangeTrackingPolicy('DEFERRED_EXPLICIT')]
class PublicContactRequest implements EntityInterface
{
    use Timestampable;
    use Uuid;

    #[ORM\Id]
    #[ORM\Column(name: 'id', type: UuidBinaryOrderedTimeType::NAME, unique: true)]
    private UuidInterface $id;

    #[ORM\Column(name: 'first_name', type: Types::STRING, length: 100)]
    private string $firstName = '';

    #[ORM\Column(name: 'last_name', type: Types::STRING, length: 100)]
    private string $lastName = '';

    #[ORM\Column(name: 'email', type: Types::STRING, length: 190)]
    private string $email = '';

    #[ORM\Column(name: 'type', type: Types::STRING, length: 100)]
    private string $type = '';

    #[ORM\Column(name: 'message', type: Types::TEXT)]
    private string $message = '';

    public function __construct()
    {
        $this->id = $this->createUuid();
    }

    #[Override]
    public function getId(): string
    {
        return $this->id->toString();
    }

    public function getFirstName(): string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = trim($firstName);

        return $this;
    }

    public function getLastName(): string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = trim($lastName);

        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = trim($email);

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = trim($type);

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = trim($message);

        return $this;
    }
}
